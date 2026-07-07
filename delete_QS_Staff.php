<?php
session_start();
include("data_scripts/pdo_conn.php");

if (!empty($_POST["idst"])) {

    foreach ($_POST["idst"] as $key => $value) {

        $qry = "DELETE FROM Users
                WHERE UserID = " . $pdo->quote($value[7]);

        if ($pdo->query($qry))
            $res = 1;
        else
            $res = 0;
    }

    echo (empty($res))
        ? "Sorry, there was an error deleting the selected host(s). Please try again."
        : "The selected Host/QS Staff have been successfully deleted!";
}
?>