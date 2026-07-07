<?php
session_start();
include("data_scripts/pdo_conn.php");
include ("data_scripts/utils.php");
if(!empty($_POST["idvisit"])) {	
	$qry = "DELETE FROM Visits WHERE VisitID = ".$pdo->quote($_POST["idvisit"]);	
	if ($pdo->query($qry)) { $res=1; writeToLog($pdo, 16, "Visit ID: " . $_POST["idvisit"]);
	} else $res = 0;
	echo (empty($res)) ? "Sorry, there is an error during status(es) update. Please try it again." : "The selected visit has been removed from the system.";
}