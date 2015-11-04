<?php
session_start();
?>

<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <link href="sas.css" rel="stylesheet" type="text/css">
  <title>SAS v. 3.0 - Studentergaarden Administration System</title>
  <script src="jquery-1.11.2.min.js"> </script>
</head>
<body>

  <center>
    <table width=90%><tr><td>

<?php
$admin = (array_key_exists("admin_id", $_SESSION)) ? $_SESSION['admin_id'] : "false";

if($admin === "false"){
	die("Du er ikke logget ind");
}
$create = (array_key_exists("create", $_POST)) 			? $_POST["create"] : "";

require_once "dbconnect.inc.php";
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_passwd, $mysql_db);
$mysqli->set_charset("utf8");
/*
 * Integrate with the existing SAS menu - ugly!
 */
$sid = session_id();
include("init.inc.php");
include("common.inc.php");

menu($sid,$dbName,"grp");
print "<hr width=\"100%\">\n";
/*
 * End integration
 */

if($create === "true"){

  
	$name = (array_key_exists("name", $_POST)) 			? $_POST["name"] : "";
	$username = (array_key_exists("username", $_POST)) 	? $_POST["username"] : "";
	$password = (array_key_exists("password", $_POST)) 	? $_POST["password"] : "";
	$study = (array_key_exists("study", $_POST)) 		? $_POST["study"] : "";
	$room = (array_key_exists("room", $_POST)) 			? $_POST["room"] : "";
	$status = (array_key_exists("status", $_POST)) 		? $_POST["status"] : "";
	$phone = (array_key_exists("phone", $_POST)) 		? $_POST["phone"] : "";
	$email = (array_key_exists("email", $_POST)) 		? $_POST["email"] : "";

	$fp = fopen('/dev/urandom', 'r'); 
	$randomString = fread($fp, 32); 
	fclose($fp);
	$salt = base64_encode($randomString);

	// sha512 doesn not seem to work. Maybe because php<5.3
	$hash = crypt($password, '$6$rounds=5000$'.$salt);
	//$hash = crypt($password, '$1$'.$salt);

	$json = //room is moved with changeRoom $room
'{
	"name":"'.$name.'",
	"username":"'.$username.'",
	"password":"'.$hash.'",
	"study":"'.$study.'",
	"room":"1", 
	"status":"'.$status.'",
	"phone":"'.$phone.'",
	"email":"'.$email.'
"}';

	/*
     * remove newlines in the string
     * each newline is sent separately - it is easier to set up the daemon
     * to handle one long line, instead of multiple. The string needs to be
     * terminated.
	*/
	$json = trim(preg_replace('/\s+/', ' ', $json));
	$json = $json . "\n";

    $rcv_msg = "";
    $timeout = 10;
    $socket = stream_socket_client('unix:///var/lock/sas.sock', 
                                    $errorno, $errorstr, $timeout);
    stream_set_timeout($socket, $timeout);

    
    if(!fwrite($socket, $json))
            die("Error while writing!!!<br>\n");

    if (!($rcv_msg = fread($socket, 1024)))
    	die("Læsningen fra serveren gik galt. Prøv igen om lidt!<br>\n");
    else{
      // extract info from socket in key=val&key2=val2 pairs
      parse_str($rcv_msg, $ret);
      //preg_match_all("/([^,= ]+)=([^,= ]+)/", $rcv_msg, $r);
      //$ret = array_combine($r[1], $r[2]);
      if( strcmp($ret["success"], "1") == 0){

	$userdir  = $ret["userdir"];
	$group_id = $ret["gid"];
	$user_id  = $ret["uid"];
	$name     = $mysqli->real_escape_string($name);
	$study    = $mysqli->real_escape_string($study);
	$room     = $mysqli->real_escape_string($room);
	$phone    = $mysqli->real_escape_string($phone);
	$email    = $mysqli->real_escape_string($email);
	// create user in sgdb with room number 1
	$str= sprintf("INSERT INTO name SET name='%s',room='1',title='%s',status='%s',public='1',mtime=now();",
		      $name, $study, $status);
	$res = $mysqli->query($str);
	// get name_id
      	$res = $mysqli->query("SELECT name_id from name WHERE name='$name' AND room='1'");
	$row = $res->fetch_array(MYSQLI_ASSOC);
      	$name_id = $row["name_id"];
	
	if (!empty($phone)){
	  $str = sprintf("INSERT INTO info (info ,name_id, type, public, mtime) VALUES ('%s', %s, 'mobil', '0', now())",
			 $phone, $name_id);
	  $res = $mysqli->query($str);

	}

	// insert into user table
	$str = sprintf("INSERT INTO user SET user_id='%s', user='%s', name_id='%s', print='10', public='1', mtime=now();",
		       $user_id, $username, $name_id);
	$res = $mysqli->query($str);

	// change room to actual number and sort any room issues
      	header("location:changeRoom.php?r=$room&n=$name_id");
      }else{
        die('<span style="color:green;">Der gik noget galt, prøv en gang til.');
      }
    }
}


?>
		<form id="userForm" action="?" method="POST">
			<table>
				<tr>
					<td>Name</td><td><input type="text" name="name" /></td>
				</tr>
				<tr>
					<td>Username</td><td><input type="text" id="username" name="username" /></td>
				</tr>
				<tr><td></td><td>
		   		<b>username</b> skal være uden mellemrum og kun med <font color="red">små bogstaver</font>.<br>
		   		Hvis feltet lyser rødt, er det fordi brugernavnet er optaget eller ugyldigt.
		   		</td></tr>
				<tr>
					<td>Password</td><td><input type="password" id="password" name="password"/></td>
				</tr>
				<tr>
					<td>Retype password</td><td><input type="password" id="password2" name="password2"/></td>
				</tr>
				<tr>
					<td>Study</td><td><input type="text" name="study" /></td>
				</tr>
				<tr>
					<td>Room</td><td><input type="text" id="room" name="room" /></td>
				</tr>
				<tr><td></td><td>
				Room-nr. is between:  100-599, <br> Ground floors(Cosmos, Undergangen, Bersærk) are 501-599
				</td></tr>
				<tr>
					<td>Status:</td>
					<td>
						<select name="status">
							<option value="fremlejer">Fremlejer</option>
							<option value="normal">Normal</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>Mobil</td><td><input type="text" name="phone" /></td>
				</tr>
				<tr>
					<td>Email</td><td><input type="text" name="email" /></td>
				</tr>
				<tr>
					<td colspan="2"><input type="hidden" name="create" value="true" /><input type="submit" value="Opret Bruger" /></td>
				</tr>
			</table>
		</form>
		   Husk du kan/bør uploade et profilbillede på <b><tt>vvv.studentergaarden.dk/minside</tt></b> (under "Tilføj et billede af dig selv")
        </td></tr></table>
        </center>
		<script>
			var validCol = "aaffaa";
			var notValidCol = "ffaaaa";

			function isInt(value) {
			  return !isNaN(value) && 
			         parseInt(Number(value)) == value && 
			         !isNaN(parseInt(value, 10));
			}

			$("#username").keyup(function() {
				var username = $('#username').val();
				
				if(username.indexOf(' ') >= 0){
					$("#username").css("background-color", notValidCol);	
					return;
				}

				$.ajax({
					url: "validUser.php?u="+username
				})
				.done(function( data ) {
					var color = (data == "true") ? validCol : notValidCol;
					$("#username").css("background-color", color);				
				});
			});

			$("#password").keyup(function() {
				var pw1 = $('#password').val();
				
				var color = (pw1.length > 4) ? validCol : notValidCol;
				$("#password").css("background-color", color);
			});

			$("#password2").keyup(function() {
				var pw1 = $('#password').val();
				var pw2 = $('#password2').val();
			
				var color = (pw1 == pw2) ? validCol : notValidCol;
				$("#password2").css("background-color", color);
			});
			
			$("#room").keyup(function() {
				var room = $('#room').val();
				
				if(!isInt(room)){
					$("#room").css("background-color", notValidCol);
					return;		
				}

				var iRoom = parseInt(room);

				var color = (iRoom > 100 && iRoom < 600) ? validCol : notValidCol;
				
				$("#room").css("background-color", color);
			});

			$("#userForm").submit(function( event ) {
				//lidt fjollet måde at validere på
				var username = $("#username").css("background-color");
				var pw = $("#password").css("background-color");
				var pw2 = $("#password2").css("background-color");
				var room = $("#room").css("background-color");

				var targetVal = "rgb(170, 255, 170)";

				if(username != targetVal || pw != targetVal || pw2 != targetVal || room != targetVal){
					
					event.preventDefault();
				}
			});
		</script>
	</body>
</html>
