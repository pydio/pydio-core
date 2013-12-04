<?php
/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
SVN Web Control
Copyright ©2006 by sTEFANs
Created on 25.02.2006
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
 * Implements the execution of SVN commands.
 * The commands should be executed before any webpage is opened in order
 * to have the results ready within all pages.
 *
 * @package Swc
 * @subpackage Inc
 * @author Stefan Schraml
 * @copyright Copyright ©2006 by sTEFANs
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @version v1.1.0
 * @since v1.0.0
 */

/** Common defines */
require_once('var.inc.php');
/** Utility functions */
require_once('utils.inc.php');
/** SVN command library */
require_once('svn_lib.inc.php');

/**
 * Executed SVN commands according to $_SESSION[IDX_ACTION].
 * Stores all execution results within $_SESSION[IDX_EXEC_RES]
 * array.
 * @param array $results A result array that should be appended by the
 * result of the command execution. A new array is returned if this
 * parameter is set to NULL.
 * @return array Array containing results of command excution.
 *
 * @since v1.0.0
 */
function &SvnExecute(&$results = NULL){
    if ($results == NULL) {
        $results = array();
    }

//PrintDebugArray($_SESSION, 'Svn Executor: SESSION');
    $config = GetSelectedConfig();
    if ($config == NULL) {
        return $results;
    }
    if (isset($_SESSION[IDX_ACTION])) {
        switch ($_SESSION[IDX_ACTION]) {
            case ACTION_LIST_REPOSITORY:
                $result = ListRepository($config, "");
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_REP_LIST);
                $results[] = $result;
                break;
            case ACTION_STATUS:
                $result = GetWebspaceStatus($config);
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_STATUS);
                $results[] = $result;
                break;
            case ACTION_LOG:
                $result = GetWebspaceLog($config, "");
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_LOG);
                $results[] = $result;
                break;
            case ACTION_INFO:
                $result = GetWebspaceInfo($config, false);
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_INFO);
                $results[] = $result;
                break;
            case ACTION_UPDATE:
                $result = UpdateWebspace($config);
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_UPDATE);
                $results[] = $result;
                break;
            case ACTION_CLEANUP:
                $result = CleanupWebspace($config);
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_CLEANUP);
                $results[] = $result;
                break;
            case ACTION_SWITCH:
                $path = NULL;
                if (isset($_SESSION[IDX_SWITCH_PATH])) {
                    $path = $_SESSION[IDX_SWITCH_PATH];
                }
                $result = SwitchWebspace($config, $path);
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_SWITCH);
                $results[] = $result;
                break;
            case ACTION_CHECKOUT:
                $result = CheckoutWebspace($config);
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_WS_CHECKOUT);
                $results[] = $result;
                break;
            case ACTION_SVN_HELP:
                $result[IDX_TITLE] = T(TK_RESULT_SVN_HELP_TITLE);
                $result[IDX_STDOUT] = array(
                    T(TK_RESULT_SVN_HELP_HEADER),
                    T(TK_RESULT_SVN_HELP_REP_SHORT),
                    T(TK_RESULT_SVN_HELP_REP_LONG),
                    T(TK_RESULT_SVN_HELP_WS_SHORT),
                    T(TK_RESULT_SVN_HELP_WS_LONG));
                $results[] = $result;
                $result = GetSvnLookHelp();
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_SVNLOOK_HELP);
                $results[] = $result;
                $result = GetSvnHelp();
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_SVN_HELP);
                $results[] = $result;
                $result = GetSvnAdminHelp();
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_SVNADMIN_HELP);
                $results[] = $result;
                $result = GetSvnVersionHelp();
                $result[IDX_TITLE] = T(TK_RESULT_TITLE_SVNADMIN_HELP);
                $results[] = $result;
                break;
            case ACTION_SVN_CMD:
                $result = array();
                $result[IDX_TITLE] = T(TK_RESULT_ERROR_CMD_NOT_EXEC);
                $result[IDX_ERROUT] = array(T(TK_RESULT_ERROR_NO_CMD));
                if (isset($_SESSION[IDX_ACTION_COMMAND]) && $_SESSION[IDX_ACTION_COMMAND] != '') {
                    $cmd = $_SESSION[IDX_ACTION_COMMAND];
                    $cmd = str_replace('%repository%', $config->GetRepositoryRoot(), $cmd);
                    $cmd = str_replace('%webspace%', $config->GetWebspaceRootDir(), $cmd);
                    $cmd = str_replace('%rep%', $config->GetRepositoryRoot(), $cmd);
                    $cmd = str_replace('%ws%', $config->GetWebspaceRootDir(), $cmd);
                    $result[IDX_ERROUT] = array(T(TK_RESULT_ERROR_NO_SVN_CMD__CMD, $cmd));
                    $rc = stripos($cmd, 'svn');
                    if ($rc !== false && $rc == 0) {
                        $result = ExecSvnCmd($cmd);
                        $result[IDX_TITLE] = T(TK_RESULT_CMD_EXEC);
                    }
                }
                $results[] = $result;
                break;
        }
    }
    return $results;
}
