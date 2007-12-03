<?php

class Utils
{
	function assemble_tableaux($t1,$t2)
	{
		$liste = array();
		$tab1=$t1; $tab2=$t2;
		if(is_array($tab1)) {while (list($cle,$val) = each($tab1)) {$liste[$cle]=$val;}}
		if(is_array($tab2)) {while (list($cle,$val) = each($tab2)) {$liste[$cle]=$val;}}
		return $liste;
	}
	
	
	function txt_vers_html($chaine)
	{
		$chaine=str_replace("&#8216;","'",$chaine);
		$chaine=str_replace("&#339;","oe",$chaine);
		$chaine=str_replace("&#8217;","'",$chaine);
		$chaine=str_replace("&#8230;","...",$chaine);
		$chaine=str_replace("&","&amp;",$chaine);
		$chaine=str_replace("<","&lt;",$chaine);
		$chaine=str_replace(">","&gt;",$chaine);
		$chaine=str_replace("\"","&quot;",$chaine);
		$chaine=str_replace("à","&agrave;",$chaine);
		$chaine=str_replace("é","&eacute;",$chaine);
		$chaine=str_replace("è","&egrave;",$chaine);
		$chaine=str_replace("ù","&ugrave;",$chaine);
		$chaine=str_replace("â","&acirc;",$chaine);
		$chaine=str_replace("ê","&ecirc;",$chaine);
		$chaine=str_replace("î","&icirc;",$chaine);
		$chaine=str_replace("ô","&ocirc;",$chaine);
		$chaine=str_replace("û","&ucirc;",$chaine);
		$chaine=str_replace("ä","&auml;",$chaine);
		$chaine=str_replace("ë","&euml;",$chaine);
		$chaine=str_replace("ï","&iuml;",$chaine);
		$chaine=str_replace("ö","&ouml;",$chaine);
		$chaine=str_replace("ü","&uuml;",$chaine);
		return $chaine;
	}
	
	
function enlever_controlM($fichier)
{
	$fic=file($fichier);
	$fp=fopen($fichier,"w");
	while (list ($cle, $val) = each ($fic))
	{
		$val=str_replace(CHR(10),"",$val);
		$val=str_replace(CHR(13),"",$val);
		fputs($fp,"$val\n");
	}
	fclose($fp);
}

function tipsandtricks()
{
	$tips = array();
	$tips[] = "DoubleClick in the list to directly download a file or to open a folder.";
	$tips[] = "When the 'Edit' button is enabled (on text files), you can directly edit the selected file online.";
	$tips[] = "Type directly a folder URL in the location bar then hit 'ENTER' to go to a given folder.";
	$tips[] = "Use MAJ+Click and CTRL+Click to perform multiple selections in the list.";
	$tips[] = "Use the Bookmark button to save your frequently accessed locations in the bookmark bar.";
	$tips[] = "Use the TAB button to navigate through the main panels (tree, list, location bar).";
	$tips[] = "Use the 'u' key to go to the parent directory.";
	$tips[] = "Use the 'h' key to refresh current listing.";
	$tips[] = "Use the 'b' key to bookmark current location to your bookmark bar.";
	$tips[] = "Use the 'l' key to open Upload Form.";
	$tips[] = "Use the 'd' key to create a new directory in this folder.";
	$tips[] = "Use the 'f' key to create a new file in this folder.";
	$tips[] = "Use the 'r' key to rename a file.";
	$tips[] = "Use the 'c' key to copy one or more file or folders to a different folder.";
	$tips[] = "Use the 'm' key to move one or more file or folders to a different folder.";
	$tips[] = "Use the 's' key to delete one or more file or folders.";
	$tips[] = "Use the 'e' key to edit a file or view an image.";
	$tips[] = "Use the 'o' key to download a file to your hard drive.";
	return $tips[array_rand($tips, 1)];
}


function traite_nom_fichier($nom)
{
	$max_caracteres = ConfService::getConf("MAX_CHAR");
	$nom=stripslashes($nom);
	$nom=str_replace("'","",$nom);
	$nom=str_replace("\"","",$nom);
	$nom=str_replace("\"","",$nom);
	$nom=str_replace("&","",$nom);
	$nom=str_replace(",","",$nom);
	$nom=str_replace(";","",$nom);
	$nom=str_replace("/","",$nom);
	$nom=str_replace("\\","",$nom);
	$nom=str_replace("`","",$nom);
	$nom=str_replace("<","",$nom);
	$nom=str_replace(">","",$nom);
	$nom=str_replace(" ","_",$nom);
	$nom=str_replace(":","",$nom);
	$nom=str_replace("*","",$nom);
	$nom=str_replace("|","",$nom);
	$nom=str_replace("?","",$nom);
	$nom=str_replace("é","",$nom);
	$nom=str_replace("è","",$nom);
	$nom=str_replace("ç","",$nom);
	$nom=str_replace("@","",$nom);
	$nom=str_replace("â","",$nom);
	$nom=str_replace("ê","",$nom);
	$nom=str_replace("î","",$nom);
	$nom=str_replace("ô","",$nom);
	$nom=str_replace("û","",$nom);
	$nom=str_replace("ù","",$nom);
	$nom=str_replace("à","",$nom);
	$nom=str_replace("!","",$nom);
	$nom=str_replace("§","",$nom);
	$nom=str_replace("+","",$nom);
	$nom=str_replace("^","",$nom);
	$nom=str_replace("(","",$nom);
	$nom=str_replace(")","",$nom);
	$nom=str_replace("#","",$nom);
	$nom=str_replace("=","",$nom);
	$nom=str_replace("$","",$nom);
	$nom=str_replace("%","",$nom);
	$nom = substr ($nom,0,$max_caracteres);
	return $nom;
}


function mimetype($fichier,$quoi)
{
	$mess = ConfService::getMessages();
	if(!eregi("MSIE",$_SERVER['HTTP_USER_AGENT'])) {$client="netscape.gif";} else {$client="html.gif";}
	/*
	if(is_dir($fichier)){$image="dossier.gif";$nom_type=$mess[8];}
	else if(eregi("\.mid$",$fichier)){$image="mid.gif";$nom_type=$mess[9];}
	else if(eregi("\.txt$",$fichier)){$image="txt.gif";$nom_type=$mess[10];}
	else if(eregi("\.sql$",$fichier)){$image="txt.gif";$nom_type=$mess[10];}
	else if(eregi("\.js$",$fichier)){$image="js.gif";$nom_type=$mess[11];}
	else if(eregi("\.gif$",$fichier)){$image="gif.gif";$nom_type=$mess[12];}
	else if(eregi("\.jpg$",$fichier)){$image="jpg.gif";$nom_type=$mess[13];}
	else if(eregi("\.html$",$fichier)){$image=$client;$nom_type=$mess[14];}
	else if(eregi("\.htm$",$fichier)){$image=$client;$nom_type=$mess[15];}
	else if(eregi("\.rar$",$fichier)){$image="rar.gif";$nom_type=$mess[60];}
	else if(eregi("\.gz$",$fichier)){$image="zip.gif";$nom_type=$mess[61];}
	else if(eregi("\.tgz$",$fichier)){$image="zip.gif";$nom_type=$mess[61];}
	else if(eregi("\.z$",$fichier)){$image="zip.gif";$nom_type=$mess[61];}
	else if(eregi("\.ra$",$fichier)){$image="ram.gif";$nom_type=$mess[16];}
	else if(eregi("\.ram$",$fichier)){$image="ram.gif";$nom_type=$mess[17];}
	else if(eregi("\.rm$",$fichier)){$image="ram.gif";$nom_type=$mess[17];}
	else if(eregi("\.pl$",$fichier)){$image="pl.gif";$nom_type=$mess[18];}
	else if(eregi("\.zip$",$fichier)){$image="zip.gif";$nom_type=$mess[19];}
	else if(eregi("\.wav$",$fichier)){$image="wav.gif";$nom_type=$mess[20];}
	else if(eregi("\.php$",$fichier)){$image="php.gif";$nom_type=$mess[21];}
	else if(eregi("\.php3$",$fichier)){$image="php.gif";$nom_type=$mess[22];}
	else if(eregi("\.phtml$",$fichier)){$image="php.gif";$nom_type=$mess[22];}
	else if(eregi("\.exe$",$fichier)){$image="exe.gif";$nom_type=$mess[50];}
	else if(eregi("\.bmp$",$fichier)){$image="bmp.gif";$nom_type=$mess[56];}
	else if(eregi("\.png$",$fichier)){$image="gif.gif";$nom_type=$mess[57];}
	else if(eregi("\.css$",$fichier)){$image="css.gif";$nom_type=$mess[58];}
	else if(eregi("\.mp3$",$fichier)){$image="mp3.gif";$nom_type=$mess[59];}
	else if(eregi("\.xls$",$fichier)){$image="xls.gif";$nom_type=$mess[64];}
	else if(eregi("\.doc$",$fichier)){$image="doc.gif";$nom_type=$mess[65];}
	else if(eregi("\.pdf$",$fichier)){$image="pdf.gif";$nom_type=$mess[79];}
	else if(eregi("\.mov$",$fichier)){$image="mov.gif";$nom_type=$mess[80];}
	else if(eregi("\.avi$",$fichier)){$image="avi.gif";$nom_type=$mess[81];}
	else if(eregi("\.mpg$",$fichier)){$image="mpg.gif";$nom_type=$mess[82];}
	else if(eregi("\.mpeg$",$fichier)){$image="mpeg.gif";$nom_type=$mess[83];}
	else if(eregi("\.swf$",$fichier)){$image="flash.gif";$nom_type=$mess[91];}
	else {$image="defaut.gif";$nom_type=$mess[23];}
	*/
	if(!eregi("MSIE",$_SERVER['HTTP_USER_AGENT'])) {$client="html.png";} else {$client="html.png";}
	if(is_dir($fichier)){$image="folder.png";$nom_type=$mess[8];}
	else if(eregi("\.mid$",$fichier)){$image="midi.png";$nom_type=$mess[9];}
	else if(eregi("\.txt$",$fichier)){$image="txt2.png";$nom_type=$mess[10];}
	else if(eregi("\.sql$",$fichier)){$image="txt2.png";$nom_type=$mess[10];}
	else if(eregi("\.js$",$fichier)){$image="javascript.png";$nom_type=$mess[11];}
	else if(eregi("\.gif$",$fichier)){$image="image.png";$nom_type=$mess[12];}
	else if(eregi("\.jpg$",$fichier)){$image="image.png";$nom_type=$mess[13];}
	else if(eregi("\.html$",$fichier)){$image=$client;$nom_type=$mess[14];}
	else if(eregi("\.htm$",$fichier)){$image=$client;$nom_type=$mess[15];}
	else if(eregi("\.rar$",$fichier)){$image="archive.png";$nom_type=$mess[60];}
	else if(eregi("\.gz$",$fichier)){$image="archive.png";$nom_type=$mess[61];}
	else if(eregi("\.tgz$",$fichier)){$image="archive.png";$nom_type=$mess[61];}
	else if(eregi("\.z$",$fichier)){$image="archive.png";$nom_type=$mess[61];}
	else if(eregi("\.ra$",$fichier)){$image="video.png";$nom_type=$mess[16];}
	else if(eregi("\.ram$",$fichier)){$image="video.png";$nom_type=$mess[17];}
	else if(eregi("\.rm$",$fichier)){$image="video.png";$nom_type=$mess[17];}
	else if(eregi("\.pl$",$fichier)){$image="source_pl.png";$nom_type=$mess[18];}
	else if(eregi("\.zip$",$fichier)){$image="archive.png";$nom_type=$mess[19];}
	else if(eregi("\.wav$",$fichier)){$image="sound.png";$nom_type=$mess[20];}
	else if(eregi("\.php$",$fichier)){$image="php.png";$nom_type=$mess[21];}
	else if(eregi("\.php3$",$fichier)){$image="php.png";$nom_type=$mess[22];}
	else if(eregi("\.phtml$",$fichier)){$image="php.png";$nom_type=$mess[22];}
	else if(eregi("\.exe$",$fichier)){$image="exe.png";$nom_type=$mess[50];}
	else if(eregi("\.bmp$",$fichier)){$image="image.png";$nom_type=$mess[56];}
	else if(eregi("\.png$",$fichier)){$image="image.png";$nom_type=$mess[57];}
	else if(eregi("\.css$",$fichier)){$image="css.png";$nom_type=$mess[58];}
	else if(eregi("\.mp3$",$fichier)){$image="sound.png";$nom_type=$mess[59];}
	else if(eregi("\.xls$",$fichier)){$image="spreadsheet.png";$nom_type=$mess[64];}
	else if(eregi("\.doc$",$fichier)){$image="document.png";$nom_type=$mess[65];}
	else if(eregi("\.pdf$",$fichier)){$image="pdf.png";$nom_type=$mess[79];}
	else if(eregi("\.mov$",$fichier)){$image="video.png";$nom_type=$mess[80];}
	else if(eregi("\.avi$",$fichier)){$image="video.png";$nom_type=$mess[81];}
	else if(eregi("\.mpg$",$fichier)){$image="video.png";$nom_type=$mess[82];}
	else if(eregi("\.mpeg$",$fichier)){$image="video.png";$nom_type=$mess[83];}
	else if(eregi("\.swf$",$fichier)){$image="flash.png";$nom_type=$mess[91];}
	else {$image="mime_empty.png";$nom_type=$mess[23];}
	if($quoi=="image"){return $image;} else {return $nom_type;}
}

function is_editable($fichier)
{
	$retour=0;
	if(eregi("\.txt$|\.sql$|\.php$|\.php3$|\.phtml$|\.htm$|\.html$|\.cgi$|\.pl$|\.js$|\.css$|\.inc$",$fichier)) {$retour=1;}
	return $retour;
}

function editWithCodePress($fichier)
{
	if(eregi("\.php$|\.php3$|.php5$|\phtml$", $fichier)) return "php";
	elseif (eregi("\.js$", $fichier)) return "javascript";
	elseif (eregi("\.java$", $fichier)) return "java";
	elseif (eregi("\.pl$", $fichier)) return "perl";
	elseif (eregi("\.sql$", $fichier)) return "sql";
	elseif (eregi("\.htm$|\.html$", $fichier)) return "html";
	elseif (eregi("\.css$", $fichier)) return "css";
	else return "";
}

function is_image($fichier)
{
	$retour=0;
	if(eregi("\.png$|\.bmp$|\.jpg$|\.jpeg$|\.gif$",$fichier)) {$retour=1;}
	return $retour;
}

function is_mp3($fichier)
{
	$retour=0;
	if(eregi("\.mp3$",$fichier)) {$retour=1;}
	return $retour;
}

function getImageMimeType($fichier)
{
	if(eregi("\.jpg$|\.jpeg$",$fichier)){return "image/jpeg";}
	else if(eregi("\.png$",$fichier)){return "image/png";}	
	else if(eregi("\.bmp$",$fichier)){return "image/bmp";}	
	else if(eregi("\.gif$",$fichier)){return "image/gif";}	
}

function roundSize($filesize)
{
	$size_unit = ConfService::getConf("SIZE_UNIT");
	if ($filesize >= 1073741824) {$filesize = round($filesize / 1073741824 * 100) / 100 . " G".$size_unit;}
	elseif ($filesize >= 1048576) {$filesize = round($filesize / 1048576 * 100) / 100 . " M".$size_unit;}
	elseif ($filesize >= 1024) {$filesize = round($filesize / 1024 * 100) / 100 . " K".$size_unit;}
	else {$filesize = $filesize . " ".$size_unit;}
	if($filesize==0) {$filesize="-";}
	return $filesize;
}


function show_hidden_files($fichier)
{
	$showhidden = ConfService::getConf("SHOW_HIDDEN");
	$retour=1;
	if(substr($fichier,0,1)=="." && $showhidden==0) {$retour=0;}
	return $retour;
}





}

?>