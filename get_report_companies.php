<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("data_scripts/pdo_conn.php");
include("data_scripts/utils.php");

$reportDate = trim($_GET['date'] ?? '');

if ($reportDate == '') {
    $reportDate = date('Y-m-d');
} else {
    $reportDate = DateFormatDB($reportDate);
}

$qry = "
    SELECT DISTINCT r.CompanyName
    FROM Visitors r
    INNER JOIN Visits v ON v.VisitorID = r.VisitorID
    WHERE v.CenterID = " . $pdo->quote($_SESSION['id_center']) . "
      AND v.VisitDate = " . $pdo->quote($reportDate) . "
      AND r.CompanyName IS NOT NULL
      AND r.CompanyName <> ''
    ORDER BY r.CompanyName
";

echo '<option value="">All Companies</option>';

foreach ($pdo->query($qry) as $row) {

    $company = htmlspecialchars($row['CompanyName'], ENT_QUOTES, 'UTF-8');

    echo '<option value="' . $company . '">' . $company . '</option>';
}