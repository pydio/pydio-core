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
	
	__implements : ["IAjxpWidget", "IFocusable", "IContextMenuable", "IActionProvider"],

	initialize: function($super, oElement, initDefaultDispOrOptions)
	{
		$super(null, true);
		$(oElement).ajxpPaneObject = this;
		this.htmlElement = $(oElement);
		if(typeof initDefaultDispOrOptions == "string"){
			this.options = {};
			this._displayMode = initDefaultDispOrOptions;		
		}else{
			this.options = initDefaultDispOrOptions;
			this._displayMode = 'list';
		}
		
		Event.observe(document, "ajaxplorer:user_logged", function(){
			if(!ajaxplorer || !ajaxplorer.user) return;
			disp = ajaxplorer.user.getPreference("display");
			if(disp && (disp == 'thumb' || disp == 'list')){
				if(disp != this._displayMode) this.switchDisplayMode(disp);
			}
			this._thumbSize = parseInt(ajaxplorer.user.getPreference("thumb_size"));	
			if(this.slider){
				this.slider.setValue(this._thumbSize);
				this.resizeThumbnails();
			}
		}.bind(this));		
		
		
		var loadObserver = this.contextObserver.bind(this);
		var loadingObs = this.setOnLoad.bind(this);
		var loadEndObs = this.removeOnLoad.bind(this);
		document.observe("ajaxplorer:context_changed", function(event){
			var newContext = event.memo;
			var previous = this.crtContext;
			if(previous){
				previous.stopObserving("loaded", loadEndObs);
				previous.stopObserving("loading", loadingObs);
			}			
			this.crtContext = newContext;
			if(this.crtContext.isLoaded()) {
				this.contextObserver();			
			}else{
				this.crtContext.observeOnce("loaded", loadObserver);
			}
			this.crtContext.observe("loaded",loadEndObs);
			this.crtContext.observe("loading",loadingObs);				
			
		}.bind(this) );
		document.observe("ajaxplorer:context_loading", loadingObs);
		document.observe("ajaxplorer:component_config_changed", function(event){
			if(event.memo.className == "FilesList"){
				var refresh = this.parseComponentConfig(event.memo.classConfig.get('all'));
				if(refresh){
					this.initGUI();
				}
			}
		}.bind(this) );
		
		document.observe("ajaxplorer:selection_changed", function(event){
			if(event.memo._selectionSource == null || event.memo._selectionSource == this) return;
			this.setSelectedNodes(ajaxplorer.getContextHolder().getSelectedNodes());
		}.bind(this));
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
		this.hiddenColumns = $A([]);
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
		
	getVisibleColumns : function(){
		var visible = $A([]);
		this.columnsDef.each(function(el){
			if(!this.hiddenColumns.include(el.attributeName)) visible.push(el);
		}.bind(this) );		
		return visible;
	},
	
	getVisibleSortTypes : function(){
		var visible = $A([]);
		var index = 0;
		for(var i=0;i<this.columnsDef.length;i++){			
			if(!this.hiddenColumns.include(this.columnsDef[i].attributeName)) visible.push(this.columnsDef[i].sortType);
		}
		return visible;		
	},
	
	setColumnVisible : function (attName, visible){
		var change = false;
		if(visible && this.hiddenColumns.include(attName)){			
			this.hiddenColumns = this.hiddenColumns.without(attName);
			change = true;
		}
		if(!visible && !this.hiddenColumns.include(attName)){
			this.hiddenColumns.push(attName);
			change = true;
		}
		if(change){
			if(ajaxplorer && ajaxplorer.user){
				var data = ajaxplorer.user.getPreference("columns_visibility", true) || {};
				data = new Hash(data);
				data.set(ajaxplorer.user.getActiveRepository(), this.hiddenColumns);
				ajaxplorer.user.setPreference("columns_visibility", data, true);				
			}			
			this.initGUI();
			this.fill(this.crtContext);
			if(ajaxplorer && ajaxplorer.user){
				ajaxplorer.user.savePreference("columns_visibility");
			}
		}
		
	},
	
	contextObserver : function(){
		if(!this.crtContext) return;
		//console.log('FILES LIST : FILL');
		this.fill(this.crtContext);
		this.removeOnLoad();
	},
	
	parseComponentConfig : function(domNode){
		refreshGUI = false;
		// CHECK FOR COLUMNS DEFINITION DATA
		var columnsNode = XPathSelectSingleNode(domNode, "columns");
		if(columnsNode){
			// DISPLAY INFO
			if(columnsNode.getAttribute('switchGridMode')){
				this.gridStyle = columnsNode.getAttribute('switchGridMode');
				refreshGUI = true;
			}
			if(columnsNode.getAttribute('switchDisplayMode')){
				var dispMode = columnsNode.getAttribute('switchDisplayMode');
				if(dispMode != this._displayMode){
					this.switchDisplayMode(dispMode);
				}				
			}						
			// COLUMNS INFO
			var columns = XPathSelectNodes(columnsNode, "column");
			var addColumns = XPathSelectNodes(columnsNode, "additional_column");
			if(columns.length){
				var newCols = $A([]);
				var sortTypes = $A([]);
				columns.concat(addColumns);
			}else{
				var newCols = this.columnsDef;
				var sortTypes = this._oSortTypes;
				columns = addColumns;
			}
			columns.each(function(col){
				var obj = {};
				$A(col.attributes).each(function(att){
					obj[att.nodeName]=att.nodeValue;
					if(att.nodeName == "sortType"){
						sortTypes.push(att.nodeValue);
					}
				});
				newCols.push(obj);					
			});
			if(newCols.size()){
				this.columnsDef=newCols;
				this._oSortTypes=sortTypes;
				if(this._displayMode == "list"){
					refreshGUI = true;
				}			
			}
		}
		var properties = XPathSelectNodes(domNode, "property");
		if(properties.length){
			for( var i=0; i<properties.length;i++){
				var property = properties[i];
				if(property.getAttribute("name") == "thumbSize"){
					this._thumbSize = parseInt(property.getAttribute("value"));
					refreshGUI = true;
				}else if(property.getAttribute("name") == "displayMode"){
					var displayMode = property.getAttribute("value");
					if(!(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("display"))){
						this._displayMode = displayMode;
						refreshGUI = true;
					}
				}
			}
		}
		return refreshGUI;
	},

	getActions : function(){
		// function may be bound to another context
		var oThis = this;
		var options = {
			name:'multi_display',
			src:'view_icon.png',
			text_id:150,
			title_id:151,
			text:MessageHash[150],
			title:MessageHash[151],
			hasAccessKey:false,
			subMenu:true,
			subMenuUpdateImage:true,
			callback: function(){
				if(window.actionArguments){
					if(Object.isString(window.actionArguments[0])){
						oThis.switchDisplayMode(window.actionArguments[0]);
					}else{
						oThis.switchDisplayMode(window.actionArguments[0].command);
					}
				}			
			},
			listeners : {
				init:function(){
					window.setTimeout(function(){					
						var displayMode = oThis.getDisplayMode();
						var item = this.subMenuItems.staticItems.detect(function(item){return item.command == displayMode;});
						this.notify("submenu_active", item);
					}.bind(window.listenerContext), 500);								
				}
			}
			};
		var context = {
			selection:false,
			dir:true,
			actionBar:true,
			actionBarGroup:'default',
			contextMenu:true,
			infoPanel:false			
			};
		var subMenuItems = {
			staticItems:[
				{text:228,title:229,src:'view_icon.png',command:'thumb',hasAccessKey:true,accessKey:'thumbs_access_key'},
				{text:226,title:227,src:'view_text.png',command:'list',hasAccessKey:true,accessKey:'list_access_key'}
				]
		};
		// Create an action from these options!
		var multiAction = new Action(options, context, {}, {}, subMenuItems);		
		return $H({multi_display:multiAction});
	},
	
	initGUI: function()
	{
		if(this.observer){
			this.stopObserving("resize", this.observer);
		}
		if(this._displayMode == "list")
		{
			var buffer = '';
			/*
			if(this.gridStyle == "grid"){
				//buffer = buffer + '<div style="overflow:hidden;background-color: #aaa;">';
			}
			*/
			if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("columns_visibility", true)){
				var data = new Hash(ajaxplorer.user.getPreference("columns_visibility", true));
				if(data.get(ajaxplorer.user.getActiveRepository())){
					this.hiddenColumns = $A(data.get(ajaxplorer.user.getActiveRepository()));
				}else{
					this.hiddenColumns = $A();
				}
			}
			var visibleColumns = this.getVisibleColumns();			
			var userPref;
			if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("columns_size", true)){
				var data = new Hash(ajaxplorer.user.getPreference("columns_size", true));
				if(data.get(ajaxplorer.user.getActiveRepository())){
					userPref = new Hash(data.get(ajaxplorer.user.getActiveRepository()));
				}
			}
			var headerData = $A();
			for(var i=0; i<visibleColumns.length;i++){
				var column = visibleColumns[i];
				var userWidth = 0;
				if(this.gridStyle != "grid" && userPref && userPref.get(i) && i<(visibleColumns.length-1)){
					userWidth = userPref.get(i);
				}
				var label = (column.messageId?MessageHash[column.messageId]:column.messageString);
				var leftPadding = 0;
				if(column.attributeName == "ajxp_label"){// Will contain an icon
					leftPadding = 24;
				}
				headerData.push({label:label, size:userWidth, leftPadding:leftPadding});				
			}
			buffer = '<div id="selectable_div_header" class="sort-table"></div>';
			buffer = buffer + '<div id="table_rows_container" style="overflow:auto;"><table id="selectable_div" class="sort-table" width="100%" cellspacing="0"><tbody></tbody></table></div>';
			this.htmlElement.update(buffer);
			oElement = $('selectable_div');
			
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
				$('table_rows_container').insert({before:this.createPaginator()});
			}
			
			this.initSelectableItems(oElement, true, $('table_rows_container'));
			this._headerResizer = new HeaderResizer($('selectable_div_header'), {
				headerData : headerData,
				body : $('table_rows_container'),
				initSizesType : 'percent',
				bodyIsMaster : (this.gridStyle == 'grid')
			});
			this._headerResizer.observe("drag_resize", function(){
				if(this.prefSaver) window.clearTimeout(this.prefSaver);
				this.prefSaver = window.setTimeout(function(){
					if(!ajaxplorer.user || this.gridStyle == "grid") return;
					var sizes = this._headerResizer.getCurrentSizes('percent');
					var data = ajaxplorer.user.getPreference("columns_size", true);
					data = (data?new Hash(data):new Hash());
					sizes['type'] = 'percent';
					data.set(ajaxplorer.user.getActiveRepository(), sizes);
					ajaxplorer.user.setPreference("columns_size", data, true);
					ajaxplorer.user.savePreference("columns_size");
				}.bind(this), 2000);				
			}.bind(this) );
			this._sortableTable = new AjxpSortable(oElement, this.getVisibleSortTypes(), $('selectable_div_header'));
			this._sortableTable.onsort = function(){
				this.redistributeBackgrounds();
				var ctxt = ajaxplorer.getContextNode();
				ctxt.getMetadata().set("filesList.sortColumn", ''+this._sortableTable.sortColumn);
				ctxt.getMetadata().set("filesList.descending", this._sortableTable.descending);
			}.bind(this);
			if(this.paginationData && this.paginationData.get('remote_order') && parseInt(this.paginationData.get('total')) > 1){
				this._sortableTable.setPaginationBehaviour(function(params){
					this.reload(params);
				}.bind(this), this.columnsDef, this.paginationData.get('currentOrderCol')||-1, this.paginationData.get('currentOrderDir') );
			}
			this.disableTextSelection($('selectable_div_header'));
			this.disableTextSelection($('table_rows_container'));
			this.observer = function(e){
				fitHeightToBottom($('table_rows_container'), this.htmlElement);
				if(Prototype.Browser.IE){
					this._headerResizer.resize($('table_rows_container').getWidth());
				}else{
					this._headerResizer.resize($('content_pane').getWidth());
				}
			}.bind(this);
			this.observe("resize", this.observer);
		
			this.headerMenu = new Proto.Menu({
			  selector: '#selectable_div_header', 
			  className: 'menu desktop',
			  menuItems: [{
					name:"tete",
					alt:"ttt",
					image:resolveImageSource("file_save.png", '/images/actions/ICON_SIZE', 16),
					isDefault:false,
					callback:function(e){alert("coucou");}
			  }],
			  fade:true,
			  zIndex:2000,
			  beforeShow : function(){
			  	var items = $A([]);
			  	this.columnsDef.each(function(column){
					var isVisible = !this.hiddenColumns.include(column.attributeName);
					items.push({
						name:(column.messageId?MessageHash[column.messageId]:column.messageString),
						alt:(column.messageId?MessageHash[column.messageId]:column.messageString),
						image:resolveImageSource((isVisible?"column-visible":"transp")+".png", '/images/actions/ICON_SIZE', 16),
						isDefault:false,
						callback:function(e){this.setColumnVisible(column.attributeName, !isVisible)}.bind(this)
					});
				}.bind(this) );		
				this.headerMenu.options.menuItems = items;
				this.headerMenu.refreshList();
			  }.bind(this)
			});
		}
		else if(this._displayMode == "thumb")
		{			
			var buffer = '<div class="panelHeader"><div style="float:right;padding-right:5px;font-size:1px;height:16px;"><input type="image" height="16" width="16" src="'+ajxpResourcesFolder+'/images/actions/16/zoom-in.png" id="slider-input-1" style="border:0px;width:16px;height:16px;margin-top:0px;padding:0px;" value="64"/></div>'+MessageHash[126]+'</div>';
			buffer += '<div id="selectable_div" style="overflow:auto; padding:2px 5px;">';
			this.htmlElement.update(buffer);
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
				$('selectable_div').insert({before:this.createPaginator()});
			}
			this.observer = function(e){
				fitHeightToBottom($('selectable_div'), this.htmlElement);
			}.bind(this);
			this.observe("resize", this.observer);
			
			if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("thumb_size")){
				this._thumbSize = parseInt(ajaxplorer.user.getPreference("thumb_size"));
			}

			
			this.slider = new SliderInput($("slider-input-1"), {
				range : $R(30, 250),
				sliderValue : this._thumbSize,
				leftOffset:0,
				onSlide : function(value)
				{
					this._thumbSize = value;
					this.resizeThumbnails();
				}.bind(this),
				onChange : function(value){
					if(!ajaxplorer || !ajaxplorer.user) return;
					ajaxplorer.user.setPreference("thumb_size", this._thumbSize);
					ajaxplorer.user.savePreference("thumb_size");								
				}.bind(this)
			});

			this.disableTextSelection($('selectable_div'));
			this.initSelectableItems($('selectable_div'), true);
		}	
		this.notify("resize");
	},
	
	createPaginator: function(){
		var current = parseInt(this.paginationData.get('current'));
		var total = parseInt(this.paginationData.get('total'));
		var div = new Element('div').addClassName("paginator");
		var currentInput = new Element('input', {value:current, className:'paginatorInput'});
		div.update(MessageHash[331]);
		div.insert(currentInput);
		div.insert('/'+total);
		if(current>1){
			div.insert({top:this.createPaginatorLink(current-1, '<b>&lt;</b>', 'Previous')});
			if(current > 2){
				div.insert({top:this.createPaginatorLink(1, '<b>&lt;&lt;</b>', 'First')});
			}
		}
		if(total > 1 && current < total){
			div.insert({bottom:this.createPaginatorLink(current+1, '<b>&gt;</b>', 'Next')});
			if(current < (total-1)){
				div.insert({bottom:this.createPaginatorLink(total, '<b>&gt;&gt;</b>', 'Last')});
			}
		}
		currentInput.observe("focus", function(){this.blockNavigation = true}.bind(this));
		currentInput.observe("blur", function(){this.blockNavigation = false}.bind(this));
		currentInput.observe("keydown", function(event){
			if(event.keyCode == Event.KEY_RETURN){
				Event.stop(event);
				var new_page = parseInt(currentInput.getValue());
				if(new_page == current) return; 
				if(new_page < 1 || new_page > total){
					ajaxplorer.displayMessage('ERROR', MessageHash[335] +' '+ total);
					currentInput.setValue(current);
					return;
				}
				var node = ajaxplorer.getContextNode();
				node.getMetadata().get("paginationData").set("new_page", new_page);
				ajaxplorer.updateContextData(node);
			}
		}.bind(this) );
		return div;
	},
	
	createPaginatorLink:function(page, text, title){
		var node = ajaxplorer.getContextNode();
		return new Element('a', {href:'#', style:'font-size:12px;padding:0 7px;', title:title}).update(text).observe('click', function(e){
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
    	if(this.options.fit && this.options.fit == 'height'){
    		var marginBottom = 0;
    		if(this.options.fitMarginBottom){
    			var expr = this.options.fitMarginBottom;
    			try{marginBottom = parseInt(eval(expr));}catch(e){}
    		}
    		fitHeightToBottom(this.htmlElement, (this.options.fitParent?$(this.options.fitParent):null), expr);
    	}		
    	if($('table_rows_container') && Prototype.Browser.IE && this.gridStyle == "file"){
    		$('table_rows_container').setStyle({width:'100%'});
    	}
		this.notify("resize");
	},
	
	setFocusBehaviour : function(){
		this.htmlElement.observe("click", function(){
			if(ajaxplorer) ajaxplorer.focusOn(this);
		}.bind(this) );
	},
	
	showElement : function(show){
		
	},
	
	switchDisplayMode: function(mode){
		if(mode){
			this._displayMode = mode;
		}
		else{
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
			ajaxplorer.user.savePreference("display");
		}
		return this._displayMode;
	},
	
	getDisplayMode: function(){
		return this._displayMode;
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
			if(this._headerResizer){
				this._headerResizer.resize(this.htmlElement.getWidth()-2);
			}
		}
		if(this.protoMenu)this.protoMenu.addElements('.ajxp_draggable');
		var allItems = this.getItems();
		for(var i=0; i<allItems.length;i++)
		{
			this.disableTextSelection(allItems[i], true);
		}
	},
	
	loadNextImage: function(){
		if(this.imagesHash && this.imagesHash.size())
		{
			if(this.loading) return;
			var oImageToLoad = this.imagesHash.unset(this.imagesHash.keys()[0]);		
			window.loader = new Image();
			window.loader.editorClass = oImageToLoad.editorClass;
			window.loader.src = window.loader.editorClass.prototype.getThumbnailSource(oImageToLoad.ajxpNode);
			var loader = function(){
				var img = oImageToLoad.rowObject.IMAGE_ELEMENT || $(oImageToLoad.index);
				if(img == null || window.loader == null) return;
				var newImg = window.loader.editorClass.prototype.getPreview(oImageToLoad.ajxpNode);
				newImg.setAttribute("is_loaded", "true");
				img.parentNode.replaceChild(newImg, img);
				oImageToLoad.rowObject.IMAGE_ELEMENT = newImg;
				this.resizeThumbnails(oImageToLoad.rowObject);
				this.loadNextImage();				
			}.bind(this);
			if(window.loader.readyState && window.loader.readyState == "complete"){
				loader();
			}else{
				window.loader.onload = loader;
			}
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
		var clientConfigs = contextNode.getMetadata().get("client_configs");
		if(clientConfigs){
			var componentData = XPathSelectSingleNode(clientConfigs, 'component_config[@className="FilesList"]');
			if(componentData){
				refreshGUI = this.parseComponentConfig(componentData);
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
		if(this._displayMode == "list" && contextNode.getMetadata().get("filesList.sortColumn")){
			var sortColumn = parseInt(contextNode.getMetadata().get("filesList.sortColumn"));
			var descending = contextNode.getMetadata().get("filesList.descending");
			this._sortableTable.sort(sortColumn, descending);
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
			window.setTimeout(function(){ajaxplorer.focusOn(this);}.bind(this),200);
		}
		//if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
	},
		
	switchCurrentLabelToEdition : function(callback){
		var sel = this.getSelectedItems();
		var item = sel[0]; // We assume this action was triggered with a single-selection active.
		var offset = {top:0,left:0};
		var scrollTop = 0;
		if(this._displayMode == "list"){
			var span = item.select('span.text_label')[0];
			var posSpan = item.select('span.list_selectable_span')[0];
			offset.top=1;
			offset.left=20;
			scrollTop = $('table_rows_container').scrollTop;
		}else{
			var span = item.select('div.thumbLabel')[0];
			var posSpan = span;
			offset.top=2;
			offset.left=3;
			scrollTop = $('selectable_div').scrollTop;
		}
		var pos = posSpan.cumulativeOffset();
		var text = span.innerHTML;
		var edit = new Element('input', {value:item.ajxpNode.getLabel('text'), id:'editbox'}).setStyle({
			zIndex:5000, 
			position:'absolute',
			marginLeft:0,
			marginTop:0,
			height:24
		});
		$(document.getElementsByTagName('body')[0]).insert({bottom:edit});				
		modal.showContent('editbox', (posSpan.getWidth()-offset.left)+'', '20', true);		
		edit.setStyle({left:pos.left+offset.left, top:(pos.top+offset.top-scrollTop)});
		window.setTimeout(function(){
			edit.focus();
			var end = edit.getValue().lastIndexOf("\.");
			if(end == -1){
				edit.select();
			}else{
				var start = 0;  
				if(edit.setSelectionRange)
				{				
					edit.setSelectionRange(start,end);
				}
				else if (edit.createTextRange) {
					var range = edit.createTextRange();
					range.collapse(true);
					range.moveStart('character', start);
					range.moveEnd('character', end);
					range.select();
				}
			}
			
		}, 300);
		var onOkAction = function(){
			var newValue = edit.getValue();
			hideLightBox();
			modal.close();			
			callback(item.ajxpNode, newValue);
		};
		edit.observe("keydown", function(event){
			if(event.keyCode == Event.KEY_RETURN){				
				Event.stop(event);
				onOkAction();
			}
		}.bind(this));
		// Add ok / cancel button, for mobile devices among others
		var buttons = modal.addSubmitCancel(edit, null, false, "after");
		var ok = buttons.select('input[name="ok"]')[0];
		ok.observe("click", onOkAction);
		var origWidth = edit.getWidth()-44;
		var newWidth = origWidth;
		if(origWidth < 70){
			// Offset edit box to be sure it's always big enough.
			edit.setStyle({left:pos.left+offset.left - 70 + origWidth});
			newWidth = 70;
		}
		edit.setStyle({width:newWidth});
		
		buttons.select('input').invoke('setStyle', {
			margin:0,
			width:22,
			border:0,
			backgroundColor:'transparent'
		});
		buttons.setStyle({
			position:'absolute',
			width:46,
			zIndex:2500,
			left:pos.left+offset.left+origWidth,
			top:(pos.top+offset.top-scrollTop)-1
		});
		var closeFunc = function(){
			span.setStyle({color:''});
			edit.remove();
			buttons.remove();
		};
		span.setStyle({color:'#ddd'});
		modal.setCloseAction(closeFunc);
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
			attributeList = $H();
			var visibleColumns = this.getVisibleColumns();
			visibleColumns.each(function(column){
				attributeList.set(column.attributeName, column);
			});
			this.parsingCache.set('attributeList', attributeList);
		}else{
			attributeList = this.parsingCache.get('attributeList');
		}
		var attKeys = attributeList.keys();
		for(i = 0; i<attKeys.length;i++ ){
			var s = attKeys[i];			
			var tableCell = new Element("td");			
			if(s == "ajxp_label")
			{
				var innerSpan = new Element("span", {
					className:"list_selectable_span", 
					style:"cursor:default;display:block;"
				//}).update("<img src=\""+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE/", 16)+"\" " + "width=\"16\" height=\"16\" hspace=\"1\" vspace=\"2\" align=\"ABSMIDDLE\" border=\"0\"> <span id=\"ajxp_label\" class=\"text_label\">" + metaData.get('text')+"</span>");
				}).update("<span id=\"ajxp_label\" class=\"text_label\" style=\"padding-left:24px; background-repeat:no-repeat;background-position:4 2px;background-image:url('"+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE/", 16)+"')\">" + metaData.get('text')+"</span>");
				innerSpan.ajxpNode = ajxpNode; // For draggable
				tableCell.insert(innerSpan);
				
				// Defer Drag'n'drop assignation for performances
				window.setTimeout(function(){
					if(ajxpNode.getAjxpMime() != "ajxp_recycle"){
						var newDrag = new AjxpDraggable(innerSpan, {revert:true,ghosting:true,scroll:($('tree_container')?'tree_container':null)},this,'filesList');							
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
				tableCell.update('<span class="text_label">' + formatDate(date) + '</span>');
			}
			else
			{
				var metaValue = metaData.get(s) || "";
				tableCell.update('<span class="text_label">' + metaValue  + "</span>");
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
			if(this._headerResizer && !this._headerResizer.options.useCSS3){
				tableCell.addClassName("resizer_"+i);
			}
			newRow.appendChild(tableCell);
			if(attributeList.get(s).modifier){
				var modifier = eval(attributeList.get(s).modifier);
				modifier(tableCell, ajxpNode, 'row');
			}
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
		var editors = ajaxplorer.findEditorsForMime(ajxpNode.getAjxpMime());
		var textNode = ajxpNode.getLabel();
		var img = AbstractEditor.prototype.getPreview(ajxpNode);
		var label = new Element('div', {
			className:"thumbLabel",
			title:textNode
		}).update(textNode);
		
		innerSpan.insert({"bottom":img});
		innerSpan.insert({"bottom":label});
		newRow.insert({"bottom": innerSpan});
		newRow.IMAGE_ELEMENT = img;
		newRow.LABEL_ELEMENT = label;
		this._htmlElement.insert(newRow);
			
		var modifiers ;
		if(!this.parsingCache.get('modifiers')){
			modifiers = $A();
			this.columnsDef.each(function(column){
				if(column.modifier){
					try{
						modifiers.push(eval(column.modifier));
					}catch(e){}
				}
			});
			this.parsingCache.set('modifiers', modifiers);			
		}else{
			modifiers = this.parsingCache.get('modifiers');
		}
		modifiers.each(function(el){
			el(newRow, ajxpNode, 'thumb');
		});

		if(editors && editors.length)
		{
			this._crtImageIndex ++;
			var imgIndex = this._crtImageIndex;
			img.writeAttribute("is_loaded", "false");
			img.writeAttribute("id", "ajxp_image_"+imgIndex);
			var crtIndex = this._crtImageIndex;
			
			ajaxplorer.loadEditorResources(editors[0].resourcesManager);
			var editorClass = Class.getByName(editors[0].editorClass);
			if(editorClass){
				var oImageToLoad = {
					index:"ajxp_image_"+crtIndex,
					ajxpNode:ajxpNode,
					editorClass:editorClass, 
					rowObject:newRow
				};
				this.imagesHash.set(oImageToLoad.index, oImageToLoad);
			}
		}			
		
		// Defer Drag'n'drop assignation for performances
		if(!ajxpNode.isRecycle()){
			window.setTimeout(function(){
				var newDrag = new AjxpDraggable(newRow, {
					revert:true,
					ghosting:true,
					scroll:($('tree_container')?'tree_container':null)
				}, this, 'filesList');
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
			var image_element = element.IMAGE_ELEMENT || element.select('img')[0];		
			var label_element = element.LABEL_ELEMENT || element.select('.thumbLabel')[0];
			var tSize = this._thumbSize;
			var tW, tH, mT, mB;
			if(image_element.resizePreviewElement && image_element.getAttribute("is_loaded") == "true")
			{
				image_element.resizePreviewElement({width:tSize, height:tSize, margin:defaultMargin});
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
				image_element.setStyle({width:tW+'px', height:tH+'px', marginTop:mT+'px', marginBottom:mB+'px'});
			}
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
		fileName = getBaseName(fileName);
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
	
	disableTextSelection: function(target, deep)
	{
		if (target.onselectstart)
		{ //IE route
			target.origOnSelectStart = target.onselectstart;
			target.onselectstart=function(){return false;}
		}
		target.unselectable = "on";
		target.style.MozUserSelect="none";
		$(target).addClassName("no_select_bg");
		if(deep){
			$(target).select("td,img,div,span").each(function(td){
				this.disableTextSelection(td);
			}.bind(this));
		}
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
