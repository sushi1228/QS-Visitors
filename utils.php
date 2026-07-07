<?php
/****
* Functions for QS Visits
* 
*********/

date_default_timezone_set('America/New_York');

$xlsFolder = "rpts"; // Folder for XLS export
$SiteURL = "http://qsvisits.georgiaquickstart.org"; 
$sys_from_email = "qsvisits@georgiaquickstart.org";
$sys_from_name = "Quick Start Reception";

// Date Format YYYY-MM-DD to MM/DD/YYYY
function DateFormat($date) {
	$formattedDate ="";
	if (!empty($date)) {
		$daty = explode("-",$date);
		$formattedDate =$daty[1]."/".$daty[2]."/".$daty[0];
	}
	return $formattedDate;
}
// Date Format MM/DD/YYYY to YYYY-MM-DD 
function DateFormatDB($date) {
	$formattedDate ="";
	if (!empty($date)) {
		$daty = explode("/",$date);
		$formattedDate =$daty[2]."-".$daty[0]."-".$daty[1];
	}
	return $formattedDate;
}

function DateTimeFormatDB($datetime) {
	// Expect XML datetime from Sync first
	$datetime = DateTime::createFromFormat('Y-m-d h:i A', $datetime);
	$formattedDateTime="";
	if ($datetime !== false) {
		$formattedDateTime = $datetime->format('Y-m-d H:i:s');
	} else {
		$dt = new DateTime($datetime);
		$formattedDateTime = $dt->format('Y-m-d H:i:s');
	}
	return $formattedDateTime;
}

// Fiscal Year Conversion : type = 1 (Actual to FY) , type = 2 (FY to Actual)
function ConvertFiscalYear ($daty, $type=1) {
	//echo $daty."<br/>";
	$datab = explode("/",$daty); //Format : MM/DD/YYYY
	//print_r($datab);
	if($datab[0]<=6) {
		$Mo = intval($datab[0]) + 6;
		$Year = $datab[2];
	} else {
		$Mo = intval($datab[0]) - 6;
		$Year =  ($type==1) ? $datab[2]+1 : $datab[2]-1;
	}
	if ($Mo<=9) $Mo="0".$Mo;
	return $Mo."/".$datab[1]."/".$Year;
}

// Dates Difference  : total months
function Months_Numbers ($date11, $date22) {
	$date1 = DateTime::createFromFormat('m/d/Y', $date11);
	$date2 = DateTime::createFromFormat('m/d/Y', $date22);
	//echo $date1->format('Y-m-d'). "|". $date2->format('Y-m-d')."<br/>";

	$d1 = new DateTime($date1->format('Y-m-d'));
	$d2 = new DateTime($date2->format('Y-m-d'));

	$interval = $d2->diff($d1);
	//$days = $interval->format('%d');
	$months = $interval->format('%m');
	$years = $interval->format('%y');
	//echo "$years | $months | $days <br/>";
	// 1 Year = 12 months
	$tot_months = $months + (12*$years);
	return  $tot_months;
}

function Days_Numbers ($date11, $date22) { 
	$start_date = strtotime(DateFormatDB($date11)); 
	$end_date = strtotime(DateFormatDB($date22));   
	// Get the difference and divide into  
	// total no. seconds 60/60/24 to get  
	// number of days 
	$tot_days = ($end_date - $start_date)/60/60/24;
	return  $tot_days;
}

function ListCenters($pdo, $select=0, $where="") {
	// Hardcoded multi-center access for specific receptionists
	// Dru Hodges, Vickie Conner, Hillery Sanchez
	// These users can only access: AMTC (10), AMTCG (20), Lanier (30)
	$multiCenterEmails = [
		'dhodges@georgiaquickstart.org',
		'vconner@georgiaquickstart.org',
		'hsanchez@georgiaquickstart.org',
		'cbing@georgiaquickstart.org'
	];
	$allowedCenters = [10, 20, 30]; // AMTC, AMTCG, Lanier
	
	// Check if current user is one of the multi-center receptionists (by email)
	$userEmail = isset($_SESSION['ttsy']) ? strtolower($_SESSION['ttsy']) : '';
	$isMultiCenterUser = in_array($userEmail, $multiCenterEmails);
	
	if ($isMultiCenterUser) {
		// For multi-center users, only show their allowed centers
		$centerList = implode(',', $allowedCenters);
		$qry = "select CenterID, Center from QSCenter where CenterID IN ({$centerList}) order by Center";
	} else {
		// For all other users, use the original query
		$qry = "select CenterID, Center from QSCenter {$where} order by Center";
	}
	
	if (empty($where)) $selStr="<option value=''>List of QS centers</option>";
	foreach ($pdo->query($qry) as $region) {
		$selection = ($select==$region['CenterID']) ? "selected" : "";
		$selStr.="<option value='".$region['CenterID']."' $selection>".$region['Center']."</option>";
	}
	return $selStr;
}

function ListCenterAreas($pdo, $select=0, $where="") {
	$qry = "select AreaID, AreaName from QSCenterArea {$where} order by AreaID";
	$selStr="<option value=''>All Areas</option>";
	foreach ($pdo->query($qry) as $floor) {
		$selection = ($select==$floor['AreaID']) ? "selected" : "";
		$selStr.= "<option value='".$floor['AreaID']."' $selection>".$floor['AreaName']."</option>";
	}
	return $selStr;
}

function GetQSCenter($pdo, $centerid) {
	$qry = "select Center from QSCenter where CenterID = " . $pdo->quote($centerid);	
	$rs = $pdo->query($qry)->fetch();
	return ($rs[0]);
}

function GetQSCenterIDByIP($pdo, $IPAddress="") {
	if ($IPAddress === "" && !empty($_ENV['IPAD_CENTER_ID'])) {
		return (string) $_ENV['IPAD_CENTER_ID'];
	}

	$ip = ($IPAddress !== "") ? $IPAddress : getCenterDetectionIp();
	$ipAddr = explode(".", $ip);
	if (count($ipAddr) < 2) {
		error_log("GetQSCenterIDByIP: invalid IP for center lookup: " . $ip);
		return null;
	}
	$ipPrefix = $ipAddr[0] . "." . $ipAddr[1];
	$qry = "select CenterID from QSCenter where IPStart = " . $pdo->quote($ipPrefix);
	$rs = $pdo->query($qry)->fetch();
	$centerId = $rs[0] ?? null;
	if ($centerId === null) {
		error_log("GetQSCenterIDByIP: no center for IP prefix {$ipPrefix} (full IP {$ip})");
	}
	return $centerId;
}

function GetQSCenterByXmlFile($pdo, $xmlFilename) {
	$qry = "select * from QSCenter where XMLFile = " . $pdo->quote($xmlFilename);	
	$rs = $pdo->query($qry)->fetch();
	if ($rs == false) return false;
	return ($rs[0]);
}

function GetRoomTimeById($pdo, $roomTimeId) {
	$qry = "select RoomTimeID from RoomTimes where RoomTimeID = " . $pdo->quote($roomTimeId);	
	$rs = $pdo->query($qry)->fetch();
	if ($rs == false) return false;
	return ($rs[0]);
}

function ListRoles($pdo, $select=0) {
	$qry = "select RoleID, Role from Roles order by `Rank`";	
	$selStr="<option value=''>List of Roles</option>";	
	foreach ($pdo->query($qry) as $row) {
		$selection = ($select===$row['RoleID']) ? "selected" : "";		
		$selStr.="<option value='".$row['RoleID']."' $selection>".$row['Role']."</option>";
	}		
	return $selStr;
}

function ListVisitStatus($pdo, $select=0, $wherec="") {
	$qry = "select VisitStatusID, VisitStatus from VisitStatus {$wherec} order by VisitStatusID";	
	$selStr="<option value=''>Select one status</option>";
	foreach ($pdo->query($qry) as $row) {
		$selection = ($select==$row['VisitStatusID']) ? "selected" : "";
		$selStr.="<option value='".$row['VisitStatusID']."' $selection>".$row['VisitStatus']."</option>";
	}		
	return $selStr;
}

// Redirection
function Redirection ($url) {
	$redirect= "<script type=\"text/javascript\">";
	$redirect.= "document.location.href='$url';";
	$redirect.= "</script>";
	return ($redirect);
}

function GetUserByEmail($pdo, $userEmail) {
	$qry = "select UserID from Users where Email = " . $pdo->quote($userEmail);	
	$rs = $pdo->query($qry)->fetch();
	if ($rs == false) return false;
	return ($rs[0]);
}

// Select Users	
function ListUsers($pdo, $selected=0, $where="") {
	$qry = "SELECT UserID, CONCAT_WS(' ', LastName, FirstName) AS name FROM Users $where ORDER BY name";		
	$selStr="<option value=''>Select a host</option>";
	foreach($pdo->query($qry) as $row) {
		$sel = ($row['UserID']==$selected) ? "selected" : "";
		$selStr.="<option value='".$row['UserID']."' $sel >".trim($row['name'])."</option>";	
	}	
	return $selStr;
}

function TimesAMPM () {
	$options = array();
	foreach (range(6,18) as $fullhour) {
	   $parthour = $fullhour > 12 ? $fullhour - 12 : $fullhour;
	   $time = $fullhour > 11 ? " pm" : " am";
	   $hour = ($fullhour<10) ? "0".$fullhour : $fullhour;
	   $options["$hour:00"] = "$parthour:00".$time;
	   $options["$hour:15"] = "$parthour:15".$time;
	   $options["$hour:30"] = "$parthour:30".$time; 
	   $options["$hour:45"] = "$parthour:45".$time; 
	}
	return $options;
}

function SelectTimesAMPM ($field, $seltime="") {
	$times = TimesAMPM();
	$times_str="<select class=\"input-sm\" name=\"{$field}\" id=\"{$field}\">";
	foreach ($times as $timek=> $timeval) {
		$select = ($timek===substr($seltime,0,-3)) ? "selected" :"";
		$times_str.="<option value='$timek' $select>$timeval</option>";
	}
	$times_str.="</select>";
	return $times_str;
}

function DisplayTimeDBtoAMPM($dbtime) {
	$times = TimesAMPM();	
	$time_ampm = (!empty($dbtime)) ? $times[$dbtime] : "";
	return $time_ampm;
}

function InsertData($pdo, $table, $data, $exclu_fld=Array()) {
	$table_fields = GetFieldsObj($pdo, $table);

	$InsertStr = "INSERT INTO $table ( ";
	$InsertVal="(";
	foreach ($table_fields as $colName=>$meta) {
		$colType = $meta[0]['Type'];
		if (!in_array($colName,$exclu_fld)) {
			if(!empty($colType)) {

				if ($colType=='date' && !empty($data[$colName])) {
					$data[$colName] = DateFormatDB($data[$colName]);
				}

				if ($colType=='datetime' && !empty($data[$colName])) {
					$data[$colName] = DateTimeFormatDB($data[$colName]);
				}

				if (preg_match("/password/i", $colName) && !empty($data[$colName])) {
					$data[$colName] = ENCRYPT_DECRYPT($data[$colName]);
				}

				$InsertStr.= " $colName,";

				if (isset($data[$colName]) && $data[$colName] === '_NULL_') {
					$InsertVal .= "NULL,";
				} elseif (isset($data[$colName]) && $data[$colName] === '') {
					// Convert empty strings to NULL for better database practice
					$InsertVal .= "NULL,";
				} else {
					$InsertVal .= $pdo->quote($data[$colName]) . ",";
				}

			}
		}
	}

	// Build Insert Query string
	$InsertStr=substr($InsertStr,0,-1);
	$InsertVal=substr($InsertVal,0,-1);
	if (!empty($InsertVal)) {
		$qry = $InsertStr.") VALUES ".$InsertVal.")";
		try {
			error_log("InsertData Query: " . $qry); // Log the query
			$result = $pdo->query($qry);
			if ($result) {
				$lastId = $pdo->lastInsertId();
				error_log("InsertData Success: Last Insert ID = " . $lastId);
				return $lastId;
			} else {
				error_log("InsertData Failed: query returned false");
				$errorInfo = $pdo->errorInfo();
				error_log("PDO Error: " . print_r($errorInfo, true));
				echo "<div style='background:red;color:white;padding:10px;'>SQL Error: " . htmlspecialchars($errorInfo[2]) . "</div>";
				return null;
			}
		} catch (PDOException $e) {
			error_log("InsertData Exception: " . $e->getMessage());
			echo "<div style='background:red;color:white;padding:10px;'>SQL Error inserting record: " . htmlspecialchars($e->getMessage()) . "<br>Query: " . htmlspecialchars($qry) . "</div>";
			return null;
		}
	} else {
		error_log("InsertData: InsertVal is empty");
		return null;
	}
}

// @Deprecating, Replace with GetFieldsObj
function GetFieldsNames ($pdo, $table) {
	$q = $pdo->prepare("DESCRIBE $table");
	$q->execute();
	$table_fields = $q->fetchAll(PDO::FETCH_COLUMN);
	return $table_fields;
}

function GetFieldsObj ($pdo, $table) {
	$q = $pdo->prepare("DESCRIBE $table");
	$q->execute();
	$table_fields = $q->fetchAll(PDO::FETCH_GROUP);
	return $table_fields;
}

function UpdateData ($pdo, $table, $data, $id, $exclu_fld=Array()) {
	$table_fields = GetFieldsObj($pdo, $table);

	$updateStr = "UPDATE $table SET ";
	$idname = null;
	foreach ($table_fields as $colName=>$meta) {
		$colType = $meta[0]['Type'];
		if (!in_array($colName, $exclu_fld)) {
			if ($idname === null) {
				$idname = $colName;
			} else {
				if (!empty($colType)) {
					if ($colType == 'date' && !empty($data[$colName])) {
						$data[$colName] = DateFormatDB($data[$colName]);
					}
					
					if ($colType == 'datetime' && !empty($data[$colName])) {
						$data[$colName] = DateTimeFormatDB($data[$colName]);
					}
					
					if (preg_match("/password/i", $colName) && !empty($data[$colName])) {
						$data[$colName] = ENCRYPT_DECRYPT($data[$colName]);
					}
					
					if (isset($data[$colName]) && $data[$colName] === '_NULL_') {
						$updateStr .= " {$colName} = NULL,";
					} elseif (isset($data[$colName]) && $data[$colName] === '') {
						// Convert empty strings to NULL for better database practice
						$updateStr .= " {$colName} = NULL,";
					} elseif (preg_match("/status/i", $colName) && empty($data[$colName])) {
						$updateStr .= " {$colName} = '0',";
					} else {
						$updateStr .= " {$colName} = ".$pdo->quote($data[$colName]).",";
					}
				}
			}
		}
	}
	if (!empty($idname)) {
		$updateStr = substr($updateStr, 0, -1);
		$updateStr .= " WHERE $idname = ".$pdo->quote($id);
		//echo "UPDATE QRY: $updateStr<br/>";
		$pdo->query($updateStr);
	}
}

function DeleteData ($pdo, $table, $id, $idname) {
	$delStr = "DELETE FROM $table WHERE $idname = ".$pdo->quote($id);
	echo "<br/>$delStr<br/>";
	//$pdo->query($delStr);
}

function DisplayDataValue($pdo, $table, $id, $idname, $order_str ="") {
	$qry = "select * from $table where $idname = ".$pdo->quote($id). $order_str;	
	return $pdo->query($qry)->fetchAll(); 
}

function writeToLog($pdo, $code, $param="") {
	$user = (!empty($_SESSION['uid'])) ? $_SESSION['uid'] : ""; 
	date_default_timezone_set("America/New_York"); 
	$time = date("H:i:s");
	$date = date('Y-m-d');
	$message="";
	$ip = getIpAddress();
	switch ($code) {
		// Admin interface
		case 0:
		    $message = "Logged on to Visitors Management System";
		    break;
		case 1:
		    $message = "Logged out of Visitors Management System";
		    break;
		case 2:
		    $message = "Failed Login - Visitors Management System";
		    break;
		case 3:
		    $message = "Visitor " . $param . "checked in via iPad";
		    break;
		case 4:
		    $message = "New Visitor " . $param . " added.";
		    break;    
		case 5:
		    $message = "New visit added: ".$param;
		    break;
		case 6:
		    $message = "Visit status changed to " . $param;
		    break;
		case 7:
		    $message = "Email sent to Host: " . $param;
		    break;
		case 8:
		    $message = "Email sent to Visitor: ". $param;
		    break;
		case 9:
		    $message = "Single QS Staff/Host added: ". $param;
		    break;
		case 10:
		    $message = "QS Staff/Host(s) imported: " . $param;
		    break;
		case 11:
		    $message = "Emergency list viewed for : " . $param;
		    break;    
		case 12:
		    $message = "Emergency list exported on : ". $param;
		    break;
		case 13:
		    $message = "A QS Staff/Host info updated: ". $param;
		    break;
		case 14:
		    $message = "Password sent to user: ". $param;
		    break;
		case 15:
		    $message = "Exisiting visit edited: " . $param;
		    break;
		case 16:
		    $message = "Visit removed: ".$param;
		    break;
		case 17:
		    $message = "Visitor(s) and their visit(s) removed: ".$param;
		    break;
		case 18:
		    $message = "Existing Visitor Info updated. ".$param;
		    break;
		case 19:
		    $message = "Visitor has been checked out from iPad. ".$param;
		    break;
		case 20:
		    $message = "Visitor has been checked in from iPad. ".$param;
		    break;
		case 21:
		    $message = " :".$param;
		    break;
		case 22:
			$message = "Room Time sync ran: ".$param;
		default:
			break;
			
	}
	
	// insert into Logs Table 	
	if (strlen($message)) {
		$sql = "INSERT INTO `Logs` (`logId` ,`date` ,`time` ,`systemMessage` ,`user`, `ipaddress` ,`code`) ";
		$sql .="VALUES ";
		$sql .="(NULL ,'$date','$time', ".$pdo->quote($message).", '$user', '$ip', '$code');";
		//echo $sql."<br>";
		if (!$pdo->query($sql))	die ("Error in inserting into log table!");
	}
}

function getIpAddress() {
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
		$ip = $_SERVER['HTTP_X_REAL_IP'];
	} else {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	}
	return $ip;
}

/**
 * IP used to match QSCenter.IPStart for iPad check-in.
 * Prefer the browser/kiosk client IP when PHP runs on a remote Linux server;
 * fall back to this host's LAN IP when PHP runs on the kiosk itself.
 */
function getCenterDetectionIp(): string {
	if (!empty($_ENV['IPAD_DEVICE_IP'])) {
		return trim((string) $_ENV['IPAD_DEVICE_IP']);
	}

	$clientIp = trim(explode(',', (string) getIpAddress())[0]);
	if ($clientIp !== '' && $clientIp !== '127.0.0.1' && isPrivateIpv4($clientIp)) {
		return $clientIp;
	}

	return getDevicePrivateIp();
}

/**
 * Private IPv4 of this machine (web server / kiosk host).
 */
function getDevicePrivateIp(): string {
	$candidates = [];

	if (!empty($_SERVER['SERVER_ADDR'])) {
		$candidates[] = trim((string) $_SERVER['SERVER_ADDR']);
	}

	$routeIp = getLocalIpViaDefaultRoute();
	if ($routeIp !== null) {
		$candidates[] = $routeIp;
	}

	$clientIp = trim(explode(',', (string) getIpAddress())[0]);
	if ($clientIp !== '') {
		$candidates[] = $clientIp;
	}

	foreach ($candidates as $ip) {
		if ($ip !== '' && $ip !== '127.0.0.1' && isPrivateIpv4($ip)) {
			return $ip;
		}
	}

	return '127.0.0.1';
}

function isPrivateIpv4(string $ip): bool {
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return false;
	}
	return filter_var(
		$ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	) === false;
}

/**
 * Parse "First Last" visitor name from iPad / kiosk forms.
 *
 * @return array{first: string, last: string}|null
 */
function parseVisitorFullName(string $visitorName): ?array
{
	$parts = preg_split('/\s+/', trim($visitorName), 2);
	if ($parts === false || count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
		return null;
	}

	return ['first' => $parts[0], 'last' => $parts[1]];
}

function normalizeVisitorFullNameKey(string $firstName, string $lastName): string
{
	return strtolower(trim($firstName) . ' ' . trim($lastName));
}

/**
 * iPad check-in validation: duplicate name on date, already checked in, etc.
 *
 * @return array{ok: bool, code: string, message: string}
 */
function validateIpadCheckin(PDO $pdo, string $visitorName, string $visitorId, string $visitId, string $visitDateYmd, ?string $centerId): array
{
	$parsed = parseVisitorFullName($visitorName);
	if ($parsed === null) {
		return ['ok' => false, 'code' => 'invalid_name', 'message' => 'Visitor name must contain first and last name separated by a space.'];
	}

	$visitDateDb = DateFormatDB($visitDateYmd);
	$centerSql = ($centerId !== null && $centerId !== '') ? ' AND v.CenterID = ' . $pdo->quote($centerId) : '';
	$nameKey = normalizeVisitorFullNameKey($parsed['first'], $parsed['last']);
	$excludeVisitSql = ($visitId !== '') ? ' AND v.VisitID <> ' . $pdo->quote($visitId) : '';

	// Another visitor record with the same name already has a visit today (different people, same name).
	if ($visitorId === '') {
		$qryName = "
			SELECT COUNT(*) AS nb
			FROM Visits v
			INNER JOIN Visitors r ON r.VisitorID = v.VisitorID
			WHERE v.VisitDate = " . $pdo->quote($visitDateDb) . "
				{$centerSql}
				AND LOWER(CONCAT(TRIM(r.FirstName), ' ', TRIM(r.LastName))) = " . $pdo->quote($nameKey) . "
				{$excludeVisitSql}";
		$nameCount = (int) ($pdo->query($qryName)->fetchColumn() ?: 0);
		if ($nameCount > 0) {
			return [
				'ok' => false,
				'code' => 'duplicate_name',
				'message' => 'A visitor with this name is already registered for today. Select the correct person from the list or use a distinguishing name (e.g. middle initial).',
			];
		}
	} else {
		$qryOtherName = "
			SELECT COUNT(*) AS nb
			FROM Visits v
			INNER JOIN Visitors r ON r.VisitorID = v.VisitorID
			WHERE v.VisitDate = " . $pdo->quote($visitDateDb) . "
				{$centerSql}
				AND LOWER(CONCAT(TRIM(r.FirstName), ' ', TRIM(r.LastName))) = " . $pdo->quote($nameKey) . "
				AND r.VisitorID <> " . $pdo->quote($visitorId) . "
				{$excludeVisitSql}";
		$otherNameCount = (int) ($pdo->query($qryOtherName)->fetchColumn() ?: 0);
		if ($otherNameCount > 0) {
			return [
				'ok' => false,
				'code' => 'duplicate_name',
				'message' => 'A visitor with this name is already registered for today. Select the correct person from the list or use a distinguishing name (e.g. middle initial).',
			];
		}
	}

	// Selected visit is no longer Expected (already checked in or out).
	if ($visitId !== '') {
		$curVisit = $pdo->query(
			"SELECT VisitStatus FROM Visits WHERE VisitID = " . $pdo->quote($visitId)
		)->fetch(PDO::FETCH_ASSOC);
		if ($curVisit && (int) $curVisit['VisitStatus'] === 2) {
			return [
				'ok' => false,
				'code' => 'already_checked_in',
				'message' => 'This person is already checked in today. Please check out first before checking in again.',
			];
		}
		if ($curVisit && (int) $curVisit['VisitStatus'] === 3) {
			return [
				'ok' => false,
				'code' => 'already_checked_out',
				'message' => 'This person has already checked out today and cannot check in again on this visit.',
			];
		}
	}

	// Same person already has an open checked-in visit today.
	$visitorScopeSql = ($visitorId !== '')
		? ' AND v.VisitorID = ' . $pdo->quote($visitorId)
		: " AND LOWER(CONCAT(TRIM(r.FirstName), ' ', TRIM(r.LastName))) = " . $pdo->quote($nameKey);

	$qryOpen = "
		SELECT COUNT(*) AS nb
		FROM Visits v
		INNER JOIN Visitors r ON r.VisitorID = v.VisitorID
		WHERE v.VisitDate = " . $pdo->quote($visitDateDb) . "
			{$centerSql}
			{$visitorScopeSql}
			AND v.VisitStatus = 2
			{$excludeVisitSql}";
	$openCount = (int) ($pdo->query($qryOpen)->fetchColumn() ?: 0);
	if ($openCount > 0) {
		return [
			'ok' => false,
			'code' => 'already_checked_in',
			'message' => 'This person is already checked in today. Please check out first before checking in again.',
		];
	}

	return ['ok' => true, 'code' => 'ok', 'message' => ''];
}

function visitorEmailsMatchForCheckout(string $dbEmail, string $formEmail): bool
{
	$db = trim($dbEmail);
	$form = trim($formEmail);
	if ($db === '' || $db === ' ') {
		return $form === '';
	}
	if ($form === '') {
		return true;
	}

	return strcasecmp($db, $form) === 0;
}

function getLocalIpViaDefaultRoute(): ?string
{
	if (!function_exists('socket_create')) {
		return null;
	}
	$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($sock === false) {
		return null;
	}
	$connected = @socket_connect($sock, '8.8.8.8', 53);
	if (!$connected) {
		socket_close($sock);
		return null;
	}
	$addr = '';
	if (@socket_getsockname($sock, $addr) && $addr !== '' && $addr !== '0.0.0.0') {
		socket_close($sock);
		return $addr;
	}
	socket_close($sock);
	return null;
}

function ENCRYPT_DECRYPT($Str_Message) 
{
    $Len_Str_Message=STRLEN($Str_Message);
    $Str_Encrypted_Message="";
    FOR ($Position = 0;$Position<$Len_Str_Message;$Position++){
        $Key_To_Use = (($Len_Str_Message+$Position)+1); // (+5 or *3 or ^2)
        $Key_To_Use = (255+$Key_To_Use) % 255;
        $Byte_To_Be_Encrypted = SUBSTR($Str_Message, $Position, 1);
        $Ascii_Num_Byte_To_Encrypt = ORD($Byte_To_Be_Encrypted);
        $Xored_Byte = $Ascii_Num_Byte_To_Encrypt ^ $Key_To_Use;  //xor operation
        $Encrypted_Byte = CHR($Xored_Byte);
        $Str_Encrypted_Message .= $Encrypted_Byte;
    }
    RETURN $Str_Encrypted_Message;
} 

function GenerateAutoPassword($len = 10)
{
    // function calculates 32-digit hexadecimal md5 hash
    // of some random data
    return substr(md5(rand().rand()), 0, $len);
}

/*upload file*/
function UploadFile($name,$type,$size,$tmp,$allowed,$err,$u,$path) {
	$msg="";
	//echo $size."|".$type."<br/>";
	//print_r($allowed)."<br/>";
	if (in_array($type, $allowed) && ($size < 1073741824)) { // 1G
		if ($err > 0) {
			$msg="There was an error [$err] uploading the file, please try again.";
		} else {
			$fileNameArr = explode('.' , $name);
			$fileName = $u.'.'.$fileNameArr[(count($fileNameArr)-1)];
			if (file_exists($fileName)) chmod($path. '/' . $fileName, 0777);
			if (move_uploaded_file($tmp, $path. '/' . $fileName)) {
				//$msg="<div style='font:bold 12px Arial,Helvetica,sans-serif; color:#b90000; text-align:left; padding-left:20px;'>".$path. "/" . $fileName."<br>The file has been uploaded successfully.</div>";
			} else {
				$msg="There was an error uploading the  file.";
			}
		}
	} else {
		$msg="The file size is large to be uploaded or you upload a wrong type of file!";
	}	
	return ($msg);
}
