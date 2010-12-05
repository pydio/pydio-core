<?php
// Here the PHP code for handling the form and the HTML code 
// for displaying it are in the same file "login.php" 
// but it's not necessary!
if(isSet($_POST["login"]) && isSEt($_POST["password"])){
	
	// Necessary to make "connection" with the glueCode
	define("AJXP_EXEC", true);
	$glueCode = "path/to/plugins/auth.remote/glueCode.php";
	$secret = "my_secret_key";

	// Initialize the "parameters holder"
	global $AJXP_GLUE_GLOBALS;
	$AJXP_GLUE_GLOBALS = array();
	$AJXP_GLUE_GLOBALS["secret"] = $secret;
	$AJXP_GLUE_GLOBALS["plugInAction"] = "login";
	$AJXP_GLUE_GLOBALS["autoCreate"] = false;
	
	// NOTE THE md5() call on the password field.
	$AJXP_GLUE_GLOBALS["login"] = array("name" => $_POST["login"], "password" => md5($_POST["password"]));
	
	// NOW call glueCode!
   	include($glueCode);
}
?>
<html>
	<body>
		<form action="login.php" method="POST">
			Login : <input name="login"><br>
			Password : <input name="password" type="password"><br>
			<input type="submit" value="SUBMIT">
		</form>
	</body>
</html>