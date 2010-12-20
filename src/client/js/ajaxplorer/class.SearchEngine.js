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
Class.create("SearchEngine", AjxpPane, {

	htmlElement:undefined,
	_inputBox:undefined,
	_resultsBox:undefined,
	_searchButtonName:undefined,
	state: 'idle',
	_runningQueries:undefined,
	_queriesIndex:0,
	_ajxpOptions:undefined,
	
	_queue : undefined,

	initialize: function($super, mainElementName, ajxpOptions)
	{
		$super($(mainElementName));
		if(ajxpOptions){
			this._ajxpOptions = ajxpOptions;
		}
		this.initGUI();
	},
	
	initGUI : function(){
		
		if(!this.htmlElement) return;
		
		this.htmlElement.update('<div id="search_form"><input style="float:left;" type="text" id="search_txt" name="search_txt" onfocus="blockEvents=true;" onblur="blockEvents=false;"><a href="" id="search_button" ajxp_message_title_id="184" title="'+MessageHash[184]+'"><img width="16" height="16" align="absmiddle" src="'+ajxpResourcesFolder+'/images/actions/16/search.png" border="0"/></a><a href="" id="stop_search_button" ajxp_message_title_id="185" title="'+MessageHash[185]+'"><img width="16" height="16" align="absmiddle" src="'+ajxpResourcesFolder+'/images/actions/16/fileclose.png" border="0" /></a></div><div id="search_results"></div>');
		
		this.metaOptions = [];
		if(this._ajxpOptions && this._ajxpOptions.metaColumns){
			var cols = this._ajxpOptions.metaColumns;
			$('search_form').insert({bottom:'<div id="search_meta">'+MessageHash[344]+' : <span id="search_meta_options"></span></div>'});			
			this.initMetaOption($('search_meta_options'), 'filename', 'Filename', true);
			for(var key in cols){
				this.initMetaOption($('search_meta_options'), key, cols[key]);
			}
		}else{
			$('search_form').insert('<div style="clear:left;height:9px;"></div>');
		}
		
		this._inputBox = $("search_txt");
		this._resultsBoxId = 'search_results';
		this._searchButtonName = "search_button";
		this._runningQueries = new Array();
		this._queue = $A([]);
		
		$('stop_'+this._searchButtonName).addClassName("disabled");
		
		this.htmlElement.select('a', 'div[id="search_results"]').each(function(element){
			disableTextSelection(element);
		});

		
		this._inputBox.onkeypress = function(e){
			if (e==null) e = window.event;
			if(e.keyCode == 13) {
				this.search();
				Event.stop(e);
			}
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
		fitHeightToBottom($(this._resultsBoxId));
		if(this.htmlElement && this.htmlElement.visible()){
			this._inputBox.setStyle({width:Math.max((this.htmlElement.getWidth()-70),70) + "px"});
		}
	},
	
	initMetaOption : function(element, optionValue, optionLabel, checked){
		var option = new Element('meta_opt', {value:optionValue}).update(optionLabel);
		if(checked) option.addClassName('checked');
		if(element.childElements().length) element.insert(', ');
		element.insert(option);
		option.observe('click', function(event){
			option.toggleClassName('checked');
		});
		this.metaOptions.push(option);
	},
	
	hasMetaSearch : function(){
		var found = false;
		this.metaOptions.each(function(opt){
			if(opt.getAttribute("value")!="filename" && opt.hasClassName("checked")) found = true;
		});
		return found;
	},
	
	getSearchColumns : function(){
		var cols = $A();
		this.metaOptions.each(function(opt){
			if(opt.hasClassName("checked")) cols.push(opt.getAttribute("value"));
		});
		return cols;
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
		this.crtText = text.toLowerCase();
		this.updateStateSearching();
		this.clearResults();
		var folder = ajaxplorer.getContextNode().getPath();
		if(folder == "/") folder = "";
		window.setTimeout(function(){
			this.searchFolderContent(folder);
		}.bind(this), 0);		
	},
	
	interrupt : function(){
		// Interrupt current search
		if(this._state == 'idle') return;
		this._state = 'interrupt';
		this._queue = $A();
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
		
	clear: function(){
		this.clearResults();
		if(this._inputBox){
			this._inputBox.value = "";
		}
	},
	
	clearResults : function(){
		// Clear the results	
		while($(this._resultsBoxId).childNodes.length)
		{
			$(this._resultsBoxId).removeChild($(this._resultsBoxId).childNodes[0]);
		}
	},
	
	addResult : function(folderName, ajxpNode, metaFound){
		var fileName = ajxpNode.getLabel();
		var icon = ajxpNode.getIcon();
		// Display the result in the results box.
		if(folderName == "") folderName = "/";
		var isFolder = false;
		if(icon == null) // FOLDER CASE
		{
			isFolder = true;
			icon = 'folder.png';
			if(folderName != "/") folderName += "/";
			folderName += fileName;
		}	
		var imageString = '<img align="absmiddle" width="16" height="16" src="'+ajxpResourcesFolder+'/images/mimes/16/'+icon+'"> ';
		var stringToDisplay;
		if(metaFound){
			stringToDisplay = fileName + ' (' + this.highlight(metaFound, this.crtText, 20)+ ') ';
		}else{
			stringToDisplay = this.highlight(fileName, this.crtText);
		}
		
		var divElement = new Element('div', {title:MessageHash[224]+' '+ folderName}).update(imageString+stringToDisplay);	
		$(this._resultsBoxId).insert(divElement);
		if(isFolder)
		{
			divElement.observe("click", function(e){
				ajaxplorer.goTo(folderName);
			});
		}
		else
		{
			divElement.observe("click", function(e){
				ajaxplorer.getContextHolder().setPendingSelection(fileName);
				ajaxplorer.goTo(folderName);
			});
		}
	},
	
	appendFolderToQueue : function(path){
		this._queue.push(path);
	},
	
	searchNext : function(){
		if(this._queue.length){
			var path = this._queue.first();
			this._queue.shift();
			this.searchFolderContent(path);
		}else{
			this.updateStateFinished();
		}
	},
	
	searchFolderContent : function(currentFolder){
		if(this._state == 'interrupt') {
			this.updateStateFinished();
			return;
		}
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'ls');				
		connexion.addParameter('options', 'a' + (this.hasMetaSearch()?'l':''));
		connexion.addParameter('dir', currentFolder);
		connexion.onComplete = function(transport){
			this._parseXmlAndSearchString(transport.responseXML, currentFolder);
			this.searchNext();
		}.bind(this);
		connexion.sendAsync();
	},
	
	_parseXmlAndSearchString : function(oXmlDoc, currentFolder){
		if(this._state == 'interrupt'){
			this.updateStateFinished();
			return;
		}
		if( oXmlDoc == null || oXmlDoc.documentElement == null){
			//alert(currentFolder);
		}else{
			var nodes = XPathSelectNodes(oXmlDoc.documentElement, "tree");
			for (var i = 0; i < nodes.length; i++) 
			{
				if (nodes[i].tagName == "tree") 
				{
					var node = this.parseAjxpNode(nodes[i]);					
					this._searchNode(node, currentFolder);
					if(!node.isLeaf())
					{
						var newPath = node.getPath();
						this.appendFolderToQueue(newPath);
					}
				}
			}		
		}
	},
	
	_searchNode : function(ajxpNode, currentFolder){
		var searchFileName = true;
		var searchCols;
		if(this.hasMetaSearch()){
			searchCols = this.getSearchColumns();
			if(!searchCols.indexOf('filename')){
				searchFileName = false;
			}
		}
		if(searchFileName && ajxpNode.getLabel().toLowerCase().indexOf(this.crtText) != -1){
			this.addResult(currentFolder, ajxpNode);
			return;
		}
		if(!searchCols) return;
		for(var i=0;i<searchCols.length;i++){
			var meta = ajxpNode.getMetadata().get(searchCols[i]);
			if(meta && meta.toLowerCase().indexOf(this.crtText) != -1){
				this.addResult(currentFolder, ajxpNode, meta);
				return;			
			}
		}
	},
	
	parseAjxpNode : function(xmlNode){
		var node = new AjxpNode(
			xmlNode.getAttribute('filename'), 
			(xmlNode.getAttribute('is_file') == "1" || xmlNode.getAttribute('is_file') == "true"), 
			xmlNode.getAttribute('text'),
			xmlNode.getAttribute('icon'));
		var reserved = ['filename', 'is_file', 'text', 'icon'];
		var metadata = new Hash();
		for(var i=0;i<xmlNode.attributes.length;i++)
		{
			metadata.set(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			if(Prototype.Browser.IE && xmlNode.attributes[i].nodeName == "ID"){
				metadata.set("ajxp_sql_"+xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			}
		}
		node.setMetadata(metadata);
		return node;
	},
	
	highlight : function(haystack, needle, truncate){
		var start = haystack.toLowerCase().indexOf(needle);
		var end = start + needle.length;
		if(truncate && haystack.length > truncate){
			var midTrunc = Math.round(truncate/2);
			var newStart = Math.max(Math.round((end + start) / 2 - truncate / 2), 0);
			var newEnd = Math.min(Math.round((end + start) / 2 + truncate / 2),haystack.length);
			haystack = haystack.substring(newStart, newEnd);
			if(newStart > 0) haystack = '...' + haystack;
			if(newEnd < haystack.length) haystack = haystack + '...';
			// recompute
			start = haystack.toLowerCase().indexOf(needle);
			end = start + needle.length;
		}
		var highlight = haystack.substring(0, start)+'<em>'+haystack.substring(start, end)+'</em>'+haystack.substring(end);
		return highlight;
	}
		
});