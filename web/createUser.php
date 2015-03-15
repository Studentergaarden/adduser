<?php
session_start();
$admin = (array_key_exists("admin_id", $_SESSION)) ? $_SESSION['admin_id'] : "false";

if($admin === "false"){
	die("Du er ikke logget ind");
}

	$create = (array_key_exists("create", $_POST)) 			? $_POST["create"] : "";
	
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
	$hash = crypt($password, '$1$'.$salt);

	$json = 
'{
	"name":"'.$name.'",
	"username":"'.$username.'",
	"password":"'.$hash.'",
	"study":"'.$study.'",
	"room":"'.$room.'",
	"status":"'.$status.'",
	"phone":"'.$phone.'",
	"email":"'.$email.'"
}';

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

    
    echo("Sending Message...<br>\n");
    if(!fwrite($socket, $json))
            echo("Error while writing!!!<br>\n");

    echo("Receiving Message...<br>\n");
    if (!($rcv_msg = fread($socket, 1024))) 
            echo("Error while reading!!!<br>\n");
    else{ 
    	if($rcv_msg == "succes:1"){
		echo "Brugeren er oprettet!";
	}else{
		echo "Der gik noget galt, prøv en gang til.";
	}
    }
}


?>
<html>
	<head>
		<script src="jquery-1.11.2.min.js"> </script>
	</head>
	<body>
		<form id="userForm" action="?" method="POST">
			<table>
				<tr>
					<td>Name</td><td><input type="text" name="name" /></td>
				</tr>
				<tr>
					<td>Username</td><td><input type="text" id="username" name="username" /></td>
				</tr>
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
					<td>Room</td><td><input type="text" id="room" name="room" /></td><td>Rum ligger indenfor 100-599, underetagen er 501-599</td>
				</tr>
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
