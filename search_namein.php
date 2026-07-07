<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
/* Visitors currently checked in today (for iPad checkout autocomplete). */
include("data_scripts/pdo_conn.php");
include("data_scripts/utils.php");

$searchTerm = $_GET['term'] ?? '';
$center = GetQSCenterIDByIP($pdo);
$centerSql = ($center !== null && $center !== '') ? ' AND vi.CenterID = ' . $pdo->quote((string) $center) : '';

$qry = "
	SELECT DISTINCT
    v.VisitorID,
    v.FirstName,
    v.LastName,
    CONCAT_WS(' ', v.FirstName, v.LastName) AS Vname,
    v.Email,
    v.EmpID,
    v.Mobile,
    v.CompanyName
	FROM Visitors v
	INNER JOIN Visits vi ON vi.VisitorID = v.VisitorID
	WHERE vi.VisitDate = " . $pdo->quote(date('Y-m-d')) . "
		AND vi.VisitStatus = 2
		AND (vi.CheckoutTime = 0 OR vi.CheckoutTime IS NULL OR vi.CheckoutTime = '00:00:00')
		{$centerSql}
		AND (v.FirstName LIKE " . $pdo->quote('%' . $searchTerm . '%') . "
			OR v.LastName LIKE " . $pdo->quote('%' . $searchTerm . '%') . ")
	ORDER BY v.FirstName, v.LastName";

$nameData = [];
foreach ($pdo->query($qry) as $row) {
	$email = trim((string) $row['Email']);
	$nameData[] = [
		'id' => $row['VisitorID'],
		'value' => $row['Vname'],
		'label' => $row['Vname'],
		'email' => ($email === '' || $email === ' ') ? '' : $email,
		'empid' => trim((string) $row['EmpID']),
		'mobile' => $row['Mobile'],
		'coname' => $row['CompanyName'],
	];
}
echo json_encode($nameData, JSON_UNESCAPED_UNICODE);
#echo json_encode($nameData);
