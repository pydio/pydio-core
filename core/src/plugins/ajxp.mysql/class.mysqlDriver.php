<?php

class mysqlDriver extends AbstractDriver 
{
	/**
	* @var Repository
	*/
	var $repository;
	
	function  mysqlDriver($driverName, $filePath, $repository){
		parent::AbstractDriver($driverName, $filePath, $repository);		
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
				$this->execQuery($query);
				$this->closeDbLink($link);
				
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage($query, null);
				AJXP_XMLWriter::reloadFileList(true);
				AJXP_XMLWriter::close();
				exit(1);				
			break;
					
			//------------------------------------
			//	CHANGE COLUMNS OR CREATE TABLE
			//------------------------------------
			case "edit_table":
				$fields = array("name", "default", "null", "size", "type", "flags", "pk");
				$rows = array();
				foreach ($httpVars as $k=>$val){
					$split = split("_", $k);
					if(count($split) == 3 && $split[0]=="field" && is_numeric($split[2]) && in_array($split[1], $fields)){
						if(!isSet($rows[intval($split[2])])) $rows[intval($split[2])] = array();
						$rows[intval($split[2])][$split[1]] = $val;
					}
				}
				$link = $this->createDbLink();
				if(isSet($current_table)){
					foreach ($rows as $row){
						$query = "ALTER TABLE $current_table CHANGE ".$row["name"]." ".$row["name"]." ".$row["type"]."(".$row["size"].") ".$row["null"];
						$res = $this->execQuery(trim($query));
						AJXP_Exception::errorToXml($res);
						AJXP_XMLWriter::header();
						AJXP_XMLWriter::sendMessage($query, null);
						AJXP_XMLWriter::reloadFileList(false);
						AJXP_XMLWriter::close();				
					}
				}else if(isSet($new_table)){
					$fieldsDef = "";
					foreach ($rows as $row){
						$defString = "";
						if(isSet($row["default"]) && trim($row["default"]) != ""){
							$defString = " DEFAULT ".$row["default"];
						}
						$fieldsDef.= $row["name"]." ".$row["type"]."(".$row["size"].")".$defString." ".$row["null"]." ".$row["flags"].",";
						if($row["pk"] == "1"){
							$pk = $row;
						}
					}
					if(isSet($pk)){
						$fieldsDef.= "PRIMARY KEY (".$pk["name"].")";
					}
					$query = "CREATE TABLE $new_table ($fieldsDef)"; 
					$res = $this->execQuery((trim($query)));
					AJXP_Exception::errorToXml($res);
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage($query, null);
					AJXP_XMLWriter::reloadFileList(false);
					AJXP_XMLWriter::close();				
				}
				$this->closeDbLink($link);
				exit(1);
			break;
			
			//------------------------------------
			//	SUPPRIMER / DELETE
			//------------------------------------
			case "delete";
			break;
		
			//------------------------------------
			//	RENOMMER / RENAME
			//------------------------------------
			case "rename";
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
				AJXP_XMLWriter::header();
				if($dir == ""){
					$tables = $this->listTables();
					print '<columns switchGridMode="filelist"><column messageString="Table Name" attributeName="text" sortType="String"/><column messageString="Byte Size" attributeName="bytesize" sortType="NumberKo"/><column messageString="Count" attributeName="count" sortType="Number"/></columns>';
					$icon = ($mode == "file_list"?"folder.png":CLIENT_RESOURCES_FOLDER."/images/foldericon.png");
					foreach ($tables as $tableName){
						$size = $this->getSize($tableName);
						$count = $this->getCount($tableName);
						print "<tree is_file=\"0\" text=\"$tableName\" filename=\"/$tableName\" bytesize=\"$size\" count=\"$count\" icon=\"$icon\"/>";
					}
				}else{
					$tableName = basename($dir);
					if(isSet($page))$currentPage = $page;
					else $currentPage = 1;
					$result = $this->showRecords("SELECT * FROM $tableName", $tableName, $currentPage);
					print '<columns switchGridMode="grid">';
					foreach ($result["COLUMNS"] as $col){
						print "<column messageString=\"".$col["NAME"]."\" attributeName=\"".$col["NAME"]."\" field_name=\"".$col["NAME"]."\" field_type=\"".$col["TYPE"]."\" field_size=\"".$col["LENGTH"]."\" field_flags=\"".$this->cleanFlagString($col["FLAGS"])."\" field_pk=\"".(eregi("primary", $col["FLAGS"])?"1":"0")."\" field_null=\"".(eregi("not_null", $col["FLAGS"])?"NOT_NULL":"NULL")."\" sortType=\"".$this->sqlTypeToSortType($col["TYPE"])."\" field_default=\"".$col["DEFAULT"]."\"/>";
					}
					print '</columns>';
					print '<pagination total="'.$result["TOTAL_PAGES"].'" current="'.$currentPage.'"/>';
					foreach ($result["ROWS"] as $row){
						print '<tree ';
						foreach ($row as $key=>$value){							
							$value = str_replace("\"", "", $value);
							$value = str_replace("&", "&amp;", $value);
							$value = str_replace(">", "", $value);
							print $key.'="'.SystemTextEncoding::toUTF8($value).'" ';
						}
						if($result["HAS_PK"] > 0){
							print 'filename="record.pk" ';
						}else{
							print 'filename="record.no_pk" ';
						}
						print 'is_file="1" />';
					}
				}
				$this->closeDbLink($link);
				AJXP_XMLWriter::close();
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
	

	function createDbLink(){
		$link = FALSE;
		//Connects to the MySQL Database.		
		$repo = ConfService::getRepository();
		$user = $repo->getOption("DB_USER");
		$pass = $repo->getOption("DB_PASS");
		$host = $repo->getOption("DB_HOST");
		$dbname = $repo->getOption("DB_NAME");
		$link = mysql_connect($host, $user, $pass);
		if(!$link) return new AJXP_Exception("Cannot connect to server!");
		if(!mysql_select_db($dbname, $link)){
			return new AJXP_Exception("Cannot find database!");
		}
		return $link;
	}
	
	function closeDbLink($link){
		if(!mysql_close($link)){
			return new AJXP_Exception("Cannot close connection!");
		}
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
	
	
	function showRecords($query, $tablename, $currentPage=1, $rpp=100, $searchval='' ){
		$repo = ConfService::getRepository();
		$dbname=$repo->getOption("DB_NAME");
		$result=$this->execQuery($query);
		
		$columns = array();
		$rows = array();
		
		if($result){

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
			$fields = mysql_list_fields( $dbname, $tablename);
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

		}
		return array("COLUMNS" => $columns, "ROWS" => $rows, "HAS_PK"=>$cpk, "TOTAL_PAGES"=>$totalPages);
	}

	
	function execQuery($sql =''){
		$output='';
		if($sql !=''){
			$result= @mysql_query( $sql );
			if($result){
				return $result;
			}else{
				return new AJXP_Exception("<b>".$sql."</b> :".mysql_error());
			}
		}else{
			return new AJXP_Exception('Empty Query');
		}
	}
 	
    
}

?>
