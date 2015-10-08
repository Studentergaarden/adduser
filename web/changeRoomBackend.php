<?php
require_once "dbconnect.inc.php";

$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_passwd, $mysql_db);
$mysqli->set_charset("utf8");

$action  = array_key_exists("a", $_GET) ? $_GET['a'] : false;
$name_id = array_key_exists("n", $_GET) ? $mysqli->real_escape_string($_GET['n']) : false;
$room 	 = array_key_exists("r", $_GET) ? $mysqli->real_escape_string($_GET['r']) : false;

if(!$action || !$name_id || !$room){
	echo ("-1:Error invalid or missing action - Don't continue!");
}

switch($action){
	case "flyt":		
		if ($mysqli->query("UPDATE name SET status='flyttet' WHERE name_id='$name_id'")){
			echo "success";
		}else{
			echo "-1:Fejl ved flyttet opdatering";
		}
		break;

	case "inte":
		$existing = $mysqli->query("select name_id, name from name where room=$room AND (status='normal')");

    	$mysqli->query("UPDATE name SET room='$room' WHERE name_id='$name_id'");
		
		if($row = $existing->fetch_array(MYSQLI_ASSOC)){
			echo $row['name_id'].":".$row['name'];
		}else{
			echo "success";
		}
		break;
	
	case "orl":
		if ($mysqli->query("UPDATE name SET status='orlov' WHERE name_id='$name_id'")){
			echo "succes";
		}else{
			echo "-1:Fejl ved orlov opdatering";
		}
		break;
	case "udl":
		if ($mysqli->query("UPDATE name SET status='udlejer' WHERE name_id='$name_id'")){
			echo "success";
		}else{
			echo "-1:Fejl ved udlejer opdatering";
		}
		break;
	default:
		echo ("-1:Error invalid or missing action - Don't continue!");
		break;
}

$mysqli->close();

?>