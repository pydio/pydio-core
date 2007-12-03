function FilesList(oElement, bSelectMultiple, oSortTypes, sCurrentRep, oAjaxplorer, sDefaultDisplay)
{
	if(sDefaultDisplay && sDefaultDisplay != '') this._displayMode = sDefaultDisplay;
	else this._displayMode = "list";
	oAjaxplorer.actionBar.updateDisplayButton(this._displayMode);
	this._thumbSize = 64;
	this._crtImageIndex = 0;

	this._bSelection = false;
	this._bUnique = false;
	this._bFile = false;
	this._bDir = false;
	this._bEditable = false;
	this._oSortTypes = oSortTypes;
	this._ajaxplorer = oAjaxplorer;
	this._pendingFile = null;
	this._currentRep = sCurrentRep;	

	this.initGUI();
		
	var oThis = this;
	$('content_pane').ownerDocument.onkeyup = function(e){
		if (e == null) e = $('content_pane').ownerDocument.parentWindow.event;
		oThis.keyup(e);
	};
	
	Event.observe(document, "keydown", function(e){
	//oElement.ownerDocument.onkeypress = function(e){
		if (e == null) e = $('content_pane').ownerDocument.parentWindow.event;
		return oThis.keydown(e);
	});
	
	//window.onfocus = function(){oThis.selectFirst()};
	//window.onblur = function(){oThis.blur()};
	
	if(this._currentRep != null)
	{
		this.loadXmlList(this._currentRep);
	}
	
}

FilesList.prototype = new SelectableElements;

FilesList.prototype.initGUI = function()
{
	if(this._displayMode == "list")
	{
		var buffer = '<TABLE width="100%" cellspacing="0"  id="selectable_div" class="sort-table">';
		buffer = buffer + '<col\><col\><col\><col\>';
		buffer = buffer + '<thead><tr>';
		buffer = buffer + '<td>'+MessageHash[1]+'</td><td>'+MessageHash[2]+'</td><td>'+MessageHash[3]+'</td><td>'+MessageHash[4]+'</td>';
		buffer = buffer + '</tr></thead><tbody></tbody></table>';
		$('content_pane').innerHTML  = buffer;
		oElement = $('selectable_div');
		
		SelectableElements.call(this, oElement, true); // "TRUE" ENABLES MULTIPLE SELECTION
		this._sortableTable = new SortableTable(oElement, this._oSortTypes);
	}
	else if(this._displayMode == "thumb")
	{
		var buffer = '<TABLE width="100%" cellspacing="0" class="sort-table">';
		buffer = buffer + '<thead><tr>';
		buffer = buffer + '<td style="border-right:0px;background-image:url(\'images/header_bg_plain\');" >'+MessageHash[126]+'</td>';
		buffer = buffer + '<td align="right" id="last_header"><div class="slider" id="slider-1"><input class="slider-input" id="slider-input-1" name="slider-input-1"/></div></td>';
		buffer = buffer + '</tr></thead><tbody><tr><td colspan="2" id="selectable_div"></td></tr></tbody></table>';
		$('content_pane').innerHTML  = buffer;
		this.slider = new Slider($("slider-1"), $("slider-input-1"));		
		this.slider.setMaximum(200);
		this.slider.setMinimum(30);		
		this.slider.recalculate();
		this.slider.setValue(this._thumbSize);
		var oThis = this;
		this.slider.onchange = function()
		{
			oThis._thumbSize = oThis.slider.getValue();
			oThis.resizeThumbnails();
		}
		

		oElement = $('selectable_div');
		
		SelectableElements.call(this, oElement, true); // "TRUE" ENABLES MULTIPLE SELECTION
	}
	//jQuery("#last_header").corner("round tr 5px");
	//jQuery("#last_header").css("border-bottom", "1px solid #aaa");
}

FilesList.prototype.switchDisplayMode = function(mode)
{
	if(mode)
	{
		this._displayMode = mode;
	}
	else
	{
		if(this._displayMode == "thumb") this._displayMode = "list";
		else this._displayMode = "thumb";
	}
	this.initGUI();
	this.reload();
	this.fireChange();
	ajaxplorer.actionBar.updateDisplayButton(this._displayMode);
	return this._displayMode;
}

FilesList.prototype.initRows = function()
{
	// Disable text select on elements
	var allItems = this.getItems();
	for(var i=0; i<allItems.length;i++)
	{
		this.disableTextSelection(allItems[i]);
	}
	if(this._displayMode == "thumb")
	{
		this.resizeThumbnails();
		var oThis = this;
		window.setTimeout(function(){oThis.loadNextImage();},10);		
	}
}

FilesList.prototype.loadNextImage = function()
{
	if(this.imagesHash && this.imagesHash.size())
	{
		var oThis = this;
		if(oThis.loading) return;
		var oImageToLoad = oThis.imagesHash.remove(oThis.imagesHash.keys()[0]);		
		var image = new Image();
		image.src = "content.php?action=image_proxy&get_thumb=true&fic="+oImageToLoad.filename;
		image.onload = function(){
			var img = $(oImageToLoad.index);
			if(img == null) return;
			img.src = "content.php?action=image_proxy&get_thumb=true&fic="+oImageToLoad.filename;
			img.height = oImageToLoad.height;
			img.width = oImageToLoad.width;
			img.setStyle({marginTop: oImageToLoad.marginTop+'px',marginBottom: oImageToLoad.marginBottom+'px'});
			img.setAttribute("is_loaded", "true");
			oThis.resizeThumbnails(oImageToLoad.rowObject);
			oThis.loadNextImage();
		};	
	}	
}

FilesList.prototype.reload = function(pendingFileToSelect)
{
	if(this._currentRep != null) this.loadXmlList(this._currentRep, pendingFileToSelect);
}

FilesList.prototype.loadXmlList = function(repToLoad, pendingFileToSelect)
{	
	// TODO : THIS SHOULD BE SET ONCOMPLETE!
	this._currentRep = repToLoad;	
	var connexion = new Connexion();
	connexion.addParameter('mode', 'file_list');
	connexion.addParameter('rep', repToLoad);
	var oThis = this;
	this._pendingFile = pendingFileToSelect;
	this.setOnLoad();
	connexion.onComplete = function (transport){
		oThis.parseXmlAndLoad(transport.responseXML);
	}	
	connexion.sendAsync();
}

FilesList.prototype.parseXmlAndLoad = function(oXmlDoc)
{	
	if( oXmlDoc == null || oXmlDoc.documentElement == null) 
	{
		this.removeOnLoad();
		return;
	}
	this.loading = false;
	this.imagesHash = new Hash();
	var root = oXmlDoc.documentElement;
	// loop through all tree children
	var cs = root.childNodes;
	var l = cs.length;
	// FIRST PASS FOR ERRORS CHECK
	for (var i = 0; i < l; i++) 
	{
		if(cs[i].tagName == "error")
		{
			this.removeOnLoad();
			alert(cs[i].firstChild.nodeValue);
			return;
		}
		else if(cs[i].tagName == "require_auth")
		{
			if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
			this.removeOnLoad();
			ajaxplorer.actionBar.fireAction('login');
		}
	}
	var items = this.getItems();
	for(var i=0; i<items.length; i++)
	{
		this.setItemSelected(items[i], false);
	}	
	this.removeCurrentLines();
	// NOW PARSE LINES
	for (var i = 0; i < l; i++) 
	{
		if (cs[i].tagName == "tree") 
		{
			if(this._displayMode == "list") 
			{
				this.xmlNodeToTableRow(cs[i]);
			}
			else if(this._displayMode == "thumb")
			{
				this.xmlNodeToDiv(cs[i]);
			}
		}
	}	

	this.initRows();
	this.removeOnLoad();
	if(this._displayMode == "list")
	{
		this._sortableTable.sortColumn = -1;
		this._sortableTable.updateHeaderArrows();
	}
	if(this._pendingFile)
	{
		this.selectFile(this._pendingFile);
		this.hasFocus = true;
		this._pendingFile = null;
	}	
	if(this.hasFocus){
		this._ajaxplorer.foldersTree.blur();
		this._ajaxplorer.sEngine.blur();
		this._ajaxplorer.actionBar.blur();
		this.focus();
	}
	else{
		this._ajaxplorer.sEngine.blur();
		this._ajaxplorer.actionBar.blur();
		this._ajaxplorer.foldersTree.focus();
	}
	if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
}

FilesList.prototype.xmlNodeToTableRow = function(xmlNode)
{
	var newRow = document.createElement("tr");
	var tBody = this._htmlElement.getElementsBySelector("tbody")[0];
	for(i=0;i<xmlNode.attributes.length;i++)
	{
		newRow.setAttribute(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
	}

	["text", "filesize", "mimetype", "modiftime"].each(function(s){
		var tableCell = document.createElement("td");
		if(s == "text")
		{
			innerSpan = document.createElement("span");
			innerSpan.setAttribute("style", "cursor:default;");			
			// Add icon
			var imgString = "<img src=\"images/crystal/mimes/16/"+xmlNode.getAttribute('icon')+"\" ";
			imgString =  imgString + "width=\"16\" height=\"16\" hspace=\"1\" vspace=\"2\" align=\"ABSMIDDLE\" border=\"0\"> " + xmlNode.getAttribute(s);
			innerSpan.innerHTML = imgString;
			tableCell.appendChild(innerSpan);
		}
		else
		{
			tableCell.innerHTML = xmlNode.getAttribute(s);
		}
		newRow.appendChild(tableCell);
	});	
	
	tBody.appendChild(newRow);
}


FilesList.prototype.xmlNodeToDiv = function(xmlNode)
{
	var newRow = document.createElement("div");	
	$(newRow).addClassName("thumbnail_selectable_cell");
	for(i=0;i<xmlNode.attributes.length;i++)
	{
		newRow.setAttribute(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
	}

	var innerSpan = document.createElement("span");
	innerSpan.setAttribute("style", "cursor:default;");	
	if(xmlNode.getAttribute("is_image") == "1")
	{
		this._crtImageIndex ++;
		var imgIndex = this._crtImageIndex;
		var textNode = xmlNode.getAttribute("text");
		var imgString = "<img id=\"ajxp_image_"+imgIndex+"\" src=\"images/crystal/image.png\" width=\"64\" height=\"64\" style=\"margin:5px;\" align=\"ABSMIDDLE\" border=\"0\" is_loaded=\"false\"/><div class=\"thumbLabel\" title=\""+textNode+"\">"+textNode+"</div>";
		var width = xmlNode.getAttribute("image_width");
		var height = xmlNode.getAttribute("image_height");		
		var sizeString, marginTop, marginHeight, newHeight, newWidth;
		if(width >= height){
			sizeString = "width=\"64\"";
			newWidth = 64;
			newHeight = parseInt(height / width * 64);
			marginTop = parseInt((64 - newHeight)/2) + 5;
			marginBottom = 64+10-newHeight-marginTop-1;
			sizeString += " style='margin: 5px;margin-top:"+marginTop+"px;margin-bottom:"+marginBottom+"px;'";
		}
		else
		{
			newHeight = 64;
			newWidth = parseInt(width / height * 64);
			marginTop = 5;
			marginBottom = 5;
			sizeString = "height=\"64\" style=\"margin: 5px;\"";
		}
		var crtIndex = this._crtImageIndex;
		var oThis = this;
		var image = new Image();
		innerSpan.innerHTML = imgString;		
		newRow.appendChild(innerSpan);
		this._htmlElement.appendChild(newRow);
		
		var fileName = xmlNode.getAttribute('filename');
		/*
		imagesHash["ajxp_image_"+crtIndex] = {filename:fileName, 
			rowObject:$(newRow), 
			height: newHeight, 
			width: newWidth, 
			marginTop: marginTop, 
			marginBottom: marginBottom};
			*/
		var oImageToLoad = {
			index:"ajxp_image_"+crtIndex,
			filename:fileName, 
			rowObject:$(newRow), 
			height: newHeight, 
			width: newWidth, 
			marginTop: marginTop, 
			marginBottom: marginBottom
		};
		this.imagesHash[oImageToLoad.index] = oImageToLoad;
		
	}
	else
	{
		// Add icon
		if(xmlNode.getAttribute("is_file") == "non") src = "images/crystal/mimes/64/folder.png";
		else src = "images/crystal/mimes/64/"+xmlNode.getAttribute('icon');
		var imgString = "<img src=\""+src+"\" ";
		imgString =  imgString + "width=\"64\" height=\"64\" align=\"ABSMIDDLE\" border=\"0\"><div class=\"thumbLabel\" title=\"" + xmlNode.getAttribute("text")+"\">" + xmlNode.getAttribute("text")+"</div>";
		innerSpan.innerHTML = imgString;		
		newRow.appendChild(innerSpan);
		this._htmlElement.appendChild(newRow);
	}

	// NOW UPDATE IMAGES SOURCES
	/*
	if(imagesHash.size() > 0)
	{
		//window.setTimeout(function(){
			imagesHash.each(function(pair){
				console.log(pair.value.filename);
				image.src = "content.php?action=image_proxy&get_thumb=true&fic="+pair.value.filename;
				image.onload = function(){
					var img = $(pair.key);
					if(img == null) return;
					img.src = "content.php?action=image_proxy&get_thumb=true&fic="+pair.value.filename;
					img.height = pair.value.height;
					img.width = pair.value.width;
					img.setStyle({marginTop: pair.value.marginTop+'px',marginBottom: pair.value.marginBottom+'px'});
					img.setAttribute("is_loaded", "true");
					oThis.resizeThumbnails(pair.value.rowObject);					
				}
			});
		//}, 100);
	}
	if(oImageToLoad)
	{
		var oThis = this;
		window.setTimeout(function(){
		if(oThis.loading) return;
			console.log(oImageToLoad.filename);
			image.src = "content.php?action=image_proxy&get_thumb=true&fic="+oImageToLoad.filename;
			image.onload = function(){
				var img = $(oImageToLoad.index);
				if(img == null) return;
				img.src = "content.php?action=image_proxy&get_thumb=true&fic="+oImageToLoad.filename;
				img.height = oImageToLoad.height;
				img.width = oImageToLoad.width;
				img.setStyle({marginTop: oImageToLoad.marginTop+'px',marginBottom: oImageToLoad.marginBottom+'px'});
				img.setAttribute("is_loaded", "true");
				oThis.resizeThumbnails(oImageToLoad.rowObject);};
		}, 100);
	}
	*/
}

FilesList.prototype.resizeThumbnails = function(one_element)
{
	
	var oThis = this;
	var defaultMargin = 5;
	var elList;
	if(one_element) elList = [one_element]; 
	else elList = this._htmlElement.getElementsBySelector('.thumbnail_selectable_cell');
	elList.each(function(element){
		var is_image = (element.getAttribute('is_image')=='1'?true:false);
		var image_element = element.getElementsBySelector('img')[0];		
		var label_element = element.getElementsBySelector('.thumbLabel')[0];
		var tSize = oThis._thumbSize;
		var tW, tH, mT, mB;
		if(is_image && image_element.getAttribute("is_loaded") == "true")
		{
			imgW = element.getAttribute("image_width");
			imgH = element.getAttribute("image_height");			
			if(imgW > imgH)
			{				
				tW = tSize;
				tH = parseInt(imgH / imgW * tW);
				mT = parseInt((tW - tH)/2) + defaultMargin;
				mB = tW+(defaultMargin*2)-tH-mT-1;				
			}
			else
			{
				tH = tSize;
				tW = parseInt(imgW / imgH * tH);
				mT = mB = defaultMargin;
			}
		}
		else
		{
			if(tSize >= 64)
			{
				tW = tH = 64;
				mT = parseInt((tSize - 64)/2) + defaultMargin;
				mB = tSize+(defaultMargin*2)-tH-mT-1;
			}
			else
			{
				tW = tH = tSize;
				mT = mB = defaultMargin;
			}
		}
		image_element.setStyle({width:tW+'px', height:tH+'px', marginTop:mT+'px', marginBottom:mB+'px'});
		element.setStyle({width:tSize+25+'px', height:tSize+30+'px'});
		
		//var el_width = element.getWidth();
		var el_width = tSize + 25;
		var charRatio = 6;
		var nbChar = parseInt(el_width/charRatio);
		var label = new String(element.getElementsBySelector('.thumbLabel')[0].getAttribute('title'));
		//alert(element.getAttribute('text'));
		label_element.innerHTML = label.truncate(nbChar, '...');
		
	});
	
}



FilesList.prototype.removeCurrentLines = function()
{
	var rows;
	if(this._displayMode == "list") rows = $(this._htmlElement).getElementsBySelector('tr');
	else if(this._displayMode == "thumb") rows = $(this._htmlElement).getElementsBySelector('div');
	for(i=0; i<rows.length;i++)
	{
		if(this.isItem(rows[i])) rows[i].remove();
	}
}

FilesList.prototype.setOnLoad = function()
{
	var parentObject = Position.offsetParent($(this._htmlElement));	
	addLightboxMarkupToElement(parentObject, $(this._htmlElement).getElementsBySelector('tr')[0]);
	var img = document.createElement("img");
	img.src = "images/loadingImage.gif";
	$(parentObject).getElementsBySelector("#element_overlay")[0].appendChild(img);
	this.loading = true;
}

FilesList.prototype.removeOnLoad = function()
{
	removeLightboxFromElement(Position.offsetParent($(this._htmlElement)));
	this.loading = false;
}

//
// OVERRIDE CHANGE FUNCTION
// 
FilesList.prototype.fireChange = function()
{
	this._ajaxplorer.getActionBar().update();
	this._ajaxplorer.infoPanel.update();
}

//
// OVERRIDE DBL CLICK FUNCTION
// 
FilesList.prototype.fireDblClick = function (e) {
	
	selRaw = this.getSelectedItems();
	if(!selRaw || !selRaw.length)
	{
		return; // Prevent from double clicking header!
	}
	isFile = selRaw[0].getAttribute('is_file');
	fileName = selRaw[0].getAttribute('filename');
	if(isFile == 'oui')
	{
		this._ajaxplorer.getActionBar().fireAction("download");
	}
	else
	{
		this._ajaxplorer.clickDir(fileName, this._currentRep, null);
	}
}

FilesList.prototype.getSelectedFileNames = function() {
	selRaw = this.getSelectedItems();
	if(!selRaw.length)
	{
		alert('Please select a file!');
		return;
	}
	var tmp = new Array(selRaw.length);
	for(i=0;i<selRaw.length;i++)
	{
		tmp[i] = selRaw[i].getAttribute('filename');
	}
	return tmp;
}

FilesList.prototype.getFilesCount = function() 
{	
	return this.getItems().length;
}


FilesList.prototype.fileNameExists = function(newFileName) 
{	
	var allItems = this.getItems();
	if(!allItems.length)
	{		
		return false;
	}
	for(i=0;i<allItems.length;i++)
	{
		var crtFileName = getBaseName(allItems[i].getAttribute('filename'));
		if(crtFileName && crtFileName.toLowerCase() == getBaseName(newFileName).toLowerCase()) 
			return true;
	}
	return false;
}

FilesList.prototype.hasFileType = function(fileType)
{
	if(fileType != 'image' && fileType != 'mp3') return false;
	$A(this.getItems()).each(function(item){
		if( (fileType == 'image' && item.getAttribute('is_image') && item.getAttribute('is_image')=='1') 
		|| (fileType == 'mp3' && item.getAttribute('is_mp3') && item.getAttribute('is_mp3')=='1') )
		return true;		
	});
	return false;
}

FilesList.prototype.selectFile = function(fileName)
{
	if(!this.fileNameExists(fileName)) 
	{
		return;
	}
	var allItems = this.getItems();
	for(var i=0; i<allItems.length; i++)
	{
		if(getBaseName(allItems[i].getAttribute('filename')) == getBaseName(fileName))
		{
			this.setItemSelected(allItems[i], true);
		}
		else
		{
			this.setItemSelected(allItems[i], false);
		}
	}
	return;
}

FilesList.prototype.getCurrentRep = function()
{
	return this._currentRep;
}

FilesList.prototype.getUserSelection = function()
{
	return new UserSelection(this.getSelectedItems(), this._currentRep);
}

FilesList.prototype.disableTextSelection = function(target)
{
	if (typeof target.onselectstart!="undefined")
	{ //IE route
		target.onselectstart=function(){return false;}
	}
	else if (typeof target.style.MozUserSelect!="undefined")
	{ //Firefox route
		target.style.MozUserSelect="none";
	}
}

FilesList.prototype.keyup = function (event)
{
	if(!this.hasFocus) return true;
	var keyCode = event.keyCode;
	if(this._displayMode == "list" && keyCode != 38 && keyCode != 40 && keyCode != 13)
	{
		return true;
	}
	if(this._displayMode == "thumb" && keyCode != 37 && keyCode != 39 && keyCode != 13)
	{
		return true;
	}
	
	var items = this._selectedItems;
	if(items.length == 0) // No selection
	{
		return false;
	}
	var nextItem;
	var currentItem;
	var shiftKey = event['shiftKey'];
	currentItem = items[items.length-1];
	
	// UP
	if(event.keyCode == 38 || event.keyCode == 37)
	{
		nextItem = this.getPrevious(currentItem);
	}
	//DOWN
	else if(event.keyCode == 40 || event.keyCode == 39)
	{
		nextItem = this.getNext(currentItem);
	}
	
	if(nextItem == null)
	{
		return false;
	}
	if(!shiftKey || !this._multiple) // Unselect everything
	{ 
		for(var i=0; i<items.length; i++)
		{
			this.setItemSelected(items[i], false);
		}		
	}
	this.setItemSelected(nextItem, !nextItem._selected);
	return false;
}

FilesList.prototype.keydown = function (event)
{
	if(event.keyCode == 9 && !ajaxplorer.blockNavigation) return false;
	if(!this.hasFocus) return true;
	var keyCode = event.keyCode;
	if(keyCode != 13)
	{
		return true;
	}
	var items = this._selectedItems;
	if(items.length == 0) // No selection
	{
		return false;
	}
	currentItem = items[items.length-1];
	
	//ENTER
	if(event.keyCode == 13)
	{
		for(var i=0; i<items.length; i++)
		{
			this.setItemSelected(items[i], false);
		}
		this.setItemSelected(currentItem, true);
		this.fireDblClick(null);
		return false;
	}	
}



FilesList.prototype.isItem = function (node) {
	if(this._displayMode == "list")
	{
		return node != null && ( node.tagName == "TR" || node.tagName == "tr") &&
			( node.parentNode.tagName == "TBODY" || node.parentNode.tagName == "tbody" )&&
			node.parentNode.parentNode == this._htmlElement;
	}
	if(this._displayMode == "thumb")
	{
		return node != null && ( node.tagName == "DIV" || node.tagName == "div") && 
			node.parentNode == this._htmlElement;
	}
};

/* Indexable Collection Interface */

FilesList.prototype.getItems = function () {
	if(this._displayMode == "list")
	{
		return this._htmlElement.rows;
	}
	if(this._displayMode == "thumb")
	{
		var tmp = [];
		var j = 0;
		var cs = this._htmlElement.childNodes;
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			if (cs[i].nodeType == 1)
				tmp[j++] = cs[i]
		}
		return tmp;
	}
};

FilesList.prototype.getItemIndex = function (el) {
	if(this._displayMode == "list")
	{
		return el.rowIndex;
	}
	if(this._displayMode == "thumb")
	{
		var j = 0;
		var cs = this._htmlElement.childNodes;
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			if (cs[i] == el)
				return j;
			if (cs[i].nodeType == 1)
				j++;
		}
		return -1;		
	}
};

FilesList.prototype.getItem = function (nIndex) {
	if(this._displayMode == "list")
	{
		return this._htmlElement.rows[nIndex];
	}
	if(this._displayMode == "thumb")
	{
		var j = 0;
		var cs = this._htmlElement.childNodes;
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			if (cs[i].nodeType == 1) {
				if (j == nIndex-1)
					return cs[i];
				j++;
			}
		}
		return null;
	}
};

/* End Indexable Collection Interface */