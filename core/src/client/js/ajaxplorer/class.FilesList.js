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
 * Description : The grid displaying either a table of rows or a grid of thumbnail.
 */
FilesList = Class.create(SelectableElements, {

	initialize: function($super, oElement, bSelectMultiple, oSortTypes, sCurrentRep, oAjaxplorer, sDefaultDisplay)
	{
		$super(oElement, bSelectMultiple);
		this._displayMode = sDefaultDisplay;		
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
		this.allDraggables = new Array();
		this.allDroppables = new Array();		
		
		// List mode style : file list or tableur mode ?
		this.gridStyle = "grid";
		this.paginationData = null;
		this.even = true;
		
		// Default headersDef
		this.columnsDef = $A([]);
		this.columnsDef.push({messageId:1,attributeName:'ajxp_label'});
		this.columnsDef.push({messageId:2,attributeName:'filesize'});
		this.columnsDef.push({messageId:3,attributeName:'mimestring'});
		this.columnsDef.push({messageId:4,attributeName:'ajxp_modiftime'});
		// Associated Defaults
		this.defaultSortTypes = ["StringDirFile", "NumberKo", "String", "MyDate"];
		this._oSortTypes = this.defaultSortTypes;
		
		this.initGUI();
			
		Event.observe(document, "keydown", function(e){	
			if (e == null) e = $('content_pane').ownerDocument.parentWindow.event;
			return this.keydown(e);		
		}.bind(this));
					
		if(this._currentRep != null){
			this.loadXmlList(this._currentRep);
		}
	},
	
	initGUI: function()
	{
		if(this._displayMode == "list")
		{
			var buffer = '';
			if(this.gridStyle == "grid"){
				buffer = buffer + '<div style="overflow:hidden;background-color: #aaa;">';
			}
			buffer = buffer + '<TABLE width="100%" cellspacing="0"  id="selectable_div_header" class="sort-table">';
			this.columnsDef.each(function(column){buffer = buffer + '<col\>';});
			buffer = buffer + '<thead><tr>';
			for(var i=0; i<this.columnsDef.length;i++){
				var column = this.columnsDef[i];
				var last = '';
				if(i==this.columnsDef.length-1) last = ' id="last_header"';
				buffer = buffer + '<td column_id="'+i+'" ajxp_message_id="'+(column.messageId || '')+'"'+last+'>'+(column.messageId?MessageHash[column.messageId]:column.messageString)+'</td>';
			}
			buffer = buffer + '</tr></thead></table>';
			if(this.gridStyle == "grid"){
				buffer = buffer + '</div>';
			}			
			buffer = buffer + '<div id="table_rows_container" style="overflow:auto;"><table id="selectable_div" class="sort-table" width="100%" cellspacing="0"><tbody></tbody></table></div>';
			$('content_pane').innerHTML  = buffer;
			oElement = $('selectable_div');
			
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
				$('table_rows_container').insert({before:this.createPaginator()});
			}
			
			this.initSelectableItems(oElement, true, $('table_rows_container'));
			this._sortableTable = new AjxpSortable(oElement, this._oSortTypes, $('selectable_div_header'));
			this._sortableTable.onsort = this.redistributeBackgrounds.bind(this);
			if(this.paginationData && this.paginationData.get('remote_order') && parseInt(this.paginationData.get('total')) > 1){
				this._sortableTable.setPaginationBehaviour(function(params){
					this.reload(null, null, params);
				}.bind(this), this.columnsDef, this.paginationData.get('currentOrderCol')||-1, this.paginationData.get('currentOrderDir') );
			}
			fitHeightToBottom($('table_rows_container'), $('content_pane'), (!Prototype.Browser.IE?2:0));
			this.disableTextSelection($('selectable_div_header'));
			this.disableTextSelection($('table_rows_container'));
			fitHeightToBottom($('table_rows_container'), $('content_pane'), (!Prototype.Browser.IE?2:0));
			document.observe("ajaxplorer:loaded", function(){
				fitHeightToBottom($('table_rows_container'), $('content_pane'), (!Prototype.Browser.IE?2:0));
			});			
		}
		else if(this._displayMode == "thumb")
		{
			var buffer = '<TABLE width="100%" cellspacing="0" cellpadding="0" class="sort-table">';
			buffer = buffer + '<thead><tr>';
			buffer = buffer + '<td style="border-right:0px;background-image:url(\''+ajxpResourcesFolder+'/images/header_bg_plain.png\');" ajxp_message_id="126">'+MessageHash[126]+'</td>';
			buffer = buffer + '<td align="right" id="last_header"><div class="slider" id="slider-1"><input class="slider-input" id="slider-input-1" name="slider-input-1"/></div></td>';
			buffer = buffer + '</tr></thead><tbody><tr><td colspan="2" style="padding:0px;"><div id="selectable_div" style="overflow:auto; padding:2px 5px;"></div></td></tr></tbody></table>';
			$('content_pane').innerHTML  = buffer;
			fitHeightToBottom($('selectable_div'), $('content_pane'), (!Prototype.Browser.IE?3:0));
			this.slider = new Slider($("slider-1"), $("slider-input-1"));		
			this.slider.setMaximum(200);
			this.slider.setMinimum(30);		
			this.slider.recalculate();
			this.slider.setValue(this._thumbSize);		
			this.slider.onchange = function()
			{
				this._thumbSize = this.slider.getValue();
				this.resizeThumbnails();
			}.bind(this);
			this.disableTextSelection($('selectable_div'));
			this.initSelectableItems($('selectable_div'), true);
		}	
		
	},
	
	createPaginator: function(){
		var current = parseInt(this.paginationData.get('current'));
		var total = parseInt(this.paginationData.get('total'));
		var div = new Element('div').setStyle({height: '20px', backgroundColor:'#FFFFC1', borderBottom: '1px solid #ddd',fontFamily:'Trebuchet MS', fontSize:'11px', textAlign:'center', paddingTop: '2px'});
		div.update('Page '+current+'/'+total);
		if(current>1){
			div.insert({top:this.createPaginatorLink(current-1, '<b>&lt;</b>&nbsp;&nbsp;&nbsp;', 'Previous')});
			if(current > 2){
				div.insert({top:this.createPaginatorLink(1, '<b>&lt;&lt;</b>&nbsp;&nbsp;&nbsp;', 'First')});
			}
		}
		if(total > 1 && current < total){
			div.insert({bottom:this.createPaginatorLink(current+1, '&nbsp;&nbsp;&nbsp;<b>&gt;</b>', 'Next')});
			if(current < (total-1)){
				div.insert({bottom:this.createPaginatorLink(total, '&nbsp;&nbsp;&nbsp;<b>&gt;&gt;</b>', 'Last')});
			}
		}
		return div;
	},
	
	createPaginatorLink:function(page, text, title){
		return new Element('a', {href:'#', style:'font-size:12px;', title:title}).update(text).observe('click', function(e){
			var path = this._currentRep;
			if(this._currentRep.indexOf("#") > -1){
				var path = this._currentRep.substring(0, this._currentRep.indexOf("#"));
			}
			path  = path + "#" + page;
			ajaxplorer.actionBar.updateLocationBar(path);
			ajaxplorer.foldersTree.setCurrentNodeProperty("pagination_anchor", page);
			this.loadXmlList(path);
			Event.stop(e);
		}.bind(this));		
	},
	
	setColumnsDef:function(aColumns){
		this.columnsDef = aColumns;
		if(this._displayMode == "list"){
			this.initGUI();
		}
	},
	
	getColumnsDef:function(){
		return this.columnsDef;
	},
	
	setContextualMenu: function(protoMenu){
		this.protoMenu = protoMenu;	
	},
	
	switchDisplayMode: function(mode){
		if(mode)
		{
			this._displayMode = mode;
		}
		else
		{
			if(this._displayMode == "thumb") this._displayMode = "list";
			else this._displayMode = "thumb";
		}
		var currentSelection = this.getSelectedFileNames();
		this.initGUI();
		this.reload(currentSelection);
		this.fireChange();
		return this._displayMode;
	},
	
	getDisplayMode: function(){
		return this._displayMode;
	},
	
	getHeadersWidth: function(){	
		if(this._displayMode == 'thumb') return;
		var tds = $('selectable_div_header').getElementsBySelector('td');
		this.headersWidth = new Hash();
		var index = 0;	
		tds.each(function(cell){		
			this.headersWidth.set(index, cell.getWidth() - 8);
			index++;
		}.bind(this));
		//alert(this.headersWidth[0]);
	},
	
	applyHeadersWidth: function(){		
		if(this.gridStyle == "grid"){
			window.setTimeout(function(){
			// Reverse!
			var allItems = this.getItems();
			if(!allItems.length) return;
			var tds = $(allItems[0]).getElementsBySelector('td');
			var headerCells = $('selectable_div_header').getElementsBySelector('td');
			var divDim = $('selectable_div').getDimensions();
			var contDim = $('table_rows_container').getDimensions();
			if(divDim.height > contDim.height && !(divDim.width > contDim.width) ){
				$('selectable_div_header').setStyle({width:($('selectable_div_header').getWidth()-17)+'px'});
			}
			var index = 0;
			headerCells.each(function(cell){				
				cell.setStyle({padding:0});
				var div = new Element('div').update('&nbsp;'+cell.innerHTML);
				div.setStyle({height: cell.getHeight(), overflow: 'hidden'});
				div.setStyle({width:tds[index].getWidth()-4+'px'});
				div.setAttribute("title", new String(cell.innerHTML).stripTags());
				cell.update(div);
				cell.setStyle({width:tds[index].getWidth()+'px'});
				index++;
			});
			}.bind(this), 10);
			return;
		}
		this.getHeadersWidth();
		var allItems = this.getItems();
		for(var i=0; i<allItems.length;i++)
		{
			var tds = $(allItems[i]).getElementsBySelector('td');
			var index = 0;
			var widthes = this.headersWidth;
			tds.each(function(cell){		
				if(index == (tds.size()-1)) return;
				cell.setStyle({width:widthes.get(index)+'px'});
				index++;
			});	
		}
	},
	
	initRows: function(){
		// Disable text select on elements
		if(this._displayMode == "thumb")
		{
			this.resizeThumbnails();		
			if(this.protoMenu) this.protoMenu.addElements('#selectable_div');	
			window.setTimeout(this.loadNextImage.bind(this),10);		
		}
		else
		{
			if(this.protoMenu) this.protoMenu.addElements('#table_rows_container');
			this.applyHeadersWidth();
		}
		if(this.protoMenu)this.protoMenu.addElements('.ajxp_draggable');
		var allItems = this.getItems();
		for(var i=0; i<allItems.length;i++)
		{
			this.disableTextSelection(allItems[i]);
		}
	},
	
	loadNextImage: function(){
		if(this.imagesHash && this.imagesHash.size())
		{
			if(this.loading) return;
			var oImageToLoad = this.imagesHash.unset(this.imagesHash.keys()[0]);		
			window.loader = new Image();
			loader.src = "content.php?action=image_proxy&get_thumb=true&file="+encodeURIComponent(oImageToLoad.filename);
			loader.onload = function(){
				var img = oImageToLoad.rowObject.IMAGE_ELEMENT || $(oImageToLoad.index);
				if(img == null) return;
				img.src = "content.php?action=image_proxy&get_thumb=true&file="+encodeURIComponent(oImageToLoad.filename);
				img.height = oImageToLoad.height;
				img.width = oImageToLoad.width;
				img.setStyle({marginTop: oImageToLoad.marginTop+'px',marginBottom: oImageToLoad.marginBottom+'px'});
				img.setAttribute("is_loaded", "true");
				this.resizeThumbnails(oImageToLoad.rowObject);
				this.loadNextImage();				
			}.bind(this);
		}else{
			if(window.loader) window.loader = null;
		}	
	},
	
	reload: function(pendingFileToSelect, url, additionnalParameters){
		if(this._currentRep != null){
			this.loadXmlList(this._currentRep, pendingFileToSelect, url, additionnalParameters);
		}
	},
	
	setPendingSelection: function(pendingFilesToSelect){
		this._pendingFile = pendingFilesToSelect;
	},
	
	loadXmlList: function(repToLoad, pendingFileToSelect, url, additionnalParameters){	
		// TODO : THIS SHOULD BE SET ONCOMPLETE!		
		this._currentRep = repToLoad;
		var connexion = new Connexion(url);
		connexion.addParameter('mode', 'file_list');
		connexion.addParameter('dir', repToLoad);
		if(additionnalParameters){
			additionnalParameters.each(function(pair){
				connexion.addParameter(pair.key,pair.value);
			});
		}
		this._pendingFile = pendingFileToSelect;
		this.setOnLoad();
		connexion.onComplete = function (transport){
			try{
				this.parseXmlAndLoad(transport.responseXML);
			}catch(e){
				alert('Erreur au chargement :'+ e.message);				
			}finally{
				this.removeOnLoad();
			}
		}.bind(this);	
		connexion.sendAsync();
	},
	
	parseXmlAndLoad: function(oXmlDoc){	
		if( oXmlDoc == null || oXmlDoc.documentElement == null) 
		{
			return;
		}
		this.loading = false;
		this.imagesHash = new Hash();
		if(this.protoMenu){
			this.protoMenu.removeElements('.ajxp_draggable');
			this.protoMenu.removeElements('#selectable_div');
		}
		
		for(var i = 0; i< this.allDroppables.length;i++){
			Droppables.remove(this.allDroppables[i]);
			delete this.allDroppables[i];
		}
		for(i = 0;i<this.allDraggables.length;i++){
			this.allDraggables[i].destroy();
			delete this.allDraggables[i];
		}
		this.allDraggables = new Array();
		this.allDroppables = new Array();
		var root = oXmlDoc.documentElement;
		// loop through all tree children
		var cs = root.childNodes;
		var l = cs.length;
		// FIRST PASS FOR REQUIRE AUTH
		for (var i = 0; i < l; i++) 
		{
			if(cs[i].tagName == "require_auth")
			{
				if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
				ajaxplorer.actionBar.fireAction('login');
				this.removeCurrentLines();
				this.fireChange();
				return;
			}
		}
		// SECOND PASS FOR ERRORS CHECK AND COLUMNS DECLARATION
		var refreshGUI = false;
		this.gridStyle = 'file';
		this.even = false;
		this._oSortTypes = this.defaultSortTypes;
		if(this.paginationData){
			this.paginationData = null;
			refreshGUI = true;
		}
		for (var i = 0; i < l; i++) 
		{
			if(cs[i].nodeName == "error" || cs[i].nodeName == "message")
			{
				var type = "ERROR";
				if(cs[i].nodeName == "message") type = cs[i].getAttribute('type');
				if(modal.pageLoading){
					alert(type+':'+cs[i].firstChild.nodeValue);
					this.fireChange();
				}else{
					ajaxplorer.displayMessage(type, cs[i].firstChild.nodeValue);
					this.fireChange();
					return;
				}
			}
			else if(cs[i].nodeName == "columns")
			{
				//Dynamically redefine columns!
				if(cs[i].getAttribute('switchGridMode')){
					this.gridStyle = cs[i].getAttribute('switchGridMode');
				}
				if(cs[i].getAttribute('switchDisplayMode')){
					var dispMode = cs[i].getAttribute('switchDisplayMode');
					if(dispMode != this._displayMode){
						this.switchDisplayMode(dispMode);
					}
				}
				var newCols = $A([]);
				var sortTypes = $A([]);
				for(var j=0;j<cs[i].childNodes.length;j++){
					var col = cs[i].childNodes[j];
					if(col.nodeName == "column"){
						var obj = {};
						$A(col.attributes).each(function(att){
							obj[att.nodeName]=att.nodeValue;
							if(att.nodeName == "sortType"){
								sortTypes.push(att.nodeValue);
							}
						});
						newCols.push(obj);
					}
				}
				if(newCols.size()){
					this.columnsDef = newCols;
					this._oSortTypes = sortTypes;
					if(this._displayMode == "list") refreshGUI = true;
				}
			}
			else if(cs[i].nodeName == "pagination")
			{
				this.paginationData = new Hash();
				$A(cs[i].attributes).each(function(att){
					this.paginationData.set(att.nodeName, att.nodeValue);
				}.bind(this));
				refreshGUI = true;
			}
		}
		if(refreshGUI)
		{
			this.initGUI();
		}
		
		var items = this.getSelectedItems();
		var setItemSelected = this.setItemSelected.bind(this);
		for(var i=0; i<items.length; i++)
		{
			setItemSelected(items[i], false);
		}
		this.removeCurrentLines();
		var parseXmlNodeFunc;
		if(this._displayMode == "list") parseXmlNodeFunc = this.xmlNodeToTableRow.bind(this);
		else parseXmlNodeFunc = this.xmlNodeToDiv.bind(this);
		// NOW PARSE LINES
		this.parsingCache = new Hash();
		for (var i = 0; i < l; i++) 
		{
			if (cs[i].nodeName == "tree") 
			{
				parseXmlNodeFunc(cs[i]);
			}
		}	
		this.initRows();
		/*
		if(this.paginationData && this.paginationData.get('count') && l < parseInt(this.paginationData.get('count'))){
			for(var p=0;p<(parseInt(this.paginationData.get('count'))-l);p++){
				var newRow = new Element("div", {id:"fake_row_"+(p+l-2)});		
				var tBody = this.parsingCache.get('tBody') || $(this._htmlElement).select("tbody")[0];
				newRow.setStyle({height:'24px'});
				tBody.insert(newRow);
				
			}
			$('table_rows_container').observe('scroll', function(e){
				this.scrollDelayer(e);
			}.bind(this) );
		}
		*/
		ajaxplorer.updateHistory(this._currentRep);
		if(this._displayMode == "list" && (!this.paginationData || !this.paginationData.get('remote_order')))
		{
			this._sortableTable.sortColumn = -1;
			this._sortableTable.updateHeaderArrows();
		}
		if(this._pendingFile)
		{
			if(typeof this._pendingFile == 'string')
			{
				this.selectFile(this._pendingFile);
			}else if(this._pendingFile.length){
				for(var f=0;f<this._pendingFile.length; f++){
					this.selectFile(this._pendingFile[f], true);
				}
			}
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
		ajaxplorer.getActionBar().fireContextChange();
		ajaxplorer.getActionBar().fireSelectionChange();
		ajaxplorer.infoPanel.update();
		if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
	},
	
	scrollDelayer : function(event){		
		if(this.scrollTimer){
			window.clearTimeout(this.scrollTimer);
		}
		this.scrollTimer = window.setTimeout(function(){
			this.scrollObserver(event);
		}.bind(this), 500);
	},
	
	scrollObserver : function(event){
		var target = event.target;
		var scrollHeight = target.scrollHeight;
		var scrollTop = target.scrollTop;
		//console.log(scrollTop);		
		var rank = Math.floor(scrollTop / 24);
		//$('fake_row_'+rank).insert(new Element('td', {colspan:4,style:'background-color:blue;'}));
		if($('fake_row_'+rank)){
			$('fake_row_'+rank).setStyle({backgroundColor:"blue"});
		}
		//Lazy load a given set of data, remove the "far ones"
	},
	
	switchCurrentLabelToEdition : function(callback){
		var sel = this.getSelectedItems();
		var item = sel[0]; // We assume this action was triggered with a single-selection active.
		var offset = {top:0,left:0};
		var scrollTop = 0;
		if(this._displayMode == "list"){
			var span = item.select('span.ajxp_label')[0];
			var posSpan = item.select('span.list_selectable_span')[0];
			offset.top=1;
			offset.left=20;
			scrollTop = $('table_rows_container').scrollTop;
		}else{
			var span = item.select('div.thumbLabel')[0];
			var posSpan = span;
			offset.top=2;
			offset.left=2;
			scrollTop = $('selectable_div').scrollTop;
		}
		var pos = posSpan.cumulativeOffset();
		var text = span.innerHTML;
		var edit = new Element('input', {value:item.getAttribute('text'), id:'editbox'}).setStyle({
			zIndex:5000, 
			position:'absolute',
			marginLeft:0,
			marginTop:0
		});
		$(document.getElementsByTagName('body')[0]).insert({bottom:edit});				
		modal.showContent('editbox', (posSpan.getWidth()-offset.left)+'', '20', true);
		edit.setStyle({left:pos.left+offset.left, top:(pos.top+offset.top-scrollTop)});
		window.setTimeout(function(){edit.focus();}, 1000);
		var closeFunc = function(){edit.remove();};
		modal.setCloseAction(closeFunc);
		edit.observe("keydown", function(event){
			if(event.keyCode == Event.KEY_RETURN){				
				Event.stop(event);
				var newValue = edit.getValue();
				callback(item, newValue);
				hideLightBox();
				modal.close();
			}
		}.bind(this));
	},
	
	xmlNodeToTableRow: function(xmlNode){		
		var newRow = document.createElement("tr");		
		var tBody = this.parsingCache.get('tBody') || $(this._htmlElement).select("tbody")[0];
		this.parsingCache.set('tBody', tBody);
		for(var i=0;i<xmlNode.attributes.length;i++)
		{
			newRow.setAttribute(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			if(Prototype.Browser.IE && xmlNode.attributes[i].nodeName == "ID"){
				newRow.setAttribute("ajxp_sql_"+xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			}
		}
		var attributeList;
		if(!this.parsingCache.get('attributeList')){
			attributeList = $A([]);
			this.columnsDef.each(function(column){
				attributeList.push(column.attributeName);
			});
			this.parsingCache.set('attributeList', attributeList);
		}else{
			attributeList = this.parsingCache.get('attributeList');
		}
		//var getNodeAttribute = xmlNode.getAttribute.bind();
		for(i = 0; i<attributeList.length;i++ ){
			var s = attributeList[i];			
			var tableCell = new Element("td");			
			if(s == "ajxp_label")
			{
				var innerSpan = new Element("span", {
					className:"list_selectable_span", 
					style:"cursor:default;display:block;"
				}).update("<img src=\""+resolveImageSource(xmlNode.getAttribute('icon'), "/images/crystal/mimes/ICON_SIZE/", 16)+"\" " + "width=\"16\" height=\"16\" hspace=\"1\" vspace=\"2\" align=\"ABSMIDDLE\" border=\"0\"> <span class=\"ajxp_label\">" + xmlNode.getAttribute('text')+"</span>");
				innerSpan.setAttribute('filename', newRow.getAttribute('filename'));
				tableCell.insert(innerSpan);
				
				// Defer Drag'n'drop assignation for performances
				window.setTimeout(function(){
					if(!xmlNode.getAttribute("is_recycle") || xmlNode.getAttribute("is_recycle") != "1"){
							var newDrag = new AjxpDraggable(innerSpan, {revert:true,ghosting:true,scroll:'tree_container'});
							this.allDraggables.push(newDrag);
							if(this.protoMenu) this.protoMenu.addElements(innerSpan);						
					}
					if(xmlNode.getAttribute("is_file") == "0")
					{
						AjxpDroppables.add(innerSpan);
						this.allDroppables.push(innerSpan);
					}
				}.bind(this), 500);
				
			}else if(s=="ajxp_modiftime"){
				var date = new Date();
				date.setTime(parseInt(xmlNode.getAttribute(s))*1000);
				newRow.ajxp_modiftime = date;
				tableCell.innerHTML = formatDate(date);
			}
			else
			{
				tableCell.innerHTML = xmlNode.getAttribute(s);
			}
			if(this.gridStyle == "grid"){
				tableCell.setAttribute('valign', 'top');				
				tableCell.setStyle({
					verticalAlign:'top', 
					borderRight:'1px solid #eee'
				});
				if(this.even){
					tableCell.setStyle({borderRightColor: '#fff'});					
				}
				if (tableCell.innerHTML == '') tableCell.innerHTML = '&nbsp;';
			}
			newRow.appendChild(tableCell);
		}
		tBody.appendChild(newRow);
		if(this.even){
			//$(newRow).setStyle({backgroundColor: '#eee'});					
			$(newRow).addClassName('even');
		}
		this.even = !this.even;
	},
	
	
	xmlNodeToDiv: function(xmlNode){
		var newRow = new Element('div', {className:"thumbnail_selectable_cell"});
		var tmpAtts = new Hash();
		for(i=0;i<xmlNode.attributes.length;i++)
		{
			newRow.setAttribute(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
			tmpAtts.set(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].nodeValue);
		}
		
		var innerSpan = new Element('span', {style:"cursor:default;"});
		if(tmpAtts.get("is_image") == "1")
		{
			this._crtImageIndex ++;
			var imgIndex = this._crtImageIndex;
			var textNode = tmpAtts.get("text");			
			var img = new Element('img', {
				id:"ajxp_image_"+imgIndex,
				src:ajxpResourcesFolder+"/images/crystal/mimes/64/image.png",
				width:"64",
				height:"64",
				style:"margin:5px",
				align:"ABSMIDDLE",
				border:"0",
				is_loaded:"false"
			});
			var label = new Element('div', {
				className:"thumbLabel",
				title:textNode
			});
			label.innerHTML = textNode;
			var width = tmpAtts.get("image_width");
			var height = tmpAtts.get("image_height");		
			var marginTop, marginHeight, newHeight, newWidth;
			if(width >= height){
				newWidth = 64;
				newHeight = parseInt(height / width * 64);
				marginTop = parseInt((64 - newHeight)/2) + 5;
				marginBottom = 64+10-newHeight-marginTop-1;
			}
			else
			{
				newHeight = 64;
				newWidth = parseInt(width / height * 64);
				marginTop = 5;
				marginBottom = 5;
			}
			var crtIndex = this._crtImageIndex;
			innerSpan.insert({"bottom":img});
			innerSpan.insert({"bottom":label});
			newRow.insert({"bottom": innerSpan});
			newRow.IMAGE_ELEMENT = img;
			newRow.LABEL_ELEMENT = label;
			this._htmlElement.appendChild(newRow);
			
			var fileName = tmpAtts.get('filename');			
			var oImageToLoad = {
				index:"ajxp_image_"+crtIndex,
				filename:fileName, 
				rowObject:newRow, 
				height: newHeight, 
				width: newWidth, 
				marginTop: marginTop, 
				marginBottom: marginBottom
			};
			this.imagesHash.set(oImageToLoad.index, oImageToLoad);
			
		}
		else
		{
			// Add icon
			//if(xmlNode.getAttribute("is_file") == "0") src = "images/crystal/mimes/64/folder.png";
			//else 
			src = resolveImageSource(tmpAtts.get('icon'), "/images/crystal/mimes/ICON_SIZE/", 64);//ajxpResourcesFolder+'/images/crystal/mimes/64/'+tmpAtts.get('icon');
			var imgString = "<img src=\""+src+"\" ";
			imgString =  imgString + "width=\"64\" height=\"64\" align=\"ABSMIDDLE\" border=\"0\"><div class=\"thumbLabel\" title=\"" + tmpAtts.get("text")+"\">" + tmpAtts.get("text")+"</div>";
			innerSpan.innerHTML = imgString;		
			newRow.appendChild(innerSpan);
			this._htmlElement.appendChild(newRow);
		}
		
		// Defer Drag'n'drop assignation for performances
		if(!tmpAtts.get("is_recycle") || tmpAtts.get("is_recycle") != "1"){
			window.setTimeout(function(){
				var newDrag = new AjxpDraggable(newRow, {revert:true,ghosting:true,scroll:'tree_container'});
				this.allDraggables.push(newDrag);						
			}.bind(this), 500);
		}
		if(xmlNode.getAttribute("is_file") == "0")
		{
			AjxpDroppables.add(newRow);
			this.allDroppables.push(newRow);
		}
		
		delete(tmpAtts);
		delete(xmlNode);
	},
	
	resizeThumbnails: function(one_element){
			
		var defaultMargin = 5;
		var elList;
		if(one_element) elList = [one_element]; 
		else elList = this._htmlElement.getElementsBySelector('.thumbnail_selectable_cell');
		elList.each(function(element){
			var is_image = (element.getAttribute('is_image')=='1'?true:false);			
			var image_element = element.IMAGE_ELEMENT || element.getElementsBySelector('img')[0];		
			var label_element = element.LABEL_ELEMENT || element.getElementsBySelector('.thumbLabel')[0];
			var tSize = this._thumbSize;
			var tW, tH, mT, mB;
			if(is_image && image_element.getAttribute("is_loaded") == "true")
			{
				imgW = parseInt(element.getAttribute("image_width"));
				imgH = parseInt(element.getAttribute("image_height"));
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
			var label = new String(label_element.getAttribute('title'));
			//alert(element.getAttribute('text'));
			label_element.innerHTML = label.truncate(nbChar, '...');
			
		}.bind(this));
		
	},
	
	redistributeBackgrounds: function(){
		var allItems = this.getItems();		
		this.even = false;
		for(var i=0;i<allItems.length;i++){
			if(this.even){
				$(allItems[i]).addClassName('even').removeClassName('odd');				
			}else{
				$(allItems[i]).removeClassName('even').addClassName('odd');
			}
			this.even = !this.even;
		}
	},
	
	removeCurrentLines: function(){
		var rows;		
		if(this._displayMode == "list") rows = $(this._htmlElement).select('tr');
		else if(this._displayMode == "thumb") rows = $(this._htmlElement).select('div');
		for(i=0; i<rows.length;i++)
		{
			try{
				rows[i].innerHTML = '';
				if(rows[i].IMAGE_ELEMENT){
					rows[i].IMAGE_ELEMENT = null;
					// Does not work on IE, silently catch exception
					delete rows[i].IMAGE_ELEMENT;
				}
			}catch(e){
			}			
			//if(this.isItem(rows[i])) rows[i].remove();i
			if(rows[i].parentNode){
				rows[i].remove();
			}
		}
	},
	
	setOnLoad: function()	{
		if(this.loading) return;
		var parentObject = $('content_pane');
		addLightboxMarkupToElement(parentObject);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+'/images/loadingImage.gif';
		$(parentObject).getElementsBySelector("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	
	removeOnLoad: function(){
		removeLightboxFromElement($('content_pane'));
		this.loading = false;
	},
	
	//
	// OVERRIDE CHANGE FUNCTION
	// 
	fireChange: function()
	{		
		//this._ajaxplorer.getActionBar().update();
		if(this._fireChange){
			ajaxplorer.actionBar.fireSelectionChange();
			this._ajaxplorer.infoPanel.update();
		}
	},
	
	//
	// OVERRIDE DBL CLICK FUNCTION
	// 
	fireDblClick: function (e) 
	{
		if(ajaxplorer.foldersTree.currentIsRecycle())
		{
			return; // DO NOTHING IN RECYCLE BIN
		}
		selRaw = this.getSelectedItems();
		if(!selRaw || !selRaw.length)
		{
			return; // Prevent from double clicking header!
		}
		isFile = selRaw[0].getAttribute('is_file');
		fileName = selRaw[0].getAttribute('filename');
		if(isFile == '1' || isFile=='true')
		{
			ajaxplorer.getActionBar().fireDefaultAction("file");
		}
		else
		{
			ajaxplorer.getActionBar().fireDefaultAction("dir", selRaw[0].getAttribute('filename'));
		}
	},
	
	getSelectedFileNames: function() {
		selRaw = this.getSelectedItems();
		if(!selRaw.length)
		{
			//alert('Please select a file!');
			return;
		}
		var tmp = new Array(selRaw.length);
		for(i=0;i<selRaw.length;i++)
		{
			tmp[i] = selRaw[i].getAttribute('filename');
		}
		return tmp;
	},
	
	getFilesCount: function() 
	{	
		return this.getItems().length;
	},
	
	
	fileNameExists: function(newFileName) 
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
	},
	
	getFileNames : function(separator){
		var fNames = $A([]);
		var allItems = this.getItems();
		for(var i=0;i<allItems.length;i++){
			fNames.push(getBaseName(allItems[i].getAttribute('filename')));
		}
		if(separator){
			return fNames.join(separator);
		}else {
			return fNames.toArray();
		}
	},


	selectFile: function(fileName, multiple)
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
			else if(multiple==null)
			{
				this.setItemSelected(allItems[i], false);
			}
		}
		return;
	},
	
	getCurrentRep: function()
	{
		return this._currentRep;
	},
	
	getUserSelection: function()
	{
		return new UserSelection(this.getSelectedItems(), this._currentRep);
	},
	
	enableTextSelection : function(target){
		if (target.origOnSelectStart)
		{ //IE route
			target.onselectstart=target.origOnSelectStart;
		}
		target.unselectable = "off";
		target.style.MozUserSelect = "text";
	},
	
	disableTextSelection: function(target)
	{
		if (target.onselectstart)
		{ //IE route
			target.origOnSelectStart = target.onselectstart;
			target.onselectstart=function(){return false;}
		}
		target.unselectable = "on";
		target.style.MozUserSelect="none";
		
	},
	
	keydown: function (event)
	{
		if(this.blockNavigation) return false;
		if(event.keyCode == 9 && !ajaxplorer.blockNavigation) return false;
		if(!this.hasFocus) return true;
		var keyCode = event.keyCode;
		if(this._displayMode == "list" && keyCode != Event.KEY_UP && keyCode != Event.KEY_DOWN && keyCode != Event.KEY_RETURN && keyCode != Event.KEY_END && keyCode != Event.KEY_HOME)
		{
			return true;
		}
		if(this._displayMode == "thumb" && keyCode != Event.KEY_UP && keyCode != Event.KEY_DOWN && keyCode != Event.KEY_LEFT && keyCode != Event.KEY_RIGHT &&  keyCode != Event.KEY_RETURN && keyCode != Event.KEY_END && keyCode != Event.KEY_HOME)
		{
			return true;
		}
		var items = this._selectedItems;
		if(items.length == 0) // No selection
		{
			return false;
		}
		
		// CREATE A COPY TO COMPARE WITH AFTER CHANGES
		// DISABLE FIRECHANGE CALL
		var oldFireChange = this._fireChange;
		this._fireChange = false;
		var selectedBefore = this.getSelectedItems();	// is a cloned array
		
		
		Event.stop(event);
		var nextItem;
		var currentItem;
		var shiftKey = event['shiftKey'];
		currentItem = items[items.length-1];
		var allItems = this.getItems();
		var currentItemIndex = this.getItemIndex(currentItem);
		var selectLine = false;
		//ENTER
		if(event.keyCode == Event.KEY_RETURN)
		{
			for(var i=0; i<items.length; i++)
			{
				this.setItemSelected(items[i], false);
			}
			this.setItemSelected(currentItem, true);
			this.fireDblClick(null);
			this._fireChange = oldFireChange;
			return false;
		}
		if(event.keyCode == Event.KEY_END)
		{
			nextItem = allItems[allItems.length-1];
			if(shiftKey && this._multiple){
				selectLine = true;
				nextItemIndex = allItems.length -1;
			}
		}
		else if(event.keyCode == Event.KEY_HOME)
		{
			nextItem = allItems[0];
			if(shiftKey && this._multiple){
				selectLine = true;
				nextItemIndex = 0;
			}
		}
		// UP
		else if(event.keyCode == Event.KEY_UP)
		{
			if(this._displayMode == 'list') nextItem = this.getPrevious(currentItem);
			else{			
				 nextItemIndex = this.findOverlappingItem(currentItemIndex, false);
				 if(nextItemIndex != null){ nextItem = allItems[nextItemIndex];selectLine = true;}
			}
		}
		else if(event.keyCode == Event.KEY_LEFT)
		{
			nextItem = this.getPrevious(currentItem);
		}
		//DOWN
		else if(event.keyCode == Event.KEY_DOWN)
		{
			if(this._displayMode == 'list') nextItem = this.getNext(currentItem);
			else{
				 nextItemIndex = this.findOverlappingItem(currentItemIndex, true);
				 if(nextItemIndex != null){ nextItem = allItems[nextItemIndex];selectLine = true;}
			}
		}
		else if(event.keyCode == Event.KEY_RIGHT)
		{
			nextItem = this.getNext(currentItem);
		}
		
		if(nextItem == null)
		{
			this._fireChange = oldFireChange;
			return false;
		}
		if(!shiftKey || !this._multiple) // Unselect everything
		{ 
			for(var i=0; i<items.length; i++)
			{
				this.setItemSelected(items[i], false);
			}		
		}
		else if(selectLine)
		{
			if(nextItemIndex >= currentItemIndex)
			{
				for(var i=currentItemIndex+1; i<nextItemIndex; i++) this.setItemSelected(allItems[i], !allItems[i]._selected);
			}else{
				for(var i=nextItemIndex+1; i<currentItemIndex; i++) this.setItemSelected(allItems[i], !allItems[i]._selected);
			}
		}
		this.setItemSelected(nextItem, !nextItem._selected);
		
		
		// NOW FIND CHANGES IN SELECTION!!!
		var found;
		var changed = selectedBefore.length != this._selectedItems.length;
		if (!changed) {
			for (var i = 0; i < selectedBefore.length; i++) {
				found = false;
				for (var j = 0; j < this._selectedItems.length; j++) {
					if (selectedBefore[i] == this._selectedItems[j]) {
						found = true;
						break;
					}
				}
				if (!found) {
					changed = true;
					break;
				}
			}
		}
	
		this._fireChange = oldFireChange;
		if (changed && this._fireChange){
			this.fireChange();
		}		
		
		return false;
	},
	
	findOverlappingItem: function(currentItemIndex, bDown)
	{	
		if(!bDown && currentItemIndex == 0) return;
		var allItems = this.getItems();
		if(bDown && currentItemIndex == allItems.length - 1) return;
		
		var element = $(allItems[currentItemIndex]);	
		var pos = Position.cumulativeOffset(element);
		var dims = Element.getDimensions(element);
		var searchingPosX = pos[0] + parseInt(dims.width/2);
		if(bDown){
			var searchingPosY = pos[1] + parseInt(dims.height*3/2);
			for(var i=currentItemIndex+1; i<allItems.length;i++){
				if(Position.within($(allItems[i]), searchingPosX, searchingPosY))
				{
					return i;
				}
			}
			return null;
		}else{
			var searchingPosY = pos[1] - parseInt(dims.height/2);
			for(var i=currentItemIndex-1; i>-1; i--){
				if(Position.within($(allItems[i]), searchingPosX, searchingPosY))
				{
					return i;
				}
			}
			return null;
		}
	},	
	
	isItem: function (node) {
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
	},
	
	/* Indexable Collection Interface */
	
	getItems: function () {
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
	},
	
	getItemIndex: function (el) {
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
	},
	
	getItem: function (nIndex) {
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
					if (j == nIndex)
						return cs[i];
					j++;
				}
			}
			return null;
		}
	}

/* End Indexable Collection Interface */
});
