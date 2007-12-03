<?php
//---------------------------------------------------------------------------------------------------
//
//	AjaXplorer v1.4
//
//	Charles du Jeu
//	http://sourceforge.net/projects/ajaxplorer
//  http://www.almasound.com
//
//---------------------------------------------------------------------------------------------------

//require_once("classes/class.BookmarksManager.php");
require_once("classes/class.Utils.php");
require_once("classes/class.ConfService.php");
require_once("classes/class.AuthService.php");
require_once("classes/class.FS_Storage.php");
require_once("classes/class.UserSelection.php");
require_once("classes/class.HTMLWriter.php");
require_once("classes/class.AJXP_XMLWriter.php");
require_once("classes/class.AJXP_User.php");

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
session_start();
ConfService::init("conf/conf.php");
$hautpage=ConfService::getConf("TOP_PAGE");
$baspage=ConfService::getConf("BOTTOM_PAGE");

if(AuthService::usersEnabled())
{
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "logout")
	{
		AuthService::disconnect();
		$loggingResult = 2;
	}	//AuthService::disconnect();
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "login")
	{
		$userId = (isSet($_GET["userid"])?$_GET["userid"]:null);
		$userPass = (isSet($_GET["password"])?$_GET["password"]:null);
		$loggingResult = AuthService::logUser($userId, $userPass);
	}
	else 
	{
		AuthService::logUser(null, null);	
	}
	// Check that current user can access current repository, try to switch otherwise.
	$loggedUser = AuthService::getLoggedUser();
	if($loggedUser != null)
	{
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) && AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
		{
			ConfService::switchRootDir(AuthService::getDefaultRootId());
		}
	}
	if($loggedUser == null)
	{
		$requireAuth = true;
	}
	if(isset($loggingResult) || (isSet($_GET["get_action"]) && $_GET["get_action"] == "logged_user"))
	{
		AJXP_XMLWriter::header();
		if(isSet($loggingResult)) AJXP_XMLWriter::loggingResult($loggingResult);
		AJXP_XMLWriter::sendUserData();
		AJXP_XMLWriter::close();
		exit(1);
	}
}

$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null)
{
	if($loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
}
$mess = ConfService::getMessages();

foreach($_GET as $getName=>$getValue)
{
	$$getName = $getValue;
}
foreach($_POST as $getName=>$getValue)
{
	$$getName = $getValue;
}

$selection = new UserSelection();
$selection->initFromHttpVars();

if(isSet($action) || isSet($get_action)) $action = (isset($get_action)?$get_action:$action);
else $action = "";

// FILTER ACTION FOR DELETE
if(ConfService::useRecycleBin() && $action == "supprimer_suite" && $rep != "/".ConfService::getRecycleBinDir())
{
	$action = "deplacer_suite";
	$dest = "/".ConfService::getRecycleBinDir();
	$dest_node = "AJAXPLORER_RECYCLE_NODE";
}

//--------------------------------------
// FIRST CHECK RIGHTS FOR THIS ACTION
//--------------------------------------
if(AuthService::usersEnabled())
{
	$loggedUser = AuthService::getLoggedUser();	
	switch ($action)
	{
		// NEEDS WRITE RIGHTS
		case "editer":
		case "copier_suite":
		case "deplacer_suite":
		case "supprimer_suite":
		case "rename_suite":
		case "mkdir":
		case "creer_fichier":
		case "upload":
			if($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))
			{
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, "You have no write permission!");
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}			
		break;
		
		// NEEDS READ RIGHTS
		case "voir":
		case "image_proxy":
		case "mp3_proxy":
		case "switch_root_dir":
		case "xml_listing":
		case "telecharger":
		case "root_tree":		
			if($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRootDirIndex().""))
			{
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, "You have no read permission!");
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}			
		break;
		// NO SPECIFIC RIGHTS
		case "display_action_bar":
		case "display_bookmark_bar":
		case "display_doc":
		default:
		break;
	}
}

//------------------------------------
//	SWITCH ON ACTION VARIABLE
//------------------------------------

switch($action)
{
	//------------------------------------
	//	EDITER / EDIT
	//------------------------------------

	case "editer";	
	include($hautpage);
	if(isset($save) && $save==1)
	{
		$code=stripslashes($code);
		$code=str_replace("&lt;","<",$code);
		$fp=fopen(ConfService::getRootDir()."/$fic","w");
		fputs ($fp,$code);
		fclose($fp);
		Utils::enlever_controlM(ConfService::getRootDir()."/$fic");
		$logMessage = $mess[115];
	}
	if(isset($logMessage) || isset($errorMessage))
	{
		echo "<div title=\"".$mess[98]."\" id=\"message_div_int\" onclick=\"new Effect.BlindUp('message_div_int');messageDivOpen=false;\" class=\"messageBox ".(isset($logMessage)?"logMessage":"errorMessage")."\"><table id=\"message_content\" style=\"padding:2px;\" width=\"100%\"><tr><td style=\"width: 66%;\">".(isset($logMessage)?$logMessage:$errorMessage)."</td><td style=\"color: #999; text-align: right;padding-right: 10px; width: 30%;\"><i>".$mess[98]."</i></tr></table></div>";		
		echo "<script>
		var messageDivOpen = true;
		setTimeout(\"if(messageDivOpen){new Effect.BlindUp('message_div_int');messageDivOpen = false;}\", 3000);
		</script>";
	}
	$codePressStyle = Utils::editWithCodePress($fic);
	echo "<form action=\"content.php\" id=\"editForm\" method=\"post\">\n";
	echo "<div id=\"action_bar\" style=\"height: 45px;\">";
	//echo '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td>';
	echo "<a href=\"#\" onclick=\"parent.hideLightBox();\"><img src=\"images/crystal/fileclose.png\"  width=\"22\" height=\"22\" alt=\"\" border=\"0\"><br>".$mess[86]."</a>\n";
	echo "<a href=\"#\" onclick=\"saveForm();return false;\"><img src=\"images/crystal/filesave.png\" width=\"22\" height=\"22\" alt=\"$mess[53]\" border=\"0\"><br>".$mess[53]."</a>\n";
	//echo '<td></tr></table>';
	echo "</div>";
	echo "<input type=\"hidden\" name=\"fic\" value=\"$fic\">\n";
	echo "<input type=\"hidden\" name=\"rep\" value=\"$rep\">\n";
	echo "<input type=\"hidden\" name=\"save\" value=\"1\">\n";
	echo "<input type=\"hidden\" name=\"action\" value=\"editer\">\n";
	if($codePressStyle != "")
	{
		echo "<input type=\"hidden\" id=\"code\" name=\"code\" value=\"\">\n";
		echo "<TEXTAREA NAME=\"myCode\" id=\"myCode\" class=\"codepress $codePressStyle linenumbers-on\" id=\"myCode\" style=\"width:100%;\" wrap=\"OFF\" rows=30>\n";		
	}
	else
	{
		echo "<TEXTAREA NAME=\"code\" style=\"width:100%; \" wrap=\"OFF\" rows=30 id=\"myCode\">\n";
	}
	$fp=fopen(ConfService::getRootDir()."/$fic","r");
	while (!feof($fp))
	{
		$tmp=fgets($fp,4096);
		$tmp=str_replace("<","&lt;",$tmp);
		echo "$tmp";
	}
	fclose($fp);
	//echo "$fic";
	echo "</TEXTAREA>\n";
	echo "</form>\n";
	echo "<script language=\"javascript\">
	jQuery('#action_bar a').corner('round 8px');
	fitHeightToBottom($('myCode'), window);
	document.getElementById('myCode').focus();
	function saveForm(){
		".($codePressStyle!=""?"$('code').value = myCode.getCode();":"")."
		$('editForm').submit();
	}
	</script>";	
	include($baspage);
	exit(0);
	break;

	//------------------------------------
	//	VOIR UNE IMAGE
	//------------------------------------

	case "voir";
	$nomdufichier=basename($fic);
	include($hautpage);
	echo "<style>body {background-color: buttonface;}</style>";
	echo "<div id=\"action_bar\" style=\"height: 45px;\">";
	echo "<a href=\"#\" onclick=\"parent.hideLightBox();\"><img width=\"22\" height=\"22\" src=\"images/crystal/fileclose.png\" alt=\"\" border=\"0\"><br>".$mess[86]."</a>";
	echo "<a href=\"#\" id=\"prevButton\" onclick=\"diapo.previous(); return false;\"><img  width=\"22\" height=\"22\" src=\"images/crystal/back_22.png\" alt=\"\" border=\"0\"><br>".$mess[178]."</a>";
	echo "<a href=\"#\" id=\"nextButton\" onclick=\"diapo.next(); return false;\"><img width=\"22\" height=\"22\" src=\"images/crystal/forward_22.png\" alt=\"\" border=\"0\"><br>".$mess[179]."</a>";	
	echo "</div>\n";
	echo "<div style=\"text-align:center; vertical-align:center;overflow:auto; background-color:#ccc; border:1px solid black;\" id=\"imageContainer\">";
	echo "<img id=\"mainImage\" src=\"content.php?action=image_proxy&fic=$fic\">\n";
	echo "</div>";
	echo "<script language=\"javascript\">
	jQuery('#action_bar a').corner('round 8px');
	fitHeightToBottom($('imageContainer'), window);
	var diapo = new Diaporama('$fic', $('prevButton'), $('nextButton'), $('mainImage'));
	</script>";
	include($baspage);
	exit;
	break;

	//------------------------------------
	//	AIDE / HELP
	//------------------------------------
	case "aide";
	include($hautpage);
	HTMLWriter::toolbar((isset($_GET["user"])?$_GET["user"]:"shared_bookmarks"));
	include("include/${langue}_help.htm");
	include($baspage);
	exit(0);
	break;


	//------------------------------------
	//	TELECHARGER / DOWNLOAD
	//------------------------------------

	case "telecharger";
	$NomFichier = basename($fic);
	$taille=filesize(ConfService::getRootDir()."/$fic");
	header("Content-Type: application/force-download; name=\"$NomFichier\"");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: $taille");
	header("Content-Disposition: attachment; filename=\"$NomFichier\"");
	header("Expires: 0");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	// For SSL websites there is a bug with IE
	// see article KB 323308
	// therefore we must reset the Cache-Control and Pragma Header
	// (although Pragma and IE 7 were not mentioned they still 
	// experience this problem)
	// 1. Check for SSL-Usage
	if (strcmp(substr(ConfService::getConf("USE_HTTPS"),0,6),"https:") == 0) 
	{
		// check for IE (this also catches browser who fake IE usage
		// like Opera
		if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT']))
		{
			// reset headers
			header("Cache-Control:");
			header("Pragma:");
		}
	}
	
	readfile(ConfService::getRootDir()."/$fic");
	exit();
	break;


	//------------------------------------
	//	COPY / MOVE
	//------------------------------------

	case "copier_suite";
	case "deplacer_suite";	
	if($selection->isEmpty())
	{
		$errorMessage = $mess[113];
		break;
	}
	if(!is_writable(ConfService::getRootDir()."/".$dest))
	{
		$errorMessage = $mess[38]." ".$dest." ".$mess[99];
		break;
	}
	if($action == "deplacer_suite" && !is_writable(dirname(ConfService::getRootDir()."/".$selection->getUniqueFile())))
	{
		$errorMessage.= "\n".$mess[38]." ".dirname($selection->getUniqueFile())." ".$mess[99];
		break;
	}
	
	$success = $error = array();
	$selectedFiles = $selection->getFiles();
	foreach ($selectedFiles as $selectedFile)
	{
		FS_Storage::copyOrMoveFile($dest, $selectedFile, $error, $success, ($action=="deplacer_suite"?true:false));
	}
	
	if(count($error)) $errorMessage = join("\n", $error);
	else $logMessage = join("\n", $success);
	$reload_current_node = true;
	$reload_dest_node = $dest_node;
	$reload_file_list = true;
	break;

	case "image_proxy":
	$taille=filesize(ConfService::getRootDir()."/$fic");
	header("Content-Type: ".Utils::getImageMimeType($fic)."; name=\"".basename($fic)."\"");
	header('Cache-Control: public');
	readfile(ConfService::getRootDir()."/$fic");
	exit(0);
	break;
	
	case "mp3_proxy":
	$taille=filesize(ConfService::getRootDir()."/$fic");
	header("Content-Type: audio/mp3; name=\"".basename($fic)."\"");
	readfile(ConfService::getRootDir()."/$fic");
	exit(0);
	break;
	
	//------------------------------------
	//	SUPPRIMER / DELETE
	//------------------------------------
	case "supprimer_suite";
	if($selection->isEmpty())
	{
		$errorMessage = $mess[113];
		break;
	}
	$logMessages = array();
	foreach ($selection->getFiles() as $selectedFile)
	{	
		$a_effacer=ConfService::getRootDir().$selectedFile;
		if($selectedFile == "" || $selectedFile == DIRECTORY_SEPARATOR)
		{
			$errorMessage = $mess[120];
			break;
		}
		if(file_exists($a_effacer))
		{
			FS_Storage::deldir($a_effacer);
			if(is_dir($a_effacer))
			{
				$logMessages[]="$mess[38] $selectedFile $mess[44].";
			}
			else 
			{
				$logMessages[]="$mess[34] $selectedFile $mess[44].";
			}
		}
		else 
		{
			$logMessages[]=$mess[100]." $selectedFile";
		}
	}
	if(count($logMessages))
	{
		$logMessage = join("\n", $logMessages);
	}
	$reload_current_node = true;
	$reload_file_list = true;
	break;


	//------------------------------------
	//	RENOMMER / RENAME
	//------------------------------------
	case "rename_suite";
	$nom_fic=basename($fic);
	$fic_new=Utils::traite_nom_fichier($fic_new);
	$old=ConfService::getRootDir()."/$fic";
	if(!is_writable($old))
	{
		$errorMessage = $mess[34]." ".$nom_fic." ".$mess[99];
		break;		
	}
	$new=dirname($old)."/".$fic_new;
	if($fic_new=="")
	{
		$errorMessage="$mess[37]";
		break;
	}
	if(file_exists($new))
	{
		$errorMessage="$fic_new $mess[43]"; 
		break;
	}
	if(!file_exists($old))
	{
		$errorMessage = $mess[100]." $nom_fic";
		break;
	}
	rename($old,$new);
	
	$logMessage="$fic $mess[41] $fic_new";
	$reload_current_node = true;
	$reload_file_list = basename($new);
	break;


	//------------------------------------
	//	CREER UN REPERTOIRE / CREATE DIR
	//------------------------------------

	case "mkdir";
	$err="";
	$messtmp="";
	$nomdir=Utils::traite_nom_fichier($nomdir);
	if($nomdir=="")
	{
		$errorMessage="$mess[37]";
		break;
	}
	if(file_exists(ConfService::getRootDir()."/$rep/$nomdir"))
	{
		$errorMessage="$mess[40]"; 
		break;
	}
	if(!is_writable(ConfService::getRootDir()."/$rep"))
	{
		$errorMessage = $mess[38]." $rep ".$mess[99];
		break;
	}
	mkdir(ConfService::getRootDir()."/$rep/$nomdir",0775);
	$reload_file_list = $nomdir;
	$messtmp.="$mess[38] $nomdir $mess[39] ";
	if($rep=="") {$messtmp.="/";} else {$messtmp.="$rep";}
	$logMessage = $messtmp;
	$reload_current_node = true;
	break;

	//------------------------------------
	//	CREER UN FICHIER / CREATE FILE
	//------------------------------------

	case "creer_fichier";
	$err="";
	$messtmp="";
	$nomfic=Utils::traite_nom_fichier($nomfic);
	if($nomfic=="")
	{
		$errorMessage="$mess[37]"; break;
	}
	if(file_exists(ConfService::getRootDir()."/$rep/$nomfic"))
	{
		$errorMessage="$mess[71]"; break;
	}
	if(!is_writable(ConfService::getRootDir()."/$rep"))
	{
		$errorMessage="$mess[38] $rep $mess[99]";break;
	}
	
	$fp=fopen(ConfService::getRootDir()."/$rep/$nomfic","w");
	if($fp)
	{
		if(eregi("\.html$",$nomfic)||eregi("\.htm$",$nomfic))
		{
			fputs($fp,"<html>\n<head>\n<title>Document sans titre</title>\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">\n</head>\n<body bgcolor=\"#FFFFFF\" text=\"#000000\">\n\n</body>\n</html>\n");
		}
		fclose($fp);
		$messtmp.="$mess[34] $nomfic $mess[39] ";
		if($rep=="") {$messtmp.="/";} else {$messtmp.="$rep";}
		$logMessage = $messtmp;
		$reload_file_list = $nomfic;
	}
	else
	{
		$err = 1;
		$errorMessage = "$mess[102] $rep/$nomfic (".$fp.")";
	}

	break;


	//------------------------------------
	//	UPLOAD
	//------------------------------------

	case "upload":

	if($rep!=""){$rep_source="/$rep";}
	else $rep_source = "";
	$destination=ConfService::getRootDir().$rep_source;
	if(!is_writable($destination))
	{
		$errorMessage = "$mess[38] $rep $mess[99].";
		break;
	}	
	$logMessage = "";
	foreach ($_FILES as $boxName => $boxData)
	{
		if(substr($boxName, 0, 9) == "userfile_")
		{
			foreach($boxData as $usFileName=>$usFileValue)
			{
				$varName = "userfile_".$usFileName;
				$$varName = $usFileValue;
			}
		}
		else 
		{
			continue;
		}
		if ($userfile_error != UPLOAD_ERR_OK)
		{
			$errorsArray = array();
			$errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = "PHP : File is too big! Max is".ini_get("upload_max_filesize");
			$errorsArray[UPLOAD_ERR_NO_FILE] = "PHP : No file found on server!($boxName)";
			$errorsArray[UPLOAD_ERR_PARTIAL] = "PHP : File is partial";
			if($userfile_error == UPLOAD_ERR_NO_FILE && ereg('Opera',$_SERVER['HTTP_USER_AGENT']))
			{
				// BEURK : Opera hack, do not display "no file found error"
				continue;
			}
			$errorMessage = $errorsArray[$userfile_error];
			continue;
		}
		if ($userfile_size!=0)
		{
			$taille_ko=$userfile_size/1024;
		}
		else
		{
			$taille_ko=0;
		}
		if ($userfile_tmp_name=="none")
		{
			$errorMessage=$mess[31];
			break;
		}
		if ($userfile_tmp_name!="none" && $userfile_size!=0)
		{
			$userfile_name=Utils::traite_nom_fichier($userfile_name);
			if (!copy($userfile_tmp_name, "$destination/$userfile_name"))
			{
				$errorMessage="$mess[33] $userfile_name";
				break;
			}
			else
			{
				/* DO NOT CHANGE RETURN CHARACTER (CF EDITION ACTION)
				if(Utils::is_editable($userfile_name))
				{
					Utils::enlever_controlM("$destination/$userfile_name");
				}
				*/
				$logMessage.="$mess[34] $userfile_name $mess[35] $rep";
			}
		}
	}
	print("<html><script language=\"javascript\">\n");
	if(isSet($errorMessage)){
		print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
	}else{		
		print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext();");
	}
	print("</script></html>");
	exit;
	break;

	//---------------------------------------------------------------------------------------------------------------------------
	//	EMAIL URL
	//---------------------------------------------------------------------------------------------------------------------------
	case "email_url":
	$to      = $_POST['email_dest'];
	$subject = "URL sent by AjaXplorer";
	$message = "Hello, \n a friend of yours has sent you an URL to browse a folder in AjaXplorer : ";
	$message .= "\n\n Sender : ".$_POST["email_exp"];
	$message .= "\n The URL : ".$_POST["email_url"];
	$message .= "\n Additional Comment : ".wordwrap($_POST["email_comment"], 70);
	$headers = 'From: '.$webmaster_email. "\r\n" .
	'Reply-To: '.$webmaster_email . "\r\n" .
	'X-Mailer: PHP/' . phpversion();
	
	$res = @mail($to, $subject, $message, $headers);
	if($res)
	{
		$logMessage = $mess[111].$message;
	}
	else 
	{
		$errorMessage = $mess[112];
	}
	break;
	
	case "switch_root_dir":
	
	if(isSet($root_dir_index))
	{
		$dirList = ConfService::getRootDirsList();
		if(!isSet($dirList[$root_dir_index]))
		{
			$errorMessage = "Trying to switch to an unkown folder!";
			break;
		}
		else
		{
			ConfService::switchRootDir($root_dir_index);
			$logMessage = "Successfully Switched!";
		}
	}
	break;

	//------------------------------------
	//	XML LISTING
	//------------------------------------
	case "xml_listing" ;
	
	if(!isSet($rep) || $rep == "/") $rep = "";
	$searchMode = $fileListMode = false;
	if(isSet($mode)){
		if($mode == "search") $searchMode = true;
		else if($mode = "file_list") $fileListMode = true;
	}
	$nom_rep = FS_Storage::initName($rep);
	$result = FS_Storage::listing($nom_rep, !($searchMode || $fileListMode));
	$reps = $result[0];
	AJXP_XMLWriter::header();
	foreach ($reps as $repIndex => $repName)
	{
		$link = "content.php?id=&ordre=nom&sens=1&action=xml_listing&rep=".$rep."/".$repName;
		$link = str_replace("/", "%2F", $link);
		$link = str_replace("&", "&amp;", $link);
		$attributes = "";
		if($searchMode)
		{
			if(is_file($nom_rep."/".$repIndex)) {$attributes = "is_file=\"true\" icon=\"$repName\""; $repName = $repIndex;}
		}
		else if($fileListMode)
		{
			$currentFile = $nom_rep."/".$repIndex;			
			$atts = array();
			$atts[] = "is_file=\"".(is_file($currentFile)?"oui":"non")."\"";
			$atts[] = "is_editable=\"".Utils::is_editable($currentFile)."\"";
			$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
			if(Utils::is_image($currentFile))
			{
				list($width, $height, $type, $attr) = @getimagesize($currentFile);
				$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
				$atts[] = "image_width=\"$width\"";
				$atts[] = "image_height=\"$height\"";
			}
			$atts[] = "is_mp3=\"".Utils::is_mp3($currentFile)."\"";
			$atts[] = "mimetype=\"".Utils::mimetype($currentFile, "type")."\"";
			$atts[] = "modiftime=\"".FS_Storage::date_modif($currentFile)."\"";
			$atts[] = "filesize=\"".Utils::roundSize(filesize($currentFile))."\"";
			$atts[] = "filename=\"".$rep."/".str_replace("&", "&amp;", $repIndex)."\"";
			$atts[] = "icon=\"".(is_file($currentFile)?$repName:"folder.png")."\"";
			
			$attributes = join(" ", $atts);
			$repName = $repIndex;
		}
		else 
		{
			$attributes = "icon=\"images/foldericon.png\"  openicon=\"images/openfoldericon.png\" src=\"$link\" action=\"javascript:ajaxplorer.clickDir('$rep/".str_replace("&", "&amp;", $repName)."','$rep',CURRENT_ID)\"";		
		}
		print("<tree text=\"".str_replace("&", "&amp;", $repName)."\" $attributes>");
		print("</tree>");
	}
	if($nom_rep == ConfService::getRootDir() && ConfService::useRecycleBin() && !$fileListMode)
	{
		// ADD RECYCLE BIN TO THE LIST
		print("<tree text=\"$mess[122]\" is_recycle=\"true\" icon=\"images/recyclebin.png\" src=\"content.php?action=xml_listing&amp;rep=/".ConfService::getRecycleBinDir()."\" openIcon=\"images/recyclebin.png\" action=\"javascript:ajaxplorer.clickDir('/".ConfService::getRecycleBinDir()."','/',CURRENT_ID)\"/>");
	}
	AJXP_XMLWriter::close();
	exit(1);
	break;

	case "root_tree":
	//------------------------------------
	//	ROOT TREE
	//------------------------------------
	include($hautpage);
	$reloadPanel = false;
	if(isSet($root_dir_index))
	{
		$dirList = ConfService::getRootDirsList();
		if(!isSet($dirList[$root_dir_index]))
		{
			//$errorMessage = "Trying to switch to an unkown folder!";
			//break;
		}
		ConfService::switchRootDir($root_dir_index);
		$reloadPanel = true;
	}
   	HTMLWriter::writeSessionDataForJs();
	HTMLWriter::writeRootDirChooser(ConfService::getRootDirsList(), ConfService::getCurrentRootDirIndex());
	HTMLWriter::writeTree($reloadPanel);
	include($baspage);
	session_write_close();
	exit(0);
	break;
	

	case "display_action_bar":
	//------------------------------------
	//	ACTION BAR
	//------------------------------------
	if(isSet($_GET["loadrep"]))
	{
		$file = implode("\n", file($hautpage));
		$jsString = "<script language=\"javascript\">var external_load_rep='".$_GET["loadrep"]."';</script>";
		$file = str_replace("<body ", "$jsString\n<body onload=\"setTimeout('externalLoadRep()', 1000);\" ", $file);
		print($file);
	}
	else 
	{
		include($hautpage);
	}
	HTMLWriter::toolbar((isset($_GET["user"])?$_GET["user"]:"shared_bookmarks"));
	include($baspage);
	exit(1);
	break;
	
		
	case "display_bookmark_bar":
	//------------------------------------
	//	BOOKMARK BAR
	//------------------------------------
	header("Content-type:text/html");
	/*
	$bookMarksManager = new BookmarksManager((isset($_GET["user"])?$_GET["user"]:"shared_bookmarks"));
	if(isSet($_GET["bm_action"]) && isset($_GET["bm_path"]))
	{
		if($_GET["bm_action"] == "add_bookmark")
		{
			$bookMarksManager->addBookMark($_GET["bm_path"]);
		}
		else if($_GET["bm_action"] == "delete_bookmark")
		{
			$bookMarksManager->removeBookMark($_GET["bm_path"]);
		}
	}
	*/
	$bmUser = null;
	if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
	{
		$bmUser = AuthService::getLoggedUser();
	}
	else if(!AuthService::usersEnabled())
	{
		$bmUser = new AJXP_User("shared");
	}
	if($bmUser == null) exit(1);
	if(isSet($_GET["bm_action"]) && isset($_GET["bm_path"]))
	{
		if($_GET["bm_action"] == "add_bookmark")
		{
			$bmUser->addBookMark($_GET["bm_path"]);
		}
		else if($_GET["bm_action"] == "delete_bookmark")
		{
			$bmUser->removeBookmark($_GET["bm_path"]);
		}
	}
	if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
	{
		$bmUser->save();
		AuthService::updateUser($bmUser);
	}
	else if(!AuthService::usersEnabled())
	{
		$bmUser->save();
	}
	HTMLWriter::bookmarkBar($bmUser->getBookMarks());
	session_write_close();
	exit(1);
	break;
	
	/*
	case "upgrade_old_bookmarks_2_users":
		if(is_dir(OLD_USERS_DIR))
		{
			// READ DIR AND CREATE USERS!
			$fp = opendir(OLD_USERS_DIR);
			while ($file = readdir($fp)) {				
				$split = split("\.", $file);
				if(count($split) != 2 || $split[1] != "txt" || AuthService::userExists($split[0]))
				{
					continue;
				}				
				$newUser = new AJXP_User($split[0]);
				$bmManager = new BookmarksManager($newUser->getId());
				$allBMarks = $bmManager->getBookMarks(false);
				print_r($allBMarks);
				foreach ($allBMarks as $repId => $bmarks)
				{					
					foreach ($bmarks as $bmark) $newUser->addBookmark($bmark, $repId);
				}
				$newUser->save();
				print("<div>Successfully Created user <b>".$newUser->getId()."</b> from old bookmarks.</div>");
			}
			//rmdir(OLD_USERS_DIR);
		}
	exit(1);
	break;
	*/
	
	case "save_user_pref":
		$userObject = AuthService::getLoggedUser();
		if($userObject == null) exit(1);
		$i = 0;
		while(isSet($_GET["pref_name_".$i]) && isSet($_GET["pref_value_".$i]))
		{
			$prefName = $_GET["pref_name_".$i];
			$prefValue = $_GET["pref_value_".$i];
			if($prefName != "password")
			{
				$userObject->setPref($prefName, $prefValue);
				$userObject->save();
				AuthService::updateUser($userObject);
				setcookie("AJXP_$prefName", $prefValue);
			}
			else
			{
				AuthService::updatePassword($userObject->getId(), $prefValue);
			}
			$i++;
		}
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Done($i)", null);
		AJXP_XMLWriter::close();
		exit(1);
	break;
	
	case "display_doc":
	{
		echo HTMLWriter::getDocFile($_GET["doc_file"]);
		exit();
	}
	
	
		
	//------------------------------------
	//	DEFAUT
	//------------------------------------

	default;
	break;
}



AJXP_XMLWriter::header();

if(isset($logMessage) || isset($errorMessage))
{
	AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null));
}

if(isset($requireAuth))
{
	AJXP_XMLWriter::requireAuth();
}

if(isset($reload_current_node) && $reload_current_node == "true")
{
	AJXP_XMLWriter::reloadCurrentNode();
}

if(isset($reload_dest_node) && $reload_dest_node != "")
{
	AJXP_XMLWriter::reloadNode($reload_dest_node);
}

if(isset($reload_file_list))
{
	AJXP_XMLWriter::reloadFileList($reload_file_list);
}

AJXP_XMLWriter::close();



session_write_close();
?>
