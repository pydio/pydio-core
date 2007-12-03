function InfoPanel(htmlElement)
{
	this.htmlElement = $(htmlElement);
	this.setContent('<br><br><center><i>'+MessageHash[132]+'</i></center>');	
}

InfoPanel.prototype.update = function()
{	
	var filesList = ajaxplorer.getFilesList();
	var userSelection = filesList.getUserSelection();
	if(userSelection.isEmpty())
	{
		/*
		if(filesList.hasFileType('image') || filesList.hasFileType('mp3'))
		{
			content = '';
			if(filesList.hasFileType('image')){
				content += 'images';
			}
			if(filesList.hasFileType('mp3')){
				content += 'mp3';
			}
			this.setContent('<br><center><i>No files selected<br>'+content+'</i></center>');
		}
		else
		{
		*/
			this.setContent('<br><br><center><i>No Selection</i></center>');
		//}
		return;
	}
	if(!userSelection.isUnique())
	{
		this.setContent(userSelection.getFileNames().length + ' files selected.');
		return;
	}
	if(userSelection.isImage())
	{
		this.displayImageInfo(userSelection.getUniqueItem());
		return;
	}
	var uniqItem = userSelection.getUniqueItem();
	if(uniqItem.getAttribute('is_mp3') == '1')
	{
		this.displayMP3Info(uniqItem);
		return;
	}
	this.displayFileInfo(uniqItem);
}

InfoPanel.prototype.setContent = function(sHtml)
{
	this.htmlElement.innerHTML = sHtml;
}

InfoPanel.prototype.displayImageInfo = function(imgData)
{
	var baseUrl = "content.php?action=image_proxy&get_thumb=true&fic=";
	var imgUrl = baseUrl + imgData.getAttribute("filename");
	var fileName = getBaseName(imgData.getAttribute("filename"));
	var imageType = imgData.getAttribute("image_type");
	var imageDimension = imgData.getAttribute("image_width") + 'px X ' + imgData.getAttribute("image_height")+'px';
	var fileSize = imgData.getAttribute("filesize");
	
	var width = imgData.getAttribute("image_width");
	var height = imgData.getAttribute("image_height");
	
	var newHeight = 150;
	if(height < newHeight) newHeight = height;
	var newWidth = newHeight*width/height;
	var dimAttr = 'height="'+newHeight+'"';
	if(newWidth > $('info_panel').getWidth() - 16) dimAttr = 'width="100%"';
	
	var tString = '<div style="padding:10px;"><center style="border:1px solid #79f;"><img src="#{url}" #{dimattr}></center>';
	tString += '<br><b>'+MessageHash[133]+'</b> : #{filename}';
	tString += '<br><b>'+MessageHash[134]+'</b> : #{imagetype}';
	tString += '<br><b>'+MessageHash[135]+'</b> : #{dimension}';
	tString += '<br><b>'+MessageHash[127]+'</b> : #{filesize}';
	tString += '<div style="text-align:right;padding-top:5px;"><a href="#" onclick="ajaxplorer.actionBar.fireAction(\'view\'); return false;">'+MessageHash[136]+'</a> | <a href="#" onclick="ajaxplorer.actionBar.fireAction(\'download\'); return false;">'+MessageHash[88]+'</a></div>';
	tString += '</div>';
	var template = new Template(tString);
	this.setContent(template.evaluate({url:imgUrl, dimattr:dimAttr, filename:fileName, imagetype:imageType, dimension:imageDimension,filesize:fileSize}));
}

InfoPanel.prototype.displayFileInfo = function(fileData)
{
	var fileName = getBaseName(fileData.getAttribute("filename"));
	var fileType = fileData.getAttribute("mimetype");
	var fileSize = fileData.getAttribute("filesize");
	var modifTime = fileData.getAttribute("modiftime");
	var is_file = (fileData.getAttribute("is_file")=='oui'?true:false);
	var is_editable = (fileData.getAttribute("is_editable")=='1'?true:false);
	var icon = fileData.getAttribute("icon");
	
	var tString = '<div style="padding:10px;">';
	if(!is_file){
		tString += '<div class="folderImage"><img src="images/crystal/mimes/64/folder.png" height="64" width="64"></div>';
	}
	else{
		tString += '<div class="folderImage"><img src="images/crystal/mimes/64/'+icon+'" height="64" width="64"></div>';
	}
	
	tString += '<b>'+MessageHash[133]+'</b> : '+fileName;
	if(is_file){
		tString += '<br><b>'+MessageHash[127]+'</b> : '+fileSize;
		tString += '<br><b>'+MessageHash[134]+'</b> : '+fileType;
	}
	tString += '<br><b>'+MessageHash[138]+'</b> : '+ modifTime;
	if(is_file){
		tString += '<div style="text-align:right;padding-top:5px;">';
		if(is_editable && (ajaxplorer.user == null || ajaxplorer.user.canWrite())){
			tString += '<a href="#" onclick="ajaxplorer.actionBar.fireAction(\'edit\'); return false;">'+MessageHash[139]+'</a> | ';
		}
		tString += '<a href="#" onclick="ajaxplorer.actionBar.fireAction(\'download\'); return false;">'+MessageHash[88]+'</a>';
		tString += '</div>';
	}
	tString += '</div>';
	this.setContent(tString);
}

InfoPanel.prototype.displayMP3Info = function(fileData)
{
	var fileName = getBaseName(fileData.getAttribute("filename"));
	var fileType = fileData.getAttribute("mimetype");
	var fileSize = fileData.getAttribute("filesize");
	var modifTime = fileData.getAttribute("modiftime");
	
	var template = new Template('<object type="application/x-shockwave-flash" data="include/flash/dewplayer-mini.swf?mp3=#{mp3_url}&amp;bgcolor=FFFFFF&amp;showtime=1" width="150" height="20"><param name="wmode" value="transparent"><param name="movie" value="include/flash/dewplayer-mini.swf?mp3=#{mp3_url}&amp;bgcolor=FFFFFF&amp;showtime=1" /></object>');
	
	var tString = '<div style="padding:10px;">';
	tString += '<div id="mp3_container" style="border:1px solid #79f; text-align:center; padding:5px; width:160px;">'+template.evaluate({mp3_url:'content.php?action=mp3_proxy%26fic=' + fileData.getAttribute("filename")})+'</div>';
	tString += '<br><b>'+MessageHash[133]+'</b> : '+fileName;
	tString += '<br><b>'+MessageHash[134]+'</b> : '+fileType;
	tString += '<br><b>'+MessageHash[127]+'</b> : '+fileSize;
	tString += '<br><b>'+MessageHash[138]+'</b> : '+ modifTime;
	tString += '<div style="text-align:right;padding-top:5px;">';
	tString += '<a href="#" onclick="ajaxplorer.actionBar.fireAction(\'download\'); return false;">'+MessageHash[88]+'</a>';
	tString += '|<a href="#" id="folder2playlist">'+MessageHash[140]+'</a>';
	tString += '</div>';
	tString += '</div>';
	this.setContent(tString);	
	var oThis = this;
	$('folder2playlist').onclick = function(){oThis.folderAsPlaylist();};
}

InfoPanel.prototype.folderAsPlaylist = function()
{
	var template = new Template('<head><title>AjaXplorer MP3 Player</title></head><body style="margin:0px; padding:10px;"><div style=\"font-family:Trebuchet MS; color:#79f; font-size:15px; font-weight:bold;\">AjaXplorer Player</div><div style="font-family:Trebuchet MS; color:#666; font-size:10px; padding-bottom: 10px;">'+MessageHash[141]+': #{current_folder}</div><object type="application/x-shockwave-flash" data="include/flash/dewplayer-multi.swf?mp3=#{mp3_url}&amp;bgcolor=FFFFFF&amp;showtime=1&amp;autoplay=1" width="240" height="20"><param name="wmode" value="transparent"><param name="movie" value="include/flash/dewplayer-multi.swf?mp3=#{mp3_url}&amp;bgcolor=FFFFFF&amp;showtime=1&amp;autoplay=1" /></object></body>');
	
	var itCopy = new Array();
	$A(ajaxplorer.getFilesList().getItems()).each(function(rowItem){
		if(rowItem.getAttribute('is_mp3')=='1') itCopy.push(rowItem.getAttribute('filename'));
	});	
	var mp3Items = itCopy.reverse();
	var mp3_url = '';
	mp3Items.each(function(url){
		mp3_url += 'content.php?action=mp3_proxy%26fic='+url;
		if(url != mp3Items.last()) mp3_url += '|';
	});
//	alert(mp3_url);
	newWin = window.open('', 'mp3_multi_player', 'width=260,height=30,directories=no,location=no,menubar=no,resizable=no,scrollbars=no,status=no,toolbar=no');
	newWin.document.write(template.evaluate({mp3_url:mp3_url, current_folder:ajaxplorer.getFilesList().getCurrentRep()}));
	newWin.document.close();
}

InfoPanel.prototype.displayFlashPlayer = function(fileName)
{
	var baseUrl = 'content.php?action=mp3_proxy%26fic=' + fileName;
    var FO = { movie:"include/flash/dewplayer-mini.swf?mp3="+baseUrl, width:"150", height:"20"};
    UFO.create(FO, 'mp3_container');
}