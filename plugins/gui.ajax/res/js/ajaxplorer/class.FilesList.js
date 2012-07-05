/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

/**
 * The godzilla of AjaXplorer, should be split in smaller pieces.. 
 * This grid displays either a table of rows or a grid of thumbnail.
 */
Class.create("FilesList", SelectableElements, {
	
	__implements : ["IAjxpWidget", "IFocusable", "IContextMenuable", "IActionProvider"],

    __allObservers : $A(),
    __currentInstanceIndex:1,
    _dataModel:null,
    _doubleClickListener:null,
	/**
	 * Constructor
	 * @param $super klass Reference to the constructor
	 * @param oElement HTMLElement
	 * @param initDefaultDispOrOptions Object Instance parameters
	 */
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
            if(this.options.displayMode) {
                this._displayMode = this.options.displayMode;
            }else {
                this._displayMode = 'list';
            }
            if(this.options.dataModel){
                this._dataModel = this.options.dataModel;
            }
            if(this.options.doubleClickListener){
                this._doubleClickListener = this.options.doubleClickListener;
            }
		}
        //this.options.replaceScroller = false;
        if(!FilesList.staticIndex) {
            FilesList.staticIndex = 1;
        }else{
            FilesList.staticIndex ++;
        }
        this.__currentInstanceIndex = FilesList.staticIndex;

        var userLoggedObserver = function(){
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
		}.bind(this);
        this._registerObserver(document, "ajaxplorer:user_logged", userLoggedObserver);
		
		
		var loadObserver = this.contextObserver.bind(this);
		var loadingObs = this.setOnLoad.bind(this);
		var loadEndObs = this.removeOnLoad.bind(this);
        var contextChangedObserver = function(event){
			var newContext = event.memo;
			var previous = this.crtContext;
			if(previous){
				previous.stopObserving("loaded", loadEndObs);
				previous.stopObserving("loading", loadingObs);
			}
			this.crtContext = newContext;
			if(this.crtContext.isLoaded()) {
				this.contextObserver(event);
			}else{
				var oThis = this;
				this.crtContext.observeOnce("loaded", function(){
					oThis.crtContext = this ;
					loadObserver();
				});
			}
			this.crtContext.observe("loaded",loadEndObs);
			this.crtContext.observe("loading",loadingObs);

		}.bind(this);
        var componentConfigObserver = function(event){
			if(event.memo.className == "FilesList"){
				var refresh = this.parseComponentConfig(event.memo.classConfig.get('all'));
				if(refresh){
					this.initGUI();
				}
			}
		}.bind(this) ;
        var selectionChangedObserver = function(event){
			if(event.memo._selectionSource == null || event.memo._selectionSource == this) return;
            var dm = (this._dataModel?this._dataModel:ajaxplorer.getContextHolder());
			this.setSelectedNodes(dm.getSelectedNodes());
		}.bind(this);

        if(this._dataModel){
            this._registerObserver(this._dataModel, "context_changed", contextChangedObserver, true );
            this._registerObserver(this._dataModel, "context_loading", loadingObs, true);
            this._registerObserver(this._dataModel, "component_config_changed", componentConfigObserver, true);
            this._registerObserver(this._dataModel, "selection_changed", selectionChangedObserver, true);
        }else{
            this._registerObserver(document, "ajaxplorer:context_changed", contextChangedObserver, false );
            this._registerObserver(document, "ajaxplorer:context_loading", loadingObs, false);
            this._registerObserver(document, "ajaxplorer:component_config_changed", componentConfigObserver, false);
            this._registerObserver(document, "ajaxplorer:selection_changed", selectionChangedObserver, false);
        }

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
        if(this.options.columnsDef){
            this.columnsDef = this.options.columnsDef;
            this.defaultSortTypes = this.options.defaultSortTypes;
            if(this.options.columnsTemplate){
                this.columnsTemplate = this.options.columnsTemplate;
            }
        }else{
            this.columnsDef = $A([]);
            this.columnsDef.push({messageId:1,attributeName:'ajxp_label'});
            this.columnsDef.push({messageId:2,attributeName:'filesize'});
            this.columnsDef.push({messageId:3,attributeName:'mimestring'});
            this.columnsDef.push({messageId:4,attributeName:'ajxp_modiftime'});
            // Associated Defaults
            this.defaultSortTypes = ["StringDirFile", "NumberKo", "String", "MyDate"];
        }
        this._oSortTypes = this.defaultSortTypes;

		this.initGUI();
        var keydownObserver = this.keydown.bind(this);
        var repoSwitchObserver = this.setOnLoad.bind(this);
		this._registerObserver(document, "keydown", keydownObserver);
        this._registerObserver(document, "ajaxplorer:trigger_repository_switch", repoSwitchObserver);
	},

    _registerObserver:function(object, eventName, handler, objectEvent){
        if(objectEvent){
            object.observe(eventName, handler);
        }else{
            Event.observe(object, eventName, handler);
        }
        this.__allObservers.push({
            object:object,
            event:eventName,
            handler:handler,
            objectEvent:objectEvent
        });
    },

    _clearObservers:function(){
        this.__allObservers.each(function(el){
            if(el.objectEvent){
                el.object.stopObserving(el.event, el.handler);
            }else{
                Event.stopObserving(el.object, el.event, el.handler);
            }
        });
        if(this.observer){
            this.stopObserving("resize", this.observer);
        }
        if(this.scrollSizeObserver){
            this.stopObserving("resize", this.scrollSizeObserver);
        }
    },

	/**
	 * Implementation of the IAjxpWidget methods
	 */
	getDomNode : function(){
		return this.htmlElement;
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */
	destroy : function(){
        this._clearObservers();
        if(window[this.htmlElement.id]){
            delete window[this.htmlElement.id];
        }
		this.htmlElement = null;
	},
	
	
	/**
	 * Gets the currently defined columns that are visible
	 * @returns $A()
	 */
	getVisibleColumns : function(){
		var visible = $A([]);
		this.columnsDef.each(function(el){
			if(!this.hiddenColumns.include(el.attributeName)) visible.push(el);
		}.bind(this) );		
		return visible;
	},
	
	/**
	 * Gets the current sort types associated to the currently visible columns
	 * @returns $A()
	 */
	getVisibleSortTypes : function(){
		var visible = $A([]);
		var index = 0;
		for(var i=0;i<this.columnsDef.length;i++){			
			if(!this.hiddenColumns.include(this.columnsDef[i].attributeName)) visible.push(this.columnsDef[i].sortType);
		}
		return visible;		
	},
	
	/**
	 * Sets a column visible/invisible by its name
	 * @param attName String Column name
	 * @param visible Boolean Visible or invisible
	 */
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
	
	/**
	 * Handler for contextChange event 
	 */
	contextObserver : function(e){
		if(!this.crtContext) return;
		//console.log('FILES LIST : FILL');
		this.fill(this.crtContext);
		this.removeOnLoad();
	},
	
	extractComponentConfig : function(){
		return {
			gridStyle : {value:this.gridStyle},
			_displayMode : {value : this._displayMode },
			columnsTemplate : {value : this.columnsTemplate},
			columnsDef : {value : (this.columnsDef?this.columnsDef.clone():this.columnsDef) },
			oSortTypes : {value : (this._oSortTypes?this._oSortTypes.clone():this._oSortTypes) },
			_thumbSize : {value : this._thumbSize },
			_fixedThumbSize : {value : this._fixedThumbSize}
		};
	},
	
	applyComponentConfig : function(config){
		for(var key in config){
			this[key] = config[key].value;
		}
	},
	
	/**
	 * Apply the config of a component_config node
	 * Returns true if the GUI needs refreshing
	 * @param domNode XMLNode
	 * @returns Boolean
	 */
	parseComponentConfig : function(domNode){
		if(domNode.getAttribute("local") && !this.restoreConfig){			
			this.restoreConfig = this.extractComponentConfig();
		}
		refreshGUI = false;
		this.columnsTemplate = false;
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
				this._fullview = false;
				if(dispMode == "full"){
					this._fullview = true;
					dispMode = "list";
				}
				if(dispMode != this._displayMode){
					this.switchDisplayMode(dispMode);
				}				
			}
			if(columnsNode.getAttribute('template_name')){
				this.columnsTemplate = columnsNode.getAttribute('template_name');
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
					}else if(att.nodeName == "defaultVisibilty" && att.nodeValue == "hidden"){
                        this.hiddenColumns.push(col.getAttribute("attributeName"));
                    }
				}.bind(this));
				newCols.push(obj);					
			}.bind(this));
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
				}else if(property.getAttribute("name") == "fixedThumbSize"){
					this._fixedThumbSize = parseInt(property.getAttribute("value"));
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

	/**
	 * Gets the action of this component
	 * @returns $H
	 */
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
	
	/**
	 * Creates the base GUI, depending on the displayMode
	 */
	initGUI: function()
	{
		if(this.observer){
			this.stopObserving("resize", this.observer);
		}
        if(this.scrollSizeObserver){
            this.stopObserving("resize", this.scrollSizeObserver);
        }
        if(this.slider){
            this.slider.destroy();
        }
		if(this._displayMode == "list")
		{
			var buffer = '';
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
				if(this.columnsTemplate && data.get(this.columnsTemplate)){
					userPref = new Hash(data.get(this.columnsTemplate));
				}else if(data.get(ajaxplorer.user.getActiveRepository())){
					userPref = new Hash(data.get(ajaxplorer.user.getActiveRepository()));
				}
			}
			var headerData = $A();
			for(var i=0; i<visibleColumns.length;i++){
				var column = visibleColumns[i];
				var userWidth = 0;
                if(column.defaultWidth){
                    userWidth = column.defaultWidth.replace('%', '');
                }
				if((this.gridStyle != "grid" || this.columnsTemplate) && userPref && userPref.get(i) && i<(visibleColumns.length-1)){
					userWidth = userPref.get(i);
				}
				if(column.fixedWidth){
					userWidth = column.fixedWidth;
				}
				var label = (column.messageId?MessageHash[column.messageId]:column.messageString);
				var leftPadding = 0;
				if(column.attributeName == "ajxp_label"){// Will contain an icon
					leftPadding = 24;
				}
				headerData.push({label:label, size:userWidth, leftPadding:leftPadding});				
			}
			buffer = '<div id="selectable_div_header-'+this.__currentInstanceIndex+'" class="sort-table"></div>';
			buffer = buffer + '<div id="table_rows_container-'+this.__currentInstanceIndex+'" class="table_rows_container"><table id="selectable_div-'+this.__currentInstanceIndex+'" class="selectable_div sort-table" width="100%" cellspacing="0"><tbody></tbody></table></div>';
			this.htmlElement.update(buffer);
            var contentContainer = this.htmlElement.down("div.table_rows_container");
            contentContainer.setStyle((this.gridStyle!="grid")?{overflowX:"hidden",overflowY:(this.options.replaceScroller?"hidden":"auto")}:{overflow:"auto"});
			attachMobileScroll(contentContainer, "vertical");
            var scrollElement = contentContainer;
			var oElement = this.htmlElement.down(".selectable_div");
			
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
				contentContainer.insert({before:this.createPaginator()});
			}

            if(this.options.selectable == undefined || this.options.selectable === true){
                this.initSelectableItems(oElement, true, contentContainer);
            }else{
                this.initNonSelectableItems(oElement);
            }
			this._headerResizer = new HeaderResizer(this.htmlElement.down('div.sort-table'), {
				headerData : headerData,
				body : contentContainer,
				initSizesType : 'percent',
				bodyIsMaster : (this.gridStyle == 'grid'),
                scrollerWidth : this.options.replaceScroller?0:18,
                handleWidth : (this.options.replaceScroller)?1:3
			});
			this._headerResizer.observe("drag_resize", function(){
				if(this.prefSaver) window.clearTimeout(this.prefSaver);
				this.prefSaver = window.setTimeout(function(){
					if(!ajaxplorer.user || (this.gridStyle == "grid" && !this.columnsTemplate)) return;
					var sizes = this._headerResizer.getCurrentSizes('percent');
					var data = ajaxplorer.user.getPreference("columns_size", true);
					data = (data?new Hash(data):new Hash());
					sizes['type'] = 'percent';
					var id = (this.columnsTemplate?this.columnsTemplate:ajaxplorer.user.getActiveRepository());
					data.set(id, sizes);
					ajaxplorer.user.setPreference("columns_size", data, true);
					ajaxplorer.user.savePreference("columns_size");
				}.bind(this), 2000);				
			}.bind(this) );
			this._sortableTable = new AjxpSortable(oElement, this.getVisibleSortTypes(), this.htmlElement.down('div.sort-table'));
			this._sortableTable.onsort = function(){
				this.redistributeBackgrounds();
				var ctxt = this.getCurrentContextNode();
				ctxt.getMetadata().set("filesList.sortColumn", ''+this._sortableTable.sortColumn);
				ctxt.getMetadata().set("filesList.descending", this._sortableTable.descending);
			}.bind(this);
			if(this.paginationData && this.paginationData.get('remote_order') && parseInt(this.paginationData.get('total')) > 1){
				this._sortableTable.setPaginationBehaviour(function(params){
					this.reload(params);
				}.bind(this), this.columnsDef, this.paginationData.get('currentOrderCol')||-1, this.paginationData.get('currentOrderDir') );
			}
			this.disableTextSelection(this.htmlElement.down('div.sort-table'), true);
			this.disableTextSelection(contentContainer, true);
			this.observer = function(e){
				fitHeightToBottom(contentContainer, this.htmlElement);
				if(Prototype.Browser.IE){
					this._headerResizer.resize(contentContainer.getWidth());
				}else{
                    var width = this.htmlElement.getWidth();
                    width -= parseInt(this.htmlElement.getStyle("borderLeftWidth")) + parseInt(this.htmlElement.getStyle("borderRightWidth"));
					this._headerResizer.resize(width);
				}
			}.bind(this);
			this.observe("resize", this.observer);
		
			if(this.headerMenu){
				this.headerMenu.destroy();
				delete this.headerMenu;
			}
			this.headerMenu = new Proto.Menu({
			  selector: '#selectable_div_header-'+this.__currentInstanceIndex,
			  className: 'menu desktop',
			  menuItems: [],
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
						callback:function(e){this.setColumnVisible(column.attributeName, !isVisible);}.bind(this)
					});
				}.bind(this) );		
				this.headerMenu.options.menuItems = items;
				this.headerMenu.refreshList();
			  }.bind(this)
			});
		}
		else if(this._displayMode == "thumb")
		{			
			if(this.headerMenu){
				this.headerMenu.destroy();
				delete this.headerMenu;
			}
			var buffer = '<div class="panelHeader"><div style="float:right;padding-right:5px;font-size:1px;height:16px;"><input type="image" height="16" width="16" src="'+ajxpResourcesFolder+'/images/actions/16/zoom-in.png" id="slider-input-1" style="border:0px;width:16px;height:16px;margin-top:0px;padding:0px;" value="64"/></div>'+MessageHash[126]+'</div>';
			buffer += '<div id="selectable_div-'+this.__currentInstanceIndex+'" class="selectable_div" style="overflow:auto; padding:2px 5px;">';
			this.htmlElement.update(buffer);
			attachMobileScroll(this.htmlElement.down(".selectable_div"), "vertical");
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){				
                this.htmlElement.down(".selectable_div").insert({before:this.createPaginator()});
			}
            var scrollElement = this.htmlElement.down(".selectable_div");
			this.observer = function(e){
				fitHeightToBottom(scrollElement, this.htmlElement);
			}.bind(this);
			this.observe("resize", this.observer);
			
			if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("thumb_size")){
				this._thumbSize = parseInt(ajaxplorer.user.getPreference("thumb_size"));
			}
			if(this._fixedThumbSize){
				this._thumbSize = parseInt(this._fixedThumbSize);
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
                    if(this.options.replaceScroller){
                        this.notify("resize");
                    }
					if(!ajaxplorer || !ajaxplorer.user) return;
					ajaxplorer.user.setPreference("thumb_size", this._thumbSize);
					ajaxplorer.user.savePreference("thumb_size");								
				}.bind(this)
			});

			this.disableTextSelection(scrollElement, true);
            if(this.options.selectable == undefined || this.options.selectable === true){
			    this.initSelectableItems(scrollElement, true);
            }else{
                this.initNonSelectableItems(scrollElement);
            }
		}

        if(this.options.replaceScroller){
            this.scroller = new Element('div', {id:'filelist_scroller'+this.__currentInstanceIndex, className:'scroller_track', style:"right:0px"});
            this.scroller.insert('<div id="filelist_scrollbar_handle'+this.__currentInstanceIndex+'" class="scroller_handle"></div>');
            scrollElement.insert({before:this.scroller});
            if(this.gridStyle == "grid"){
                scrollElement.setStyle({overflowY:"hidden",overflowX:"auto"});
            }else{
                scrollElement.setStyle({overflow:"hidden"});
            }
            this.scrollbar = new Control.ScrollBar(scrollElement,'filelist_scroller'+this.__currentInstanceIndex);
            if(this.scrollSizeObserver){
                this.stopObserving("resize", this.scrollSizeObserver);
            }
            this.scrollSizeObserver = function(){
                this.scroller.setStyle({height:parseInt(scrollElement.getHeight())+"px"});
                this.scrollbar.recalculateLayout();
            }.bind(this);
            this.observe("resize", this.scrollSizeObserver);
        }


		this.notify("resize");
	},
	
	/**
	 * Adds a pagination navigator at the top of the current GUI
	 * @returns HTMLElement
	 */
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
		currentInput.observe("focus", function(){this.blockNavigation = true;}.bind(this));
		currentInput.observe("blur", function(){this.blockNavigation = false;}.bind(this));
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
				var node = this.getCurrentContextNode();
				node.getMetadata().get("paginationData").set("new_page", new_page);
				ajaxplorer.updateContextData(node);
			}
		}.bind(this) );
		return div;
	},
	
	/**
	 * Utility for generating pagination link
	 * @param page Integer Target page
	 * @param text String Label of the link
	 * @param title String Tooltip of the link
	 * @returns HTMLElement
	 */
	createPaginatorLink:function(page, text, title){
		var node = this.getCurrentContextNode();
		return new Element('a', {href:'#', style:'font-size:12px;padding:0 7px;', title:title}).update(text).observe('click', function(e){
			node.getMetadata().get("paginationData").set("new_page", page);
			ajaxplorer.updateContextData(node);
			Event.stop(e);
		}.bind(this));		
	},
	
	/**
	 * Sets the columns definition object
	 * @param aColumns $H
	 */
	setColumnsDef:function(aColumns){
		this.columnsDef = aColumns;
		if(this._displayMode == "list"){
			this.initGUI();
		}
	},
	
	/**
	 * Gets the columns definition object
	 * @returns $H
	 */
	getColumnsDef:function(){
		return this.columnsDef;
	},
	
	/**
	 * Sets the contextual menu
	 * @param protoMenu Proto.Menu
	 */
	setContextualMenu: function(protoMenu){
		this.protoMenu = protoMenu;	
	},

    getCurrentContextNode : function(){
        if(this._dataModel) return this._dataModel.getContextNode();
        else return ajaxplorer.getContextNode();
    },

	/**
	 * Resizes the widget
	 */
	resize : function(){
    	if(this.options.fit && this.options.fit == 'height'){
    		var marginBottom = 0;
    		if(this.options.fitMarginBottom){
    			var expr = this.options.fitMarginBottom;
    			try{marginBottom = parseInt(eval(expr));}catch(e){}
    		}
    		fitHeightToBottom(this.htmlElement, (this.options.fitParent?$(this.options.fitParent):null), expr);
    	}		
    	if(this.htmlElement.down('.table_rows_container') && Prototype.Browser.IE && this.gridStyle == "file"){
            this.htmlElement.down('.table_rows_container').setStyle({width:'100%'});
    	}
		this.notify("resize");
	},
	
	/**
	 * Link focusing to ajaxplorer main
	 */
	setFocusBehaviour : function(){
        var clickObserver = function(){
			if(ajaxplorer) ajaxplorer.focusOn(this);
		}.bind(this) ;
        this._registerObserver(this.htmlElement, "click", clickObserver);
	},
	
	/**
	 * Do nothing
	 * @param show Boolean
	 */
	showElement : function(show){
		
	},
	
	/**
	 * Switch between various display modes. At the moment, thumb and list.
	 * Should keep the selected nodes after switch
	 * @param mode String "thumb" or "list
	 * @returns String
	 */
	switchDisplayMode: function(mode){
        var dm = (this._dataModel?this._dataModel:ajaxplorer.getContextHolder());
		dm.setPendingSelection(dm.getSelectedNodes());
        this.removeCurrentLines(true);
        
        if(mode){
            this._displayMode = mode;
        }else{
            this._displayMode = (this._displayMode == "thumb"?"list":"thumb");
        }

		this.initGUI();
		this.reload();
		this.fireChange();
		if(ajaxplorer && ajaxplorer.user){
			ajaxplorer.user.setPreference("display", this._displayMode);
			ajaxplorer.user.savePreference("display");
		}
		return this._displayMode;
	},
	
	/**
	 * Returns the display mode
	 * @returns {String}
	 */
	getDisplayMode: function(){
		return this._displayMode;
	},
	
	/**
	 * Called after the rows/thumbs are populated
	 */
	initRows: function(){
        this.notify("rows:willInitialize");
		// Disable text select on elements
		if(this._displayMode == "thumb")
		{
			this.resizeThumbnails();		
			if(this.protoMenu) this.protoMenu.addElements('#selectable_div-'+this.__currentInstanceIndex);
			window.setTimeout(this.loadNextImage.bind(this),10);		
		}
		else
		{
			if(this.protoMenu) this.protoMenu.addElements('#table_rows_container-'+this.__currentInstanceIndex);
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
        this.notify("resize");
        this.notify("rows:didInitialize");
	},
	/**
	 * Queue processor for thumbnail async loading
	 */
	loadNextImage: function(){
		if(this.imagesHash && this.imagesHash.size())
		{
			if(this.loading) return;
			var oImageToLoad = this.imagesHash.unset(this.imagesHash.keys()[0]);		
			window.loader = new Image();
			window.loader.editorClass = oImageToLoad.editorClass;
            window.loader.onerror = this.loadNextImage.bind(this);
			window.loader.src = window.loader.editorClass.prototype.getThumbnailSource(oImageToLoad.ajxpNode);
			var loader = function(){
				var img = oImageToLoad.rowObject.IMAGE_ELEMENT || $(oImageToLoad.index);
				if(img == null || window.loader == null) return;
				var newImg = window.loader.editorClass.prototype.getPreview(oImageToLoad.ajxpNode);
				newImg.setAttribute("data-is_loaded", "true");
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
	/**
	 * Triggers a reload of the rows/thumbs
	 * @param additionnalParameters Object
	 */
	reload: function(additionnalParameters){
		if(this.getCurrentContextNode()){
			this.fill(this.getCurrentContextNode());
		}
	},
	/**
	 * Attach a pending selection that will be applied after rows are populated
	 * @param pendingFilesToSelect $A()
	 */
	setPendingSelection: function(pendingFilesToSelect){
		this._pendingFile = pendingFilesToSelect;
	},
		
	/**
	 * Populates the list with the children of the passed contextNode
	 * @param contextNode AjxpNode
	 */
	fill: function(contextNode){
		this.imagesHash = new Hash();
		if(this.protoMenu){
			this.protoMenu.removeElements('.ajxp_draggable');
			this.protoMenu.removeElements('.selectable_div');
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
                if(AllAjxpDraggables[i].element.IMAGE_ELEMENT){
                    try{
                        if(AllAjxpDraggables[i].element.IMAGE_ELEMENT.destroyElement){
                            AllAjxpDraggables[i].element.IMAGE_ELEMENT.destroyElement();
                        }
                        AllAjxpDraggables[i].element.IMAGE_ELEMENT = null;
                        delete AllAjxpDraggables[i].element.IMAGE_ELEMENT;
                    }catch(e){}
                }
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
		}else if(this.restoreConfig){
			this.applyComponentConfig(this.restoreConfig);
			this.restoreConfig = null;
			refreshGUI = true;
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
            newItem.addClassName("ajxpNodeProvider");
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
        var dm = (this._dataModel?this._dataModel:ajaxplorer.getContextHolder());
		if(dm.getPendingSelection())
		{
			var pendingFile = dm.getPendingSelection();
			if(Object.isString(pendingFile))
			{
				this.selectFile(pendingFile);
			}else if(pendingFile.length){
				for(var f=0;f<pendingFile.length; f++){
                    if(Object.isString(pendingFile[f])){
                        this.selectFile(pendingFile[f], true);
                    }else{
                        this.selectFile(pendingFile[f].getPath(), true);
                    }
				}
			}
			this.hasFocus = true;
			dm.clearPendingSelection();
		}	
		if(this.hasFocus){
			window.setTimeout(function(){ajaxplorer.focusOn(this);}.bind(this),200);
		}
		//if(modal.pageLoading) modal.updateLoadingProgress('List Loaded');
	},
		
	/**
	 * Inline Editing of label
	 * @param callback Function Callback after the label is edited.
	 */
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
			scrollTop = this.htmlElement.down('div.table_rows_container').scrollTop;
		}else{
			var span = item.select('div.thumbLabel')[0];
			var posSpan = span;
			offset.top=2;
			offset.left=3;
			scrollTop = this.htmlElement.down('.selectable_div').scrollTop;
		}
		var pos = posSpan.cumulativeOffset();
		var text = span.innerHTML;
		var edit = new Element('input', {value:item.ajxpNode.getLabel('text'), id:'editbox'}).setStyle({
			zIndex:5000, 
			position:'absolute',
			marginLeft:'0px',
			marginTop:'0px',
			height:'24px',
            padding: 0
		});
		$(document.getElementsByTagName('body')[0]).insert({bottom:edit});				
		modal.showContent('editbox', (posSpan.getWidth()-offset.left)+'', '20', true);		
		edit.setStyle({left:(pos.left+offset.left)+'px', top:(pos.top+offset.top-scrollTop)+'px'});
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
		edit.setStyle({width:newWidth+'px'});
		
		buttons.select('input').invoke('setStyle', {
			margin:0,
			width:'22px',
			border:0,
			backgroundColor:'transparent'
		});
		buttons.setStyle({
			position:'absolute',
			width:'46px',
			zIndex:2500,
			left:(pos.left+offset.left+origWidth)+'px',
			top:((pos.top+offset.top-scrollTop)-1)+'px'
		});
		var closeFunc = function(){
			span.setStyle({color:''});
			edit.remove();
			buttons.remove();
		};
		span.setStyle({color:'#ddd'});
		modal.setCloseAction(closeFunc);
	},
	
	/**
	 * Populate a node as a TR element
	 * @param ajxpNode AjxpNode
	 * @returns HTMLElement
	 */
	ajxpNodeToTableRow: function(ajxpNode){		
		var metaData = ajxpNode.getMetadata();
		var newRow = new Element("tr");
		var tBody = this.parsingCache.get('tBody') || $(this._htmlElement).select("tbody")[0];
		this.parsingCache.set('tBody', tBody);
		metaData.each(function(pair){
			//newRow.setAttribute(pair.key, pair.value);
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
			var fullview = '';
			if(this._fullview){
				fullview = ' full';
			}
			if(s == "ajxp_label")
			{
                var textLabel = new Element("span", {
                    id          :'ajxp_label',
                    className   :'text_label'+fullview
                }).update(metaData.get('text'));

                var backgroundPosition = '4px 2px';
                var backgroundImage = 'url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                if(metaData.get('overlay_icon') && Modernizr.multiplebgs){
                    var ovIcs = metaData.get('overlay_icon').split(',');
                    switch(ovIcs.length){
                        case 1:
                            backgroundPosition = '14px 11px, 4px 2px';
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                        case 2:
                            backgroundPosition = '2px 11px, 14px 11px, 4px 2px';
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[1], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                        case 3:
                            backgroundPosition = '14px 2px, 2px 11px, 14px 11px, 4px 2px';
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[1], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[2], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                        case 4:
                        default:
                            backgroundPosition = '2px 2px, 14px 2px, 2px 11px, 14px 11px, 4px 2px';
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[1], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[2], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[3], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                    }
                }
                textLabel.setStyle({
                    paddingLeft:'24px',
                    backgroundRepeat:'no-repeat',
                    backgroundPosition:backgroundPosition,
                    backgroundImage:backgroundImage
                });

				var innerSpan = new Element("span", {
					className:"list_selectable_span", 
					style:"cursor:default;display:block;"
				}).update(textLabel);

				innerSpan.ajxpNode = ajxpNode; // For draggable
				tableCell.insert(innerSpan);
				
				// Defer Drag'n'drop assignation for performances
                if(this.options.draggable == undefined || this.options.draggable === true){
                    window.setTimeout(function(){
                        if(ajxpNode.getAjxpMime() != "ajxp_recycle"){
                            var newDrag = new AjxpDraggable(
                                innerSpan,
                                {
                                    revert:true,
                                    ghosting:true,
                                    scroll:($('tree_container')?'tree_container':null),
                                    containerScroll: this.htmlElement.down('div.table_rows_container')
                                },
                                this,
                                'filesList'
                            );
                            if(this.protoMenu) this.protoMenu.addElements(innerSpan);
                        }
                        if(!ajxpNode.isLeaf())
                        {
                            AjxpDroppables.add(innerSpan);
                        }
                    }.bind(this), 500);
                }
				
			}else if(s=="ajxp_modiftime"){
				var date = new Date();
				date.setTime(parseInt(metaData.get(s))*1000);
				newRow.ajxp_modiftime = date;
				tableCell.update('<span class="text_label'+fullview+'">' + formatDate(date) + '</span>');
			}
			else
			{
				var metaValue = metaData.get(s) || "";
				tableCell.update('<span class="text_label'+fullview+'">' + metaValue  + "</span>");
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
        // test hidden modifiers
        var hiddenModifiers = $A();
        if(this.parsingCache.get("hiddenModifiers")){
            hiddenModifiers = this.parsingCache.get("hiddenModifiers");
        }else{
            this.hiddenColumns.each(function(col){
                try{
                    this.columnsDef.each(function(colDef){
                        if(colDef.attributeName == col && colDef.modifier){
                           var mod = eval(colDef.modifier);
                           hiddenModifiers.push(mod);
                        }
                    });
                }catch(e){}
            }.bind(this) );
            this.parsingCache.set("hiddenModifiers", hiddenModifiers);
        }
        hiddenModifiers.each(function(mod){
            mod(null,ajxpNode,'row', newRow);
        });
		tBody.appendChild(newRow);
		if(this.even){
			$(newRow).addClassName('even');
		}
		this.even = !this.even;
		return newRow;
	},
	
	/**
	 * Populates a node as a thumbnail div
	 * @param ajxpNode AjxpNode
	 * @returns HTMLElement
	 */
	ajxpNodeToDiv: function(ajxpNode){
		var newRow = new Element('div', {className:"thumbnail_selectable_cell"});
		var metadata = ajxpNode.getMetadata();
				
		var innerSpan = new Element('span', {style:"cursor:default;"});
		var editors = ajaxplorer.findEditorsForMime((ajxpNode.isLeaf()?ajxpNode.getAjxpMime():"mime_folder"), true);
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
        if(ajxpNode.getMetadata().get("overlay_icon")){
            var ovDiv = new Element("div");
            var ovIcs = $A(ajxpNode.getMetadata().get("overlay_icon").split(","));
            var bgPos = $A();
            var bgImg = $A();
            var bgRep = $A();
            var index = 0;
            ovIcs.each(function(ic){
                bgPos.push('0px '+((index*12)+(index>0?2:0))+'px');
                bgImg.push("url('"+resolveImageSource(ovIcs[index], "/images/overlays/ICON_SIZE", 12)+"')");
                bgRep.push('no-repeat');
                index++;
            });


            ovDiv.setStyle({
                position: "absolute",
                top: "3px",
                right: "2px",
                height: ((ovIcs.length*12) + (ovIcs.length > 1 ? (ovIcs.length-1)*2 : 0 )) + "px",
                width: "12px",
                backgroundImage:bgImg.join(', '),
                backgroundPosition:bgPos.join(', '),
                backgroundRepeat:bgRep.join(', ')
            });
            innerSpan.insert({after:ovDiv});
        }

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
			img.writeAttribute("data-is_loaded", "false");
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
					scroll:($('tree_container')?'tree_container':null),
					containerScroll:this.htmlElement.down(".selectable_div")
				}, this, 'filesList');
			}.bind(this), 500);
		}
		if(!ajxpNode.isLeaf())
		{
			AjxpDroppables.add(newRow);
		}		
		return newRow;
	},
		
	partSizeCellRenderer : function(element, ajxpNode, type){
        if(!element) return;
		element.setAttribute("data-sorter_value", ajxpNode.getMetadata().get("bytesize"));
		if(!ajxpNode.getMetadata().get("target_bytesize")){
			return;
		}
		var percent = parseInt( parseInt(ajxpNode.getMetadata().get("bytesize")) / parseInt(ajxpNode.getMetadata().get("target_bytesize")) * 100  );
		var uuid = 'ajxp_'+(new Date()).getTime();		
		var div = new Element('div', {style:'padding-left:3px;', className:'text_label'}).update('<span class="percent_text" style="line-height:19px;padding-left:5px;">'+percent+'%</span>');
		var span = new Element('span', {id:uuid}).update('0%');		
		var options = {
			animate		: true,										// Animate the progress? - default: true
			showText	: false,									// show text with percentage in next to the progressbar? - default : true
			width		: 80,										// Width of the progressbar - don't forget to adjust your image too!!!
			boxImage	: window.ajxpResourcesFolder+'/images/progress_box_80.gif',			// boxImage : image around the progress bar
			barImage	: window.ajxpResourcesFolder+'/images/progress_bar_80.gif',	// Image to use in the progressbar. Can be an array of images too.
			height		: 8,										// Height of the progressbar - don't forget to adjust your image too!!!
            visualStyle : 'position:relative;'
		};
		element.update(div);
		div.insert({top:span});
		if(ajxpNode.getMetadata().get("process_stoppable")){
			var stopButton = new Element('a', {className:'pg_cancel_button'}).update("X");
			stopButton.observe("click", function(){
				var conn = new Connexion();
				conn.setParameters({
					action: 'stop_dl',
					file : ajxpNode.getPath(),
					dir : this.getCurrentContextNode().getPath()
				});
				conn.onComplete = function(transport){
					if(transport.responseText == 'stop' && $(uuid).pe) {
						$(uuid).pe.stop();
						$(uuid).pgBar.setPercentage(0);
						window.setTimeout(function(){
							ajaxplorer.actionBar.fireAction("refresh");
						}, 2);
					}
				};
				conn.sendAsync();
			});
			div.insert({bottom:stopButton});			
		}
		span.setAttribute('data-target_size', ajxpNode.getMetadata().get("target_bytesize"));
		window.setTimeout(function(){
			span.pgBar = new JS_BRAMUS.jsProgressBar(span, percent, options);
			var pe = new PeriodicalExecuter(function(){
				if(!$(uuid)){ 
					pe.stop();
					return;
				}
				var conn = new Connexion();
				conn.setParameters({
					action: 'update_dl_data',
					file : ajxpNode.getPath()
				});
				conn.onComplete = function(transport){
					if(transport.responseText == 'stop'){
						pe.stop();
						ajaxplorer.actionBar.fireAction("refresh");
					}else{
						var newPercentage = parseInt( parseInt(transport.responseText)/parseInt($(uuid).getAttribute('data-target_size'))*100 );
						$(uuid).pgBar.setPercentage(newPercentage);
						$(uuid).next('span.percent_text').update(newPercentage+"%");
					}
				};
				conn.sendAsync();
			}, 2);
			$(uuid).pe = pe;
		}, 2);
	},
	
	/**
	 * Resize the thumbnails
	 * @param one_element HTMLElement Optionnal, if empty all thumbnails are resized.
	 */
	resizeThumbnails: function(one_element){
			
		var defaultMargin = 5;
		var elList;
		if(one_element) elList = [one_element]; 
		else elList = this._htmlElement.select('div.thumbnail_selectable_cell');
		elList.each(function(element){
			var node = element.ajxpNode;
			var image_element = element.IMAGE_ELEMENT || element.select('img')[0];		
			var label_element = element.LABEL_ELEMENT || element.select('.thumbLabel')[0];
			var tSize = this._thumbSize;
			var tW, tH, mT, mB;
			if(image_element.resizePreviewElement && image_element.getAttribute("data-is_loaded") == "true")
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
	/**
	 * For list mode, recompute alternate BG distribution
	 * Should use CSS3 when possible!
	 */
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
	/**
	 * Clear the current lines/thumbs 
	 */
	removeCurrentLines: function(skipFireChange){
        this.notify("rows:willClear");
		var rows;		
		if(this._displayMode == "list") rows = $(this._htmlElement).select('tr');
		else if(this._displayMode == "thumb") rows = $(this._htmlElement).select('div.thumbnail_selectable_cell');
		for(i=0; i<rows.length;i++)
		{
			try{
				rows[i].innerHTML = '';
				if(rows[i].IMAGE_ELEMENT){
                    if(rows[i].IMAGE_ELEMENT.destroyElement){
                        rows[i].IMAGE_ELEMENT.destroyElement();
                    }
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
		if(!skipFireChange) this.fireChange();
        this.notify("rows:didClear");
	},
	/**
	 * Add a "loading" image on top of the component
	 */
	setOnLoad: function()	{
		if(this.loading) return;
		addLightboxMarkupToElement(this.htmlElement);
		var img = new Element('img', {
			src : ajxpResourcesFolder+'/images/loadingImage.gif'
		});
		var overlay = this.htmlElement.down("#element_overlay");
		overlay.insert(img);
		img.setStyle({marginTop : Math.max(0, (overlay.getHeight() - img.getHeight())/2) + "px"});
		this.loading = true;
	},
	/**
	 * Remove the loading image
	 */
	removeOnLoad: function(){
		removeLightboxFromElement(this.htmlElement);
		this.loading = false;
	},
	
	/**
	 * Overrides base fireChange function
	 */
	fireChange: function()
	{		
		if(this._fireChange){
            if(this._dataModel){
                this._dataModel.setSelectedNodes(this.getSelectedNodes());
            }else{
                ajaxplorer.updateContextData(null, this.getSelectedNodes(), this);
            }
		}
	},
	
	/**
	 * Overrides base fireDblClick function
	 */
	fireDblClick: function (e) 
	{
		if(this.getCurrentContextNode().getAjxpMime() == "ajxp_recycle")
		{
			return; // DO NOTHING IN RECYCLE BIN
		}
		selRaw = this.getSelectedItems();
		if(!selRaw || !selRaw.length)
		{
			return; // Prevent from double clicking header!
		}
		var selNode = selRaw[0].ajxpNode;
        if(this._doubleClickListener){
            this._doubleClickListener(selNode);
            return;
        }
        if(this._dataModel) return;
		if(selNode.isLeaf())
		{
			ajaxplorer.getActionBar().fireDefaultAction("file");
		}
		else
		{
			ajaxplorer.getActionBar().fireDefaultAction("dir", selNode);
		}
	},

	/**
	 * Select a row/thum by its name
	 * @param fileName String
	 * @param multiple Boolean
	 */
	selectFile: function(fileName, multiple)
	{
		fileName = getBaseName(fileName);
		if(!ajaxplorer.getContextHolder().fileNameExists(fileName))
		{
			return;
		}
		var allItems = this.getItems();
		for(var i=0; i<allItems.length; i++)
		{
			if(getBaseName(allItems[i].ajxpNode.getPath()) == getBaseName(fileName))
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
	
	/**
	 * Utilitary for selection behaviour
	 * @param target HTMLElement
	 */
	enableTextSelection : function(target){
		if (target.origOnSelectStart)
		{ //IE route
			target.onselectstart=target.origOnSelectStart;
		}
		target.unselectable = "off";
		target.style.MozUserSelect = "text";
	},
	
	/**
	 * Utilitary for selection behaviour
	 * @param target HTMLElement
	 * @param deep Boolean
	 */
	disableTextSelection: function(target, deep)
	{
		if (target.onselectstart)
		{ //IE route
			target.origOnSelectStart = target.onselectstart;
			target.onselectstart=function(){return false;};
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
	
	/**
	 * Handler for keyDown event
	 * @param event Event
	 * @returns Boolean
	 */
	keydown: function (event)
	{
		if(ajaxplorer.blockNavigation || this.blockNavigation) return false;
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
	/**
	 * Utilitary to find the next item to select, depending on the key (up or down) 
	 * @param currentItemIndex Integer
	 * @param bDown Boolean
	 * @returns Integer|null
	 */
	findOverlappingItem: function(currentItemIndex, bDown)
	{	
		if(!bDown && currentItemIndex == 0) return null;
		var allItems = this.getItems();
		if(bDown && currentItemIndex == allItems.length - 1) return null;
		
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
	
	/**
	 * Check if a domnode is indeed an item of the list
	 * @param node DOMNode
	 * @returns Boolean
	 */
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
	/**
	 * Get all items
	 * @returns Array
	 */
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
					tmp[j++] = cs[i];
			}
			return tmp;
		}
	},
	/**
	 * Find an item index
	 * @param el HTMLElement
	 * @returns Integer
	 */
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
	/**
	 * Get an item by its index
	 * @param nIndex Integer
	 * @returns HTMLElement
	 */
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
