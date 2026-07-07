<?php
session_start();
include("data_scripts/pdo_conn.php");
include("data_scripts/utils.php");

if (!empty($_POST["idst"])) {
    $blocked = 0;
    $res = 0;
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    foreach ($_POST["idst"] as $key => $value) {
        $visitId = $value[10];

        $curRow = $pdo->query(
            "SELECT VisitStatus, VisitDate 
             FROM Visits 
             WHERE VisitID = " . $pdo->quote($visitId)
        )->fetch(PDO::FETCH_ASSOC);

        if (!$curRow) {
            $blocked++;
            continue;
        }

        $currentStatus = (int) $curRow['VisitStatus'];

        if ($action === 'checkin') {

            if ($currentStatus === 1) {
                $status = 2;
                $stname = "Checked in";
                $eventType = "IN";
                $chkoutqry = ", CheckinTime = " . $pdo->quote(date('H:i')) . ", CheckoutTime = NULL";
            } elseif ($currentStatus === 2) {
                $blocked++;
                continue;
            } elseif ($currentStatus === 3) {

                $qryInsert = "
            INSERT INTO Visits
            (
                VisitorID,
                VisitDate,
                CheckinTime,
                CheckoutTime,
                HostID,
                VisitAbout,
                VisitComment,
                VisitStatus,
                CenterID,
                chkosystem,
                ipad,
                AreaID,
                BadgeID,
                RoomTimeID,
                VisitorType
            )
            SELECT
                VisitorID,
                CURDATE(),
                CURTIME(),
                NULL,
                HostID,
                VisitAbout,
                VisitComment,
                2,
                CenterID,
                chkosystem,
                ipad,
                AreaID,
                BadgeID,
                RoomTimeID,
                VisitorType
            FROM Visits
            WHERE VisitID = " . $pdo->quote($visitId);

                if ($pdo->query($qryInsert)) {
                    $newVisitId = $pdo->lastInsertId();

                    $pdo->query(
                        "INSERT INTO VisitEvents (VisitID, Email, EventType)
                 SELECT " . $pdo->quote($newVisitId) . ", vr.Email, 'IN'
                 FROM Visits v
                 INNER JOIN Visitors vr ON vr.VisitorID = v.VisitorID
                 WHERE v.VisitID = " . $pdo->quote($newVisitId)
                    );

                    writeToLog($pdo, 6, "Checked in - New Visit ID: " . $newVisitId);
                    $res = 1;
                } else {
                    $res = 0;
                }

                continue;
            }

        } elseif ($action === 'checkout') {

            if ($currentStatus !== 2) {
                $blocked++;
                continue;
            }

            $status = 3;
            $stname = "Checked out";
            $eventType = "OUT";
            $chkoutqry = ", CheckoutTime = " . $pdo->quote(date('H:i'));

        } else {

            if ($value[1] == "Expected" || $currentStatus === 1) {
                $status = 2;
                $stname = "Checked in";
                $eventType = "IN";
                $chkoutqry = ", CheckinTime = " . $pdo->quote(date('H:i')) . ", CheckoutTime = NULL";
            } else {
                $blocked++;
                continue;
            }
        }

        $qry = "UPDATE Visits 
        SET VisitStatus = " . $pdo->quote($status) . " {$chkoutqry} 
        WHERE VisitID = " . $pdo->quote($visitId);

        if ($pdo->query($qry)) {
            $res = 1;
            writeToLog($pdo, 6, $stname . " - Visit ID: " . $visitId);

            $qryVisitDetails = "
        SELECT vr.Email, v.VisitAbout
        FROM Visits v
        INNER JOIN Visitors vr ON vr.VisitorID = v.VisitorID
        WHERE v.VisitID = " . $pdo->quote($visitId);

            $visitDetails = $pdo->query($qryVisitDetails)->fetch(PDO::FETCH_ASSOC);
            $visitor_email = trim((string) ($visitDetails['Email'] ?? ''));
            $visit_about = $visitDetails['VisitAbout'] ?? '';

            $pdo->query(
                "INSERT INTO VisitEvents (VisitID, Email, EventType) 
         VALUES (" .
                $pdo->quote($visitId) . ", " .
                $pdo->quote($visitor_email) . ", " .
                $pdo->quote($eventType) . ")"
            );

            $host_email = $value[11];
            $host_name = $value[6];
            $visitor_name = $value[2];
            $visitor_company = $value[8];
            $sys_from_email = $_SESSION['ttsy'];
            $sys_from_name = $_SESSION['firstName'] . " " . $_SESSION['lastName'];

            if ($status == 2) {
                include("visit_email.php");
            } else if ($status == 3) {
                $chkotime = date('H:i');
                include("host_email.php");
            }
        } else {
            $res = 0;
        }
    }

    if (!empty($blocked) && empty($res)) {
        echo "Some selected visits could not be updated. Check-out is only allowed for visits currently checked in.";
    } elseif (!empty($blocked)) {
        echo "The selected visits' status(es) have been updated. Some visits were skipped because the requested action was not valid for their current status.";
    } else {
        echo (empty($res)) ? "Sorry, there is an error during status(es) update. Please try it again." : "The selected visits' status(es) have been successfully updated!";
    }
}