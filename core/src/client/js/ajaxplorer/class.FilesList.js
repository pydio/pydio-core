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
Class.create("FilesList", SelectableElements, {
	
	__implements : ["IAjxpPane", "IFocusable", "IContextMenuable"],

	initialize: function($super, oElement, initDefaultDisp)
	{
		$super(null, true);
		$(oElement).ajxpPaneObject = this;
		this.htmlElement = $(oElement);
		this._displayMode = initDefaultDisp;		
		
		Event.observe(document, "ajaxplorer:user_logged", function(){
			if(!ajaxplorer || !ajaxplorer.user) return;
			disp = ajaxplorer.user.getPreference("display");
			if(disp && (disp == 'thumb' || disp == 'list'))
			{
				if(disp != this._displayMode) ajaxplorer.switchDisplayMode(disp);
			}			
			this._thumbSize = parseInt(ajaxplorer.user.getPreference("thumb_size"));	
			if(this.slider){
				this.slider.setValue(this._thumbSize);
				this.resizeThumbnails();
			}
		}.bind(this));		
		
		document.observe("ajaxplorer:context_refresh", function(){ 
			this.reload();
		}.bind(this)  );
		document.observe("ajaxplorer:context_changed", function(event){
			this.fill(event.memo.getContextNode());
		}.bind(this) );
		document.observe("ajaxplorer:context_loading", this.setOnLoad.bind(this));
		document.observe("ajaxplorer:context_loaded", this.removeOnLoad.bind(this));
		document.observe("ajaxplorer:display_switched", function(event){
			this.switchDisplayMode(event.memo);
		}.bind(this) );
		document.observe("ajaxplorer:data_columns_def_changed", function(event){
			this.setColumnsDef(event.memo);
		}.bind(this) );
		
		
		this._thumbSize = 64;
		this._crtImageIndex = 0;
	
		this._pendingFile = null;
		this.allDraggables = new Array();
		this.allDroppables = new Array();		
		
		// List mode style : file list or tableur mode ?
		this.gridStyle = "file";
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
		Event.observe(document, "keydown", this.keydown.bind(this));		
	},
	
	addPaneHeader : function(){},
	
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
			var userPref;
			if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("columns_size", true)){
				var data = new Hash(ajaxplorer.user.getPreference("columns_size", true));
				if(data.get(ajaxplorer.user.getActiveRepository())){
					userPref = new Hash(data.get(ajaxplorer.user.getActiveRepository()));
				}
			}
			for(var i=0; i<this.columnsDef.length;i++){
				var column = this.columnsDef[i];
				var last = ((i==this.columnsDef.length-1)?' id="last_header"':'');
				var stringWidth =((userPref && userPref.get(i))?' style="width:'+userPref.get(i)+(userPref.get('type')?'%':'px')+'"':'');
				buffer = buffer + '<td column_id="'+i+'" ajxp_message_id="'+(column.messageId || '')+'"'+last+stringWidth+'>'+(column.messageId?MessageHash[column.messageId]:column.messageString)+'</td>';
			}
			buffer = buffer + '</tr></thead></table>';
			if(this.gridStyle == "grid"){
				buffer = buffer + '</div>';
			}			
			buffer = buffer + '<div id="table_rows_container" style="overflow:auto;"><table id="selectable_div" class="sort-table" width="100%" cellspacing="0"><tbody></tbody></table></div>';
			this.htmlElement.update(buffer);
			oElement = $('selectable_div');
			
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
				$('table_rows_container').insert({before:this.createPaginator()});
			}
			
			this.initSelectableItems(oElement, true, $('table_rows_container'));
			this._sortableTable = new AjxpSortable(oElement, this._oSortTypes, $('selectable_div_header'));			
			this._sortableTable.onsort = this.redistributeBackgrounds.bind(this);
			if(this.paginationData && this.paginationData.get('remote_order') && parseInt(this.paginationData.get('total')) > 1){
				this._sortableTable.setPaginationBehaviour(function(params){
					this.reload(params);
				}.bind(this), this.columnsDef, this.paginationData.get('currentOrderCol')||-1, this.paginationData.get('currentOrderDir') );
			}
			this.disableTextSelection($('selectable_div_header'));
			this.disableTextSelection($('table_rows_container'));
			fitHeightToBottom($('table_rows_container'), this.htmlElement, (!Prototype.Browser.IE?2:0));
			document.observe("ajaxplorer:loaded", function(){
				fitHeightToBottom($('table_rows_container'), this.htmlElement, (!Prototype.Browser.IE?2:0), true);
			});			
		}
		else if(this._displayMode == "thumb")
		{			
			var buffer = '<div class="panelHeader"><div style="float:right;"><div class="slider" id="slider-1"><input class="slider-input" id="slider-input-1" name="slider-input-1"/></div></div>'+MessageHash[126]+'</div>';
			buffer += '<div id="selectable_div" style="overflow:auto; padding:2px 5px;">';
			this.htmlElement.update(buffer);
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
				$('selectable_div').insert({before:this.createPaginator()});
			}
			fitHeightToBottom($('selectable_div'), this.htmlElement, (!Prototype.Browser.IE?6:0), false, 100);
			document.observe("ajaxplorer:loaded", function(){
				fitHeightToBottom($('selectable_div'), this.htmlElement, (!Prototype.Browser.IE?6:0), false, 100);
			});			
			
			if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("thumb_size")){
				this._thumbSize = parseInt(ajaxplorer.user.getPreference("thumb_size"));
			}

			
			this.slider = new Slider($("slider-1"), $("slider-input-1"));		
			this.slider.setMaximum(250);
			this.slider.setMinimum(30);		
			this.slider.recalculate();
			this.slider.setValue(this._thumbSize);		
			this.slider.onchange = function()
			{
				this._thumbSize = this.slider.getValue();
				this.resizeThumbnails();
				if(!ajaxplorer || !ajaxplorer.user) return;
				
				if(this.sliderTimer) clearTimeout(this.sliderTimer);
				this.sliderTimer = setTimeout(function(){
					ajaxplorer.user.setPreference("thumb_size", this._thumbSize);
					ajaxplorer.user.savePreferences();
				}.bind(this), 100);
				
			}.bind(this);
			this.disableTextSelection($('selectable_div'));
			this.initSelectableItems($('selectable_div'), true);
		}	
		
	},
	
	createPaginator: function(){
		var current = parseInt(this.paginationData.get('current'));
		var total = parseInt(this.paginationData.get('total'));
		var div = new Element('div').addClassName("paginator");
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
		var node = ajaxplorer.getContextNode();
		return new Element('a', {href:'#', style:'font-size:12px;', title:title}).update(text).observe('click', function(e){
			node.getMetadata().get("paginationData").set("new_page", page);
			ajaxplorer.updateContextData(node);
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
	
	resize : function(){
		this.applyHeadersWidth();
	},
	
	setFocusBehaviour : function(){
		this.htmlElement.observe("click", function(){
			if(ajaxplorer) ajaxplorer.focusOn(this);
		}.bind(this) );
	},
	
	showElement : function(show){
		
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
		this.pendingSelection = this.getSelectedFileNames();
		this.initGUI();		
		this.reload();
		this.pendingSelection = null;
		this.fireChange();
		if(ajaxplorer && ajaxplorer.user){
			ajaxplorer.user.setPreference("display", this._displayMode);
			ajaxplorer.user.savePreferences();
		}
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
		if(this._displayMode == "thumb") return; // Sometimes happens in IE...
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
					var div = cell.select('div')[0];
					div.setAttribute("title", new String(cell.innerHTML).stripTags().replace("&nbsp;", ""));
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
	
	reload: function(additionnalParameters){
		if(ajaxplorer.getContextNode()){
			this.fill(ajaxplorer.getContextNode());
		}
	},
	
	setPendingSelection: function(pendingFilesToSelect){
		this._pendingFile = pendingFilesToSelect;
	},
		
	fill: function(contextNode){
		this.imagesHash = new Hash();
		if(this.protoMenu){
			this.protoMenu.removeElements('.ajxp_draggable');
			this.protoMenu.removeElements('#selectable_div');
		}
		for(var i = 0; i< AllAjxpDroppables.length;i++){
			var el = AllAjxpDroppables[i];
			if(this.isItem(el)){
				Droppables.remove(AllAjxpDroppables[i]);
				delete(AllAjxpDroppables[i]);
			}
		}
		for(i = 0;i< AllAjxpDraggables.length;i++){
			if(AllAjxpDraggables[i] && AllAjxpDraggables[i].element && this.isItem(AllAjxpDraggables[i].element)){
				Element.remove(AllAjxpDraggables[i].element);
			}			
		}
		AllAjxpDraggables = $A([]);
				
		var items = this.getSelectedItems();
		var setItemSelected = this.setItemSelected.bind(this);
		for(var i=0; i<items.length; i++)
		{
			setItemSelected(items[i], false);
		}
		this.removeCurrentLines();
		
		var refreshGUI = false;
		this.gridStyle = 'file';
		this.even = false;
		this._oSortTypes = this.defaultSortTypes;
		
		var hasPagination = (this.paginationData?true:false);
		if(contextNode.getMetadata().get("paginationData")){
			this.paginationData = contextNode.getMetadata().get("paginationData");
			refreshGUI = true;
		}else{
			this.paginationData = null;
			if(hasPagination){
				refreshGUI = true;
			}
		}
		var displayData = contextNode.getMetadata().get("displayData");
		if(displayData){
			//Dynamically redefine columns!
			if(displayData.get('gridMode')){
				this.gridStyle = displayData.get('gridMode');
				refreshGUI = true;
			}
			if(displayData.get('displayMode')){
				var dispMode = displayData.get('displayMode');
				if(dispMode != this._displayMode){
					ajaxplorer.switchDisplayMode(dispMode);
				}
			}
		}
		var columnsData = contextNode.getMetadata().get("columnsData");
		if(columnsData){
			this.columnsDef = columnsData.get("columnsDef");
			this._oSortTypes = columnsData.get("sortTypes");
			if(this._displayMode == "list"){
				refreshGUI = true;
			}
		}
		
		if(refreshGUI){
			this.initGUI();
		}
		
		// NOW PARSE LINES
		this.parsingCache = new Hash();		
		var children = contextNode.getChildren();
		for (var i = 0; i < children.length ; i++) 
		{
			var child = children[i];
			var newItem;
			if(this._displayMode == "list") {
				newItem = this.ajxpNodeToTableRow(child);
			}else {
				newItem = this.ajxpNodeToDiv(child);
			}
			newItem.ajxpNode = child;
		}	
		this.initRows();
		
		if(this._displayMode == "list" && (!this.paginationData || !this.paginationData.get('remote_order')))
		{
			this._sortableTable.sortColumn = -1;
			this._sortableTable.updateHeaderArrows();
		}
		if(ajaxplorer.getContextHolder().getPendingSelection())
		{
			var pendingFile = ajaxplorer.getContextHolder().getPendingSelection();
			if(Object.isString(pendingFile))
			{
				this.selectFile(pendingFile);
			}else if(pendingFile.length){
				for(var f=0;f<pendingFile.length; f++){
					this.selectFile(pendingFile[f], true);
				}
			}
			this.hasFocus = true;
			ajaxplorer.getContextHolder().clearPendingSelection();
		}	
		if(this.hasFocus){
			ajaxplorer.focusOn(this);
		}
		if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
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
	
	ajxpNodeToTableRow: function(ajxpNode){		
		var metaData = ajxpNode.getMetadata();
		var newRow = document.createElement("tr");		
		var tBody = this.parsingCache.get('tBody') || $(this._htmlElement).select("tbody")[0];
		this.parsingCache.set('tBody', tBody);
		metaData.each(function(pair){
			newRow.setAttribute(pair.key, pair.value);
			if(Prototype.Browser.IE && pair.key == "ID"){
				newRow.setAttribute("ajxp_sql_"+pair.key, pair.value);
			}			
		});
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
		for(i = 0; i<attributeList.length;i++ ){
			var s = attributeList[i];			
			var tableCell = new Element("td");			
			if(s == "ajxp_label")
			{
				var innerSpan = new Element("span", {
					className:"list_selectable_span", 
					style:"cursor:default;display:block;"
				}).update("<img src=\""+resolveImageSource(metaData.get('icon'), "/images/crystal/mimes/ICON_SIZE/", 16)+"\" " + "width=\"16\" height=\"16\" hspace=\"1\" vspace=\"2\" align=\"ABSMIDDLE\" border=\"0\"> <span class=\"ajxp_label\">" + metaData.get('text')+"</span>");
				innerSpan.ajxpNode = ajxpNode; // For draggable
				tableCell.insert(innerSpan);
				
				// Defer Drag'n'drop assignation for performances
				window.setTimeout(function(){
					if(ajxpNode.getAjxpMime() != "ajxp_recycle"){
							var newDrag = new AjxpDraggable(innerSpan, {revert:true,ghosting:true,scroll:'tree_container'},this,'filesList');							
							if(this.protoMenu) this.protoMenu.addElements(innerSpan);						
					}
					if(!ajxpNode.isLeaf())
					{
						AjxpDroppables.add(innerSpan);
					}
				}.bind(this), 500);
				
			}else if(s=="ajxp_modiftime"){
				var date = new Date();
				date.setTime(parseInt(metaData.get(s))*1000);
				newRow.ajxp_modiftime = date;
				tableCell.innerHTML = formatDate(date);
			}
			else
			{
				tableCell.innerHTML = metaData.get(s);
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
			$(newRow).addClassName('even');
		}
		this.even = !this.even;
		return newRow;
	},
	
	ajxpNodeToDiv: function(ajxpNode){
		var newRow = new Element('div', {className:"thumbnail_selectable_cell"});
		var metadata = ajxpNode.getMetadata();
				
		var innerSpan = new Element('span', {style:"cursor:default;"});
		if(metadata.get("is_image") == "1")
		{
			this._crtImageIndex ++;
			var imgIndex = this._crtImageIndex;
			var textNode = ajxpNode.getLabel();
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
			var width = metadata.get("image_width");
			var height = metadata.get("image_height");		
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
			
			var fileName = ajxpNode.getPath();
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
			src = resolveImageSource(ajxpNode.getIcon(), "/images/crystal/mimes/ICON_SIZE/", 64);
			var imgString = "<img src=\""+src+"\" ";
			imgString =  imgString + "width=\"64\" height=\"64\" align=\"ABSMIDDLE\" border=\"0\"><div class=\"thumbLabel\" title=\"" + ajxpNode.getLabel() +"\">" + ajxpNode.getLabel() +"</div>";
			innerSpan.innerHTML = imgString;		
			newRow.appendChild(innerSpan);
			this._htmlElement.appendChild(newRow);
		}
		
		// Defer Drag'n'drop assignation for performances
		if(!ajxpNode.isRecycle()){
			window.setTimeout(function(){
				var newDrag = new AjxpDraggable(newRow, {revert:true,ghosting:true,scroll:'tree_container'}, this, 'filesList');
			}.bind(this), 500);
		}
		if(!ajxpNode.isLeaf())
		{
			AjxpDroppables.add(newRow);
		}		
		return newRow;
	},
		
	
	resizeThumbnails: function(one_element){
			
		var defaultMargin = 5;
		var elList;
		if(one_element) elList = [one_element]; 
		else elList = this._htmlElement.getElementsBySelector('.thumbnail_selectable_cell');
		elList.each(function(element){
			var node = element.ajxpNode;
			var is_image = (node.getMetadata().get('is_image') == "1");
			var image_element = element.IMAGE_ELEMENT || element.getElementsBySelector('img')[0];		
			var label_element = element.LABEL_ELEMENT || element.getElementsBySelector('.thumbLabel')[0];
			var tSize = this._thumbSize;
			var tW, tH, mT, mB;
			if(is_image && image_element.getAttribute("is_loaded") == "true")
			{
				imgW = parseInt(node.getMetadata().get("image_width"));
				imgH = parseInt(node.getMetadata().get("image_height"));
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
					delete(rows[i].IMAGE_ELEMENT);
				}
			}catch(e){
			}			
			if(rows[i].parentNode){
				rows[i].remove();
			}
		}
		this.fireChange();
	},
	
	setOnLoad: function()	{
		if(this.loading) return;
		addLightboxMarkupToElement(this.htmlElement);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+'/images/loadingImage.gif';
		$(this.htmlElement).getElementsBySelector("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	
	removeOnLoad: function(){
		removeLightboxFromElement(this.htmlElement);
		this.loading = false;
	},
	
	//
	// OVERRIDE CHANGE FUNCTION
	// 
	fireChange: function()
	{		
		if(this._fireChange){			
			ajaxplorer.updateContextData(null, this.getSelectedNodes(), this);			
		}
	},
	
	//
	// OVERRIDE DBL CLICK FUNCTION
	// 
	fireDblClick: function (e) 
	{
		if(ajaxplorer.getContextNode().getAjxpMime() == "ajxp_recycle")
		{
			return; // DO NOTHING IN RECYCLE BIN
		}
		selRaw = this.getSelectedItems();
		if(!selRaw || !selRaw.length)
		{
			return; // Prevent from double clicking header!
		}
		var selNode = selRaw[0].ajxpNode;
		if(selNode.isLeaf())
		{
			ajaxplorer.getActionBar().fireDefaultAction("file");
		}
		else
		{
			ajaxplorer.getActionBar().fireDefaultAction("dir", selNode);
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
		return ajaxplorer.getContextNode().getPath();
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
