<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
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
 * Description : Access a MySQL database and use AjaXplorer as a phpMyAdmin.
 * @todo Put the DB content encoding as a driver option, then manually for current encoding 
 *       in SystemTextEncoding.
 */
class mysqlAccessDriver extends AbstractAccessDriver 
{
    /** The user name */
    var $user;
    /** The user password */
    var $password;
	
	function  mysqlAccessDriver($driverName, $filePath, $repository, $optOptions = NULL)
    {
        $this->user = $optOptions ? $optOptions["user"] : $repository->getOption("DB_USER");
        $this->password = $optOptions ? $optOptions["password"] : $repository->getOption("DB_PASS");
    
		parent::AbstractAccessDriver($driverName, $filePath, $repository);		
	}
	
	function initRepository(){
		$link = $this->createDbLink();
		$this->closeDbLink($link);
	}
	

	function createDbLink(){
		$link = FALSE;
		//Connects to the MySQL Database.		
		$host = $this->repository->getOption("DB_HOST");
		$dbname = $this->repository->getOption("DB_NAME");
		$link = @mysql_connect($host, $this->user, $this->pass);
		if(!$link) {
			$ajxpExp = new AJXP_Exception("Cannot connect to server!");
			AJXP_Exception::errorToXml($ajxpExp);
		}
		if(!@mysql_select_db($dbname, $link)){
			$ajxpExp = new AJXP_Exception("Cannot find database!");
			AJXP_Exception::errorToXml($ajxpExp);
		}
		return $link;
	}
	
	function closeDbLink($link){
		if(!mysql_close($link)){
			return new AJXP_Exception("Cannot close connection!");
		}
	}
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = Utils::securePath($getValue);
		}
		$selection = new UserSelection();
		$selection->initFromHttpVars($httpVars);
		if(isSet($dir) && $action != "upload") { 
			$safeDir = $dir; 
			$dir = SystemTextEncoding::fromUTF8($dir); 
		}
		// FILTER DIR PAGINATION ANCHOR
		if(isSet($dir) && strstr($dir, "#")!==false){
			$parts = split("#", $dir);
			$dir = $parts[0];
			$page = $parts[1];
		}				
		if(isSet($dest)) {
			$dest = SystemTextEncoding::fromUTF8($dest);			
		}
		$mess = ConfService::getMessages();
		
		switch($action)
		{				
			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "edit_record";	
				$isNew = false;
				if(isSet($record_is_new) && $record_is_new == "true") $isNew = true;
				$tableName = $_POST["table_name"];
				$pkName = $_POST["pk_name"];
				$query = "";
				$arrValues = array();
				foreach ($_POST as $key=>$value){
					if(substr($key, 0, strlen("ajxp_mysql_")) == "ajxp_mysql_"){
						$newKey = substr($key, strlen("ajxp_mysql_"));
						$arrValues[$newKey] = $value;
					}
				}
				if($isNew){
					$string = "";
					$index = 0;
					foreach ($arrValues as $k=>$v){
						// CHECK IF AUTO KEY!!!
						$string .= "'".addslashes(SystemTextEncoding::fromUTF8($v))."'";
						if($index < count($arrValues)-1) $string.=",";
						$index++;
					}
					$query = "INSERT INTO $tableName VALUES ($string)";
				}else{
					$string = "";
					$index = 0;
					foreach ($arrValues as $k=>$v){
						if($k == $pkName){
							$pkValue = $v;
						}else{
							$string .= $k."='".addslashes(SystemTextEncoding::fromUTF8($v))."'";
							if($index<count($arrValues)-1) $string.=",";
						}
						$index++;
					}
					$query = "UPDATE $tableName SET $string WHERE $pkName='$pkValue'";					
				}
				$link = $this->createDbLink();
				$res = $this->execQuery($query);
				$this->closeDbLink($link);
				
				if(is_a($res, "AJXP_Exception")){
					$errorMessage = $res->messageId;
				}else{
					$logMessage = $query;
					$reload_file_list = true;							
				}
			break;
					
			//------------------------------------
			//	CHANGE COLUMNS OR CREATE TABLE
			//------------------------------------
			case "edit_table":
				$link = $this->createDbLink();				
				if(isSet($httpVars["current_table"])){
					if(isSet($httpVars["delete_column"])){
						$query = "ALTER TABLE ".$httpVars["current_table"]." DROP COLUMN ".$httpVars["delete_column"];
						$res = $this->execQuery($query);
						if(is_a($res, "AJXP_Exception")){
							$errorMessage = $res->messageId;
						}else{
							$logMessage = $query;
							$reload_file_list = true;							
						}
						$this->closeDbLink($link);
						break;
					}
					if(isSet($httpVars["add_column"])){
						$defString = $this->makeColumnDef($httpVars, "add_field_");
						$query = "ALTER TABLE ".$httpVars["current_table"]." ADD COLUMN ($defString)";
						if(isSet($httpVars["add_field_pk"]) && $httpVars["add_field_pk"]=="1"){
							$query.= ", ADD PRIMARY KEY (".$httpVars["add_field_name"].")";
						}
						if(isSet($httpVars["add_field_index"]) && $httpVars["add_field_index"]=="1"){
							$query.= ", ADD INDEX (".$httpVars["add_field_name"].")";
						}
						if(isSet($httpVars["add_field_uniq"]) && $httpVars["add_field_uniq"]=="1"){
							$query.= ", ADD UNIQUE (".$httpVars["add_field_name"].")";
						}
						$res = $this->execQuery($query);
						if(is_a($res, "AJXP_Exception")){
							$errorMessage = $res->messageId;
						}else{
							$logMessage = $query;
							$reload_file_list = true;							
						}
						$this->closeDbLink($link);
						break;
					}
				}
				
				$fields = array("origname","name", "default", "null", "size", "type", "flags", "pk", "index", "uniq");
				$rows = array();
				foreach ($httpVars as $k=>$val){
					$split = split("_", $k);
					if(count($split) == 3 && $split[0]=="field" && is_numeric($split[2]) && in_array($split[1], $fields)){
						if(!isSet($rows[intval($split[2])])) $rows[intval($split[2])] = array();
						$rows[intval($split[2])][$split[1]] = $val;
					}
				}
				if(isSet($current_table)){
					$qMessage = '';
					foreach ($rows as $row){
						$sizeString = ($row["size"]!=""?"(".$row["size"].")":"");
						$defString = ($row["default"]!=""?" DEFAULT ".$row["default"]."":"");
						$query = "ALTER TABLE $current_table CHANGE ".$row["origname"]." ".$row["name"]." ".$row["type"].$sizeString.$defString." ".$row["null"];
						$res = $this->execQuery(trim($query));
						if(is_a($res, "AJXP_Exception")){
							$errorMessage = $res->messageId;
							$this->closeDbLink($link);
							break;
						}else{
							$qMessage .= $query;
							$reload_file_list = true;							
						}
					}
					$logMessage = $qMessage;
				}else if(isSet($new_table)){
					$fieldsDef = "";
					$pks = array();
					$indexes = array();
					$uniqs = array();
					foreach ($rows as $index=>$row){
						$fieldsDef .= $this->makeColumnDef($row);
						// Analyse keys
						if($row["pk"] == "1")$pks[] = $row["name"];
						if($row["index"]=="1") $indexes[] = $row["name"];
						if($row["uniq"]=="1") $uniqs[] = $row["name"];
						
						if($index < count($rows)-1){
							$fieldsDef.=",";
						}
					}
					if(count($pks)){
						$fieldsDef.= ",PRIMARY KEY (".join(",", $pks).")";
					}
					if(count($indexes)){
						$fieldsDef.=",INDEX (".join(",", $indexes).")";
					}
					if(count($uniqs)){
						$fieldsDef.=",UNIQUE (".join(",", $uniqs).")";
					}
					$query = "CREATE TABLE $new_table ($fieldsDef)"; 
					$res = $this->execQuery((trim($query)));
					if(is_a($res, "AJXP_Exception")){
						$errorMessage = $res->messageId;
					}else{
						$logMessage = $query;
						$reload_file_list = true;	
						$reload_current_node = true;						
					}
				}
				$this->closeDbLink($link);
			break;
			
			//------------------------------------
			//	SUPPRIMER / DELETE
			//------------------------------------
			case "delete_table":
			case "delete_record":
				$dir = basename($dir);
				$link = $this->createDbLink();
				if(trim($dir) == ""){
					// ROOT NODE => DROP TABLES
					$tables = $selection->getFiles();
					$query = "DROP TABLE";
					foreach ($tables as $index => $tableName){
						$tables[$index] = basename($tableName);
					}
					$query.= " ".join(",", $tables);
					$res = $this->execQuery($query);
					$reload_current_node = true;
				}else{
					// TABLE NODE => DELETE RECORDS
					$tableName = $dir;
					$pks = $selection->getFiles();
					foreach ($pks as $key => $pkString){
						$parts = split("\.", $pkString);
						array_pop($parts); // remove .pk extension
						array_shift($parts); // remove record prefix
						foreach ($parts as $index => $pkPart){
							$parts[$index] = str_replace("__", "='", $pkPart)."'";
						}
						$pks[$key] = "(".implode(" AND ", $parts).")";
					}
					$query = "DELETE FROM $tableName WHERE ". implode(" OR ", $pks);
					$res = $this->execQuery($query);
				}
				AJXP_Exception::errorToXml($res);
				if(is_a($res, "AJXP_Exception")){
					$errorMessage = $res->messageId;
				}else{
					$logMessage = $query;
					$reload_file_list = true;							
				}
				$this->closeDbLink($link);
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
				if(isSet($mode)){
					if($mode == "search") $searchMode = true;
					else if($mode == "file_list") $fileListMode = true;
					else if($mode == "complete") $completeMode = true;
				}	
				$link = $this->createDbLink();
				AJXP_Exception::errorToXml($link);
				if($dir == ""){
					AJXP_XMLWriter::header();
					$tables = $this->listTables();					
					print '<columns switchDisplayMode="list" switchGridMode="filelist"><column messageString="Table Name" attributeName="ajxp_label" sortType="String"/><column messageString="Byte Size" attributeName="bytesize" sortType="NumberKo"/><column messageString="Count" attributeName="count" sortType="Number"/></columns>';
					$icon = ($mode == "file_list"?"sql_images/mimes/ICON_SIZE/table_empty.png":"sql_images/mimes/ICON_SIZE/table_empty_tree.png");
					foreach ($tables as $tableName){
						$size = $this->getSize($tableName);
						$count = $this->getCount($tableName);
						print "<tree is_file=\"0\" text=\"$tableName\" filename=\"/$tableName\" bytesize=\"$size\" count=\"$count\" icon=\"$icon\" ajxp_mime=\"table\" />";
					}
					print "<tree is_file=\"0\" text=\"Search Results\" ajxp_node=\"true\" filename=\"/ajxpmysqldriver_searchresults\" bytesize=\"-\" count=\"-\" icon=\"".($mode == "file_list"?"search.png":CLIENT_RESOURCES_FOLDER."/images/crystal/mimes/16/search.png")."\"/>";
					AJXP_XMLWriter::close();
				}else{
					$tableName = basename($dir);
					if(isSet($page))$currentPage = $page;
					else $currentPage = 1;
					$query = "SELECT * FROM $tableName";
					$searchQuery = false;
					if($tableName == "ajxpmysqldriver_searchresults"){
						if(isSet($_SESSION["LAST_SQL_QUERY"])){
							$query = $_SESSION["LAST_SQL_QUERY"];
							$matches = array();
							if(preg_match("/SELECT [\S, ]* FROM (\S*).*/i", $query, $matches)!==false){
								$tableName = $matches[1];
								$searchQuery = true;
							}else{
								break;
							}
						}else{
							break;
						}
					}
					if(isSet($order_column)){
						$query .= " ORDER BY $order_column ".strtoupper($order_direction);
						if(!isSet($_SESSION["AJXP_ORDER_DATA"])){
							$_SESSION["AJXP_ORDER_DATA"] = array();
						}
						$_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName] = array("column" => $order_column, "dir" => $order_direction);
					}else if(isSet($_SESSION["AJXP_ORDER_DATA"])){
						if(isSet($_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName])){
							$order_column = $_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName]["column"];
							$order_direction = $_SESSION["AJXP_ORDER_DATA"][$this->repository->getUniqueId()."_".$tableName]["dir"];
							$query .= " ORDER BY $order_column ".strtoupper($order_direction);
						}
					}
					$result = $this->showRecords($query, $tableName, $currentPage);					
					if($searchQuery && is_a($result, "AJXP_Exception")){
						unset($_SESSION["LAST_SQL_QUERY"]); // Do not store wrong query!
					}
					AJXP_Exception::errorToXml($result);
					AJXP_XMLWriter::header();
					$blobCols = array();
					print '<columns switchDisplayMode="list" switchGridMode="grid">';
					foreach ($result["COLUMNS"] as $col){
						print "<column messageString=\"".$col["NAME"]."\" attributeName=\"".$col["NAME"]."\" field_name=\"".$col["NAME"]."\" field_type=\"".$col["TYPE"]."\" field_size=\"".$col["LENGTH"]."\" field_flags=\"".$this->cleanFlagString($col["FLAGS"])."\" field_pk=\"".(eregi("primary", $col["FLAGS"])?"1":"0")."\" field_null=\"".(eregi("not_null", $col["FLAGS"])?"NOT_NULL":"NULL")."\" sortType=\"".$this->sqlTypeToSortType($col["TYPE"])."\" field_default=\"".$col["DEFAULT"]."\"/>";
						if(stristr($col["TYPE"],"blob")!==false && ($col["FLAGS"]!="" && stristr($col["FLAGS"], "binary"))){
							$blobCols[]=$col["NAME"];
						}
					}
					
					print '</columns>';
					print '<pagination total="'.$result["TOTAL_PAGES"].'" current="'.$currentPage.'" remote_order="true" currentOrderCol="'.$order_column.'" currentOrderDir="'.$order_direction.'"/>';
					foreach ($result["ROWS"] as $row){
						print '<tree ';
						$pkString = "";
						foreach ($row as $key=>$value){
							if(in_array($key, $blobCols)){
								$sizeStr = "-NULL";
								if(strlen($value)) $sizeStr = "-".Utils::roundSize(strlen($sizeStr));
								print "$key=\"BLOB$sizeStr\" ";
							}else{
								$value = str_replace("\"", "", $value);
								$value = Utils::xmlEntities($value);
								print $key.'="'.SystemTextEncoding::toUTF8($value).'" ';
								if($result["HAS_PK"]>0){
									if(in_array($key, $result["PK_FIELDS"])){
										$pkString .= $key."__".$value.".";
									}
								}
							}
						}
						if($result["HAS_PK"] > 0){
							print 'filename="record.'.$pkString.'pk" ';
							print 'is_file="1" ajxp_mime="pk"/>';
						}else{
							print 'filename="record.no_pk" ';
							print 'is_file="1" ajxp_mime="row"/>';
						}
						
					}
					AJXP_XMLWriter::close();
				}
				$this->closeDbLink($link);
				exit(1);
												
			break;		
		}

		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}
		
		if(isset($requireAuth))
		{
			$xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
		}
		
		if(isset($reload_current_node) && $reload_current_node == "true")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadCurrentNode(false);
		}
		
		if(isset($reload_dest_node) && $reload_dest_node != "")
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadNode($reload_dest_node, false);
		}
		
		if(isset($reload_file_list))
		{
			$xmlBuffer .= AJXP_XMLWriter::reloadFileList($reload_file_list, false);
		}
		
		return $xmlBuffer;
	}
	
	function getSize($tablename){
		$repo = ConfService::getRepository();
		$dbname = $repo->getOption("DB_NAME");
		$like="";
		$total="";
		$t=0;
		if($tablename !=""){
			$like=" like '$tablename'";
		}
		$sql= "SHOW TABLE STATUS FROM $dbname $like";
		$result=$this->execQuery($sql);
		if($result){

			while($rec = mysql_fetch_array($result)){
				$t+=($rec['Data_length'] + $rec['Index_length']);
			}
			$total = Utils::roundSize($t);
		}else{
			$total="Unknown";
		}
		return($total);
	}
	
	function getCount($tableName){
		$sql = "SELECT count(*) FROM $tableName";
		$result = $this->execQuery($sql);
		$t = 0;
		if($result){
			while($res = mysql_fetch_array($result)){
				$t+=$res[0];
			}
		}
		return $t;
	}

	function getColumnData($tableName, $columnName){
		$sql = "SHOW COLUMNS FROM $tableName LIKE '$columnName'";
		$res = $this->execQuery($sql);
		if($res){
			return mysql_fetch_array($res);
			// ["Field", "Type", "Null", "Key", "Default", "Extra"] => Type is like "enum('a', 'b', 'c')"
		}
	}
	
	function makeColumnDef($row, $prefix="", $suffix=""){
		$defString = "";
		if(isSet($row[$prefix."default".$suffix]) && trim($row[$prefix."default".$suffix]) != ""){
			$defString = " DEFAULT ".$row[$prefix."default".$suffix];
		}
		$sizeString = ($row[$prefix."size".$suffix]!=""?"(".$row[$prefix."size".$suffix].")":"");
		$fieldsDef = $row[$prefix."name".$suffix]." ".$row[$prefix."type".$suffix].$sizeString.$defString." ".$row[$prefix."null".$suffix]." ".$row[$prefix."flags".$suffix];
		return trim($fieldsDef);
	}
	
	function cleanFlagString($flagString){
		$arr = split(" ", $flagString);
		$newFlags = array();
		foreach ($arr as $flag){
			if($flag == "primary_key" || $flag == "null" || $flag == "not_null"){
				continue;
			}
			$newFlags[] = $flag;
		}
		return join(" ", $newFlags);
	}
	
	function sqlTypeToSortType($fieldType){
		switch ($fieldType){
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
	
	function listTables(){
		$repo = ConfService::getRepository();
		$result = mysql_list_tables($repo->getOption("DB_NAME"));
		$numtab = mysql_num_rows ($result);
		$allTables = array();
		for ($i =0; $i < $numtab; $i++) {
			$table = trim(mysql_tablename($result, $i));
			$allTables[] = $table;
		}
		return $allTables;
	}
	
	
	function showRecords($query, $tablename, $currentPage=1, $rpp=50, $searchval='' ){		
		
		$repo = ConfService::getRepository();
		$dbname=$repo->getOption("DB_NAME");
		$result=$this->execQuery($query);
		
		$columns = array();
		$rows = array();
		
		if(is_a($result, "AJXP_Exception")) return $result;
		
		$num_rows = mysql_num_rows($result);
		$pg=$currentPage-1;
		if(isset($_POST['first'])){
			$pg=0;
		}else if(isset($_POST['back'])){
			$pg=$pg-1;
		}else if(isset($_POST['next'])){
			$pg++;
		}else if(isset($_POST['last'])){
			$pgs = $num_rows/$rpp;
			$pg=ceil($pgs)-1;
		}
		if($pg < 0 ){
			$pg=0;
		}
		if($pg > $num_rows/$rpp){
			$pg=ceil($num_rows/$rpp)-1;
		}
		$totalPages = ceil($num_rows/$rpp);
		$beg = $pg * $rpp;

		$flds = mysql_num_fields($result);
		$fields = @mysql_list_fields( $dbname, $tablename);
		if(!$fields){
			return new AJXP_Exception("Non matching fields for table '$tablename'");
		}
		$z=0;
		$x=0;
		$pkfield=array();

		// MAKE COLUMNS HEADER
		for ($i = 0; $i < $flds; $i++) {
			$c=$i+1;
			$title=mysql_field_name($fields, $i);
			$type=mysql_field_type($fields, $i);
			$size=mysql_field_len($fields, $i);
			$flagstring = mysql_field_flags ($fields, $i);
			$colData = $this->getColumnData($tablename, $title);
			$colDataType = $colData["Type"];
			if(preg_match("/(.*)\((.*)\)/", $colDataType, $matches)){
				$type = $matches[1];
				$size = $matches[2];
			}
			$columns[] = array("NAME" => $title, "TYPE"=>$type, "LENGTH"=>$size, "FLAGS"=>$flagstring, "DEFAULT"=>$colData["Default"]);

			//Find the primary key
			$flagstring = mysql_field_flags ($result, $i);
			if(eregi("primary",$flagstring )){
				$pk[$z] = $i;
				$pkfield[$z]= mysql_field_name($fields, $i);
				$z++;
			}
		}
		$v=$flds+1;

		if($z > 0){
			$cpk=count($pk);
		}else{
			$cpk=0;
		}

		// MAKE ROWS RESULT
		for ($s=$beg; $s < $beg + $rpp; $s++){
			if($s < $num_rows){
				if (!mysql_data_seek ($result, $s)) {
					continue;
				}
				$row=mysql_fetch_array($result);
				if(!isset($pk)){
					$pk=' ';
					$pkfield= array();
				}
				$values = array();
				for($col = 0; $col < $flds; $col ++)
				{
					$values[mysql_field_name($fields, $col)] = stripslashes($row[$col]);
				}					
				$rows[] = $values;
			}
		}

		return array("COLUMNS" => $columns, "ROWS" => $rows, "HAS_PK"=>$cpk, "TOTAL_PAGES"=>$totalPages, "PK_FIELDS"=>$pkfield);
	}

	
	function execQuery($sql =''){
		$output='';
		if($sql !=''){
			$result= @mysql_query( $sql );
			if($result){
				AJXP_Logger::logAction("exec", array($sql));
				return $result;
			}else{
				return new AJXP_Exception($sql.":".mysql_error());
			}
		}else{
			return new AJXP_Exception('Empty Query');
		}
	}
 	
    
}

?>
