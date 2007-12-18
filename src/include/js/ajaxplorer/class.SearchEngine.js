function SearchEngine(mainElementName, inputName, resultsDivName, buttonName, oAjaxplorer)
{
	this.htmlElement = $(mainElementName);
	this._inputBox = $(inputName);
	this._resultsBox = $(resultsDivName);
	this._searchButtonName = buttonName;
	$('stop_'+this._searchButtonName).addClassName("disabled");
	var oThis = this;
	$(inputName).onkeypress = function(e){
		if (e==null) e = window.event;
		if(e.keyCode == 13) oThis.search();
		if(e.keyCode == 9) return false;		
	}
	
	$(inputName).onkeydown  = function(e){
		if(e == null) e = window.event;
		if(e.keyCode == 9) return false;
		return true;		
	}
	
	$(inputName).onfocus = function(e){
		ajaxplorer.disableShortcuts();
		oThis.hasFocus = true;
		$(inputName).select();
		return false;
	};
		
	$(inputName).onblur = function(e){
		ajaxplorer.enableShortcuts();
		oThis.hasFocus = false;
	};
	
	$(this._searchButtonName).onclick = function(){
		oThis.search();
		return false;
	};
	
	$('stop_'+this._searchButtonName).onclick = function(){
		oThis.interrupt();
		return false;
	};
	
	this._state = 'idle';
	this._runningQueries = new Array();
	this._queriesIndex = 0;
	this._ajaxplorer = oAjaxplorer;
}

SearchEngine.prototype.focus = function()
{
	if(this.htmlElement.visible()){
		this._inputBox.activate();
		this.hasFocus = true;
	}
}

SearchEngine.prototype.blur = function()
{
	this._inputBox.blur();
	this.hasFocus = false;
}

SearchEngine.prototype.search = function()
{
	var text = this._inputBox.value;
	if(text == '') return;
	this.updateStateSearching();
	this.clearResults();
	var folder = this._ajaxplorer.getActionBar().getLocationBarValue();
	if(folder == "/") folder = "";
	this.searchFolderContent(text, folder);
}

SearchEngine.prototype.interrupt = function()
{
	// Interrupt current search
	if(this._state == 'idle') return;
	this._state = 'interrupt';
}

SearchEngine.prototype.updateStateSearching = function ()
{
	this._state = 'searching';
	//try{this._inputBox.disabled = true;}catch(e){}
	$(this._searchButtonName).addClassName("disabled");
	$('stop_'+this._searchButtonName).removeClassName("disabled");
}

SearchEngine.prototype.updateStateFinished = function (interrupt)
{
	this._state = 'idle';
	this._inputBox.disabled = false;
	$(this._searchButtonName).removeClassName("disabled");
	$('stop_'+this._searchButtonName).addClassName("disabled");
}

SearchEngine.prototype.registerQuery = function(queryId)
{
	this._runningQueries.push(''+queryId);
}

SearchEngine.prototype.unregisterQuery = function(queryId)
{
	// USES PROTOTYPE WITHOUT() FUNCTION
	this._runningQueries = this._runningQueries.without(''+queryId);
	if(this._runningQueries.length == 0)
	{
		if(this._state == 'searching') this.updateStateFinished(false);
		else if(this._state == 'interrupt') this.updateStateFinished(true);
	}
}

SearchEngine.prototype.clearResults = function()
{
	// Clear the results	
	while(this._resultsBox.childNodes.length)
	{
		this._resultsBox.removeChild(this._resultsBox.childNodes[0]);
	}
}

SearchEngine.prototype.addResult = function(folderName, fileName, icon)
{
	// Display the result in the results box.
	if(folderName == "") folderName = "/";
	var divElement = document.createElement('div');	
	var isFolder = false;
	if(icon == null) // FOLDER CASE
	{
		isFolder = true;
		icon = 'folder.png';
		if(folderName != "/") folderName += "/";
		folderName += fileName;
	}	
	var imageString = '<img align="absmiddle" width="16" height="16" src="images/crystal/mimes/16/'+icon+'"> ';
	var stringToDisplay = fileName;	
	
	divElement.innerHTML = imageString+stringToDisplay;
	divElement.title = MessageHash[224]+' '+ folderName;
	if(isFolder)
	{
		divElement.onclick = function(e){ajaxplorer.goTo(folderName);}
	}
	else
	{
		divElement.onclick = function(e){ajaxplorer.goTo(folderName, fileName);}
	}
	this._resultsBox.appendChild(divElement);
}

SearchEngine.prototype.searchFolderContent = function(text, currentFolder)
{
	if(this._state == 'interrupt') return;
	this._queriesIndex ++;
	var queryIndex = this._queriesIndex;
	this.registerQuery(this._queriesIndex);
	var oThis = this;
	var connexion = new Connexion();
	connexion.addParameter('mode', 'search');
	connexion.addParameter('dir', currentFolder);
	connexion.onComplete = function(transport){
		oThis._parseXmlAndSearchString(transport.responseXML, text, currentFolder, queryIndex);
	}
	connexion.sendAsync();
}

SearchEngine.prototype._parseXmlAndSearchString = function(oXmlDoc, text, currentFolder, queryIndex)
{
	if(this._state == 'interrupt')
	{
		this.unregisterQuery(queryIndex);
		return;
	}
	if( oXmlDoc == null || oXmlDoc.documentElement == null) 
	{
		//alert(currentFolder);
	}
	else
	{
		var root = oXmlDoc.documentElement;
		// loop through all tree children
		var cs = root.childNodes;
		var l = cs.length;
		for (var i = 0; i < l; i++) 
		{
			if (cs[i].tagName == "tree") 
			{
				
				var icon = cs[i].getAttribute('icon');
				if(cs[i].getAttribute('text').toLowerCase().indexOf(text.toLowerCase()) != -1)
				{
					this.addResult(currentFolder, cs[i].getAttribute('text'), icon);
				}
				if(cs[i].getAttribute('is_file') == null)
				{
					this.searchFolderContent(text, currentFolder+"/"+cs[i].getAttribute('text'));
				}
			}
		}		
	}
	this.unregisterQuery(queryIndex);
}