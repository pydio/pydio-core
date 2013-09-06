<?php
/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
SVN Web Control
Copyright �2006 by sTEFANs
Created on 20.02.2006
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation; either version 2.1 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

GNU Lesser General Public License can be found online
at http://opensource.org/licenses/lgpl-license.php
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
*/

/**
 * A collection of functions that encapsulates SVN commands.
 *
 * @package Swc
 * @subpackage Inc
 * @author Stefan Schraml
 * @copyright Copyright �2006 by sTEFANs
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @version v1.1.0
 * @since v1.0.0
 */

/**
 * Index of commandline text within
 * SVN excution result array.
 * @name IDX_CMDLINE
 * @since v1.0.0
 */
define('IDX_CMDLINE', 0);

/**
 * Index of standard output array
 * within SVN excution result array.
 * @name IDX_STDOUT
 * @since v1.0.0
 */
define('IDX_STDOUT', 1);

/**
 * Index of error output array
 * within SVN excution result array.
 * @name IDX_ERROUT
 * @since v1.0.0
 */
define('IDX_ERROUT', 2);

/**
 * Index of command result code
 * within SVN excution result array.
 * @name IDX_CMD_RC
 * @since v1.0.0
 */
define('IDX_CMD_RC', 3);

/**
 * Title text index of
 * SVN command.
 * @name IDX_TITLE
 * @since v1.0.0
 */
define('IDX_TITLE', 4);

/**
 * Name text index
 * @name IDX_NAME
 * @since v1.0.0
 */
define('IDX_NAME', 0);

/**
 * Value index
 * @name IDX_VALUE
 * @since v1.0.0
 */
define('IDX_VALUE', 1);

/** Common defines */
//require_once('var.inc.php');
/** Project configuration interface */
//require_once ('config.inc.php');

    /**
     * Returns the help output of <i>svn</i>.
     * @return array <i>svn help</i> output in
     * form of a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function GetSvnHelp()
    {
        $command = 'svn';
        $switches = '--help';
        $result = ExecSvnCmd($command, '', $switches);
        return $result;
    }

    /**
     * Returns the help output of <i>svnlook</i>.
     * @return array <i>svnlook help</i> output in
     * form of a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function GetSvnLookHelp()
    {
        $command = 'svnlook';
        $switches = '--help';
        $result = ExecSvnCmd($command, '', $switches);
        return $result;
    }

    /**
     * Returns the help output of <i>svnadmin</i>.
     * @return array <i>svnadmin</i> help output in
     * form of a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function GetSvnAdminHelp()
    {
        $command = 'svnadmin';
        $switches = '--help';
        $result = ExecSvnCmd($command, '', $switches);
        return $result;
    }

    /**
     * Returns the help output of <i>svnversion</i>.
     * @return array <i>svnversion</i> help output in
     * form of a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function GetSvnVersionHelp()
    {
        $command = 'svnversion';
        $switches = '--help';
        $result = ExecSvnCmd($command, '', $switches);
        return $result;
    }

    /**
     * Returns the version of SVN.
     * @return string Version of Subversion
     *
     * @since v1.0.0
     */
    function GetSvnVersion()
    {
        $command = 'svn';
        $switches = '--version';
        static $version = NULL;
        if ($version == NULL) {
            $result = ExecSvnCmd($command, '', $switches);
            if ($result[IDX_CMD_RC] == 0) {
                $version = ParseArray($result[IDX_STDOUT], ', ', ' (r');
                $version = substr($version, strpos($version, '.')-1);
            }
        }
        return $version;
    }

    /**
     * Returns the output of <i>svn info</i> for
     * the repository contained in a result
     * array as produced by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Output line by line.
     * @see ExecSvnCmd
     *
     * @since v1.1.0
     */
    function &GetRepositoryInfo($config){
        $command = 'svn info';
        $arg = $config->GetRepositoryRoot();
        $switches = '--xml';
        static $result = NULL;
        if (empty($result)) {
            $result = ExecSvnCmd($command, $arg, $switches);
        }
        return $result;
    }

    /**
     * Returns the number of the youngest SVN revision (HEAD)
     * available in the repository.
     * @param SwcConfig $config SWC config for the operation.
     * @return int Number of HEAD revision
     *
     * @since v1.0.0
     * */
    function GetHeadRevision($config)
    {
        $info = GetRepositoryInfo($config);
        $rev = ParseArray($info[IDX_STDOUT], 'revision="', '">');
        return $rev;
    }

    /**
     * Returns the formated date of the latest changes
     * within the repository.
     * @param SwcConfig $config SWC config for the operation.
     * @return string Date of HEAD revision.
     *
     * @since v1.0.0
     */
    function GetHeadDate($config)
    {
        $info = GetRepositoryInfo($config);
        $timestamp = ParseArray($info[IDX_STDOUT], '<date>', '</date>');
        $timestamp = strtotime($timestamp);
        $ts = GetLocalizedTimestamp($timestamp);
        return $ts;
    }

    /**
     * Returns the output of <i>svn info</i>
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @param boolean $xml_output Whether or not output shall
     * be printed in xml format (default).
     * @return array Output line by line.
     * @see ExecSvnCmd
     *
     * @version v1.1.0
     * @since v1.0.0
     */
    function &GetWebspaceInfo($config, $xml_output = true){
        $command = 'svn info';
        $arg = $config->GetWebspaceRootDir();
        $switches = '';
        static $result_plain = NULL;
        static $result_xml = NULL;
        if (!$xml_output && empty($result_plain)) {
            $result_plain = ExecSvnCmd($command, $arg, $switches);
        } else if ($xml_output && empty($result_xml)) {
            $switches .= '--xml';
            $result_xml = ExecSvnCmd($command, $arg, $switches);
        }
        $res = ($xml_output ? $result_xml : $result_plain);
        return $res;
    }

    /**
     * Returns the number of the SVN revision
     * of the webspace.
     * @param SwcConfig $config SWC config for the operation.
     * @return int Webspace revision.
     *
     * @since v1.0.0
     */
    function GetWebspaceRevision($config)
    {
        $info = GetWebspaceInfo($config);
        return ParseArray($info[IDX_STDOUT], 'revision="', '">');
    }

    /**
     * Returns the path of the repository
     * where the webspace referes to with
     * repository root directory.
     * @param SwcConfig $config SWC config for the operation.
     * @return string Repository path the webspace points to.
     *
     * @since v1.0.0
     */
    function GetWebspaceSourcePath($config)
    {
        $info = GetWebspaceInfo($config);
        $ws_path = ParseArray($info[IDX_STDOUT], '<url>', '</url>');
        $ws_root = ParseArray($info[IDX_STDOUT], '<root>', '</root>');
        $path = substr($ws_path, strlen($ws_root));
        return $path;
    }

    /**
     * Returns the formated date of the
     * revision of the workspace.
     * @param SwcConfig $config SWC config for the operation.
     * @return string Date of webspace revision.
     *
     * @since v1.0.0
     */
    function GetWebspaceRevisionDate($config)
    {
        $info = GetWebspaceInfo($config);
        $timestamp = ParseArray($info[IDX_STDOUT], '<date>', '</date>');
        $timestamp = strtotime($timestamp);
        return GetLocalizedTimestamp($timestamp);
    }

    /**
     * Returns the output of <i>svn status</i>
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Result of <i>svn status</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function &GetWebspaceStatus($config){
        $command = 'svn status';
        $switches =  '--verbose --show-updates --non-interactive';
        $switches .= GetSvnUsr($config);
        $switches .= GetSvnPw($config);
        $arg = $config->GetWebspaceRootDir();
        static $result = NULL;
        if ($result == NULL) {
            $result = ExecSvnCmd($command, $arg, $switches);
            for ($i = count($result[IDX_STDOUT]); $i > 0; $i--) {
                $result[IDX_STDOUT][$i] = $result[IDX_STDOUT][$i-1];
            }
            $result[IDX_STDOUT][0] = T(TK_WEBSPACE_STATUS_LIST_HEADER);
        }
        return $result;
    }

    /**
     * Returns the output of <i>svn log</i>
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Result of <i>svn log</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function &GetWebspaceLog($config, $path){
        $command = 'svn log';
        $switches = '--xml --non-interactive';
        //$switches .= GetSvnUsr($config);
        //$switches .= GetSvnPw($config);
        $arg = $config->getOption("REPOSITORY_ROOT").$path;
        $result = ExecSvnCmd($command, $arg, $switches);
        return $result;
    }

    /**
     * Returns the output of <i>svn list</i>
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Result of <i>svn list</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.1.0
     */
    function ListRepository($config, $path)
    {
        //$rep_root = $config->GetRepositoryRoot();
        $rep_root = $config->getOption("REPOSITORY_ROOT").$path;
        $command = 'svn list';
        //$switches = '--recursive';
        $switches = '--xml';
        $paths = array();
        $arg = trim($rep_root);
        $res = ExecSvnCmd($command, $arg, $switches);
        return $res;
    }

    /**
     * Returns the array of tags available
     * in the repository.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Version tags.
     *
     * @since v1.0.0
     */
    function &GetTags($config){
        $tag_dirs = $config->GetTagDirs();
        $depth = $config->GetMaxTagDirDepth();
        $paths = GetRepositoryPaths($config, $tag_dirs, $depth);
        return $paths;
    }

    /**
     * Returns the array of branches available
     * in the repository.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Version branches.
     *
     * @since v1.0.0
     */
    function &GetBranches($config){
        $branch_dirs = $config->GetBranchDirs();
        $depth = $config->GetMaxBranchDirDepth();
        $paths = GetRepositoryPaths($config, $branch_dirs, $depth);
        return $paths;
    }

    /**
     * Returns an array of repository paths starting
     * at $root with maximum directory count given in $level.
     * @param SwcConfig $config SWC config for the operation.
     * @param string or array $roots (Array of) repository directories to start from
     * @param int $depth Maximum directory depth.
     * @return array Array of paths of repository.
     *
     * @since v1.0.0
     */
    function GetRepositoryPaths($config, $roots, $depth)
    {
        $rep_root = $config->GetRepositoryRoot();
        if (!is_array($roots)) {
            $root_dirs[] = $roots;
        } else {
            $root_dirs = $roots;
        }
        $command = 'svn list';
        $switches = '--recursive';
        $paths = array();
        foreach ($root_dirs as $dir) {
            $parent_dir = trim($dir);
            $arg = trim($rep_root).$parent_dir;
            $res = ExecSvnCmd($command, $arg, $switches);
            if ($res[IDX_CMD_RC] == 0) {
                $paths = BuildSvnDirTree($paths, $res[IDX_STDOUT], $depth, $parent_dir);
            }
        }
        return $paths;
    }

    /**
     * Builds SVN directory tree.
     * @param array $paths Reference to SVN path array
     * @param array $tree Reference to 'svnlook tree' stdout that
     * should be transformed into SVN paths.
     * @param int $max_level Mamimum level for paths.
     * @param string $parent_dir Parent directory in path.
     * @return array Paths already found.
     * @version v1.1.0
     * @since v1.0.0
     */
    function &BuildSvnDirTree(&$paths, &$tree, $max_level, $parent_dir = ''){
        if (! empty($parent_dir)) {
            $paths[] = $parent_dir;
        }
        foreach ($tree as $path) {
            $level = 0;
            $offset = strpos($path, '/');
            while ($offset !== false && ($level < $max_level)) {
                $offset = strpos($path, '/', $offset);
                ++$level;
            }
            if ($offset === false) {
                $offset = strrpos($path, '/');
            }
            $path = substr($path, 0, $offset);
            if (!empty($parent_dir)) {
                $path = $parent_dir.'/'.$path;
            }
            $paths[$path] = $path;
        }
        return $paths;
    }

    /**
     * Performs webspace update to head revision
     * and returns the output of 'svn update'
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Result of <i>svn update</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function UpdateWebspace($config)
    {
        $command = 'svn update';
        $switches = '--non-interactive';
        $switches .= GetSvnUsr($config);
        $switches .= GetSvnPw($config);
        $arg = $config->GetWebspaceRootDir();
        static $result = NULL;
        if ($result == NULL) {
            $result = ExecSvnCmd($command, $arg, $switches);
        }
        return $result;

    }

    /**
     * Performs workspace (webspace) checkout
     * and returns the output of 'svn checkout'
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Result of <i>svn checkout</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function CheckoutWebspace($config)
    {
        $trunk_path = $config->GetTrunkDir();
        $command = 'svn checkout';
        $switches = '--revision HEAD --non-interactive';
        $switches .= GetSvnUsr($config);
        $switches .= GetSvnPw($config);
        $arg = $config->GetRepositoryRoot().'/'.$trunk_path;
        $arg = $arg.' '.$config->GetWebspaceRootDir();
        return ExecSvnCmd($command, $arg);
    }

    /**
     * Performs webspace cleanup
     * and returns the output of <i>svn cleanup</i>
     * contained in a result array as produced
     * by <b>ExecSvnCmd</b>.
     * @param SwcConfig $config SWC config for the operation.
     * @return array Result of <i>svn cleanup</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function &CleanupWebspace($config){
        $command = 'svn cleanup';
        $switches = '--non-interactive';
        $switches .= GetSvnUsr($config);
        $switches .= GetSvnPw($config);
        $arg = $config->GetWebspaceRootDir();
        $result = ExecSvnCmd($command, $arg);
        return $result;
    }

    /**
     * Switches webspace to given repository path.
     * @param SwcConfig $config SWC config for the operation.
     * @param string $path Repository path to switch to
     * A path without repository root should be applied.
     * @return array Result of <i>svn switch</i> execution.
     * @see ExecSvnCmd
     *
     * @since v1.0.0
     */
    function SwitchWebspace($config, $path)
    {
        if ($path == NULL) {
            $path = $config->GetTrunkDir();
        }
        $command = 'svn switch';
        $switches = '--non-interactive';
        $switches .= GetSvnUsr($config);
        $switches .= GetSvnPw($config);
        $arg = $config->GetRepositoryRoot();
        $arg .= '/'.$path;
        $arg .= ' '.$config->GetWebspaceRootDir();
        return ExecSvnCmd($command, $arg);
    }

    /**
     * Returns the SVN User switch for the given config.
     * @param SwcConfig $config Config for retrieving user.
     * @return string svn switch for user (empty string if no user
     * is applied.
     *
     * @since v1.0.0
     */
    function GetSvnUsr($config)
    {
        $user = $config->GetSvnUser();
        $switch = '';
        if ($user != NULL && $user != '') {
            $switch = ' --username '.$user;
        }
        return $switch;
    }

    /**
     * Returns the SVN password switch for the given config.
     * @param SwcConfig $config Config for retrieving user.
     * @return string svn switch for password (empty string if no password
     * is applied.
     *
     * @since v1.0.0
     */
    function GetSvnPw($config)
    {
        $pw = $config->GetSvnPassword();
        $switch = '';
        if ($pw != NULL && $pw != '') {
            $switch = ' --password '.$pw;
        }
        return $switch;
    }

    /**
     * Executes an SVN command.
     * @param string $cmd Command to execute
     * @param string $switch Switches to be applied for the given command
     * @param string $arg Arguments of the command
     * @return array Result array containing commandline (idx = IDX_CMDLINE),
     * 	standard out array (idx = IDX_STDOUT),	error array (idx = IDX_ERROUT),
     *  and return code of the command (idx = IDX_CMD_RC).
     *
     * @since v1.0.0
     */
    function &ExecSvnCmd($cmd, $arg = '', $switches = ''){
        $descriptorspec = array(
            0 => array("pipe", "r"),  	// stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  	// stdout is a pipe that the child will write to
            2 => array("pipe", "w") 	// stderr is a pipe to write to
        );
        set_time_limit(100);
        $cwd = NULL; //'/tmp';
        $pipes = NULL;

        if (is_array($arg)) {
            $arg = implode(" ", array_map("escapeshellarg", $arg));
        } else {
            $arg = escapeshellarg(SystemTextEncoding::toUTF8($arg));
        }

        $cmdline = (SVNLIB_PATH!=""?SVNLIB_PATH."/":"").$cmd." ".$switches." ".$arg;

        /*
        $output = shell_exec($cmdline);
        $result = array();

        $result[IDX_CMDLINE] = $cmdline;
        if (strpos($switches, "xml")) {
            $result[IDX_STDOUT] = $output;
        } else {
            $result[IDX_STDOUT] = explode("\n", $output);
        }
        $result[IDX_ERROUT] = "";
        return $result;
        */

        $env = null;
        if (defined('AJXP_LOCALE')) {
            $env = array("LC_ALL" => AJXP_LOCALE);
        }

        $process = proc_open($cmdline, $descriptorspec, $pipes, NULL, $env, array("bypass_shell"=>false));

        $result = array();
        $result[IDX_CMDLINE] = $cmdline;
        $result[IDX_STDOUT] = array();
        $result[IDX_ERROUT] = array();
        $result[IDX_CMD_RC] = -1;

        if (is_resource($process)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // 2 => readable handle connected to child errout
            fclose($pipes[0]);

            $result = array();
            $result[IDX_CMDLINE] = $cmdline;
            if (strpos($switches, "xml")) {
                $result[IDX_STDOUT] = GetLineString($pipes[1]);
            } else {
                $result[IDX_STDOUT] = GetLineArray($pipes[1]);
            }
            fclose($pipes[1]);

            $result[IDX_ERROUT] = GetLineArray($pipes[2]);
            fclose($pipes[2]);


            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            $result[IDX_CMD_RC] = proc_close($process);

//		echo "CMD: $cmdline<br/>";
//		PrintDebugArray($result, "Result");
        }

        if (is_array($result[IDX_ERROUT]) && count($result[IDX_ERROUT])) {
            $join = trim(implode("", $result[IDX_ERROUT]));
            if ($join != "") {
                throw new Exception($join);
            }
        }

        return $result;
    }

    /**
     * Reads data from a resource (e.g. pipe) and
     * returns an array that contains each line for a
     * separate index.
     * @param resource $handle Resource handle
     * @param int $length Maximum line length used if a line is not delimited.
     * @return array Array containing a delimited line per index.
     *
     * @since v1.0.0
     */
    function &GetLineArray($handle, $length = 4096){
        $content = array();
        $line = fgets($handle, $length);
        while ($line !== false) {
            $content[] = $line;
            $line = fgets($handle, $length);
        }
        return $content;
    }

    /**
     * Reads data from a resource (e.g. pipe) and
     * returns an array that contains each line for a
     * separate index.
     * @param resource $handle Resource handle
     * @param int $length Maximum line length used if a line is not delimited.
     * @return array Array containing a delimited line per index.
     *
     * @since v1.0.0
     */
    function &GetLineString($handle, $length = 4096){
        $content = "";
        $line = fgets($handle, $length);
        while ($line !== false) {
            $content .= $line;
            $line = fgets($handle, $length);
        }
        return $content;
    }

    /**
     * Returns a string of the given array that is
     * encapsulated within $startstr and $endstr.
     * @param array $array Line array as provided by 'GetLineArray' to
     * search the string within.
     * @param string $startstr String to search.
     * @param string $endstr String to delimit search. If NULL, the returned string
     * is not delimited.
     * @return string String within a 'line' of $array that starts with '$startstr'
     * and ends with '$endstr'. If $startstr is not found, '?' is returned. $startstr
     * is not returned.
     *
     * @since v1.0.0
     */
    function ParseArray (&$array, $startstr, $endstr = NULL)
    {
        $idx = 0;
        while ($idx < count($array) && strpos($array[$idx], $startstr) === false) {
            $idx++;
        }
        $val = '?';
        if ($idx < count($array)) {
            $start = strpos($array[$idx], $startstr) + strlen($startstr);
            if ($endstr != NULL) {
                $len = strpos($array[$idx], $endstr) - $start;
                $val = substr($array[$idx], $start, $len);
            } else {
                $val = substr($array[$idx], $start);
            }
            $val = trim($val);
        }
        return $val;
    }

    /**
     * HTML and user friendly output of an array.
     * @param array $array Array to print
     * @param string $name Name of the array, also printed.
     * @param boolean $encode_html_chars Whether or not to
     * encode special characters to HTML equivalents (default).
     * @version v.1.1.0
     * @since v1.0.0
     */
    function PrintArray($array, $name = '', $encode_html_chars = true)
    {
        if (is_array($array)) {
            if (strlen($name) > 0) {
                echo '<span class="text_low_bold">'.$name.'</span><br/>';
            }
            echo '<pre>';
            foreach ($array as $line) {
                if ($encode_html_chars) {
                    echo htmlspecialchars($line);
                } else {
                    echo ($line);
                }
            }
            echo '</pre>';
        }
    }
