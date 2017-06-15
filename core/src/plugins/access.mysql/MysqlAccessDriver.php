<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Access\Driver\DataProvider;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Exception\PydioException;

use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class MysqlAccessDriver extends AbstractAccessDriver
{
    /** The user name */
    public $user;
    /** The user password */
    public $password;

    /** @var  resource */
    private $link;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        $this->user     = $contextInterface->getRepository()->getContextOption($contextInterface, "DB_USER");
        $this->password = $contextInterface->getRepository()->getContextOption($contextInterface, "DB_PASS");
        $link           = $this->createDbLink($contextInterface);
        $this->closeDbLink($link);
    }

    /**
     * @param ContextInterface $ctx
     * @return bool|\mysqli
     * @throws PydioException
     */
    public function createDbLink(ContextInterface $ctx)
    {
        //Connects to the MySQL Database.
        $host   = $ctx->getRepository()->getContextOption($ctx, "DB_HOST");
        $dbname = $ctx->getRepository()->getContextOption($ctx, "DB_NAME");
        $link = @mysqli_connect($host, $this->user, $this->password);
        if (!$link) {
            throw new PydioException("Cannot connect to server!");
        }
        if (!@mysqli_select_db($link, $dbname)) {
            throw new PydioException("Cannot find database!");
        }
        return $link;
    }

    /**
     * @param $link
     * @throws PydioException
     */
    public function closeDbLink($link)
    {
        if (!mysqli_close($link)) {
            throw new PydioException("Cannot close connection!");
        }
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return string
     * @throws AuthRequiredException
     * @throws PydioException
     * @throws \Pydio\Core\Exception\ForbiddenCharacterException
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $httpVars       = $requestInterface->getParsedBody();
        $action         = $requestInterface->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx            = $requestInterface->getAttribute("ctx");
        $dir            = $httpVars["dir"];

        $selection = UserSelection::fromContext($ctx, $httpVars);
        // FILTER DIR PAGINATION ANCHOR
        if (isSet($dir) && strstr($dir, "%23")!==false) {
            $parts = explode("%23", $dir);
            $dir = $parts[0];
            $page = $parts[1];
        }else if(isSet($httpVars["page"])){
            $page = InputFilter::sanitize($httpVars["page"], InputFilter::SANITIZE_ALPHANUM);
        }

        // Sanitize all httpVars entries
        foreach($httpVars as $k=>&$value){
            if(is_string($value)){
                $value = InputFilter::sanitize($value, InputFilter::SANITIZE_FILENAME);
            }
        }

        switch ($action) {

            //------------------------------------
            //	ONLINE EDIT
            //------------------------------------
            case "edit_record";

                $isNew = false;
                if(isSet($httpVars['record_is_new']) && $httpVars['record_is_new'] == "true") {
                    $isNew = true;
                }
                $tableName = $httpVars["table_name"];
                $pkName = $httpVars["pk_name"];
                $arrValues = array();
                foreach ($httpVars as $key=>$value) {
                    if (substr($key, 0, strlen("ajxp_mysql_")) == "ajxp_mysql_") {
                        $newKey = substr($key, strlen("ajxp_mysql_"));
                        $arrValues[$newKey] = $value;
                    }
                }
                $autoKey = $this->findTableAutoIncrementKey($ctx, $tableName);
                if ($isNew) {
                    $values = [];
                    $index = 0;
                    foreach ($arrValues as $k=>$v) {
                        if($autoKey !== false && $k === $autoKey){
                            $values[] = 'NULL';
                        }else{
                            $values []= "'".addslashes($v)."'";
                        }
                        $index++;
                    }
                    $query = "INSERT INTO `$tableName` VALUES (".implode(",", $values).")";
                } else {
                    $string = "";
                    $index = 0;
                    foreach ($arrValues as $k=>$v) {
                        if ($k == $pkName) {
                            $pkValue = $v;
                        } else {
                            $string .= $k."='".addslashes($v)."'";
                            if($index<count($arrValues)-1) $string.=",";
                        }
                        $index++;
                    }
                    if(!isSet($pkValue)) throw new PydioException("Cannot find PK Value");
                    $query = "UPDATE `$tableName` SET $string WHERE $pkName='$pkValue'";
                }
                $this->execQuery($ctx, $query);
                $logMessage = $query;
                $reload_file_list = true;

            break;

            //------------------------------------
            //	CHANGE COLUMNS OR CREATE TABLE
            //------------------------------------
            case "edit_table":
                if (isSet($httpVars["current_table"])) {
                    $current_table = InputFilter::sanitize($httpVars["current_table"], InputFilter::SANITIZE_ALPHANUM);
                    if (isSet($httpVars["delete_column"])) {
                        $query = "ALTER TABLE ".$httpVars["current_table"]." DROP COLUMN ".$httpVars["delete_column"];
                        $this->execQuery($ctx, $query);
                        $logMessage = $query;
                        $reload_file_list = true;
                        break;
                    }
                    if (isSet($httpVars["add_column"])) {
                        $defString = $this->makeColumnDef($httpVars, "add_field_");
                        $query = "ALTER TABLE `".$current_table."` ADD COLUMN ($defString)";
                        if (isSet($httpVars["add_field_pk"]) && $httpVars["add_field_pk"]=="1") {
                            $query.= ", ADD PRIMARY KEY (".$httpVars["add_field_name"].")";
                        }
                        if (isSet($httpVars["add_field_index"]) && $httpVars["add_field_index"]=="1") {
                            $query.= ", ADD INDEX (".$httpVars["add_field_name"].")";
                        }
                        if (isSet($httpVars["add_field_uniq"]) && $httpVars["add_field_uniq"]=="1") {
                            $query.= ", ADD UNIQUE (".$httpVars["add_field_name"].")";
                        }
                        $this->execQuery($ctx, $query);
                        $logMessage = $query;
                        $reload_file_list = true;
                        break;
                    }
                }

                $fields = array("origname","name", "default", "null", "size", "type", "flags", "pk", "index", "uniq");
                $rows = array();
                foreach ($httpVars as $k=>$val) {
                    $split = explode("_", $k);
                    if (count($split) == 3 && $split[0]=="field" && is_numeric($split[2]) && in_array($split[1], $fields)) {
                        if(!isSet($rows[intval($split[2])])) $rows[intval($split[2])] = array();
                        $rows[intval($split[2])][$split[1]] = $val;
                    } else if (count($split) == 2 && $split[0] == "field" && in_array($split[1], $fields)) {
                        if(!isSet($rows[0])) $rows[0] = array();
                        $rows[0][$split[1]] = $val;
                    }
                }
                if (isSet($current_table)) {
                    $qMessage = '';
                    foreach ($rows as $row) {
                        $sizeString = ($row["size"]!=""?"(".$row["size"].")":"");
                        $defString = ($row["default"]!=""?" DEFAULT ".$row["default"]."":"");
                        $query = "ALTER TABLE $current_table CHANGE ".$row["origname"]." ".$row["name"]." ".$row["type"].$sizeString.$defString." ".$row["null"];
                        $this->execQuery($ctx, trim($query));
                        $qMessage .= $query;
                        $reload_file_list = true;
                    }
                    $logMessage = $qMessage;
                } else if (isSet($httpVars["new_table"])) {
                    $new_table = InputFilter::sanitize($httpVars["new_table"], InputFilter::SANITIZE_ALPHANUM);
                    $fieldsDef = array();
                    $pks = array();
                    $indexes = array();
                    $uniqs = array();
                    foreach ($rows as $index=>$row) {
                        $fieldsDef[]= $this->makeColumnDef($row);
                        // Analyse keys
                        if($row["pk"] == "1")$pks[] = $row["name"];
                        if($row["index"]=="1") $indexes[] = $row["name"];
                        if($row["uniq"]=="1") $uniqs[] = $row["name"];

                    }
                    $fieldsDef = implode(",", $fieldsDef);
                    if (count($pks)) {
                        $fieldsDef.= ",PRIMARY KEY (".implode(",", $pks).")";
                    }
                    if (count($indexes)) {
                        $fieldsDef.=",INDEX (".implode(",", $indexes).")";
                    }
                    if (count($uniqs)) {
                        $fieldsDef.=",UNIQUE (".implode(",", $uniqs).")";
                    }
                    $query = "CREATE TABLE $new_table ($fieldsDef)";
                    $this->execQuery($ctx, (trim($query)));
                    $logMessage = $query;
                    $reload_file_list = true;
                    $reload_current_node = true;
                }

            break;

            //------------------------------------
            //	SUPPRIMER / DELETE
            //------------------------------------
            case "delete_table":
            case "delete_record":
                $dir = basename($dir);
                if (trim($dir) == "") {
                    // ROOT NODE => DROP TABLES
                    $tables = $selection->getFiles();
                    $query = "DROP TABLE";
                    foreach ($tables as $index => $tableName) {
                        $tables[$index] = basename($tableName);
                    }
                    $query.= " ".implode(",", $tables);
                    $this->execQuery($ctx, $query);
                    $reload_current_node = true;
                } else {
                    // TABLE NODE => DELETE RECORDS
                    $tableName = $dir;
                    $pks = $selection->getFiles();
                    foreach ($pks as $key => $pkString) {
                        $parts = explode(".", $pkString);
                        array_pop($parts); // remove .pk extension
                        array_shift($parts); // remove record prefix
                        foreach ($parts as $index => $pkPart) {
                            $parts[$index] = str_replace("__", "='", $pkPart)."'";
                        }
                        $pks[$key] = "(".implode(" AND ", $parts).")";
                    }
                    $query = "DELETE FROM $tableName WHERE ". implode(" OR ", $pks);
                    $this->execQuery($ctx, $query);
                }
                $logMessage = $query;
                $reload_file_list = true;

            break;

            //------------------------------------
            //	RENOMMER / RENAME
            //------------------------------------
            case "set_query":
                $query = $httpVars["query"];
                $_SESSION["LAST_SQL_QUERY"] = $query;
                print("<tree store=\"true\"></tree>");
            break;

            //------------------------------------
            //	XML LISTING
            //------------------------------------
            case "ls":

                if(!isSet($dir) || $dir == "/") $dir = "";
                $searchMode = $fileListMode = $completeMode = false;
                if (isSet($httpVars["mode"])) {
                    $mode = $httpVars["mode"];
                    if($mode == "search") $searchMode = true;
                    else if($mode == "file_list") $fileListMode = true;
                    else if($mode == "complete") $completeMode = true;
                }else{
                    $mode = "file_list";
                }
                if ($dir == "") {
                    $nodesList = new NodesList("/");

                    $tables = $this->listTables($ctx);
                    $nodesList->initColumnsData("filelist", "list");
                    $nodesList->appendColumn("Table Name", "ajxp_label");
                    $nodesList->appendColumn("Byte Size", "bytesize", "Number");
                    $nodesList->appendColumn("Count", "count", "Number");

                    $icon = ($mode == "file_list"?"sql_images/mimes/ICON_SIZE/table_empty.png":"sql_images/mimes/ICON_SIZE/table_empty_tree.png");
                    foreach ($tables as $tableName) {
                        if(InputFilter::detectXSS($tableName)) {
                            $tableName = "XSS Detected!";
                            $size = 'N/A';
                            $count = 'N/A';
                        }else{
                            $size = $this->getSize($ctx, $tableName);
                            $count = $this->getCount($ctx, $tableName);
                        }
                        $node = new AJXP_Node("/$tableName", ["bytesize" => $size, "count" => $count, "icon" => $icon, "ajxp_mime" => "table"]);
                        $node->setLabel($tableName);
                        $node->setLeaf(false);
                        $nodesList->addBranch($node);
                    }
                    $node = new AJXP_Node("/ajxpmysqldriver_searchresults", ["bytesize" => "-", "count" => "-", "icon" => "search.phng", "ajxp_node" => "true"]);
                    $node->setLabel("Search Results");
                    $node->setLeaf(false);
                    $nodesList->addBranch($node);

                } else {
                    $tableName = basename($dir);
                    $nodesList = new NodesList("/$tableName");

                    if(isSet($page))$currentPage = $page;
                    else $currentPage = 1;
                    $query = "SELECT * FROM `$tableName`";
                    if ($tableName == "ajxpmysqldriver_searchresults") {
                        if (isSet($_SESSION["LAST_SQL_QUERY"])) {
                            $query = $_SESSION["LAST_SQL_QUERY"];
                            $matches = array();
                            if (preg_match("/SELECT [\S, ]* FROM (\S*).*/i", $query, $matches)!==false) {
                                $tableName = $matches[1];
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                    if (isSet($order_column) && isSet($order_direction)) {
                        $query .= " ORDER BY $order_column ".strtoupper($order_direction);
                        if (!isSet($_SESSION["AJXP_ORDER_DATA"])) {
                            $_SESSION["AJXP_ORDER_DATA"] = array();
                        }
                        $_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName] = array("column" => $order_column, "dir" => $order_direction);
                    } else if (isSet($_SESSION["AJXP_ORDER_DATA"])) {
                        if (isSet($_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName])) {
                            $order_column = $_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName]["column"];
                            $order_direction = $_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName]["dir"];
                            $query .= " ORDER BY $order_column ".strtoupper($order_direction);
                        }
                    }
                    try {
                        $result = $this->showRecords($ctx, $query, $tableName, $currentPage);
                        $count = count($result);
                    } catch (PydioException $ex) {
                        unset($_SESSION["LAST_SQL_QUERY"]);
                        throw $ex;
                    }

                    $blobCols = array();
                    $nodesList->initColumnsData("grid", "list");
                    foreach ($result["COLUMNS"] as $col) {
                        $columMeta = [
                            "field_name" => $col["NAME"],
                            "field_type" => $col["TYPE"],
                            "field_size" => $col["LENGTH"],
                            "field_flags" => $this->cleanFlagString($col["FLAGS"]),
                            "field_pk"  => preg_match("/primary/", $col["FLAGS"])?"1":"0",
                            "field_null" => preg_match("/not_null/", $col["FLAGS"])?"NOT_NULL":"NULL",
                            "field_default" => $col["DEFAULT"]
                        ];
                        $nodesList->appendColumn($col["NAME"], $col["NAME"], $this->sqlTypeToSortType($col["TYPE"]), '', $columMeta);
                        if (stristr($col["TYPE"],"blob")!==false && ($col["FLAGS"]!="" && stristr($col["FLAGS"], "binary"))) {
                            $blobCols[]=$col["NAME"];
                        }
                    }
                    if ($result["TOTAL_PAGES"] > 1) {
                        $nodesList->setPaginationData($count, $currentPage, $result["TOTAL_PAGES"]);
                    }
                    foreach ($result["ROWS"] as $arbitIndex => $row) {
                        $nodeMeta = [];
                        $pkString = "";
                        foreach ($row as $key=>$value) {
                            if (in_array($key, $blobCols)) {
                                $sizeStr = " - NULL";
                                if(strlen($value)) $sizeStr = " - ". StatHelper::roundSize(strlen($value));
                                $nodeMeta[$key] = "BLOB$sizeStr";
                            } else {
                                $value = str_replace("\"", "", $value);
                                if(InputFilter::detectXSS($value)) $value = "Possible XSS Detected - Cannot display value!";
                                $nodeMeta[$key] = $value;
                                if ($result["HAS_PK"]>0) {
                                    if (in_array($key, $result["PK_FIELDS"])) {
                                        $pkString .= $key."__".$value.".";
                                    }
                                }
                            }
                        }
                        if ($result["HAS_PK"] > 0) {
                            $nodeMeta["ajxp_mime"] = "pk";
                            $node = new AJXP_Node("/$tableName/record.".$pkString."pk", $nodeMeta);
                            $node->setLeaf(true);
                        } else {
                            $nodeMeta["ajxp_mime"] = "no_pk";
                            $node = new AJXP_Node("/$tableName/record.".$arbitIndex.".no_pk", $nodeMeta);
                            $node->setLeaf(true);
                        }
                        $nodesList->addBranch($node);
                    }
                }
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream($nodesList));
                return null;

            break;
        }

        if (isset($requireAuth)) {
            throw new AuthRequiredException();
        }

        $serialStream = new SerializableResponseStream();

        if(isSet($errorMessage)){
            $uMessage = new UserMessage($errorMessage, LOG_LEVEL_ERROR);
        }else if(isSet($logMessage)){
            $uMessage = new UserMessage($logMessage, LOG_LEVEL_INFO);
        }
        if(isSet($uMessage)){
            $serialStream->addChunk($uMessage);
        }

        if (( isset($reload_current_node) && $reload_current_node == "true") || (isset($reload_file_list)) ) {
            $serialStream->addChunk(new ReloadMessage());
        }

        $responseInterface = $responseInterface->withBody($serialStream);
    }

    /**
     * @param ContextInterface $ctx
     * @param $tablename
     * @return string
     * @throws PydioException
     */
    public function getSize(ContextInterface $ctx, $tablename)
    {
        $dbname = $ctx->getRepository()->getContextOption($ctx, "DB_NAME");
        $like="";
        $t=0;
        if ($tablename !="") {
            $like=" like '$tablename'";
        }
        $sql= "SHOW TABLE STATUS FROM `$dbname` $like";
        $result=$this->execQuery($ctx, $sql);
        if ($result) {

            while ($rec = mysqli_fetch_array($result)) {
                $t+=($rec['Data_length'] + $rec['Index_length']);
            }
            $total = StatHelper::roundSize($t);
        } else {
            $total="Unknown";
        }
        return($total);
    }

    /**
     * Get total rows count for a table
     * @param $ctx
     * @param $tableName
     * @return int|string
     */
    public function getCount($ctx, $tableName)
    {
        try{
            $sql = "SELECT count(*) FROM $tableName";
            $result = $this->execQuery($ctx, $sql);
            $t = 0;
            if ($result) {
                while ($res = mysqli_fetch_array($result)) {
                    $t+=$res[0];
                }
            }
        }catch (\Exception $e){
            $t = "-";
        }
        return $t;
    }

    /**
     * @param $row
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    public function makeColumnDef($row, $prefix="", $suffix="")
    {
        $defString = "";
        if (isSet($row[$prefix."default".$suffix]) && trim($row[$prefix."default".$suffix]) != "") {
            $defString = " DEFAULT ".$row[$prefix."default".$suffix];
        }
        $sizeString = ($row[$prefix."size".$suffix]!=""?"(".$row[$prefix."size".$suffix].")":"");
        $fieldsDef = $row[$prefix."name".$suffix]." ".$row[$prefix."type".$suffix].$sizeString.$defString." ".$row[$prefix."null".$suffix]." ".$row[$prefix."flags".$suffix];
        return trim($fieldsDef);
    }

    /**
     * @param $flagString
     * @return string
     */
    public function cleanFlagString($flagString)
    {
        $arr = explode(" ", $flagString);
        $newFlags = array();
        foreach ($arr as $flag) {
            if ($flag == "primary_key" || $flag == "null" || $flag == "not_null") {
                continue;
            }
            $newFlags[] = $flag;
        }
        return implode(" ", $newFlags);
    }

    /**
     * @param $fieldType
     * @return string
     */
    public function sqlTypeToSortType($fieldType)
    {
        switch ($fieldType) {
            case "int":
                return "Number";
            case "string":
            case "datetime":
            case "timestamp":
                return "String";
            case "blob":
                return "NumberKo";
            default:
                return "String";
        }
    }

    /**
     * @param ContextInterface $ctx
     * @return array
     * @throws PydioException
     */
    public function listTables(ContextInterface $ctx)
    {
        $result = $this->execQuery($ctx, "SHOW TABLES FROM `".$ctx->getRepository()->getContextOption($ctx, "DB_NAME")."` LIKE '".$ctx->getRepository()->getContextOption($ctx, "DB_PTRN")."%'");
        $allTables = array();
        while ($row = mysqli_fetch_row($result)) {
           $allTables[] = $row[0];
        }
        return $allTables;
    }

    /**
     * Find autoincrement key
     * @param ContextInterface $ctx
     * @param $tablename
     * @return bool
     * @throws PydioException
     */
    public function findTableAutoIncrementKey(ContextInterface $ctx, $tablename){

        $result = $this->execQuery($ctx, "SELECT * from `$tablename` LIMIT 0,1");
        $fields =  mysqli_fetch_fields($result);
        foreach($fields as $field){
            if($field->flags & MYSQLI_AUTO_INCREMENT_FLAG){
                return $field->name;
            }
        }
        return false;
    }

    /**
     * @param ContextInterface $ctx
     * @param $query
     * @param $tablename
     * @param int $currentPage
     * @param int $rpp
     * @param string $searchval
     * @return array
     * @throws PydioException
     */
    public function showRecords(ContextInterface $ctx, $query, $tablename, $currentPage=1, $rpp=50, $searchval='' )
    {
        $totalCount = $this->getCount($ctx, $tablename);


        $columns = array();
        $rows = array();

        $pg=$currentPage-1;
        if (isset($_POST['first'])) {
            $pg=0;
        } else if (isset($_POST['back'])) {
            $pg=$pg-1;
        } else if (isset($_POST['next'])) {
            $pg++;
        } else if (isset($_POST['last'])) {
            $pgs = $totalCount/$rpp;
            $pg=ceil($pgs)-1;
        }
        if ($pg < 0) {
            $pg=0;
        }
        if ($pg > $totalCount/$rpp) {
            $pg=ceil($totalCount/$rpp)-1;
        }
        $totalPages = ceil($totalCount/$rpp);
        $beg = $pg * $rpp;

        $query .= " LIMIT $beg,$rpp";
        $result = $this->execQuery($ctx, $query);


        $flds = mysqli_num_fields($result);
        $fields =  mysqli_fetch_fields($result);
        if (!$fields) {
            throw new PydioException("Non matching fields for table '$tablename'");
        }
        $z=0;
        $pk = [];
        $pkfield= [];

        // MAKE COLUMNS HEADER
        for ($i = 0; $i < $flds; $i++) {
            $fieldMeta = $fields[$i];
            $title = $fieldMeta->name;
            $type  = $this->h_type2txt($fieldMeta->type);
            $flagstring = $this->h_flags2txt($fieldMeta->flags);
            $size = $fieldMeta->length;
            $default = "";
            if(property_exists($fieldMeta, "default")){
                $default = $fieldMeta->default;
            }

            $columns[] = array("NAME" => $title, "TYPE"=>$type, "LENGTH"=>$size, "FLAGS"=>$flagstring, "DEFAULT"=> $default);

            //Find the primary key
            if ($fieldMeta->flags & MYSQLI_PRI_KEY_FLAG) {
                $pk[$z] = $i;
                $pkfield[$z]= $title;
                $z++;
            }
        }

        if ($z > 0) {
            $cpk=count($pk);
        } else {
            $cpk=0;
        }

        // MAKE ROWS RESULT
        for ($s=0; $s < min($rpp, mysqli_num_rows($result)); $s++) {
            $row=mysqli_fetch_array($result);
            if (!isset($pk)) {
                $pk=' ';
                $pkfield= array();
            }
            $values = array();
            for ($col = 0; $col < $flds; $col ++) {
                $colMeta = $fields[$col];
                $values[$colMeta->name] = stripslashes($row[$col]);
            }
            $rows[] = $values;
        }

        return array("COLUMNS" => $columns, "ROWS" => $rows, "HAS_PK"=>$cpk, "TOTAL_PAGES"=>$totalPages, "PK_FIELDS"=>$pkfield);
    }

    /**
     * Execute a query by creating a db link
     * @param ContextInterface $ctx
     * @param $sql
     * @return bool|\mysqli_result
     * @throws PydioException
     */
    public function execQuery(ContextInterface $ctx, $sql)
    {
        if(empty($sql)){
            throw new PydioException('Empty Query');
        }
        if(!isSet($this->link) || !is_resource($this->link)){
            $link = $this->createDbLink($ctx);
            $this->link = $link;
            register_shutdown_function(function () use ($link){
                mysqli_close($link);
            });
        }
        $result= @mysqli_query($this->link, stripslashes($sql));
        if ($result) {
            $this->logInfo("exec", array($sql));
            return $result;
        } else {
            throw new PydioException($sql.": ".mysqli_error($this->link));
        }
    }

    /**
     * Convert mysqli numeric types to text
     * @param $type_id
     * @return mixed|null
     */
    private function h_type2txt($type_id)
    {
        static $types;

        if (!isset($types))
        {
            $types = array();
            $constants = get_defined_constants(true);
            foreach ($constants['mysqli'] as $c => $n) if (preg_match('/^MYSQLI_TYPE_(.*)/', $c, $m)) $types[$n] = $m[1];
        }

        $type = array_key_exists($type_id, $types)? strtolower($types[$type_id]) : NULL;
        if($type === null) return null;
        $convert = [
            "string"     => "varchar",
            "var_string" => "varchar"
        ];
        return array_key_exists($type, $convert) ? $convert[$type] : $type;
    }

    /**
     * Convert mysqli numeric flags to text
     * @param $flags_num
     * @return string
     */
    private function h_flags2txt($flags_num)
    {
        static $flags;

        if (!isset($flags))
        {
            $flags = array();
            $constants = get_defined_constants(true);
            foreach ($constants['mysqli'] as $c => $n) if (preg_match('/MYSQLI_(.*)_FLAG$/', $c, $m)) if (!array_key_exists($n, $flags)) $flags[$n] = $m[1];
        }

        $convert = [
            "pri_key" => "primary_key"
        ];
        $result = array();
        foreach ($flags as $n => $t) {
            if ($flags_num & $n) {
                $t = strtolower($t);
                if(isSet($convert[$t])) $t = $convert[$t];
                $result[] = $t;
            }
        }
        return implode(' ', $result);
    }

}
