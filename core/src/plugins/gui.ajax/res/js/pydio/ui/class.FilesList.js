/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

/**
 * The godzilla of Pydio, should be split in smaller pieces..
 * This grid displays either a table of rows or a grid of thumbnail.
 */
Class.create("FilesList", SelectableElements, {
	
	__implements : ["IAjxpWidget", "IFocusable", "IContextMenuable", "IActionProvider"],

    __allObservers : null,
    __currentInstanceIndex:1,
    _dataModel:null,
    _doubleClickListener:null,
    _previewFactory : null,
    _detailThumbSize : 28,
    _inlineToolbarOptions: null,
    _instanciatedToolbars : null,
    // Copy get/setUserPrefs from AjxpPane
    getUserPreference: AjxpPane.prototype.getUserPreference,
    setUserPreference: AjxpPane.prototype.setUserPreference,

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
        this._previewFactory = new PreviewFactory();
        this._previewFactory.sequencialLoading = true;
        this.__allObservers = $A();
        this._parsingCache = new $H();
        this._filters = $H();

		if(typeof initDefaultDispOrOptions == "string"){
			this.options = {};
			this._displayMode = initDefaultDispOrOptions;
		}else{
			this.options = initDefaultDispOrOptions;
            if(this.options.displayMode) {
                this._displayMode = this.options.displayMode;
            }else {
                this._displayMode = 'detail';
            }
            if(this.options.dataModel){
                this._dataModel = this.options.dataModel;
            }
            if(this.options.doubleClickListener){
                this._doubleClickListener = this.options.doubleClickListener;
            }
            if(this.options.detailThumbSize){
                this._detailThumbSize = this.options.detailThumbSize;
            }
            if(this.options.inlineToolbarOptions){
                this._inlineToolbarOptions = this.options.inlineToolbarOptions;
                this._instanciatedToolbars = $A();
            }
		}
        if(this.options.fit && this.options.fit == "content"){
            this.options.replaceScroller = false;
        }
        if(!FilesList.staticIndex) {
            FilesList.staticIndex = 1;
        }else{
            FilesList.staticIndex ++;
        }
        this.__currentInstanceIndex = FilesList.staticIndex;

        var userLoggedObserver = function(){
			if(!ajaxplorer || !ajaxplorer.user || !this.htmlElement) return;
			var disp = this.getUserPreference("display");
			if(disp && (disp == 'thumb' || disp == 'list' || disp == 'detail')){
				if(disp != this._displayMode) this.switchDisplayMode(disp);
			}
			this._thumbSize = parseInt(this.getUserPreference("thumb_size"));
			if(this.slider){
				this.slider.setValue(this._thumbSize);
				this.resizeThumbnails();
			}
		}.bind(this);
        this._registerObserver(document, "ajaxplorer:user_logged", userLoggedObserver);

		
		var loadObserver = this.contextObserver.bind(this);
		var childAddedObserver = this.childAddedToContext.bind(this);
		var loadingObs = this.setOnLoad.bind(this);
		var loadEndObs = this.removeOnLoad.bind(this);
        var contextChangedObserver = function(event){
			var newContext = event.memo;
			var previous = this.crtContext;
			if(previous){
				previous.stopObserving("loaded", loadEndObs);
				previous.stopObserving("loading", loadingObs);
				previous.stopObserving("child_added", childAddedObserver);
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
			this.crtContext.observe("child_added",childAddedObserver);

		}.bind(this);
        var componentConfigObserver = function(event){
            if(!this.htmlElement) return;
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
            var origFC = this._fireChange;
			this._fireChange = false;
            this.setSelectedNodes(dm.getSelectedNodes());
            this._fireChange = origFC;
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
        var userDisplay = this.getUserPreference("display");
        if(userDisplay) this._displayMode = userDisplay;
		this._thumbSize = 64;
		this._crtImageIndex = 0;
        if(this.options.fixedThumbSize){
            this._fixedThumbSize = this.options.fixedThumbSize;
        }
	
		this._pendingFile = null;
		this.allDraggables = $A();
		this.allDroppables = $A();
		
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
        if(!this._dataModel){
            this._registerObserver(document, "ajaxplorer:trigger_repository_switch", repoSwitchObserver);
        }else{
            document.fire("ajaxplorer:datamodel-loaded-"+this.htmlElement.id);
        }
        if(this.options.messageBoxReference && ajaxplorer){
            ajaxplorer.registerAsMessageBoxReference(this.htmlElement);
        }

	},

    getDataModel: function(){
        return this._dataModel;
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
	destroy : function($super){
        this.empty(true);
        $super();
        this._clearObservers();
        if(window[this.htmlElement.id]){
            try{delete window[this.htmlElement.id];}catch(e){}
        }
        if(this.boundSizeEvents){
            this.boundSizeEvents.each(function(pair){
                document.stopObserving(pair.key, pair.value);
            });
        }
        if(this.resizeEvents){
            this.resizeEvents.each(function(pair){
                document.stopObserving(pair.key, pair.value);
            });
        }
        if(Class.objectImplements(this, 'IFocusable')){
            ajaxplorer.unregisterFocusable(this);
        }
        if(Class.objectImplements(this, "IActionProvider")){
            this.getActions().each(function(act){
                ajaxplorer.guiActions.unset(act.key);
            }.bind(this));
        }
        if(this.slider) this.slider.destroy();
        if(this.headerMenu) this.headerMenu.destroy();
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
            this.setUserPreference("columns_visibility", this.hiddenColumns);
			this.initGUI();
            this.empty(true);
			this.fill(this.crtContext);
		}
		
	},
	
	/**
	 * Handler for contextChange event 
	 */
	contextObserver : function(e){
		if(!this.crtContext || !this.htmlElement) return;
		//console.log('FILES LIST : FILL');
        var base = getBaseName(this.crtContext.getLabel());
        if(!base){
            try{base = ajaxplorer.user.repositories.get(ajaxplorer.repositoryId).getLabel();}catch(e){}
        }
        if(!this.options.muteUpdateTitleEvent){
            this.htmlElement.fire("editor:updateTitle", base);
        }
        this.empty();
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
            if(config.hasOwnProperty(key)) {
                this[key] = config[key].value;
            }
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
		var refreshGUI = false;
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
                    refreshGUI = true;
				}				
			}
			if(columnsNode.getAttribute('template_name')){
				this.columnsTemplate = columnsNode.getAttribute('template_name');
			}
			// COLUMNS INFO
			var columns = XPathSelectNodes(columnsNode, "column");
			var addColumns = XPathSelectNodes(columnsNode, "additional_column");
            var newCols, sortTypes;
			if(columns.length){
				newCols = $A([]);
				sortTypes = $A([]);
				columns.concat(addColumns);
			}else{
				newCols = this.columnsDef;
				sortTypes = this._oSortTypes;
				columns = addColumns;
			}
			columns.each(function(col){
				var obj = {};
				$A(col.attributes).each(function(att){
					obj[att.nodeName]=att.value;
					if(att.nodeName == "sortType"){
						sortTypes.push(att.value);
					}else if(att.nodeName == "defaultVisibilty" && att.value == "hidden"){
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
                    var userPrefDisp = this.getUserPreference("display");
					if(!userPrefDisp){
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
        var oThisId = this.htmlElement.id;

		var options1 = {
			name:oThisId+'-multi_display',
			src:'view_icon.png',
            icon_class:'icon-th-large',
			text_id:150,
			title_id:151,
			text:MessageHash[150],
			title:MessageHash[151],
			hasAccessKey:false,
			subMenu:true,
			subMenuUpdateImage:true,
			callback: function(){
				if(window.actionArguments){
                    var command;
					if(Object.isString(window.actionArguments[0])){
                        command = window.actionArguments[0];
					}else{
                        command = window.actionArguments[0].command;
					}
                    oThis.switchDisplayMode(command);
                    /*
                    window.setTimeout(function(){
                        var item = this.subMenuItems.staticItems.detect(function(item){return item.command == command;});
                        this.notify("submenu_active", item);
                    }.bind(window.listenerContext), 500);
                    */
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
		var context1 = {
			selection:false,
			dir:true,
			actionBar:true,
			actionBarGroup:oThisId+'-actions',
			contextMenu:false,
			infoPanel:false			
			};
		var subMenuItems1 = {
			staticItems:[
                {text:226,title:227,src:'view_text.png',icon_class:'icon-table',command:'list',hasAccessKey:true,accessKey:'list_access_key'},
                {text:460,title:461,src:'view_list_details.png',icon_class:'icon-th-list',command:'detail',hasAccessKey:true,accessKey:'detail_access_key'},
                {text:228,title:229,src:'view_icon.png',icon_class:'icon-th',command:'thumb',hasAccessKey:true,accessKey:'thumbs_access_key'}
            ]
		};
		// Create an action from these options!
		var multiAction = new Action(options1, context1, {}, {}, subMenuItems1);

        var options2 = {
			name:oThisId+'-thumb_size',
			src:'view_icon.png',
            icon_class:'icon-resize-full',
			text_id:452,
			title_id:453,
			text:MessageHash[452],
			title:MessageHash[453],
			hasAccessKey:false,
			subMenu:false,
			subMenuUpdateImage:false,
			callback: function(){
                oThis.slider.show($(oThisId+'-thumb_size_button'));
			},
			listeners : {
				init:function(){
                    var actBar = window.ajaxplorer.actionBar;
                    oThis.observe('switch-display-mode', function(e){
                        if(oThis._displayMode != 'thumb') actBar.getActionByName(oThisId+'-thumb_size').disable();
                        else actBar.getActionByName(oThisId+'-thumb_size').enable();
                    });
                    window.setTimeout(function(){
                        if(oThis._displayMode != 'thumb') actBar.getActionByName(oThisId+'-thumb_size').disable();
                        else actBar.getActionByName(oThisId+'-thumb_size').enable();
                    }.bind(window.listenerContext), 800);
                }
			}
	    };
		var context2 = {
			selection:false,
			dir:true,
			actionBar:true,
			actionBarGroup:oThisId+'-actions',
			contextMenu:false,
			infoPanel:false
		};
		// Create an action from these options!
		var thumbsizeAction = new Action(options2, context2, {}, {});

        var options3 = {
			name:oThisId+'-thumbs_sortby',
			src:'view_icon.png',
            icon_class:'icon-sort',
			text_id:450,
			title_id:451,
			text:MessageHash[450],
			title:MessageHash[451],
			hasAccessKey:false,
			subMenu:true,
			subMenuUpdateImage:false,
			callback: function(){
                //oThis.slider.show($('thumb_size_button'));
			},
			listeners : {
				init:function(){
                    var actBar = window.ajaxplorer.actionBar;
                    oThis.observe('switch-display-mode', function(e){
                        if(oThis._displayMode == 'list') actBar.getActionByName(oThisId+'-thumbs_sortby').disable();
                        else actBar.getActionByName(oThisId+'-thumbs_sortby').enable();
                    });
                    window.setTimeout(function(){
                        if(oThis._displayMode == 'list') actBar.getActionByName(oThisId+'-thumbs_sortby').disable();
                        else actBar.getActionByName(oThisId+'-thumbs_sortby').enable();
                    }.bind(window.listenerContext), 800);
                }
			}
	    };
		var context3 = {
			selection:false,
			dir:true,
			actionBar:true,
			actionBarGroup:oThisId+'-actions',
			contextMenu:false,
			infoPanel:false
		};
        var submenuItems3 = {
            dynamicBuilder : function(protoMenu){
                "use strict";
                var items = $A([]);
                var index = 0;
                oThis.columnsDef.each(function(column){
                    var isSorted = this._sortableTable.sortColumn == index;
                    items.push({
                        name:(column.messageId?MessageHash[column.messageId]:column.messageString),
                        alt:(column.messageId?MessageHash[column.messageId]:column.messageString),
                        image:resolveImageSource((isSorted?"column-visible":"transp")+".png", '/images/actions/ICON_SIZE', 16),
                        icon_class:(isSorted?'icon-caret-'+(this._sortableTable.descending?'down':'up'):''),
                        isDefault:false,
                        callback:function(e){
                            var clickIndex = this.columnsDef.indexOf(column);
                            var sorted = (this._sortableTable.sortColumn == clickIndex);
                            if(sorted) this._sortableTable.descending = !this._sortableTable.descending;
                            this._sortableTable.sort(clickIndex, this._sortableTable.descending);
                        }.bind(this)
                    });
                    index++;
                    protoMenu.options.menuItems = items;
                    protoMenu.refreshList();
                }.bind(oThis) );
            }
        };
		// Create an action from these options!
		var thumbSortAction = new Action(options3, context3, {}, {}, submenuItems3);

        var butts = $H();
        butts.set(oThisId+'-thumb_size', thumbsizeAction);
        butts.set(oThisId+'-thumb_sort', thumbSortAction);
        butts.set(oThisId+'-multi_display', multiAction);
        return butts;
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
        $A(['list', 'thumb', 'detail']).each(function(f){
            if(this._displayMode == f){
                this.htmlElement.addClassName('fl-displayMode-' + f);
            } else {
                this.htmlElement.removeClassName('fl-displayMode-' + f);
            }
        }.bind(this));
        var scrollElement, buffer;
		if(this._displayMode == "list")
		{
            this.hiddenColumns = $A();
            var testPref = this.getUserPreference("columns_visibility");
			if(testPref){
                this.hiddenColumns = $A(testPref);
			}
			var visibleColumns = this.getVisibleColumns();			
			var userPref;
			if(this.getUserPreference("columns_size")){
				var data = new Hash(this.getUserPreference("columns_size"));
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
				var leftPadding = this.options.cellPaddingCorrection || 0 ;
				if(column.attributeName == "ajxp_label"){// Will contain an icon
					leftPadding = 24;
				}
				headerData.push({label:label, size:userWidth, leftPadding:leftPadding, metaName:column.attributeName});
			}
			buffer = '<div id="selectable_div_header-'+this.__currentInstanceIndex+'" class="sort-table"></div>';
            buffer += '<div id="selectable_div_header_filter-'+this.__currentInstanceIndex+'" class="sort-table-filter"></div>';
			buffer = buffer + '<div id="table_rows_container-'+this.__currentInstanceIndex+'" class="table_rows_container"><table id="selectable_div-'+this.__currentInstanceIndex+'" class="selectable_div sort-table" width="100%" cellspacing="0"><tbody></tbody></table></div>';
			this.htmlElement.update(buffer);
            var contentContainer = this.htmlElement.down("div.table_rows_container");
            contentContainer.setStyle((this.gridStyle!="grid")
                ?
                {
                    overflowX:"hidden",
                    overflowY:(this.options.replaceScroller?"hidden":"auto"),
                    paddingBottom: '16px'
                }
                    :
                {
                    overflow:"auto",
                    paddingBottom: '0'
                }
            );
            if(this.options.horizontalScroll){
                attachMobileScroll(this.htmlElement, "horizontal");
            }else{
                attachMobileScroll(contentContainer, "vertical");
            }
            scrollElement = contentContainer;
			var oElement = this.htmlElement.down(".selectable_div");
			
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){
                this.htmlElement.addClassName("paginated");
				contentContainer.insert({before:this.createPaginator()});
			}else{
                this.htmlElement.removeClassName("paginated");
            }

            if(this.options.selectable == undefined || this.options.selectable === true){
                this.initSelectableItems(oElement, true, contentContainer, true);
            }else{
                this.initNonSelectableItems(oElement);
            }
			this._headerResizer = new HeaderResizer(this.htmlElement.down('div.sort-table'), {
				headerData : headerData,
				body : contentContainer,
                filterPanel: this.htmlElement.down("div.sort-table-filter"),
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
					var data = this.getUserPreference("columns_size");
					data = (data?new Hash(data):new Hash());
					sizes['type'] = 'percent';
					var id = (this.columnsTemplate?this.columnsTemplate:ajaxplorer.user.getActiveRepository());
					data.set(id, sizes);
                    this.setUserPreference("columns_size", data);
				}.bind(this), 2000);
			}.bind(this) );
            this._headerResizer.observe("filter_update", function(event){
                if(event.metaValue) this.addMetadataFilter(event.metaName, event.metaValue+'*');
                else this.removeMetadataFilter(event.metaName);
            }.bind(this));
			this._sortableTable = new AjxpSortable(oElement, this.getVisibleSortTypes(), this.htmlElement.down('div.sort-table'));
            if(this.options.groupByData) this._sortableTable.setGroupByData(this.options.groupByData);
			this._sortableTable.onsort = function(){
				this.redistributeBackgrounds();
				var ctxt = this.getCurrentContextNode();
				ctxt.getMetadata().set("filesList.sortColumn", ''+this._sortableTable.sortColumn);
				ctxt.getMetadata().set("filesList.descending", this._sortableTable.descending);
			}.bind(this);
			if(this.paginationData && this.paginationData.get('remote_order') && parseInt(this.paginationData.get('total')) > 1){
				this._sortableTable.setPaginationBehaviour(function(params){
                    this.getCurrentContextNode().getMetadata().set("remote_order", params);
                    var oThis = this;
                    this.crtContext.observeOnce("loaded", function(){
                        oThis.crtContext = this ;
                        oThis.fill(oThis.crtContext);
                    });
                    this.getCurrentContextNode().reload();
				}.bind(this), this.getVisibleColumns(), this.paginationData.get('currentOrderCol')||-1, this.paginationData.get('currentOrderDir') );
			}

			this.observer = function(e){
                if(this.options.fit && this.options.fit == 'height') fitHeightToBottom(contentContainer, this.htmlElement);
				if(Prototype.Browser.IE){
					this._headerResizer.resize(contentContainer.getWidth());
				}else{
                    var width = this.htmlElement.getWidth();
                    width -= parseInt(this.htmlElement.getStyle("borderLeftWidth")) + parseInt(this.htmlElement.getStyle("borderRightWidth"));
					this._headerResizer.resize(width);
				}
			}.bind(this);
			this.observe("resize", this.observer);

            if(!this.options.noContextualMenu){
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
		}
		else if(this._displayMode == "thumb" || this._displayMode == "detail")
		{
			if(this.headerMenu){
				this.headerMenu.destroy();
				delete this.headerMenu;
			}
			buffer = '<div class="panelHeader"><div style="float:right;padding-right:5px;font-size:1px;height:16px;"><input type="image" height="16" width="16" src="'+ajxpResourcesFolder+'/images/actions/16/zoom-in.png" id="slider-input-1" style="border:0px;width:16px;height:16px;margin-top:0px;padding:0px;" value="64"/></div>'+MessageHash[126]+'</div>';
			buffer += '<div id="selectable_div-'+this.__currentInstanceIndex+'" class="selectable_div'+(this._displayMode == "detail" ? ' detailed':'')+'" style="overflow:auto;">';
			this.htmlElement.update(buffer);
            if(this.options.horizontalScroll){
                attachMobileScroll(this.htmlElement, "horizontal");
            }else{
                attachMobileScroll(this.htmlElement.down(".selectable_div"), "vertical");
            }
			if(this.paginationData && parseInt(this.paginationData.get('total')) > 1 ){
                this.htmlElement.addClassName("paginated");
                this.htmlElement.down(".selectable_div").insert({before:this.createPaginator()});
			}else{
                this.htmlElement.removeClassName("paginated");
            }
            scrollElement = this.htmlElement.down(".selectable_div");
            if(this.options.horizontalScroll){
                scrollElement.setStyle({width:'100000px'});
                this.htmlElement.setStyle({overflowX:'auto'});
            }
            this.observer = function(e){
                if(this.options.fit && this.options.fit == 'height') fitHeightToBottom.defer(scrollElement, this.htmlElement);
            }.bind(this);
            this.observe("resize", this.observer);
			
			if(this.getUserPreference("thumb_size")){
				this._thumbSize = parseInt(this.getUserPreference("thumb_size"));
			}
			if(this._fixedThumbSize){
				this._thumbSize = parseInt(this._fixedThumbSize);
			}

            this._sortableTable = new AjxpSortable(scrollElement, null, null);
            this._sortableTable.setMetaSortType(this.columnsDef);
            if(this.options.groupByData) this._sortableTable.setGroupByData(this.options.groupByData);
            this._sortableTable.onsort = function(){
                var ctxt = this.getCurrentContextNode();
                ctxt.getMetadata().set("filesList.sortColumn", ''+this._sortableTable.sortColumn);
                ctxt.getMetadata().set("filesList.descending", this._sortableTable.descending);
            }.bind(this);
            if(!this.options.noContextualMenu){
                if(this.headerMenu){
                    this.headerMenu.destroy();
                    delete this.headerMenu;
                }
                this.headerMenu = new Proto.Menu({
                    selector: '#content_pane div.panelHeader',
                    className: 'menu desktop',
                    menuItems: [],
                    fade:true,
                    zIndex:2000,
                    beforeShow : function(){
                        var items = $A([]);
                        var index = 0;
                        this.columnsDef.each(function(column){
                            var isSorted = this._sortableTable.sortColumn == index;
                            items.push({
                                name:(column.messageId?MessageHash[column.messageId]:column.messageString),
                                alt:(column.messageId?MessageHash[column.messageId]:column.messageString),
                                image:resolveImageSource((isSorted?"column-visible":"transp")+".png", '/images/actions/ICON_SIZE', 16),
                                isDefault:false,
                                callback:function(e){
                                    var clickIndex = this.columnsDef.indexOf(column);
                                    var sorted = (this._sortableTable.sortColumn == clickIndex);
                                    if(sorted) this._sortableTable.descending = !this._sortableTable.descending;
                                    this._sortableTable.sort(clickIndex, this._sortableTable.descending);
                                }.bind(this)
                            });
                            index++;
                        }.bind(this) );
                        this.headerMenu.options.menuItems = items;
                        this.headerMenu.refreshList();
                    }.bind(this)
                });
            }

            if(this._displayMode == 'thumb'){
                if(this.slider){
                    this.slider.destroy();
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
                        this.setUserPreference("thumb_size", this._thumbSize);
                    }.bind(this)
                });
            }

			//this.disableTextSelection(scrollElement, true);
            if(this.options.selectable == undefined || this.options.selectable === true){
			    this.initSelectableItems(scrollElement, true, scrollElement, true);
            }else{
                this.initNonSelectableItems(scrollElement);
            }
		}

        if(this.options.replaceScroller){
            this.scroller = new Element('div', {id:'filelist_scroller'+this.__currentInstanceIndex, className:'scroller_track', style:"right:0px"});
            this.scroller.insert('<div id="filelist_scrollbar_handle'+this.__currentInstanceIndex+'" class="scroller_handle"></div>');
            scrollElement.insert({before:this.scroller});
            if(this.gridStyle == "grid"){
                scrollElement.setStyle({
                    overflowY:"hidden",
                    overflowX:"auto",
                    paddingBottom: '16px'
                });
            }else{
                scrollElement.setStyle({
                    overflow:"hidden",
                    paddingBottom: '0'
                });
            }
            this.scrollbar = new Control.ScrollBar(scrollElement,this.scroller);
            if(this.scrollSizeObserver){
                this.stopObserving("resize", this.scrollSizeObserver);
            }
            this.stopObserving("resize", this.observer);
            this.scrollSizeObserver = function(){
                window.setTimeout(function(){
                    if(!this.htmlElement || !this.scrollbar) return;
                    if(this._displayMode == "list" && contentContainer){
                        if(this.options.fit && this.options.fit == 'height') fitHeightToBottom(contentContainer, this.htmlElement);
                        if(Prototype.Browser.IE){
                            this._headerResizer.resize(contentContainer.getWidth());
                        }else{
                            var width = this.htmlElement.getWidth();
                            width -= parseInt(this.htmlElement.getStyle("borderLeftWidth")) + parseInt(this.htmlElement.getStyle("borderRightWidth"));
                            this._headerResizer.resize(width);
                        }
                    }else{
                        if(this.options.fit && this.options.fit == 'height') fitHeightToBottom(scrollElement, this.htmlElement);
                    }
                    this.scroller.setStyle({height:parseInt(scrollElement.getHeight())+"px"});
                    this.scrollbar.recalculateLayout();
                }.bind(this), 0.01);
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
		currentInput.observe("focus", function(){ajaxplorer.disableAllKeyBindings();}.bind(this));
		currentInput.observe("blur", function(){ajaxplorer.enableAllKeyBindings();}.bind(this));
		currentInput.observe("keydown", function(event){
			if(event.keyCode == Event.KEY_RETURN){
				Event.stop(event);
                currentInput.blur();
				var new_page = parseInt(currentInput.getValue());
				if(new_page == current) return; 
				if(new_page < 1 || new_page > total){
					ajaxplorer.displayMessage('ERROR', MessageHash[335] +' '+ total);
					currentInput.setValue(current);
					return;
				}
				var node = this.getCurrentContextNode();
				node.getMetadata().get("paginationData").set("new_page", new_page);
                if(this._dataModel) this._dataModel.requireContextChange(node);
                else ajaxplorer.updateContextData(node);
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
            if(this._dataModel) this._dataModel.requireContextChange(node);
			else ajaxplorer.updateContextData(node);
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
    	}else if(this.options.fit && this.options.fit == 'content' && this.options.horizontalScroll){
            this.htmlElement.setStyle({height:(this._thumbSize + 60) + 'px'});
        }
    	if(this.htmlElement.down('.table_rows_container') && Prototype.Browser.IE && this.gridStyle == "file"){
            this.htmlElement.down('.table_rows_container').setStyle({width:'100%'});
    	}
        if(this._displayMode == 'thumb'){
            this.resizeThumbnails(null, true);
        }
		this.notify("resize");
        document.fire("ajaxplorer:resize-FilesList-" + this.htmlElement.id, this.htmlElement.getDimensions());
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
        if(show) {
            if(ajaxplorer) {
                if(!this._dataModel){
                    ajaxplorer.updateContextData(null, this.getSelectedNodes(), this);
                }
                ajaxplorer.focusOn(this);
            }
            this.htmlElement.setStyle({display:'block'});
        }else{
            this.blur();
            if(!this._dataModel && ajaxplorer){
                ajaxplorer.updateContextData(null, [], this);
            }
            this.htmlElement.setStyle({display:'none'});
        }
	},
	
	/**
	 * Switch between various display modes. At the moment, thumb and list.
	 * Should keep the selected nodes after switch
	 * @param mode String "thumb" or "list
	 * @returns String
	 */
	switchDisplayMode: function(mode){
        if(this.options.fixedDisplayMode) {
            if(this.options.fixedDisplayMode == this._displayMode) return this._displayMode;
            else mode = this.options.fixedDisplayMode;
        }
        this.removeCurrentLines(true);

        if(mode){
            this._displayMode = mode;
        }else{
            this._displayMode = (this._displayMode == "thumb"?"list":"thumb");
        }
        this.notify('switch-display-mode');
		this.initGUI();
        this.empty(true);
		this.fill(this.getCurrentContextNode());
		this.fireChange();
        this.setUserPreference("display", this._displayMode);
        document.fire("ajaxplorer:switchDisplayMode-FilesList-" + this.htmlElement.id);
		return this._displayMode;
	},
	
	/**
	 * Returns the display mode
	 * @returns {String}
	 */
	getDisplayMode: function(){
		return this._displayMode;
	},

    initRowsBuffered : function(){
        if(this.iRBTimer){
            window.clearTimeout(this.iRBTimer);
            this.iRBTimer = null;
        }
        this.iRBTimer = window.setTimeout(function(){
            this.initRows();
            this.iRBTimer = null;
        }.bind(this), 200);
    },

	/**
     *
	 * Called after the rows/thumbs are populated
	 */
	initRows: function(){
        this.notify("rows:willInitialize");
		// Disable text select on elements
		if(this._displayMode == "thumb" || this._displayMode == "detail")
		{
			var adjusted = this.resizeThumbnails();
			if(this.protoMenu) {
                this.protoMenu.addElements('#selectable_div-'+this.__currentInstanceIndex);
                this.protoMenu.addElements('#selectable_div-'+this.__currentInstanceIndex + ' > .ajxpNodeProvider');
            }
			window.setTimeout(function(){
                if(adjusted) this._previewFactory.setThumbSize(adjusted);
                else this._previewFactory.setThumbSize((this._displayMode=='detail'? this._detailThumbSize:this._thumbSize));
                this._previewFactory.loadNextImage();
            }.bind(this),10);
		}
		else
		{
			if(this.protoMenu){
                this.protoMenu.addElements('#table_rows_container-'+this.__currentInstanceIndex);
                this.protoMenu.addElements('#table_rows_container-'+this.__currentInstanceIndex+ ' > .ajxpNodeProvider');
            }
			if(this._headerResizer){
				this._headerResizer.resize(this.htmlElement.getWidth()-2);
			}
		}
        /*
		var allItems = this.getItems();
		for(var i=0; i<allItems.length;i++)
		{
			this.disableTextSelection.bind(this).defer(allItems[i], true);
		}
		*/
        this.notify("resize");
        this.notify("rows:didInitialize");
	},


	/**
	 * Triggers a reload of the rows/thumbs
	 * @param additionnalParameters Object
	 */
	reload: function(additionnalParameters){
		if(this.getCurrentContextNode()){
            this.empty();
			this.fill(this.getCurrentContextNode());
		}
	},

    softReload: function(){
        this.setSelectedNodes([]);
        this.removeCurrentLines(true);
        this.fill(this.getCurrentContextNode(), true);
    },

    empty : function(skipFireChange){
        this._previewFactory.clear();
        if(this.protoMenu){
            if(this._displayMode == "thumb" || this._displayMode == "detail"){
                this.protoMenu.removeElements('#selectable_div-'+this.__currentInstanceIndex + ' > .ajxpNodeProvider');
                this.protoMenu.removeElements('#selectable_div-'+this.__currentInstanceIndex);
            }else{
                this.protoMenu.removeElements('#table_rows_container-'+this.__currentInstanceIndex);
                this.protoMenu.removeElements('#table_rows_container-'+this.__currentInstanceIndex+ ' > .ajxpNodeProvider');
            }
        }
        for(var i = 0; i< AllAjxpDroppables.length;i++){
            var el = AllAjxpDroppables[i];
            if(this.isItem(el)){
                Droppables.remove(AllAjxpDroppables[i]);
                delete(AllAjxpDroppables[i]);
            }
        }
        for(i = 0;i< window.AllAjxpDraggables.length;i++){
            if(window.AllAjxpDraggables[i] && window.AllAjxpDraggables[i].element && this.isItem(window.AllAjxpDraggables[i].element)){
                  if(window.AllAjxpDraggables[i].element.IMAGE_ELEMENT){
                      try{
                          if(window.AllAjxpDraggables[i].element.IMAGE_ELEMENT.destroyElement){
                              window.AllAjxpDraggables[i].element.IMAGE_ELEMENT.destroyElement();
                          }
                          window.AllAjxpDraggables[i].element.IMAGE_ELEMENT = null;
                          delete window.AllAjxpDraggables[i].element.IMAGE_ELEMENT;
                      }catch(e){}
                  }
                Element.remove(window.AllAjxpDraggables[i].element);
            }
        }
        window.AllAjxpDraggables = $A([]);

        var items = this.getSelectedItems();
        var setItemSelected = this.setItemSelected.bind(this);
        for(i=0; i<items.length; i++)
        {
            setItemSelected(items[i], false);
        }
        this.removeCurrentLines(skipFireChange);
    },

    makeItemRefreshObserver: function (ajxpNode, item, renderer){
        return function(){
            //try{
                if(item.ajxpNode) {
                    if(item.REMOVE_OBS) item.ajxpNode.stopObserving("node_removed", item.REMOVE_OBS);
                    if(item.REPLACE_OBS) item.ajxpNode.stopObserving("node_replaced", item.REPLACE_OBS);
                }
                if(!item.parentNode){
                    return;
                }
                var newItem = renderer(ajxpNode, item);
                item.insert({before: newItem});
                item.remove();
                newItem.ajxpNode = ajxpNode;
                newItem.addClassName("ajxpNodeProvider");
                if(ajxpNode.isLeaf()) newItem.addClassName("ajxpNodeLeaf");
                this.initRows();
                item.ajxpNode = null;
                delete item;
                newItem.REPLACE_OBS = this.makeItemRefreshObserver(ajxpNode, newItem, renderer);
                newItem.REMOVE_OBS = this.makeItemRemovedObserver(ajxpNode, newItem);
                ajxpNode.observe("node_replaced", newItem.REPLACE_OBS);
                ajxpNode.observe("node_removed", newItem.REMOVE_OBS);
                var dm = (this._dataModel?this._dataModel:ajaxplorer.getContextHolder());
                if(dm.getSelectedNodes() && dm.getSelectedNodes().length && dm.getSelectionSource() == this)
                {
                    var selectedNodes = dm.getSelectedNodes();
                    this._selectedItems = [];
                    for(var f=0;f<selectedNodes.length; f++){
                        if(Object.isString(selectedNodes[f])){
                            this.selectFile(selectedNodes[f], true);
                        }else{
                            this.selectFile(selectedNodes[f].getPath(), true);
                        }
                    }
                    if(dm.getSelectionSource() == this) this.hasFocus = true;
                }
            //}catch(e){

            //}
        }.bind(this);
    },

    makeItemRemovedObserver: function (ajxpNode, item){
        return function(){
            try{
                if(this.loading) return;
                this.setItemSelected(item, false);
                if(item.ajxpNode) {
                    if(item.REMOVE_OBS) item.ajxpNode.stopObserving("node_removed", item.REMOVE_OBS);
                    if(item.REPLACE_OBS) item.ajxpNode.stopObserving("node_replaced", item.REPLACE_OBS);
                    if(!item.parentNode){
                        item =  this.htmlElement.down('[id="'+item.ajxpNode.getPath()+'"]');
                    }
                }
                item.ajxpNode = null;
                if(Prototype.Browser.IE && Prototype.Version.startsWith('1.6')){
                    window.setTimeout(function(){
                        item.remove();
                        delete item;
                        this.initRowsBuffered();
                    }.bind(this), 10);
                }else{
                    new Effect.RowFade(item, {afterFinish:function(){
                        try{
                            item.remove();
                        }catch(e){if(console) console.log(e);}
                        delete item;
                        this.initRowsBuffered();
                    }.bind(this), duration:0.2});
                }
                /*
                item.remove();
                delete item;
                this.initRowsBuffered();
                */
            }catch(e){

            }
        }.bind(this);
    },

    childAddedToContext : function(childPath){

        if(this.loading) return;
        var renderer = this.getRenderer(); //(this._displayMode == "list"?this.ajxpNodeToTableRow.bind(this):this.ajxpNodeToDiv.bind(this));
        var child = this.crtContext.findChildByPath(childPath);
        if(!child) return;
        if(this._rejectNodeByFilters(child)){
            return;
        }
        var newItem;
        newItem = renderer(child);
        newItem.ajxpNode = child;
        newItem.addClassName("ajxpNodeProvider");
        if(child.isLeaf()) newItem.addClassName("ajxpNodeLeaf");
        newItem.REPLACE_OBS = this.makeItemRefreshObserver(child, newItem, renderer);
        newItem.REMOVE_OBS = this.makeItemRemovedObserver(child, newItem);
        child.observe("node_replaced", newItem.REPLACE_OBS);
        child.observe("node_removed", newItem.REMOVE_OBS);

        if(this._sortableTable){
            var sortColumn = this.crtContext.getMetadata().get("filesList.sortColumn");
         	var descending = this.crtContext.getMetadata().get("filesList.descending");
            if(sortColumn == undefined || !this.columnsDef[sortColumn]) {
                sortColumn = 0;
            }
            if(sortColumn != undefined){
                sortColumn = parseInt(sortColumn);
                var sortFunction = this._sortableTable.getSortFunction(this._sortableTable.getSortType(sortColumn), sortColumn);
                var sortCache = this._sortableTable.getCache(this._sortableTable.getSortType(sortColumn), sortColumn);
                sortCache.sort(sortFunction);
                for(var i=0;i<sortCache.length;i++){
                    if(sortCache[i].element == newItem){
                        if(i == 0) $(newItem.parentNode).insert({top:newItem});
                        else {
                            if(sortCache[i-1].element.ajxpNode.getPath() == newItem.ajxpNode.getPath()){
                                $(newItem.parentNode).remove(newItem);
                                break;
                            }
                            sortCache[i-1].element.insert({after:newItem});
                        }
                        break;
                    }
                }
                this._sortableTable.destroyCache(sortCache);
            }
        }
        this.initRows();


    },

    getRenderer : function(){
        if(this._displayMode == "thumb") return this.ajxpNodeToDiv.bind(this);
        else if(this._displayMode == "detail") return this.ajxpNodeToLargeDiv.bind(this);
        else if(this._displayMode == "list") return this.ajxpNodeToTableRow.bind(this);
    },

	/**
	 * Populates the list with the children of the passed contextNode
	 * @param contextNode AjxpNode
	 */
	fill: function(contextNode, forceNoRefreshGui){

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
			var componentData = XPathSelectSingleNode(clientConfigs, 'component_config');
			if(componentData && componentData.getAttribute('className') && componentData.getAttribute('className')=="FilesList"){
				refreshGUI = this.parseComponentConfig(componentData);
			}
		}else if(this.restoreConfig){
			this.applyComponentConfig(this.restoreConfig);
			this.restoreConfig = null;
			refreshGUI = true;
		}
		
		if(refreshGUI && !forceNoRefreshGui){
			this.initGUI();
		}
		
		// NOW PARSE LINES
		this.clearParsingCache();
		var children = contextNode.getChildren();
        var renderer = this.getRenderer();
		for (var i = 0; i < children.length ; i++) 
		{
			var child = children[i];
            if(this._rejectNodeByFilters(child)){
                continue;
            }
			var newItem;
            newItem = renderer(child);
			newItem.ajxpNode = child;
            newItem.addClassName("ajxpNodeProvider");
            if(child.isLeaf()) newItem.addClassName("ajxpNodeLeaf");
            newItem.REPLACE_OBS = this.makeItemRefreshObserver(child, newItem, renderer);
            newItem.REMOVE_OBS = this.makeItemRemovedObserver(child, newItem);
            child.observe("node_replaced", newItem.REPLACE_OBS);
            child.observe("node_removed", newItem.REMOVE_OBS);
		}
		this.initRows();
		
		if((!this.paginationData || !this.paginationData.get('remote_order')))
		{
			this._sortableTable.sortColumn = -1;
			this._sortableTable.updateHeaderArrows();
		}
        if(this.options.fixedSortColumn && this.options.fixedSortDirection){
            var col = this.columnsDef.detect(function(c){
                return c.attributeName == this.options.fixedSortColumn;
            }.bind(this));
            if(col){
                var index = this.columnsDef.indexOf(col);
                this._sortableTable.sort(index, (this.options.fixedSortDirection=="desc"));
                this._sortableTable.updateHeaderArrows();
            }
        }else if(contextNode.getMetadata().get("filesList.sortColumn") && this.columnsDef[contextNode.getMetadata().get("filesList.sortColumn")]){
			var sortColumn = parseInt(contextNode.getMetadata().get("filesList.sortColumn"));
			var descending = contextNode.getMetadata().get("filesList.descending");
			this._sortableTable.sort(sortColumn, descending);
			this._sortableTable.updateHeaderArrows();
		}
        var dm = (this._dataModel?this._dataModel:ajaxplorer.getContextHolder());
		if(dm.getSelectedNodes() && dm.getSelectedNodes().length && dm.getSelectionSource() == this)
		{
			var selectedNodes = dm.getSelectedNodes();
            for(var f=0;f<selectedNodes.length; f++){
                if(Object.isString(selectedNodes[f])){
                    this.selectFile(selectedNodes[f], true);
                }else{
                    this.selectFile(selectedNodes[f].getPath(), true);
                }
            }
            if(dm.getSelectionSource() == this) this.hasFocus = true;
		}
		if(this.hasFocus){
			window.setTimeout(function(){ajaxplorer.focusOn(this);}.bind(this),200);
		}
	},

    toggleFilterPane: function(){
        this.htmlElement.toggleClassName('fl-showFilterPane');
        if(!this.htmlElement.hasClassName('fl-showFilterPane')){
            this.clearMetadataFilters();
        }
        window.setTimeout(function(){this.resize()}.bind(this), 0);
    },

    addMetadataFilter: function(metaName, metaValue){
        if(!metaValue){
            this._filters.unset(metaName);
        }else{
            this._filters.set(metaName, metaValue);
        }
        this.softReload();
    },

    removeMetadataFilter: function(metaName){
        this._filters.unset(metaName);
        this.softReload();
    },

    clearMetadataFilters: function(){
        this._filters = $H();
        this.softReload();
    },

    _rejectNodeByFilters: function(ajxpNode){
        var reject = false;
        this._filters.each(function(pair){
            if(this._filterNodeByMetadata(ajxpNode, pair.key, pair.value)){
                reject = true;
                throw $break;
            }
        }.bind(this));
        return reject;
    },

    _filterNodeByMetadata: function(ajxpNode, metaName, metaValue){
        var currentMeta = ajxpNode.getMetadata().get(metaName);
        if(!currentMeta) currentMeta = '';
        metaValue = metaValue.toLowerCase();
        currentMeta = currentMeta.toLocaleLowerCase();
        var reject = false;
        var correspond = false;
        if(metaValue.startsWith('!')){
            reject = true;
            metaValue = metaValue.replace('!', '');
        }
        if(metaValue.endsWith('*')){
            correspond = (currentMeta.indexOf(metaValue.replace("*", "")) !== -1);
        }else{
            correspond = (currentMeta == metaValue);
        }
        if(reject) return correspond;
        else return !correspond;
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
        var addStyle = {fontSize: '12px'};
        var span, posSpan;
        if(this._displayMode == "list"){
            span = item.select('span.text_label')[0];
            posSpan = item.select('span.list_selectable_span')[0];
            offset.top=-3;
            offset.left=25;
            scrollTop = this.htmlElement.down('div.table_rows_container').scrollTop;
        }else if(this._displayMode == "thumb"){
            span = item.select('div.thumbLabel')[0];
            posSpan = span;
            offset.top=-2;
            offset.left=3;
            scrollTop = this.htmlElement.down('.selectable_div').scrollTop;
        }else if(this._displayMode == "detail"){
            span = item.select('div.thumbLabel')[0];
            posSpan = span;
            offset.top=0;
            offset.left= 0;
            scrollTop = this.htmlElement.down('.selectable_div').scrollTop;
            addStyle = {
                fontSize : '20px',
                paddingLeft: '2px',
                color: 'rgb(111, 121, 131)'
            };
        }
		var pos = posSpan.cumulativeOffset();
		var text = span.innerHTML;
        if(!item.ajxpNode){
            item.ajxpNode = $(item.id).ajxpNode;
        }
		var edit = new Element('input', {value:item.ajxpNode.getLabel('text'), id:'editbox'}).setStyle({
			zIndex:5000, 
			position:'absolute',
			marginLeft:'0px',
			marginTop:'0px',
			height:'24px',
            padding: 0
		});
        edit.setStyle(addStyle);
		$(document.getElementsByTagName('body')[0]).insert({bottom:edit});				
		modal.showContent('editbox', (posSpan.getWidth()-offset.left)+'', '26', true, false, {opacity:0.25, backgroundColor:'#fff'});
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
        buttons.addClassName("inlineEdition");
		var ok = buttons.select('input[name="ok"]')[0];
		ok.observe("click", onOkAction);
		var origWidth = edit.getWidth()-44;
		var newWidth = origWidth;
		if(origWidth < 70){
			// Offset edit box to be sure it's always big enough.
			edit.setStyle({left:pos.left+offset.left - 70 + origWidth});
			newWidth = 70;
		}
        if(this._displayMode == "detail") {
            origWidth -= 20;
            newWidth -= 20;
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
			top:((pos.top+offset.top-scrollTop)+1)+'px'
		});
		var closeFunc = function(){
			span.setStyle({color:''});
			edit.remove();
			buttons.remove();
		};
		span.setStyle({color:'#ddd'});
		modal.setCloseAction(closeFunc);
	},

    getFromCache:function(cacheKey){
        var result;
        if(!this._parsingCache.get(cacheKey)){
            result = $H();
            var columns;
            switch(cacheKey){
                case "visibleColumns":
                    columns = this.getVisibleColumns();
                    break;
                case "hiddenColumns":
                    columns = $A();
                    var hColumns = this.hiddenColumns;
                    hColumns.each(function(colName){
                        var colObject = this.columnsDef.detect(function(element){
                            return (element.attributeName == colName);
                        });
                        if(colObject) columns.push(colObject);
                    }.bind(this));
                    break;
                case "tBody":
                    var tBody = $(this._htmlElement).down("tbody");
                    this._parsingCache.set('tBody', tBody);
                    return tBody;
                default :
                    columns = this.getColumnsDef();
                    break;
            }
            columns.each(function(column){
                if(column.modifier && !column.modifierFunc) {
                    try{
                        column.modifierFunc = eval(column.modifier);
                    }catch (e){}
                }
                result.set(column.attributeName, column);
            });
            this._parsingCache.set(cacheKey, result);
        }else{
            result = this._parsingCache.get(cacheKey);
        }
        return result;
    },

    clearParsingCache: function(){
        this._parsingCache = new $H();
    },

	/**
	 * Populate a node as a TR element
	 * @param ajxpNode AjxpNode
     * @param HTMLElement replaceItem
	 * @returns HTMLElement
	 */
	ajxpNodeToTableRow: function(ajxpNode, replaceItem){
		var metaData = ajxpNode.getMetadata();
		var newRow = new Element("tr", {id:"item-"+slugString(ajxpNode.getPath())});
		var tBody = this.getFromCache('tBody');

		metaData.each(function(pair){
			//newRow.setAttribute(pair.key, pair.value);
			if(Prototype.Browser.IE && pair.key == "ID"){
				newRow.setAttribute("ajxp_sql_"+pair.key, pair.value);
			}			
		});
		var attributeList = this.getFromCache("visibleColumns");
		var attKeys = attributeList.keys();
		for(var i = 0; i<attKeys.length;i++ ){
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

                var backgroundPosition = this.options.iconBgPosition || '4px 2px';
                var backgroundImage = 'url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                if(metaData.get('overlay_class') && ajaxplorer.currentThemeUsesIconFonts){
                    metaData.get('overlay_class').split(',').each(function(c){
                        textLabel.insert(new Element('span', {className:c+' overlay-class-span'}));
                    });
                }else if(metaData.get('overlay_icon') && Modernizr.multiplebgs){
                    var ovIcs = metaData.get('overlay_icon').split(',');
                    switch(ovIcs.length){
                        case 1:
                            backgroundPosition = '14px 11px, ' + backgroundPosition;
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                        case 2:
                            backgroundPosition = '2px 11px, 14px 11px, ' + backgroundPosition;
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[1], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                        case 3:
                            backgroundPosition = '14px 2px, 2px 11px, 14px 11px, ' + backgroundPosition;
                            backgroundImage = 'url("'+resolveImageSource(ovIcs[0], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[1], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(ovIcs[2], "/images/overlays/ICON_SIZE", 8)+'"), url("'+resolveImageSource(metaData.get('icon'), "/images/mimes/ICON_SIZE", 16)+'")';
                        break;
                        case 4:
                        default:
                            backgroundPosition = '2px 2px, 14px 2px, 2px 11px, 14px 11px, ' + backgroundPosition;
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
                        if(!this.htmlElement) return;// can be destroyed.
                        if(ajxpNode.getAjxpMime() != "ajxp_recycle"){
                            if(!this.htmlElement) return;
                            new AjxpDraggable(
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
                        if(!ajxpNode.isLeaf() && (this.options.droppable === undefined || this.options.droppable === true ))
                        {
                            AjxpDroppables.add(innerSpan, ajxpNode);
                        }
                    }.bind(this), 500);
                }
				
			}else if(s=="ajxp_modiftime"){
				var date = new Date();
				date.setTime(parseInt(metaData.get(s))*1000);
				newRow.ajxp_modiftime = date;
				tableCell.innerHTML = '<span class="text_label'+fullview+'">' + formatDate(date) + '</span>';
			}
			else
			{
				var metaValue = metaData.get(s) || "";
				tableCell.innerHTML = '<span class="text_label'+fullview+'">' + metaValue  + "</span>";
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
			if(attributeList.get(s).modifierFunc){
                attributeList.get(s).modifierFunc(tableCell, ajxpNode, 'row', attributeList.get(s));
			}
		}

        this.getFromCache('hiddenColumns').each(function(pair){
            if(pair.value.modifierFunc){
                pair.value.modifierFunc(null,ajxpNode,'row', pair.value, newRow);
            }
        });

		tBody.appendChild(newRow);
        if(!replaceItem){
            if(this.even){
                $(newRow).addClassName('even');
            }
            this.even = !this.even;
        }else{
            if(replaceItem.hasClassName('even')) $(newRow).addClassName('even');
        }

        this.addInlineToolbar(textLabel ? textLabel : tableCell, ajxpNode);


        return newRow;
	},
	
	/**
	 * Populates a node as a thumbnail div
	 * @param ajxpNode AjxpNode
	 * @returns HTMLElement
	 */
	ajxpNodeToDiv: function(ajxpNode){
		var newRow = new Element('div', {
            className:"thumbnail_selectable_cell",
            id:"item-"+slugString(ajxpNode.getPath())});

		var innerSpan = new Element('span', {style:"cursor:default;"});

        var img = this._previewFactory.generateBasePreview(ajxpNode);

        var textNode = ajxpNode.getLabel();
		var label = new Element('div', {
			className:"thumbLabel",
			title:textNode.stripTags()
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
            if(ajxpNode.getMetadata().get("overlay_class") && ajaxplorer.currentThemeUsesIconFonts){
                ajxpNode.getMetadata().get("overlay_class").split(",").each(function(ovC){
                    ovDiv.insert(new Element('span',{className:ovC+' overlay-class-span'}));
                });
                ovDiv.addClassName('overlay_icon_div');
            }
            innerSpan.insert({after:ovDiv});
        }

		this._htmlElement.insert(newRow);

        this.getFromCache("columns").each(function(pair){
            if(pair.value.modifierFunc){
                pair.value.modifierFunc(newRow, ajxpNode, 'thumb', pair.value);
            }
        });

        this._previewFactory.enrichBasePreview(ajxpNode, newRow);
		
		// Defer Drag'n'drop assignation for performances
		if(!ajxpNode.isRecycle()){
			window.setTimeout(function(){
                if(!this.htmlElement) return;
				new AjxpDraggable(newRow, {
					revert:true,
					ghosting:true,
					scroll:($('tree_container')?'tree_container':null),
					containerScroll:this.htmlElement.down(".selectable_div")
				}, this, 'filesList');
			}.bind(this), 500);
		}
		if(!ajxpNode.isLeaf() && (this.options.droppable === undefined || this.options.droppable === true ))
		{
			AjxpDroppables.add(newRow, ajxpNode);
		}

        this.addInlineToolbar(newRow, ajxpNode);

        return newRow;
	},
		
	/**
	 * Populates a node as a thumbnail div
	 * @param ajxpNode AjxpNode
	 * @returns HTMLElement
	 */
	ajxpNodeToLargeDiv: function(ajxpNode){

        var largeRow = new Element('div', {
            className:"thumbnail_selectable_cell detailed",
            id:"item-"+slugString(ajxpNode.getPath())+"-cont"
        });
        var metadataDiv = new Element("div", {className:"thumbnail_cell_metadata"});

        var newRow = new Element('div', {className:"thumbnail_selectable_cell", id:ajxpNode.getPath()});
		var metaData = ajxpNode.getMetadata();

		var innerSpan = new Element('span', {style:"cursor:default;"});

        var img = this._previewFactory.generateBasePreview(ajxpNode);

        var textNode = ajxpNode.getLabel();
		var label = new Element('div', {
			className:"thumbLabel",
			title:textNode.stripTags()
		}).update(textNode);

		innerSpan.insert({"bottom":img});
		//newRow.insert({"bottom":label});
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
            if(ajxpNode.getMetadata().get("overlay_class") && ajaxplorer.currentThemeUsesIconFonts){
                ajxpNode.getMetadata().get("overlay_class").split(",").each(function(ovC){
                    ovDiv.insert(new Element('span',{className:ovC+' overlay-class-span'}));
                });
                ovDiv.addClassName('overlay_icon_div');
            }else{
                newRow.setStyle({position:'relative'});
            }
            innerSpan.insert({after:ovDiv});
        }

        largeRow.insert(newRow);
        largeRow.insert(label);
        largeRow.insert(metadataDiv);

        var addedCell = 0;
        if(metaData.get("ajxp_description")){
            addedCell ++;
            metadataDiv.insert(new Element("span", {className:'metadata_chunk metadata_chunk_description'}).update(metaData.get("ajxp_description")));
        }

        var attributeList = this.getFromCache('visibleColumns');
        var first = false;
        var attKeys = attributeList.keys();
        for(var i = 0; i<attKeys.length;i++ ){
            var s = attKeys[i];
            var cell = new Element("span", {className:'metadata_chunk metadata_chunk_standard metadata_chunk_' + s});
            if(s == "ajxp_label")
            {
                continue;
            }else if(s=="ajxp_modiftime"){
                var date = new Date();
                date.setTime(parseInt(metaData.get(s))*1000);
                newRow.ajxp_modiftime = date;
                cell.update('<span class="text_label">' + formatDate(date) + '</span>');
            }else if(s == "ajxp_dirname" && metaData.get("filename")){
                var dirName = getRepName(metaData.get("filename"));
                cell.update('<span class="text_label">' + (dirName?dirName:"/") + '</span>');
            }else if(s == "filesize" && metaData.get(s) == "-"){
                continue;
            }else{
                var metaValue = metaData.get(s) || "";
                if(!metaValue) continue;
                cell.update('<span class="text_label">' + metaValue  + "</span>");
            }
            if(!first){
                cell.insert({top:new Element('span', {className:'icon-angle-right'})});
            }
            metadataDiv.insert(cell);
            addedCell++;
            first = false;
            if(attributeList.get(s).modifierFunc){
                attributeList.get(s).modifierFunc(cell, ajxpNode, 'detail', attributeList.get(s));
            }
        }

        if(!addedCell){
            largeRow.addClassName('metadata_empty');
        }

        this._htmlElement.insert(largeRow);

        this.getFromCache("hiddenColumns").each(function(pair){
            if(pair.value.modifierFunc){
                pair.value.modifierFunc(largeRow, ajxpNode, 'detail', pair.value);
            }
        });


        this._previewFactory.enrichBasePreview(ajxpNode, newRow);

		// Defer Drag'n'drop assignation for performances
		if(!ajxpNode.isRecycle()){
			window.setTimeout(function(){
                if(!this.htmlElement) return;
                new AjxpDraggable(largeRow, {
					revert:true,
					ghosting:true,
					scroll:($('tree_container')?'tree_container':null),
					containerScroll:this.htmlElement.down(".selectable_div")
				}, this, 'filesList');
			}.bind(this), 500);
		}
		if(!ajxpNode.isLeaf() && (this.options.droppable === undefined || this.options.droppable === true ))
		{
			AjxpDroppables.add(largeRow, ajxpNode);
		}

        this.addInlineToolbar(largeRow, ajxpNode);

        return largeRow;
	},

    addInlineToolbar : function(element, ajxpNode){
        if(this._inlineToolbarOptions){
            var options = this._inlineToolbarOptions;
            if(!this._inlineToolbarOptions.unique){
                options = Object.extend(this._inlineToolbarOptions, {
                    attachToNode: ajxpNode
                });
            }
            var tBarElement = new Element('div', {id:"FL-tBar-"+this._instanciatedToolbars.size(), className:"FL-inlineToolbar" + (this._inlineToolbarOptions.unique?' FL-inlineToolbarUnique':' FL-inlineToolbarMultiple')});
            element.insert(tBarElement);
            var aT = new ActionsToolbar(tBarElement, options);
            aT.actions = ajaxplorer.actionBar.actions;
            aT.initToolbars();
            if(!this._inlineToolbarOptions.unique){
                var dm = (this._dataModel?this._dataModel:ajaxplorer.getContextHolder());
                aT.registeredButtons.each(function(button){
                    // MAKE SURE THE CURRENT ROW IS SELECTED BEFORE TRIGGERING THE ACTION
                    button.stopObserving('click');
                    button.observe("click", function(event){
                        Event.stop(event);
                        dm.setSelectedNodes([ajxpNode]);
                        window.setTimeout(function(){
                            button.ACTION.apply([ajxpNode]);
                        }, 20);
                    });
                });
            }
            this._instanciatedToolbars.push(aT);
        }
    },

	partSizeCellRenderer : function(element, ajxpNode, type, metadataDef){
        if(!element) return;
        if(type == "row"){
            element.setAttribute("data-sorter_value", ajxpNode.getMetadata().get("bytesize"));
        }else{
            element.setAttribute("data-"+metadataDef['attributeName']+"-sorter_value", ajxpNode.getMetadata().get("bytesize"));
        }
		if(!ajxpNode.getMetadata().get("target_bytesize")){
			return;
		}
		var percent = parseInt( parseInt(ajxpNode.getMetadata().get("bytesize")) / parseInt(ajxpNode.getMetadata().get("target_bytesize")) * 100  );
		var uuid = "ajxp_"+(new Date()).getTime();
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
        if(type == "detail" && element.down(".thumbnail_cell_metadata")){
            element.down(".thumbnail_cell_metadata").update(div);
        }else{
    		element.update(div);
        }
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
            }.bind(this));
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
                conn.discrete = true;
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
	resizeThumbnails: function(one_element, skipResize){
			
		var elList;
		if(one_element) elList = [one_element]; 
		else elList = this._htmlElement.select('div.thumbnail_selectable_cell');
        if(this._displayMode == "detail"){
            elList = this._htmlElement.select('div.thumbnail_selectable_cell.detailed');
        }
        var ellipsisDetected;
        var tSize=0;
		elList.each(function(element){

            try{
                var image_element = element.IMAGE_ELEMENT || element.down('img');
                var label_element = element.LABEL_ELEMENT || element.down('.thumbLabel');
            }catch(e){
                return;
            }
            var elementsAreSiblings = (label_element && (label_element.siblings().indexOf(image_element) !== -1));
            tSize = this.getAdjustedThumbSize(element);

            element.removeClassName('fl-displayMode-thumbsize-small');
            element.removeClassName('fl-displayMode-thumbsize-medium');
            element.removeClassName('fl-displayMode-thumbsize-large');
            if(tSize < 80) element.addClassName('fl-displayMode-thumbsize-small');
            else if(tSize < 150) element.addClassName('fl-displayMode-thumbsize-medium');
            else element.addClassName('fl-displayMode-thumbsize-large');

            if(element.down('div.thumbnail_selectable_cell')){
                element.down('div.thumbnail_selectable_cell').setStyle({width:tSize+5+'px', height:tSize+10 +'px'});
            }else{
                element.setStyle({width:tSize+25+'px', height:tSize + 10 + 'px'});
            }
            this._previewFactory.setThumbSize(tSize);
            if(image_element){
                this._previewFactory.resizeThumbnail(image_element);
            }
            if(ellipsisDetected == undefined){
                ellipsisDetected = label_element.getStyle('textOverflow') == "ellipsis";
            }
            if(label_element && !ellipsisDetected){
                // RESIZE LABEL
                var el_width = (!elementsAreSiblings ? (element.getWidth() - tSize - 10)  : (tSize + 25) ) ;
                var charRatio = 6;
                var nbChar = parseInt(el_width/charRatio);
                var label = String(label_element.getAttribute('title'));
                //alert(element.getAttribute('text'));
                label_element.innerHTML = label.truncate(nbChar, '...');
            }

		}.bind(this));

        if(this.options.horizontalScroll){
            var scrollElement = this.htmlElement.down(".selectable_div");
            scrollElement.setStyle({width:(elList.length * (this._thumbSize + 46)) + 'px'});
        }
        if(this.options.fit && this.options.fit == 'content' && !skipResize){
            this.resize();
        }
        return tSize;
    },

    getAdjustedThumbSize:function(referenceElement){
        var tSize = (this._displayMode=='detail'? this._detailThumbSize:this._thumbSize);
        if(this._displayMode == 'thumb' && !this._fixedThumbSize){
            // Readjust tSize
            var w = this._htmlElement.getWidth();
            var margin = parseInt(referenceElement.getStyle('marginLeft')) + parseInt(referenceElement.getStyle('marginRight'));
            var realBlockSize = tSize + 25 + margin;
            var number = Math.ceil(w / realBlockSize);
            var blockSize = w / number;
            tSize = blockSize - 25 - margin;
        }
        return tSize;
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
        if(this._instanciatedToolbars && this._instanciatedToolbars.size()){
            this._instanciatedToolbars.invoke('destroy');
            this._instanciatedToolbars = $A();
        }
		var rows;
		if(this._displayMode == "list") rows = $(this._htmlElement).select('tr');
		else rows = $(this._htmlElement).select('div.thumbnail_selectable_cell');
		for(var i=0; i<rows.length;i++)
		{
			try{
                if(rows[i].ajxpNode){
                    if(rows[i].REPLACE_OBS) rows[i].ajxpNode.stopObserving("node_replaced", rows[i].REPLACE_OBS);
                    if(rows[i].REMOVE_OBS) rows[i].ajxpNode.stopObserving("node_removed", rows[i].REMOVE_OBS);
                }
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
        if(this.options.silentLoading){
            this.loading = true;
            return;
        }
		if(this.loading) return;
        this.htmlElement.setStyle({position:'relative'});
        var element = this.htmlElement; // this.htmlElement.down('.selectable_div,.table_rows_container') || this.htmlElement;
		addLightboxMarkupToElement(element);
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
        if(this.options.silentLoading){
            this.loading = false;
            return;
        }
        if(this.htmlElement) removeLightboxFromElement(this.htmlElement);
		this.loading = false;
	},
	
	/**
	 * Overrides base fireChange function
	 */
	fireChange: function()
	{		
		if(this._fireChange && this.hasFocus){
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
		var selRaw = this.getSelectedItems();
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
		if(!ajaxplorer.getContextHolder().fileNameExists(fileName, true))
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
				this.disableTextSelection(td, false);
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
		if(this._displayMode != "thumb" && keyCode != Event.KEY_UP && keyCode != Event.KEY_DOWN && keyCode != Event.KEY_RETURN && keyCode != Event.KEY_END && keyCode != Event.KEY_HOME)
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
		var nextItem, nextItemIndex;
		var currentItem;
		var shiftKey = event['shiftKey'];
		currentItem = items[items.length-1];
		var allItems = this.getItems();
		var currentItemIndex = this.getItemIndex(currentItem);
		var selectLine = false;
        var i;
		//ENTER
		if(event.keyCode == Event.KEY_RETURN)
		{
			for(i=0; i<items.length; i++)
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
			if(this._displayMode != 'thumb') nextItem = this.getPrevious(currentItem);
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
			if(this._displayMode != 'thumb') nextItem = this.getNext(currentItem);
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
			for(i=0; i<items.length; i++)
			{
				this.setItemSelected(items[i], false);
			}		
		}
		else if(selectLine)
		{
			if(nextItemIndex >= currentItemIndex)
			{
				for(i=currentItemIndex+1; i<nextItemIndex; i++) this.setItemSelected(allItems[i], !allItems[i]._selected);
			}else{
				for(i=nextItemIndex+1; i<currentItemIndex; i++) this.setItemSelected(allItems[i], !allItems[i]._selected);
			}
		}
		this.setItemSelected(nextItem, !nextItem._selected);
		
		
		// NOW FIND CHANGES IN SELECTION!!!
		var found;
		var changed = selectedBefore.length != this._selectedItems.length;
		if (!changed) {
			for (i = 0; i < selectedBefore.length; i++) {
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
        var searchingPosY, i;
		if(bDown){
			searchingPosY = pos[1] + parseInt(dims.height*3/2);
			for(i=currentItemIndex+1; i<allItems.length;i++){
				if(Position.within($(allItems[i]), searchingPosX, searchingPosY))
				{
					return i;
				}
			}
			return null;
		}else{
			searchingPosY = pos[1] - parseInt(dims.height/2);
			for(i=currentItemIndex-1; i>-1; i--){
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
		else
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
			return this._htmlElement.rows || [];
		}
		else
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
		else
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
		else
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
