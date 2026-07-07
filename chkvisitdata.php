<?php
session_start();
include("data_scripts/pdo_conn.php");
include("data_scripts/utils.php");

/**
 * New iPad pre-check-in validation.
 *
 * Rules:
 * - Do NOT block by duplicate name.
 * - Do NOT block already checked-in visitors.
 * - Do NOT block already checked-out visitors.
 * - Do NOT check BadgeID.
 * - Only block when Email and EmpID belong to different visitors.
 *
 * Response format stays same:
 * emailConflict ### visitDup ### badgeDup ### nameDup
 */

$emailConflict = 0;
$visitDup = 0;
$badgeDup = 0;
$nameDup = 0;

$visitorId = trim((string) ($_POST["vid"] ?? ""));
$email = trim((string) ($_POST["em"] ?? ""));
$empId = trim((string) ($_POST["empid"] ?? ""));
$company = trim((string) ($_POST["company"] ?? ""));

$emailVisitorId = null;
$empVisitorId = null;

if ($email !== '') {
    $row = $pdo->query(
        "SELECT VisitorID 
         FROM Visitors 
         WHERE Email = " . $pdo->quote($email) . " 
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!empty($row["VisitorID"])) {
        $emailVisitorId = (string) $row["VisitorID"];
    }
}

$company = trim((string) ($_POST["company"] ?? ""));

if ($empId !== '') {
    $companySql = ($company !== '')
        ? " AND CompanyName = " . $pdo->quote($company)
        : "";

    $row = $pdo->query(
        "SELECT VisitorID 
         FROM Visitors 
         WHERE EmpID = " . $pdo->quote($empId) . "
         " . $companySql . "
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!empty($row["VisitorID"])) {
        $empVisitorId = (string) $row["VisitorID"];
    }
}

// If both Email and EmpID exist but belong to different visitors, block it.
if ($emailVisitorId !== null && $empVisitorId !== null && $emailVisitorId !== $empVisitorId) {
    $emailConflict = 1;
}

// If frontend already has VisitorID, make sure entered Email/EmpID do not belong to another visitor.
if ($visitorId !== '') {
    if ($emailVisitorId !== null && $emailVisitorId !== $visitorId) {
        $emailConflict = 1;
    }

    if ($empVisitorId !== null && $empVisitorId !== $visitorId) {
        $emailConflict = 1;
    }
}

echo $emailConflict . "###" . $visitDup . "###" . $badgeDup . "###" . $nameDup;