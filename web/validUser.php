<?php

        require_once "dbconnect.inc.php";
	$link = mysql_connect($mysql_host, $mysql_user, $mysql_passwd);
	mysql_select_db($mysql_db, $link);

        $username = (array_key_exists("u", $_GET)) ? mysql_real_escape_string($_GET['u']) : "";

	if($username === "" or strlen($username) < 3){
		mysql_close($link);
		die("false");	
	}

	$result = mysql_query("select 1 from user where user ='$username'", $link) or die(mysql_error());
	
	if(mysql_num_rows($result) > 0){
		echo "false";
	}else{
		echo "true";
	}

	mysql_close($link);
?>
