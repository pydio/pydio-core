<?php

require_once("class.SSHOperations.php");

   $SSHOp = new SSHOperations("ssh.alwaysdata.com", "ajaxplorer", "dumbpass");
   echo "<h1>Testing connection</h1>";
   echo $SSHOp->checkConnection()."<br>";
   
   $arr = $SSHOp->listFilesIn('/www');
   print_r($arr[0]);
   
   // Create a file on the server
   $SSHOp->setRemoteContent('/home/ajaxplorer/www/test.html', '<html><body>Hello</body></html>');

?>
   
