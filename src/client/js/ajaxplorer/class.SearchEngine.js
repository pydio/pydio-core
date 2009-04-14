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
 * Description : The Search Engine abstraction.
 */
SearchEngine = Class.create({

	htmlElement:undefined,
	_inputBox:undefined,
	_resultsBox:undefined,
	_searchButtonName:undefined,
	state: 'idle',
	_runningQueries:undefined,
	_queriesIndex:0,

	initialize: function(mainElementName)
	{
		this.htmlElement = $(mainElementName);
		this.initGUI();
	},
	
	initGUI : function(){
		
		if(!this.htmlElement) return;
		
		this.htmlElement.update('<div id="search_form"><input style="float:left;" type="text" id="search_txt" name="search_txt" onfocus="blockEvents=true;" onblur="blockEvents=false;"><a href="" id="search_button" ajxp_message_title_id="184" title="'+MessageHash[184]+'"><img width="16" height="16" align="absmiddle" src="'+ajxpResourcesFolder+'/images/crystal/actions/16/search.png" border="0"/></a><a href="" id="stop_search_button" ajxp_message_title_id="185" title="'+MessageHash[185]+'"><img width="16" height="16" align="absmiddle" src="'+ajxpResourcesFolder+'/images/crystal/actions/16/fileclose.png" border="0" /></a></div><div id="search_results"></div>');
		
		this._inputBox = $("search_txt");
		this._resultsBox = $("search_results");
		this._searchButtonName = "search_button";
		this._runningQueries = new Array();
		
		$('stop_'+this._searchButtonName).addClassName("disabled");
		
		this.htmlElement.select('a', 'div[id="search_results"]').each(function(element){
			disableTextSelection(element);
		});

		
		this._inputBox.onkeypress = function(e){
			if (e==null) e = window.event;
			if(e.keyCode == 13) this.search();
			if(e.keyCode == 9) return false;		
		}.bind(this);
		
		this._inputBox.onkeydown  = function(e){
			if(e == null) e = window.event;
			if(e.keyCode == 9) return false;
			return true;		
		};
		
		this._inputBox.onfocus = function(e){
			ajaxplorer.disableShortcuts();
			this.hasFocus = true;
			this._inputBox.select();
			return false;
		}.bind(this);
			
		this._inputBox.onblur = function(e){
			ajaxplorer.enableShortcuts();
			this.hasFocus = false;
		}.bind(this);
		
		$(this._searchButtonName).onclick = function(){
			this.search();
			return false;
		}.bind(this);
		
		$('stop_'+this._searchButtonName).onclick = function(){
			this.interrupt();
			return false;
		}.bind(this);
		
		this.resize();
	},
	
	showElement : function(show){
		if(!this.htmlElement) return;
		if(show) this.htmlElement.show();
		else this.htmlElement.hide();
	},
	
	resize: function(){
		fitHeightToBottom(this._resultsBox, null, 10, true);
	},
	
	focus : function(){
		if(this.htmlElement && this.htmlElement.visible()){
			this._inputBox.activate();
			this.hasFocus = true;
		}
	},
	
	blur : function(){
		if(this._inputBox){
			this._inputBox.blur();
		}
		this.hasFocus = false;
	},
	
	search : function(){
		var text = this._inputBox.value;
		if(text == '') return;
		this.updateStateSearching();
		this.clearResults();
		var folder = ajaxplorer.getActionBar().getLocationBarValue();
		if(folder == "/") folder = "";
		this.searchFolderContent(text, folder);
	},
	
	interrupt : function(){
		// Interrupt current search
		if(this._state == 'idle') return;
		this._state = 'interrupt';
	},
	
	updateStateSearching : function (){
		this._state = 'searching';
		//try{this._inputBox.disabled = true;}catch(e){}
		$(this._searchButtonName).addClassName("disabled");
		$('stop_'+this._searchButtonName).removeClassName("disabled");
	},
	
	updateStateFinished : function (interrupt){
		this._state = 'idle';
		this._inputBox.disabled = false;
		$(this._searchButtonName).removeClassName("disabled");
		$('stop_'+this._searchButtonName).addClassName("disabled");
	},
	
	registerQuery : function(queryId){
		this._runningQueries.push(''+queryId);
	},
	
	unregisterQuery : function(queryId){
		// USES PROTOTYPE WITHOUT() FUNCTION
		this._runningQueries = this._runningQueries.without(''+queryId);
		if(this._runningQueries.length == 0)
		{
			if(this._state == 'searching') this.updateStateFinished(false);
			else if(this._state == 'interrupt') this.updateStateFinished(true);
		}
	},
	
	clear: function(){
		this.clearResults();
		if(this._inputBox){
			this._inputBox.value = "";
		}
	},
	
	clearResults : function(){
		// Clear the results	
		while(this._resultsBox.childNodes.length)
		{
			this._resultsBox.removeChild(this._resultsBox.childNodes[0]);
		}
	},
	
	addResult : function(folderName, fileName, icon){
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
		var imageString = '<img align="absmiddle" width="16" height="16" src="'+ajxpResourcesFolder+'/images/crystal/mimes/16/'+icon+'"> ';
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
	},
	
	searchFolderContent : function(text, currentFolder){
		if(this._state == 'interrupt') return;
		this._queriesIndex ++;
		var queryIndex = this._queriesIndex;
		this.registerQuery(this._queriesIndex);
		var connexion = new Connexion();
		connexion.addParameter('mode', 'search');
		connexion.addParameter('dir', currentFolder);
		connexion.onComplete = function(transport){
			this._parseXmlAndSearchString(transport.responseXML, text, currentFolder, queryIndex);
		}.bind(this);
		connexion.sendAsync();
	},
	
	_parseXmlAndSearchString : function(oXmlDoc, text, currentFolder, queryIndex){
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
});