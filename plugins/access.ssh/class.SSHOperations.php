<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Cyril Russo
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : SSH protocol operations.
 */
class SSHOperations
{
   /** 
    * The default LS command to get the files list on a remote server
    * @var lsCommand
    */
   var $lsCommand;
   
   /**
    * Receiving a file from ssh command to directly receive the file content
    * @var catCommand
    */
   var $catCommand;
   
   /** The server to connect to
    * @var server
    */
   var $server;
   
   /** The account to use on the server 
    * @var account
    */
   var $account;
   /** The password for the given account
    * @var password
    */
   var $password;
   
   /** The ssh config file on this server 
    *  @var configFile
    */
   var $configFile;
   
   /** The zipping command */
   var $zipCommand;
   /** The send file command */
   var $setFileCommand;
   /** The get server charset command */
   var $getServerCharsetCommand;
   /** The copy command */
   var $copyCommand;
   /** The move command */
   var $moveCommand;
   /** The create dir command */
   var $makeDirCommand;
   /** The delete command */
   var $deleteCommand;
   /** The chmod command */
   var $chmodCommand;
   
   /** The SSH command itself */
   var $sshCommand;
   
   function SSHOperations($server, $account, $password, $configFile = "/etc/ssh/ssh_config")
   {
      // We use this command because it fixes the output style, and permit to distinguish from link to directory to simple link
      // (doesn't change depending on server configuration)
      $this->lsCommand = "ls -l -n -F --quoting-style=escape --time-style=long-iso ";
      $this->catCommand = "cat ";
      $this->zipCommand = "zip -9 -q -r -j - ";
      $this->setFileCommand = "cat > ";
      $this->getServerCharsetCommand = "set | grep 'LANG=' | sed -e 's/LANG=//'";
      $this->copyCommand = "cp -R ";
      $this->moveCommand = "mv ";
      $this->makeDirCommand = "mkdir -p ";
      $this->deleteCommand = "rm -rf ";
      $this->chmodCommand = "chmod -R ";
      $this->sshCommand = "ssh -o \"ConnectTimeout=5\" -o \"PasswordAuthentication=no\" -o \"ServerAliveInterval=10\" -o \"ServerAliveCountMax=3\""; 
      $this->server = $server;
      $this->account = $account;
      $this->password = $password;
      $this->configFile = $configFile;
   }

// Helpers
   /** Helper function to fetch the content of a stream */
   function streamGetContents($stream, $maxLength = -1, $offset = 0)
   {
       if (function_exists(stream_get_contents)) return stream_get_contents($stream, $maxLength, $offset);
       // Else, first get all the bytes up to the given offset
       @fread($stream, $offset);
       // Then get the content
       if ($maxLength == -1)
       {
           $buffer = "";
           while (!feof($stream) && connection_status()==0) $buffer.= fread($stream, 1);
           return $buffer;
       } else return fread($stream, $maxLength);
   }

   /** Helper function to copy the content of a stream to another stream */
   function streamCopyTo($stream, $out, $maxLength = -1, $offset = 0)
   {
       if (function_exists(stream_copy_to_stream)) return stream_copy_to_stream($stream, $out, $maxLength, $offset);
       // Else, first get all the bytes up to the given offset
       @fread($stream, $offset);
       // Then get the content
       if ($maxLength == -1)
       {
           while (!feof($stream) && connection_status()==0) 
                fwrite($out, fread($stream, 2048));
       } else fwrite($out, fread($stream, $maxLength));
   }

   /** This function split the given text in fields with space as a separator 
       It is different from the basic split function because it only accumulate non empty field */
   function smartSplit($text)
   {
       $retArray = array();
       $lastPos = 0;
       for($i = 0; $i < strlen($text); $i++)
       {
           if ($text[$i] == ' ')
           {
               $retArray[] = substr($text, $lastPos, $i - $lastPos);
               while ($i < strlen($text) && $text[$i] == ' ') $i++;
               $lastPos = $i;
           }
       }
       $retArray[] = substr($text, $lastPos, $i - $lastPos);
       return $retArray;
    }

    /** Parse a file name to create an unescaped UTF-8 version of that name (to the browser file list name)
        @param $name    The file name to parse
        @return A string containing the unescaped UTF-8 version of the file name */
    function unescapeFileName($name)
    {
        // In theory should retrieve the charset from the server fs with such command: set | grep "LANG="
        // In practice, most Unix are UTF8 and so is the browser output, so if I have time I'll add this conversion later on.
        return str_replace('\\', '', $name);
    }

    /** This function parse the output of a remote ls command and returns an array of files on the remote size with 
        all their attributes (isDir, type, access, uid, gid, size, time, name)
        @return isDir    Set to 1 if the item is a directory or behave like a directory (like a symbolic link to a directory)
        @return type     Any of 'file', 'dir', 'link', 'block', 'char', 'fifo', 'socket'
        @return access   Any of 'read', 'read-write', 'write'
        @return uid, gid The user UID/GID (this number is specific to the target system)
        @return size     The item's size in bytes
        @return time     The item's time as ISO date : YYYY-MM-DD HH:MM
        @return name     The item's name in the server charset, escaped so can be reused as-is */
    function parseLs($content)
    {
        // Check the first line 
        $lines = split("\n", $content);
        if (stristr($lines[0], "total")) array_shift($lines);

        // Ok, split each line with a file
        $retArray = array();
        $typeArray = array('-'=>'file', 'd'=>'dir', 'l'=>'link', 'b'=>'block', 'c'=>'char', 'p'=>'fifo', 's'=>'socket');
        foreach($lines as $line)
        {
           $columns = $this->smartSplit($line);
           if (count($columns) < 7) break;
           // The first column is the rights
           $right = $columns[0];
           // Should parse rights here (for now, we simply extract the type)
           $isDir = $right[0]=='d';
           $isLink = $right[0]=='l';
           $isExe = $right[3]=='x' || $right[6]=='x' || $right[9]=='x';
           $isRead = $right[1]=='r' || $right[4]=='r' || $right[7]=='r';
           $isWrite = $right[2]=='w' || $right[5]=='w' || $right[8]=='w';
           $isFifo = $right[0]=='p';

           $name = implode(' ', array_slice($columns, 7));

           // If the link is on a directory, let's fix it
           if ($isLink && substr($name, -1)=='/') $isDir = true;

           // Remove the last char when it's been appended
           if ($isDir || $isExe || $isFifo) $name = substr($name, 0, -1);
       
           if ($isLink)
           {
               // Remove all text after ->
               $name = substr($name, 0, strpos($name, '->'));
           }

           // Show the other colimns
           $retArray[] = array("isDir" => $isDir ? 1 : 0, "type"=> $typeArray[$right[0]], "access" => $isRead ? ($isWrite ? 'read-write' : 'read') : ($isWrite ? 'write' : ''), "uid"=>$columns[2], "gid"=>$columns[3], "size"=>$columns[4], "time"=>$columns[5]." ".$columns[6], "name"=>trim($name)); 
       }
       return $retArray;
    }

    /** Used internally for debugging 
        @return the command output text */
    function testConnection($server, $account, $password)
    {
        $fileName = __FILE__;
        $fileName = substr($fileName, 0, strrpos($fileName, "/"));
        $finalCommand = "export DISPLAY=xxx && export SSH_PASSWORD=".$password." && export SSH_ASKPASS='".$fileName."/showPass.php' && ".$this->sshCommand." ".$account."@".$server." echo test";
        echo $finalCommand."<br>";
        $handle = popen($finalCommand, "r");
        $output = $this->streamGetContents($handle);
        pclose($handle);
        echo $output."<br>";
    }
    /** Execute the given command, expecting only to read input from the command 
        @param $server      The server to contact
        @param $account     The account to use while connecting on the remote server
        @param $password    The account password to use while connecting on the remote server
        @param $command     The command to execute (must be full command and valid on the remote side) 
        @return the command output text */
    function executeRemoteCommand($server, $account, $password, $command)
    {
        $fileName = __FILE__;
        $fileName = substr($fileName, 0, strrpos($fileName, "/"));
        $finalCommand = "export DISPLAY=xxx && export SSH_PASSWORD=".$password." && export SSH_ASKPASS='".$fileName."/showPass.php' && ".$this->sshCommand." ".$account."@".$server." ".$command;
        $handle = popen($finalCommand, "r");
        $output = $this->streamGetContents($handle);
        pclose($handle);
        return $output;
    }

    /** Execute the given download, expecting only to read input from the command 
        @param $out         The output stream to feed
        @param $server      The server to contact
        @param $account     The account to use while connecting on the remote server
        @param $password    The account password to use while connecting on the remote server
        @param $command     The command to execute (must be full command and valid on the remote side) 
        @return the command output text */
    function executeRemoteDownload($out, $server, $account, $password, $command)
    {
        $fileName = __FILE__;
        $fileName = substr($fileName, 0, strrpos($fileName, "/"));
        $finalCommand = "export DISPLAY=xxx && export SSH_PASSWORD=".$password." && export SSH_ASKPASS='".$fileName."/showPass.php' && ".$this->sshCommand." ".$account."@".$server." ".$command;
        $handle = popen($finalCommand, "r");
        register_shutdown_function('pclose', $handle); // Might be interrupted by user
        if (is_resource($handle))
            $output = $this->streamCopyTo($handle, $out);
        pclose($handle);
    }

    /** Execute the given command, expecting only to write input to the command 
        @param $server      The server to contact
        @param $account     The account to use while connecting on the remote server
        @param $password    The account password to use while connecting on the remote server
        @param $command     The command to execute (must be full command and valid on the remote side) 
        @return the command output text */
    function executeRemoteWriting($server, $account, $password, $command, $content)
    {
        $fileName = __FILE__;
        $fileName = substr($fileName, 0, strrpos($fileName, "/"));
        $finalCommand = "export DISPLAY=xxx && export SSH_PASSWORD=".$password." && export SSH_ASKPASS='".$fileName."/showPass.php' && ".$this->sshCommand." ".$account."@".$server." ".$command;
        $handle = popen($finalCommand, "w");
        $writtenSize = 0;
        if (is_resource($handle)) $writtenSize = @fwrite($handle, $content); 
        pclose($handle);
        return $writtenSize == strlen($content);
    }
    
    /** Execute the given command, expecting only to write input to the command
        @param $server     The server to contact
        @param $account    The account to use while connecting on the remote server
        @param $password   The account password to use while connecting on the remote server
        @param $command    The command to execute (must be full command and valid on the remote side)
        @param $filePointer  A pointer on a file that'll be read and sent to the remote side (the file is closed on output)
        @return the command output text */
    function executeRemoteTransfering($server, $account, $password, $command, $filePointer)
    {
        $fileName = __FILE__;
        $fileName = substr($fileName, 0, strrpos($fileName, "/"));
        $finalCommand = "export DISPLAY=xxx && export SSH_PASSWORD=".$password." && export SSH_ASKPASS='".$fileName."/showPass.php' && ".$this->sshCommand." ".$account."@".$server." ".$command;
        $handle = popen($finalCommand, "w");
        $writtenSize = 0;
        if (is_resource($handle))
        {
            while (!feof($filePointer))
            {
                $content = @fread($filePointer, 1024);
                $writtenSize += @fwrite($handle, $content);
            }
        }
        pclose($handle);
        fclose($filePointer);
        return $writtenSize;
    }
                                                                                                                                                                                                                            
/*
    function remoteCommand
        while (!feof($handle))
        {
                $read = fgets($handle, 2096);
                        echo $read;
                        }
                        pclose($handle);
                        
        
        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
           2 => array("pipe", "r")   // stderr is a file to write to
        );

        putenv('some_option=aeiou');

        $process = proc_open($lsCommand . $cwd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // Any error output will be appended to /tmp/error-output.txt

            fwrite($pipes[0], '<?php print_r($_ENV); ?>');
            fclose($pipes[0]);

            $content = stream_get_contentsW($pipes[1]);
            $ret = parseLs($content);
            print_r($ret);
            fclose($pipes[1]);

            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            $return_value = proc_close($process);

        //    echo "command returned $return_value\n";
        }
    }
*/

    // Interface 
    /** Get the list of files for the given remote directory 
        @param $path   The path on the remote side to list 
        @return an array of file as specified in the parseLs method */
    function listFilesIn($path)
    {
        $output = $this->executeRemoteCommand($this->server, $this->account, $this->password, $this->lsCommand.".".$path."/");
        return $this->parseLs($output);
    }
    
    /** Check if the connection is successful (and retrieve the current directory on the remote server) */
    function checkConnection()
    {
        $pwd = $this->executeRemoteCommand($this->server, $this->account, $this->password, "pwd");
        $charset = $this->executeRemoteCommand($this->server, $this->account, $this->password, $this->getServerCharsetCommand);
        $pwd = trim($pwd); $charset = trim($charset);
        $retArray = array($pwd, $charset);
        return $retArray;
    }
    
    /** Get the given files either as an archive or as a single file download 
        @param $pathToFile   Can be an array or a string if a single file */
    function getRemoteContent($pathToFile)
    {
        if (is_array($pathToFile))
        {
            // Need to get an archive here
            $command = $this->zipCommand.implode($pathToFile, " ");
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$command.'"');//'"'.$this->zipCommand.implode(" ", $pathToFile).'"');
        } else
        {
            // Single file download
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, $this->catCommand.$pathToFile);
        }
    }

    /** Download the given file to the output stream
        @param $pathToFile   Can be an array or a string if a single file */
    function downloadRemoteFile($pathToFile)
    {
        $out = fopen("php://output", "a");
        if (is_array($pathToFile))
        {
            // Need to get an archive here
            $command = $this->zipCommand.implode($pathToFile, " ");
            $this->executeRemoteDownload($out, $this->server, $this->account, $this->password, '"'.$command.'"');//'"'.$this->zipCommand.implode(" ", $pathToFile).'"');
        } else
        {
            // Single file download
            $this->executeRemoteDownload($out, $this->server, $this->account, $this->password, $this->catCommand.$pathToFile);
        }
        fclose($out);
    }


    
    /** Save the given content onto the given file on the server
        @param $pathToFile   The full, escaped path to the destination file
        @param $content      The final file content (can be empty, will create a file) 
        @return true on success */
    function setRemoteContent($pathToFile, $content)
    {
        $command = '"'.$this->setFileCommand.str_replace('"', '\\"', $pathToFile).'"';
        return $this->executeRemoteWriting($this->server, $this->account, $this->password, $command, $content);
    }
    
    /** Create a remote directory */
    function createRemoteDirectory($pathToFile)
    {
        $command = '"'.$this->makeDirCommand.str_replace('"', '\\"', $pathToFile).'"';
        return $this->executeRemoteCommand($this->server, $this->account, $this->password, $command);
    }
    
    /** Copy a file (or directory) to a remote location */
    function copyFile($pathToFile, $finalPath)
    {
        if (is_array($pathToFile))
        {
            // Need to get an archive here
            $command = $this->copyCommand.implode($pathToFile, " ");
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$command." ".$finalPath.'"');
        } else
        {
            // Single file download
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$this->copyCommand.$pathToFile." ".$finalPath.'"');
        }
    }

    /** Copy a file (or directory) to a remote location */
    function moveFile($pathToFile, $finalPath)
    {
        if (is_array($pathToFile))
        {
            // Need to get an archive here
            $command = $this->moveCommand.implode($pathToFile, " ");
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$command." ".$finalPath.'"');
        } else
        {
            // Single file download
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$this->chmodCommand.$pathToFile." ".$finalPath.'"');
        }
    }
    
    /** Change the permission of a file (or directory) */
    function chmodFile($pathToFile, $value)
    {
        if (is_array($pathToFile))
        {
            // Need to get an archive here
            $command = $this->chmodCommand.$value." ".implode($pathToFile, " ");
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$command.'"');
        } else
        {
            // Single file download
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$this->chmodCommand.$value." ".$pathToFile.'"');
        }
    }
    
    /** Delete a file (or directory) */
    function deleteFile($pathToFile)
    {
        if (is_array($pathToFile))
        {
            // Need to get an archive here
            $command = $this->deleteCommand.implode($pathToFile, " ");
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$command.'"');
        } else
        {
            // Single file download
            return $this->executeRemoteCommand($this->server, $this->account, $this->password, '"'.$this->deleteCommand.$pathToFile.'"');
        }
    }
    
    /** Upload a file in the given server */
    function uploadFile($localFile, $pathToFile)
    {
        $command = '"'.$this->setFileCommand.str_replace('"', '\\"', $pathToFile).'"';
        $fileSize = @filesize($localFile);
        if ($fileSize === FALSE) return false;
        $filePointer = @fopen($localFile, "rb");
        return $this->executeRemoteTransfering($this->server, $this->account, $this->password, $command, $filePointer) == $fileSize;
    }
                                                        
}

?>
