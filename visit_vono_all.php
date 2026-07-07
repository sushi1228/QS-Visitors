<?php
session_start();
include("data_scripts/pdo_conn.php");
include ("data_scripts/utils.php");
if(!empty($_POST["idst"])) {
	foreach ($_POST["idst"] as $key=>$value) {		
		$qry = "DELETE FROM Visits WHERE VisitID = ".$pdo->quote($value[10]);	
		if ($pdo->query($qry)) { $res=1; writeToLog($pdo, 16, " - Visit ID: ". $value[10]);
		} else $res = 0;
	}
	echo (empty($res)) ? "Sorry, there is an error during vistor removal. Please try it again." : "The selected visitor(s) have been successfully removed!";
}