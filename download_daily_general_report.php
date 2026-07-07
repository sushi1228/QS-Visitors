<?php
/**
 * Download Daily General Visitors Report
 * Backward-compatible wrapper for today's general report.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/checklogin.php';
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/attendance_report_helpers.php';

attendanceReportRequireDependencies();
require_once attendanceReportBasePath() . '/data_scripts/pdo_conn.php';

date_default_timezone_set('America/New_York');

$range = parseAttendanceReportDateRange(
    $_GET['date_from'] ?? null,
    $_GET['date_to'] ?? null
);
$context = resolveAttendanceReportSessionContext();
$generatedAt = date('m/d/Y h:i:s A');
$fileSuffix = attendanceReportFileSuffix($range['date_from'], $range['date_to']);

$spreadsheet = buildGeneralAttendanceSpreadsheet(
    $pdo,
    $range['date_from'],
    $range['date_to'],
    $context['center_id'],
    $context['center_name'],
    $generatedAt
);

$prefix = $range['is_range'] ? 'General_Report_' : 'General_Daily_Report_';
sendAttendanceSpreadsheetDownload($spreadsheet, $prefix . $fileSuffix . '.xlsx');
