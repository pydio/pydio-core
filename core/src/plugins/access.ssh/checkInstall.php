<?php
// Testing the SSH installation
header("Content-Type: text/html; charset=UTF-8");

echo "<html><head>
       <!-- Should include a stylesheet here -->
      </head><body>";
      
// First test, check that the required files are all present
echo "<h1>Testing installation</h1>";
if (!file_exists("class.sshDriver.php") 
 || !file_exists("class.SSHOperations.php")
 || !file_exists("manifest.xml")
 || !file_exists("showPass.php")
 || !file_exists("sshActions.xml"))
    echo "ERROR: Missing at least one of these files:
    <ul>
    <li>class.sshDriver.php</li>
    <li>class.SSHOperations.php</li>
    <li>manifest.xml</li>
    <li>showPass.php</li>
    <li>sshActions.xml</li>
    </ul>";
else echo "All required files are installed";
    
// Testing file permissions
echo "<h1>Testing file permission</h1>";
$stat = stat("showPass.php");
$mode = $stat['mode'] & 0x7FFF; // We don't care about the type
if (is_executable('showPass.php')
    || (($mode & 0x40) && $stat['uid'] == posix_getuid())
    || (($mode & 0x08) && $stat['gid'] == posix_getgid())
    || ($mode & 0x01))
{
    echo "Required showPass.php is executable";
}    
else
{
    echo "ERROR: showPass.php isn't executable.<br><h2>Trying to make it executable</h2>";
    chmod('showPass.php', 0555);
    if (!is_executable('showPass.php'))
       echo "FAILED: Please log on the server and make showPass.php executable for your webserver user";
    else
       echo "Success: showPass.php was made executable";
}
 
// Checking if ssh is accessible
echo "<h1>Testing ssh access from webserver's user</h1>";
$handle = popen("ssh 2>&1", "r");
$usage = fread($handle, 30);
if (strpos($usage, "usage") === FALSE)
    echo "ERROR: ssh is not accessible, or not in the path. Please fix the path and/or install ssh";
else
    echo "ssh is accessible and runnable";
pclose($handle);

// Check if the destination server host key is accessible
echo "<h1>Checking if we can connect to destination SSH server</h1>";
if ($_GET["destServer"] == "")
{
   echo "<form method=GET>Please enter SSH server address to test:<input type=text name='destServer' value=''><input type='submit' value='Ok'></form>";
} else
{ 
   $handle = popen("export DISPLAY=xxx && export SSH_ASKPASS=/bin/sh && ssh -T -t -o StrictHostKeyChecking=yes -o LogLevel=QUIET".$_GET["destServer"]." 2>&1", "r");
   $key = fread($handle, 30);
   if (strpos($key, "Host") <= 4)
   {
      echo "ERROR: The server ".$_GET["destServer"]." you are trying to contact doesn't have its host key installed<br>
      Please install server host key in /etc/ssh/ssh_known_hosts file, as the webserver user can't store server's key";
      echo "<br>You should type this command as root to add the key to the main host file:<br>ssh -o StrictHostKeyChecking=ask -o UserKnownHostsFile=/etc/ssh/ssh_known_hosts ".$_GET["destServer"]."<br>";
   }
   else
      echo "Server host key installed and working";   
   pclose($handle);
}


echo "</body></html>";

?>
