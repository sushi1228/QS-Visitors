<?php
/**
 * Shared helpers for general and trainee attendance Excel reports.
 */

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooterDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const ATTENDANCE_REPORT_HEADER_ROW = 4;

function attendanceReportBasePath(): string
{
    return dirname(__DIR__);
}

function attendanceReportRequireDependencies(): void
{
    require_once attendanceReportBasePath() . '/vendor/autoload.php';
    require_once attendanceReportBasePath() . '/data_scripts/utils.php';
}

/**
 * @return array{date_from: string, date_to: string, label: string, is_range: bool}
 */
function parseAttendanceReportDateRange(?string $dateFromInput, ?string $dateToInput): array
{
    $today = date('Y-m-d');
    $dateFrom = normalizeAttendanceReportDate($dateFromInput) ?: $today;
    $dateTo = normalizeAttendanceReportDate($dateToInput) ?: $dateFrom;

    if ($dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $isRange = $dateFrom !== $dateTo;
    $label = $isRange
        ? date('m/d/Y', strtotime($dateFrom)) . ' to ' . date('m/d/Y', strtotime($dateTo))
        : date('m/d/Y', strtotime($dateFrom));

    return [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'label' => $label,
        'is_range' => $isRange,
    ];
}

function normalizeAttendanceReportDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
        $converted = DateFormatDB($value);
        return $converted !== '' ? $converted : null;
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function attendanceReportFileSuffix(string $dateFrom, string $dateTo): string
{
    if ($dateFrom === $dateTo) {
        return $dateFrom;
    }

    return $dateFrom . '_to_' . $dateTo;
}

/** @return array{key: string, header: string} */
function attendanceReportSerialColumn(): array
{
    return ['key' => 'SerialNo', 'header' => 'S.No.'];
}

/** @return list<array{key: string, header: string}> */
function generalAttendanceReportColumns(bool $includeVisitDate): array
{
    $columns = [attendanceReportSerialColumn()];

    if ($includeVisitDate) {
        $columns[] = ['key' => 'VisitDate', 'header' => 'Visit Date'];
    }

    return array_merge($columns, [
        ['key' => 'FirstName', 'header' => 'First Name'],
        ['key' => 'LastName', 'header' => 'Last Name'],
        ['key' => 'Email', 'header' => 'Email'],
        ['key' => 'EmpID', 'header' => 'EmpID'],
        ['key' => 'CompanyName', 'header' => 'Company'],
        ['key' => 'HostName', 'header' => 'Host'],
        ['key' => 'VisitAbout', 'header' => 'Visit Purpose'],
        ['key' => 'CheckInTime', 'header' => 'Check In'],
        ['key' => 'CheckOutTime', 'header' => 'Check Out'],
        ['key' => 'VisitStatus', 'header' => 'Visit Status'],
    ]);
}

/** @return list<array{key: string, header: string}> */
function traineeAttendanceReportColumns(bool $includeVisitDate): array
{
    $columns = [attendanceReportSerialColumn()];

    if ($includeVisitDate) {
        $columns[] = ['key' => 'VisitDate', 'header' => 'Visit Date'];
    }

    return array_merge($columns, [
        ['key' => 'FirstName', 'header' => 'First Name'],
        ['key' => 'LastName', 'header' => 'Last Name'],
        ['key' => 'Email', 'header' => 'Email'],
        ['key' => 'EmpID', 'header' => 'Employee ID'],
        ['key' => 'ClassName', 'header' => 'Class Name'],
        ['key' => 'RoomName', 'header' => 'Room Name'],
        ['key' => 'AreaName', 'header' => 'Area'],
        ['key' => 'CheckinActivity', 'header' => 'Check-in Activity'],
        ['key' => 'VisitStatus', 'header' => 'Visit Status'],
    ]);
}

function fetchGeneralReportRows(PDO $pdo, string $dateFrom, string $dateTo, string $centerId, string $company = ''): array
{
    $sql = "
        SELECT
            v.VisitID,
            v.VisitDate,
            r.FirstName,
            r.LastName,
            r.Email,
            r.EmpID,
            r.CompanyName,
            CONCAT_WS(' ', u.FirstName, u.LastName) AS HostName,
            v.VisitAbout,
            TIME_FORMAT(v.CheckinTime, '%h:%i %p') AS CheckInTime,
            IF(v.CheckoutTime IS NULL OR v.CheckoutTime = '00:00:00', '', TIME_FORMAT(v.CheckoutTime, '%h:%i %p')) AS CheckOutTime,
            vs.VisitStatus
        FROM Visits v
        INNER JOIN Visitors r ON r.VisitorID = v.VisitorID
        INNER JOIN VisitStatus vs ON vs.VisitStatusID = v.VisitStatus
        LEFT JOIN Users u ON u.UserID = v.HostID
        WHERE v.VisitDate >= :date_from
            AND v.VisitDate <= :date_to
            AND v.CenterID = :center_id
            AND v.VisitorType = 'GENERAL'
    ";

    if ($company !== '') {
        $sql .= " AND r.CompanyName = :company";
    }

    $sql .= "
        ORDER BY v.VisitDate ASC, r.FirstName ASC, r.LastName ASC, v.VisitID ASC
    ";

    $params = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'center_id' => $centerId,
    ];

    if ($company !== '') {
        $params['company'] = $company;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['VisitDate'] = formatAttendanceReportDate((string) ($row['VisitDate'] ?? ''));
        $row['Email'] = cleanAttendanceReportValue($row['Email'] ?? '');
        $row['EmpID'] = cleanAttendanceReportValue($row['EmpID'] ?? '');
        $rows[] = $row;
    }

    return $rows;
}

function fetchTraineeReportRows(PDO $pdo, string $dateFrom, string $dateTo, string $centerId): array
{
    $sql = "
        SELECT
            v.VisitID,
            v.VisitDate,
            r.FirstName,
            r.LastName,
            r.Email,
            r.EmpID,
            rt.ClassName,
            rt.RoomName,
            qca.AreaName,
            vs.VisitStatus
        FROM Visits v
        INNER JOIN Visitors r ON r.VisitorID = v.VisitorID
        INNER JOIN VisitStatus vs ON vs.VisitStatusID = v.VisitStatus
        LEFT JOIN RoomTimes rt ON rt.RoomTimeID = v.RoomTimeID
        LEFT JOIN QSCenterArea qca
            ON qca.CenterID = v.CenterID
            AND qca.AreaID = IFNULL(v.AreaID, rt.AreaID)
        WHERE v.VisitDate >= :date_from
            AND v.VisitDate <= :date_to
            AND v.CenterID = :center_id
            AND v.VisitorType = 'TRAINEE'
        ORDER BY v.VisitDate ASC, rt.ClassName ASC, rt.RoomName ASC, r.FirstName ASC, r.LastName ASC, v.VisitID ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'center_id' => $centerId,
    ]);

    $rows = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['VisitDate'] = formatAttendanceReportDate((string) ($row['VisitDate'] ?? ''));
        $row['Email'] = cleanAttendanceReportValue($row['Email'] ?? '');
        $row['EmpID'] = cleanAttendanceReportValue($row['EmpID'] ?? '');
        $row['CheckinActivity'] = getAttendanceCheckinActivity($pdo, (int) $row['VisitID']);
        $rows[] = $row;
    }

    return $rows;
}

function getAttendanceCheckinActivity(PDO $pdo, int $visitId): string
{
    $sql = "
        SELECT EventType, CreatedAt
        FROM VisitEvents
        WHERE VisitID = :visit_id
        ORDER BY CreatedAt ASC, VisitEventID ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['visit_id' => $visitId]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($events)) {
        return '';
    }

    $activity = [];
    $openInTime = '';

    foreach ($events as $event) {
        $eventType = strtoupper(trim((string) ($event['EventType'] ?? '')));
        $eventTime = formatAttendanceReportDateTime((string) ($event['CreatedAt'] ?? ''));

        if ($eventType === 'IN') {
            if ($openInTime !== '') {
                $activity[] = $openInTime . ' - Still checked in';
            }
            $openInTime = $eventTime;
        } elseif ($eventType === 'OUT') {
            if ($openInTime !== '') {
                $activity[] = $openInTime . ' - ' . $eventTime;
                $openInTime = '';
            } else {
                $activity[] = 'OUT ' . $eventTime;
            }
        }
    }

    if ($openInTime !== '') {
        $activity[] = $openInTime . ' - Still checked in';
    }

    return implode("\n", $activity);
}

function formatAttendanceReportDateTime(string $dateTime): string
{
    $dateTime = trim($dateTime);

    if ($dateTime === '' || $dateTime === '0000-00-00 00:00:00') {
        return '';
    }

    $ts = strtotime($dateTime);
    return $ts === false ? $dateTime : date('g:i A', $ts);
}

function formatAttendanceReportDate(string $date): string
{
    $date = trim($date);

    if ($date === '' || $date === '0000-00-00') {
        return '';
    }

    $ts = strtotime($date);
    return $ts === false ? $date : date('m/d/Y', $ts);
}

function cleanAttendanceReportValue($value): string
{
    $value = trim((string) $value);
    return ($value === '_NULL_') ? '' : $value;
}

function buildGeneralAttendanceSpreadsheet(
    PDO $pdo,
    string $dateFrom,
    string $dateTo,
    string $centerId,
    string $centerName,
    string $generatedAt,
    string $company = ''
): Spreadsheet {
    $range = parseAttendanceReportDateRange($dateFrom, $dateTo);
    $rows = fetchGeneralReportRows($pdo, $range['date_from'], $range['date_to'], $centerId, $company);
    $columns = generalAttendanceReportColumns($range['is_range']);
    $title = $range['is_range'] ? 'General Visitors Report' : 'General Daily Visitors Report';

    return buildAttendanceReportSpreadsheet(
        $rows,
        $columns,
        $title,
        $centerName,
        $range['label'],
        $generatedAt,
        'General Report'
    );
}

function buildTraineeAttendanceSpreadsheet(
    PDO $pdo,
    string $dateFrom,
    string $dateTo,
    string $centerId,
    string $centerName,
    string $generatedAt
): Spreadsheet {
    $range = parseAttendanceReportDateRange($dateFrom, $dateTo);
    $rows = fetchTraineeReportRows($pdo, $range['date_from'], $range['date_to'], $centerId);
    $columns = traineeAttendanceReportColumns($range['is_range']);
    $title = $range['is_range'] ? 'Trainee Visitors Report' : 'Trainee Daily Visitors Report';

    return buildAttendanceReportSpreadsheet(
        $rows,
        $columns,
        $title,
        $centerName,
        $range['label'],
        $generatedAt,
        'Trainee Report'
    );
}

function buildAttendanceReportSpreadsheet(
    array $rows,
    array $columns,
    string $title,
    string $centerName,
    string $dateLabel,
    string $generatedAt,
    string $sheetTitle
): Spreadsheet {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetTitle);

    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);
    $sheet->getPageSetup()->setHorizontalCentered(true);

    $sheet->getPageMargins()->setTop(0.8);
    $sheet->getPageMargins()->setRight(0.4);
    $sheet->getPageMargins()->setLeft(0.4);
    $sheet->getPageMargins()->setBottom(0.5);
    $sheet->getPageMargins()->setHeader(0.3);
    $sheet->getPageMargins()->setFooter(0.2);

    $qsLogoPath = attendanceReportBasePath() . '/assets/images/qslogo_250.jpg';

    if (file_exists($qsLogoPath)) {
        $qsLogo = new HeaderFooterDrawing();
        $qsLogo->setName('QS Logo');
        $qsLogo->setDescription('Quick Start Logo');
        $qsLogo->setPath($qsLogoPath);
        $qsLogo->setWidthAndHeight(158, 49);
        $sheet->getHeaderFooter()->addImage($qsLogo, HeaderFooter::IMAGE_HEADER_LEFT);
    }

    $sheet->getHeaderFooter()->setOddHeader("&L&G&C&B&15 {$title}\n{$dateLabel}");
    $sheet->getHeaderFooter()->setOddFooter('&L&BReport generated on ' . $generatedAt . '&RPage &P of &N');

    $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

    $sheet->mergeCells("A1:{$lastColumn}1");
    $sheet->setCellValue('A1', $centerName);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(22);
    $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FF1F4E78');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(34);

    $sheet->mergeCells("A2:{$lastColumn}2");
    $sheet->setCellValue('A2', $title . ' - ' . $dateLabel);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    foreach ($columns as $index => $column) {
        $colLetter = Coordinate::stringFromColumnIndex($index + 1);
        $sheet->setCellValue($colLetter . ATTENDANCE_REPORT_HEADER_ROW, $column['header']);
    }

    $headerRange = 'A' . ATTENDANCE_REPORT_HEADER_ROW . ':' . $lastColumn . ATTENDANCE_REPORT_HEADER_ROW;
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF0B2E6F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD9D9D9']]],
    ]);
    $sheet->getRowDimension(ATTENDANCE_REPORT_HEADER_ROW)->setRowHeight(28);

    $dataRow = ATTENDANCE_REPORT_HEADER_ROW + 1;

    if (empty($rows)) {
        $sheet->mergeCells("A{$dataRow}:{$lastColumn}{$dataRow}");
        $sheet->setCellValue("A{$dataRow}", 'No records found.');
        $sheet->getStyle("A{$dataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    } else {
        $serialNo = 0;
        foreach ($rows as $row) {
            $serialNo++;
            foreach ($columns as $index => $column) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                if ($column['key'] === 'SerialNo') {
                    $value = $serialNo;
                } else {
                    $value = cleanAttendanceReportValue($row[$column['key']] ?? '');
                }
                $sheet->setCellValue($colLetter . $dataRow, $value);
            }
            $dataRow++;
        }
    }

    $lastDataRow = max($dataRow - 1, ATTENDANCE_REPORT_HEADER_ROW + 1);

    $empIdColumn = null;
    foreach ($columns as $index => $column) {
        if ($column['key'] === 'EmpID') {
            $empIdColumn = Coordinate::stringFromColumnIndex($index + 1);
            break;
        }
    }

    if ($empIdColumn !== null) {
        $sheet->getStyle($empIdColumn . ':' . $empIdColumn)->getNumberFormat()->setFormatCode('@');
    }

    $sheet->getStyle('A' . (ATTENDANCE_REPORT_HEADER_ROW + 1) . ':' . $lastColumn . $lastDataRow)->applyFromArray([
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE5E5E5']]],
    ]);

    for ($rowNumber = ATTENDANCE_REPORT_HEADER_ROW + 1; $rowNumber <= $lastDataRow; $rowNumber++) {
        if ($rowNumber % 2 === 0) {
            $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7F9FC');
        }
    }

    foreach (range(1, count($columns)) as $colIndex) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $sheet->getColumnDimension('A')->setAutoSize(false);
    $sheet->getColumnDimension('A')->setWidth(8);
    if (!empty($rows)) {
        $sheet->getStyle('A' . (ATTENDANCE_REPORT_HEADER_ROW + 1) . ':A' . $lastDataRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $activityColumnIndex = null;
    foreach ($columns as $index => $column) {
        if ($column['key'] === 'CheckinActivity') {
            $activityColumnIndex = $index + 1;
            break;
        }
    }

    if ($activityColumnIndex !== null) {
        $activityColumn = Coordinate::stringFromColumnIndex($activityColumnIndex);
        $sheet->getColumnDimension($activityColumn)->setWidth(30);
    }

    $statusColumnIndex = count($columns);
    $statusColumn = Coordinate::stringFromColumnIndex($statusColumnIndex);
    $sheet->getColumnDimension($statusColumn)->setWidth(18);

    return $spreadsheet;
}

function spreadsheetToString(Spreadsheet $spreadsheet): string
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    ob_start();
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    $content = ob_get_clean();
    $spreadsheet->disconnectWorksheets();

    return $content === false ? '' : $content;
}

function sendAttendanceSpreadsheetDownload(Spreadsheet $spreadsheet, string $fileName): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    exit;
}

function sendAttendanceReportsZipDownload(array $files, string $zipFileName): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        die('ZipArchive is required to download both reports together.');
    }

    $tmpZip = tempnam(sys_get_temp_dir(), 'attendance_reports_');
    if ($tmpZip === false) {
        http_response_code(500);
        die('Unable to create temporary zip file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpZip);
        http_response_code(500);
        die('Unable to create zip archive.');
    }

    foreach ($files as $fileName => $content) {
        $zip->addFromString($fileName, $content);
    }

    $zip->close();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . filesize($tmpZip));
    header('Cache-Control: max-age=0');

    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}

function resolveAttendanceReportSessionContext(): array
{
    $centerId = trim((string) ($_SESSION['id_center'] ?? ''));
    $centerName = trim((string) ($_SESSION['center'] ?? ''));

    if ($centerId === '') {
        http_response_code(400);
        die('Center is missing from session.');
    }

    return [
        'center_id' => $centerId,
        'center_name' => $centerName,
    ];
}
