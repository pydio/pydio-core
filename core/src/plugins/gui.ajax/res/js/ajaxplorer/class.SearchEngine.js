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
 * The Search Engine abstraction.
 */
Class.create("SearchEngine", AjxpPane, {

	/**
	 * @var HTMLElement
	 */
	htmlElement:undefined,
	_inputBox:undefined,
	_resultsBoxId:undefined,
	_searchButtonName:undefined,
	/**
	 * @var String Default 'idle'
	 */
	state: 'idle',
	_runningQueries:undefined,
	_queriesIndex:0,
	_ajxpOptions:undefined,
	
	_queue : undefined,

    _searchMode : "local",
    _even : false,

    _rootNode : null,
    _dataModel : null,
    _fileList : null,

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param mainElementName String
	 * @param ajxpOptions Object
	 */
	initialize: function($super, mainElementName, ajxpOptions)
	{
        this._ajxpOptions = {
            toggleResultsVisibility : false
        };
        if($(mainElementName).getAttribute("data-globalOptions")){
            this._ajxpOptions = $(mainElementName).getAttribute("data-globalOptions").evalJSON();
        }
        if(ajxpOptions){
            this._ajxpOptions = Object.extend(this._ajxpOptions, ajxpOptions);
        }
		$super($(mainElementName), this._ajxpOptions);

        if(!this._ajxpOptions.metaColumns) this._ajxpOptions.metaColumns = {};

        this.updateSearchModeFromRegistry();
        this.searchModeObserver = this.updateSearchModeFromRegistry.bind(this);
        document.observe("ajaxplorer:registry_loaded", this.searchModeObserver);

        this._dataModel = new AjxpDataModel(true);
        this._rootNode = new AjxpNode("/", false, "Results", "folder.png");
        this._dataModel.setRootNode(this._rootNode);

        this.initGUI();

         this._dataModel.observe("selection_changed", function(){
             var selectedNode = this._dataModel.getSelectedNodes()[0];
             if(selectedNode) {
                 if(this._ajxpOptions.openSearchInput){
                     this.closeSearchInput();
                 }
                 if(ajxpOptions['leavesOpenOnSelect'] && selectedNode.isLeaf()){
                     ajaxplorer.openCurrentSelectionInEditor(null, selectedNode);
                 }else{
                     ajaxplorer.goTo(selectedNode);
                 }
                 this._dataModel.setSelectedNodes([]);
             }
         }.bind(this));

    },

    updateSearchModeFromRegistry : function(){
        if(!this.htmlElement) return;
        if($(this._resultsBoxId) && !this._rootNode){
            $(this._resultsBoxId).update('');
        }else if(this._rootNode){
            this._rootNode.clear();
        }
        var reg = ajaxplorer.getXmlRegistry();
        var indexerNode = XPathSelectSingleNode(reg, "plugins/indexer");
        if(indexerNode != null){
            if(indexerNode.getAttribute("indexed_meta_fields")){
                this.indexedFields = indexerNode.getAttribute("indexed_meta_fields").evalJSON();
                if(this.indexedFields["indexed_meta_fields"]){
                    var addColumns = this.indexedFields["additionnal_meta_columns"];
                    this.indexedFields = $A(this.indexedFields["indexed_meta_fields"]);
                    if(!this._ajxpOptions.metaColumns) this._ajxpOptions.metaColumns = {};
                    for(var key in addColumns){
                        if(addColumns.hasOwnProperty(key)){
                            this._ajxpOptions.metaColumns[key] = addColumns[key];
                        }
                    }
                }else{
                    this.indexedFields = $A(this.indexedFields);
                }
            }else{
                this.indexedFields = $A();
            }
            this._searchMode = "remote";
        }else{
            this._searchMode = "local";
        }

        if(this.htmlElement && this.htmlElement.down('#search_meta')) {
            this.htmlElement.down('#search_meta').remove();
        }
        if(this.htmlElement.down('#search_form') && this.htmlElement.down('#search_form').down('#search_meta')) {
            this.htmlElement.down('#search_form').down('#search_meta').remove();
        }
        if(this._ajxpOptions && this._ajxpOptions.metaColumns){
            if(this._ajxpOptions.toggleResultsVisibility && this.htmlElement && this.htmlElement.down("#" + this._ajxpOptions.toggleResultsVisibility)){
                this.htmlElement.down("#" + this._ajxpOptions.toggleResultsVisibility).insert({top:'<div id="search_meta"></div>'});
            }else if(this.htmlElement.down('#search_form')){
                this.htmlElement.down('#search_form').insert({bottom:'<div id="search_meta"></div>'});
            }
            if(this.htmlElement.down('#search_meta')){
                this.initMetadataForm(this.htmlElement.down('#search_meta'), this._ajxpOptions.metaColumns);
            }
        }

    },

    initMetadataForm: function(formPanel, metadataColumns){

        var searchChooser = new Element('div',{id:"basic_search"}).update('<span class="toggle_button open"><span class="icon-chevron-left"></span> '+MessageHash[486]+'</span>' +
            '<span class="toggle_button close"><span class="icon-chevron-right"></span> '+MessageHash[487]+'</span> ' +
            '<span class="search_label open">'+MessageHash[344]+' : </span><span class="search_label close">'+MessageHash[488]+' <span id="refresh_search_button" class="icon-refresh" title="Apply filter now"></span></span><span id="search_meta_options"></span></div>');

        if(this._ajxpOptions.searchChooserAsResultsHeader){
            formPanel.insert({after:searchChooser});
        }else{
            formPanel.insert(searchChooser);
        }

        formPanel.insert('<div>' +
            '<div class="scroller_track"><div class="scroller_handle"></div></div> ' +
            '<div id="search_meta_detailed"><div class="advanced_search_section_title"><span class="icon-circle"></span> '+MessageHash[489]+'</div><div class="advanced_search_section search_section_freemeta"></div></div>' +
        '</div>');

        var oThis = this;
        searchChooser.select('.toggle_button').invoke('observe', 'click', function(){
            formPanel.toggleClassName('toggle_open');
            searchChooser.toggleClassName('toggle_open');
            window.setTimeout(function(){
                oThis.resize();
            }, 150);
            if(formPanel.hasClassName('toggle_open') && !formPanel.down('#basename').getValue() && oThis._inputBox.getValue()){
                formPanel.down('#basename').setValue(oThis._inputBox.getValue());
                formPanel.down('#basename').up('.advanced_search_section').addClassName('visible');
            }
            oThis._closeAdvancedPanel = function(){
                if(!formPanel.hasClassName('toggle_open')) return;
                formPanel.toggleClassName('toggle_open');
                searchChooser.toggleClassName('toggle_open');
            };
            oThis._advancedPanelOpen = formPanel.hasClassName('toggle_open');
        });

        var simpleMeta = searchChooser.down('#search_meta_options');
        var advancedMeta = formPanel.down('#search_meta_detailed').down('.advanced_search_section');

        this.initMetaOption(simpleMeta, advancedMeta, 'filename', MessageHash[1], true);
        for(var key in metadataColumns){
            if(metadataColumns.hasOwnProperty(key)){
                if(this.indexedFields && !this.indexedFields.include(key)) continue;
                this.initMetaOption(simpleMeta, advancedMeta, key, metadataColumns[key], false);
            }
        }

        var docPropertyTemplate =
            '<div class="advanced_search">' +
            '<div class="advanced_search_section_title"><span class="icon-circle"></span> '+MessageHash[490]+'</div>'+
            '<div class="advanced_search_section search_section_date">'+
            '<span class="c4"><span class="icon-calendar"></span> '+MessageHash[491]+' </span><input id="ajxp_modiftime_from" class="c3" type="text" placeholder="YYYY/MM/DD"><span class="c6">'+MessageHash[492]+'</span><input class="c3" type="text"  id="ajxp_modiftime_to" placeholder="YYYY/MM/DD">'+
            '<div id="modiftime_fixed_radio"><span id="ajxp_modiftime_fixed" class="c3" data-value="AJXP_SEARCH_RANGE_TODAY" type="text">'+MessageHash[493]+'</span>' +
            '<span id="ajxp_modiftime_fixed" class="c3" data-value="AJXP_SEARCH_RANGE_YESTERDAY" type="text">'+MessageHash[494]+'</span>' +
            '<span id="ajxp_modiftime_fixed" class="c3" data-value="AJXP_SEARCH_RANGE_LAST_WEEK" type="text">'+MessageHash[495]+'</span>' +
            '<span id="ajxp_modiftime_fixed" class="c3" data-value="AJXP_SEARCH_RANGE_LAST_MONTH" type="text">'+MessageHash[496]+'</span>' +
            '<span id="ajxp_modiftime_fixed" class="c3" data-value="AJXP_SEARCH_RANGE_LAST_YEAR" type="text">'+MessageHash[497]+'</span></div>'+
            '</div>'+
            '<div class="advanced_search_section_title"><span class="icon-circle"></span> '+MessageHash[498]+'</div>'+
            '<div class="advanced_search_section search_section_property">'+
            '<span class="c4"><span class="icon-file"></span> '+MessageHash[499]+' </span><input id="ajxp_mime" class="c3" type="text" placeholder="'+MessageHash[500]+'"><span class="c6">'+MessageHash[501]+'</span><span class="c3" id="ajxp_folder"><span class="icon-folder-open"></span>'+MessageHash[502]+'</span>'+
            '<br><span class="c4"><span class="icon-cloud-download"></span> '+MessageHash[503]+'</span><input  id="ajxp_bytesize_from" type="text" class="c3" placeholder="'+MessageHash[504]+'..."><span class="c6"> '+MessageHash[505]+' </span><input  id="ajxp_bytesize_to" type="text" class="c3" placeholder="'+MessageHash[504]+'..."></div>'+
            '</div>' +
            '';
        formPanel.down('#search_meta_detailed').insert({bottom:docPropertyTemplate});
        if(this._ajxpOptions.searchChooserAsResultsHeader){
            formPanel.down('#search_meta_detailed').insert({top:searchChooser.down('span.search_label.close')});
            formPanel.down('#refresh_search_button').observe('click', function(e){
                this.search();
            }.bind(this));
        }else{
            searchChooser.down('#refresh_search_button').observe('click', function(e){
                this.search();
            }.bind(this));
        }

        formPanel.select('input').each(function(el){
            el.observe('focus', ajaxplorer.disableAllKeyBindings.bind(ajaxplorer));
            el.observe('blur', ajaxplorer.enableAllKeyBindings.bind(ajaxplorer));
            el.observe('keydown', function(event){
                if(event.keyCode == Event.KEY_RETURN){
                    oThis.search();
                }
            });
        });
        var radios = formPanel.down('#modiftime_fixed_radio').select('span.c3');
        radios.each(function(el){
            el.observe('click', function(e){
                if(el.hasClassName('selected')){
                    el.removeClassName('selected');
                }else{
                    radios.invoke('removeClassName', 'selected');
                    el.addClassName('selected');
                }
                formPanel.down('#ajxp_modiftime_from').disabled = formPanel.down('#ajxp_modiftime_to').disabled = el.hasClassName('selected');
                oThis.search();
            });
        });

        formPanel.down('#ajxp_folder').observe('click', function(e){
            formPanel.down('#ajxp_folder').toggleClassName('selected');
            formPanel.down('#ajxp_mime').disabled = formPanel.down('#ajxp_folder').hasClassName('selected');
            oThis.search();
        });


        formPanel.select('.advanced_search_section_title').invoke('observe', 'click', function(ev){
            Event.findElement(ev, '.advanced_search_section_title').next('.advanced_search_section').toggleClassName('visible');
            oThis.resize();
        });

        this.scrollbar = new Control.ScrollBar(formPanel.down('#search_meta_detailed'),formPanel.down('.scroller_track'));

    },

    parseMetadataForm: function(){
        var formPanel = this.htmlElement.down('#search_meta');
        if(!formPanel) {
            return false;
        }
        if(!formPanel.hasClassName('toggle_open')){
            return false;
        }
        var metadata = $H();
        formPanel.select('input,select,span.c3.selected').each(function(el){
            if(el.tagName.toLowerCase() == 'input' || el.tagName.toLowerCase() == 'select'){
                if(!el.getValue() || el.disabled) return;
                var name = el.id || el.name;
                if(name) metadata.set(name, el.getValue());
            }else{
                if(el.id == 'ajxp_folder'){
                    metadata.set('ajxp_mime', 'ajxp_folder');
                }else if(el.id == 'ajxp_modiftime_fixed'){
                    metadata.set('ajxp_modiftime', el.readAttribute('data-value'));
                }
            }
        });
        if(metadata.get('ajxp_modiftime_from') || metadata.get('ajxp_modiftime_to')){
            if(!metadata.get('ajxp_modiftime_from')) metadata.set('ajxp_modiftime_from','1970/01/01');
            if(!metadata.get('ajxp_modiftime_to')) metadata.set('ajxp_modiftime_to','2150/01/01');
            metadata.set('ajxp_modiftime', '['+metadata.get('ajxp_modiftime_from').replace(/\//g, '')+' TO '+metadata.get('ajxp_modiftime_to').replace(/\//g, '')+']');
            metadata.unset('ajxp_modiftime_from');
            metadata.unset('ajxp_modiftime_to');
        }
        if(metadata.get('ajxp_bytesize_from') || metadata.get('ajxp_bytesize_to')){
            if(!metadata.get('ajxp_bytesize_from')) metadata.set('ajxp_bytesize_from',0);
            if(!metadata.get('ajxp_bytesize_to')) metadata.set('ajxp_bytesize_to',1024*1024*1024*1024);
            metadata.set('ajxp_bytesize', '['+metadata.get('ajxp_bytesize_from')+' TO '+metadata.get('ajxp_bytesize_to')+']');
            metadata.unset('ajxp_bytesize_to');
            metadata.unset('ajxp_bytesize_from');
        }

        return metadata;
    },

	/**
	 * Creates the HTML
	 */
	initGUI : function(){
		
		if(!this.htmlElement) return;
		
		this.htmlElement.insert('<div id="search_panel"><div id="search_form"><input style="float:left;" type="text" id="search_txt" placeholder="'+ MessageHash[87] +'" name="search_txt" onfocus="blockEvents=true;" onblur="blockEvents=false;"><a href="" id="search_button" class="icon-search" ajxp_message_title_id="184" title="'+MessageHash[184]+'"><img width="16" height="16" align="absmiddle" src="'+ajxpResourcesFolder+'/images/actions/16/search.png" border="0"/></a><span class="search_advanced_direct_access">'+MessageHash[486].toLocaleLowerCase()+' <span class="icon-caret-down"></span></span><a class="icon-remove" href="" id="stop_search_button" ajxp_message_title_id="185" title="'+MessageHash[185]+'"><img width="16" height="16" align="absmiddle" src="'+ajxpResourcesFolder+'/images/actions/16/fileclose.png" border="0" /></a></div><div id="search_results"></div></div>');
        if(this._ajxpOptions.toggleResultsVisibility){
            this.htmlElement.down("#search_results").insert({before:"<div style='display: none;' id='"+this._ajxpOptions.toggleResultsVisibility+"'></div>"});
            this.htmlElement.down("#" + this._ajxpOptions.toggleResultsVisibility).insert(this.htmlElement.down("#search_results"));
        }
        if(this.htmlElement.down('div.panelHeader')){
            this.htmlElement.down('div#search_panel').insert({top:this.htmlElement.down('div.panelHeader')});
        }
		
		this.metaOptions = [];
        this.htmlElement.select('#search_meta').invoke('remove');
        this.htmlElement.select('.meta_toggle_button').invoke('remove');
		if(this._ajxpOptions && this._ajxpOptions.metaColumns){

            if(this._ajxpOptions.toggleResultsVisibility && this.htmlElement && this.htmlElement.down("#" + this._ajxpOptions.toggleResultsVisibility)){
                this.htmlElement.down("#" + this._ajxpOptions.toggleResultsVisibility).insert({top:'<div id="search_meta"></div>'});
            }else if(this.htmlElement.down('#search_form')){
                this.htmlElement.down('#search_form').insert({bottom:'<div id="search_meta"></div>'});
            }

            var searchMeta = this.htmlElement.down('#search_meta');
            if(searchMeta){
                this.initMetadataForm(searchMeta, this._ajxpOptions.metaColumns);
            }

		}else{
			this.htmlElement.down('#search_form').insert('<div style="clear:left;height:9px;"></div>');
		}
		
		this._inputBox = this.htmlElement.down("#search_txt");
		this._resultsBoxId = 'search_results';
		this._searchButtonName = "search_button";
		this._runningQueries = $A();
		this._queue = $A();
		
		$('stop_'+this._searchButtonName).addClassName("disabled");
        var groupByData = 'mimestring_id';
        if(this.options['groupByData'] !== undefined){
            groupByData = this.options['groupByData'];
        }

        this._fileList = new FilesList($(this._resultsBoxId), {
            dataModel:this._dataModel,
            columnsDef:[{attributeName:"ajxp_label", messageId:1, sortType:'String'},
                {attributeName:"ajxp_dirname", messageString:'Path', sortType:'String'},
                {attributeName:"mimestring_id", messageString:'Type', sortType:'String'}
            ],
            groupByData:groupByData,
            displayMode: 'detail',
            fixedDisplayMode: 'detail',
            defaultSortTypes:["String", "String", "String"],
            columnsTemplate:"search_results",
            selectable: true,
            draggable: false,
            replaceScroller:true,
            fit:'height',
            fitParent : this.options.toggleResultsVisibility,
            detailThumbSize: this.options.detailThumbSize?this.options.detailThumbSize:22,
            skipSelectFirstOnFocus:true
        });
        ajaxplorer.registerFocusable(this._fileList);


        this.htmlElement.select('a', 'div[id="search_results"]').each(function(element){
			disableTextSelection(element);
		});
        
		this._inputBox.observe("keydown", function(e){
            if(e.keyCode == Event.KEY_RETURN) {
                Event.stop(e);
                this.search();
            }
			return e.keyCode != Event.KEY_TAB;
		}.bind(this));
        this._inputBox.observe("input", function(e){
            if(this._inputBox.getValue().length > 2){
                bufferCallback('searchByTyping', 300, this.searchWhenTyping.bind(this));
                bufferCallback('fullSearch', 2000, this.searchCompleteTypedResults.bind(this));
            }
        }.bind(this));

        var opener = function(e){
            ajaxplorer.disableShortcuts();
            ajaxplorer.disableNavigation();
            this.hasFocus = true;
            this._inputBox.select();
            if(this.hasResults && this._ajxpOptions.toggleResultsVisibility && !$(this._ajxpOptions.toggleResultsVisibility).visible()){
                this.showToggleResult(true);
            }
            if(this._ajxpOptions.openSearchInput){
                this.openSearchInput();
            }
            if(this._advancedPanelOpen && this.htmlElement.down("input#basename")){
                this.htmlElement.down("input#basename").focus();
            }
            return false;
        }.bind(this);
		this._inputBox.observe("focus", opener);
		this._inputBox.observe("click", opener);

		this._inputBox.observe("blur", function(e){
			ajaxplorer.enableShortcuts();
            ajaxplorer.enableNavigation();
			this.hasFocus = false;
            if(this._ajxpOptions.openSearchInput){
                window.setTimeout(function(){
                    if(!this._ajxpOptions.toggleResultsVisibility || !$(this._ajxpOptions.toggleResultsVisibility).visible()){
                        this.closeSearchInput();
                    }
                }.bind(this), 150);
            }
		}.bind(this));
		
		$(this._searchButtonName).observe("click", function(e){
            Event.stop(e);
            if(this._ajxpOptions.openSearchInput && !this.searchInputIsOpen()){
                this._inputBox.focus();
            }else{
    			this.search();
            }
			return false;
		}.bind(this));
		
		$('stop_'+this._searchButtonName).onclick = function(){
			this.interrupt();
			return false;
		}.bind(this);

        this.htmlElement.down('span.search_advanced_direct_access').observe('click', function(){
            this.openSearchInput(true);
            this.updateStateSearching();
            this.updateStateFinished();
            window.setTimeout(function(){
                this.htmlElement.down(".toggle_button").click();
            }.bind(this), 250);
        }.bind(this));

        this.refreshObserver = function(e){
            "use strict";
            this._inputBox.setValue("");
            this.clearResults();
            if($(this.options.toggleResultsVisibility)){
                this.showToggleResult(false);
            }
        }.bind(this);

        document.observe("ajaxplorer:repository_list_refreshed", this.refreshObserver );
        this._dataModel.setContextNode(this._dataModel.getRootNode(), true);
        this.resize();
	},

    searchInputIsOpen: function(){
        return this.htmlElement.hasClassName("search_active");
    },

    openSearchInput: function(withResult){
        var container = this.htmlElement;
        container.absolutize();
        var leftPos = 285;
        if(this.options['openSearchStickLeftTo']){
            leftPos = parseInt($(this.options['openSearchStickLeftTo']).getWidth()) + 5;
        }
        var pos = container.positionedOffset();
        var width = pos['left'] + container.getWidth() - leftPos;
        container.setStyle({width:width+'px',left:leftPos+'px'});
        container.addClassName("search_active");
        container.addClassName("skipSibling");
        if(withResult || this._inputBox.getValue()){
            window.setTimeout(function(){
                this.showToggleResult(true);
                this.resize();
            }.bind(this), 1000);
        }
    },
    closeSearchInput: function(){
        var container = this.htmlElement;
        container.removeClassName("search_active");
        this.showToggleResult(false);
        this._inputBox.blur();
        window.setTimeout(function(){
            if(container.hasClassName("search_active")) {
                return;
            }
            if(this.htmlElement.down('#search_meta')){
                this.htmlElement.down('#search_meta').removeClassName("toggle_open");
            }
            container.relativize();
            container.setStyle({position:'relative'});
            container.removeClassName("skipSibling");
            // Resize parent
            container.up('[ajxpClass]').ajxpPaneObject.resize();
        }.bind(this), 1000);
    },

    showToggleResult: function(show){
        if(show){
            var panel = $(this._ajxpOptions.toggleResultsVisibility);
            panel.setStyle({display:'block'});
            this.updateSearchResultPosition(panel);
        }else{
            $(this._ajxpOptions.toggleResultsVisibility).setStyle({display:'none'});
        }
        if(this._fileList) {
            this._fileList.showElement(show);
        }
    },

	/**
	 * Show/Hide the widget
	 * @param show Boolean
	 */
	showElement : function(show){
		if(!this.htmlElement) return;
		if(show) this.htmlElement.show();
		else this.htmlElement.hide();
	},
	/**
	 * Resize the widget
	 */
	resize: function($super){
        if(this._ajxpOptions.toggleResultsVisibility){
            fitHeightToBottom($(this._ajxpOptions.toggleResultsVisibility), (this._ajxpOptions.toggleResultsFitTo?$(this._ajxpOptions.toggleResultsFitTo):null), (this._ajxpOptions.fitMarginBottom?this._ajxpOptions.fitMarginBottom:0));
            fitHeightToBottom($(this._resultsBoxId));
            if(this._ajxpOptions.toggleResultsFitTo && $(this._ajxpOptions.toggleResultsFitTo) && $(this._ajxpOptions.toggleResultsVisibility)){
                $(this._ajxpOptions.toggleResultsVisibility).setStyle({width:(parseInt($(this._ajxpOptions.toggleResultsFitTo).getWidth()) - (this._ajxpOptions.toggleResultsOffsetRight!==undefined?this._ajxpOptions.toggleResultsOffsetRight:20))+'px'});
            }
        }else{
            fitHeightToBottom($(this._resultsBoxId), null, (this._ajxpOptions.fitMarginBottom?this._ajxpOptions.fitMarginBottom:0));
        }

        if(this.htmlElement && this.htmlElement.down('#search_meta')){
            var formPanel = this.htmlElement.down('#search_meta');
            if(formPanel.getStyle('float') == 'left'){
                fitHeightToBottom(formPanel);
                formPanel.select('.advanced_search_section').invoke('addClassName', 'visible');
                formPanel.select('.toggle_button').invoke('hide');
            }
            fitHeightToBottom(formPanel.down('#search_meta_detailed'), formPanel);
            if(this.scrollbar) {
                this.scrollbar.track.setStyle({height:formPanel.down('#search_meta_detailed').getHeight()+'px'});
                this.scrollbar.recalculateLayout();
            }
        }

        if(this._fileList){
            this._fileList.resize();
        }

		if(this.htmlElement && this.htmlElement.visible()){
			//this._inputBox.setStyle({width:Math.max((this.htmlElement.getWidth() - this.htmlElement.getStyle("paddingLeft")- this.htmlElement.getStyle("paddingRight") -70),70) + "px"});
		}
	},
	
	destroy : function(){
        if(this._fileList){
            ajaxplorer.unregisterFocusable(this._fileList);
            this._fileList.destroy();
            this._fileList = null;
        }
        if(this.htmlElement) {
            var ajxpId = this.htmlElement.id;
            this.htmlElement.update('');
        }
        document.stopObserving("ajaxplorer:repository_list_refreshed", this.refreshObserver);
        document.stopObserving("ajaxplorer:registry_loaded", this.searchModeObserver);
        /*
        if(this.ctxChangeObserver){
            document.stopObserving("ajaxplorer:context_changed", this.ctxChangeObserver);
        }
        */
        if(this.boundSizeEvents){
            this.boundSizeEvents.each(function(pair){
                document.stopObserving(pair.key, pair.value);
            });
        }
		this.htmlElement = null;
        if(ajxpId && window[ajxpId]){
            try {delete window[ajxpId];}catch(e){}
        }
	},
    /**
     * Initialise the options for search Metadata
     * @param element HTMLElement
     * @param optionValue String
     * @param optionLabel String
     * @param checked Boolean
     * @param advancedPanel
     */
	initMetaOption : function(element, advancedPanel, optionValue, optionLabel, checked){
		var option = new Element('span', {value:optionValue, className:'search_meta_opt'}).update('<span class="icon-ok"></span>'+ optionLabel);
		if(checked) option.addClassName('checked');
		if(element.childElements().length) element.insert(', ');
		element.insert(option);
		option.observe('click', function(event){
			option.toggleClassName('checked');
		});
        var fName = (optionValue == 'filename'?'basename':'ajxp_meta_'+optionValue);
        /*
        var fName;
        if(optionValue == 'ajxp_document_content') fName = optionValue;
        else if (optionValue == 'filename') fName = 'basename';
        else fName = "ajxp_meta_" + optionValue;
        */
        advancedPanel.insert('<div><span class="c4" style="width: 35%;"><span class="icon-tag"></span> '+optionLabel+'</span><input style="width: 35%;" type="text" class="c3" name="'+optionValue+'" id="'+fName+'"></div>');

        if(this._ajxpOptions.metaColumnsRenderers && this._ajxpOptions.metaColumnsRenderers[optionValue]){
            var input = advancedPanel.down('#'+fName);
            var func = eval(this._ajxpOptions.metaColumnsRenderers[optionValue]);
            if(Object.isFunction(func)){
                func(input, advancedPanel);
            }
        }

		this.metaOptions.push(option);
	},
	/**
	 * Check wether there are metadata search selected
	 * @returns Boolean
	 */
	hasMetaSearch : function(){
		var found = false;
		this.metaOptions.each(function(opt){
			if(opt.getAttribute("value")!="filename" && opt.hasClassName("checked")) found = true;
		});
		return found;
	},
	/**
	 * Get the searchable columns
	 * @returns $A()
	 */
	getSearchColumns : function(){
		var cols = $A();
		this.metaOptions.each(function(opt){
			if(opt.hasClassName("checked")) cols.push(opt.getAttribute("value"));
		});
		return cols;
	},
	/**
	 * Focus on this widget (focus input)
	 */
	focus : function(){
		if(this.htmlElement && this.htmlElement.visible()){
			this._inputBox.activate();
			this.hasFocus = true;
            if(this._ajxpOptions.openSearchInput){
                this.openSearchInput()
            }
		}
	},
	/**
	 * Blur this widget
	 */
	blur : function(){
		if(this._inputBox){
			this._inputBox.blur();
		}
        if(this._ajxpOptions.openSearchInput){
            this.closeSearchInput();
        }
		this.hasFocus = false;
	},

    searchWhenTyping:function(){
        if(this._searchMode == 'remote'){
            this.search(9);
        }
    },
    searchCompleteTypedResults:function(){
        if(this._searchMode == 'remote'){
            var text = this._inputBox.value.toLowerCase();
            if(text == this.crtText){
                if(this._rootNode.getChildren().length >= 9) {
                    // Get more
                    this.search(50, true);
                }
            }else{
                this.search(50, false);
            }
        }
    },

	/**
	 * Perform search
	 */
	search : function(limit, skipClear){
		var text = this._inputBox.value.toLowerCase();
        var searchQuery;
        var metadata = this.parseMetadataForm();
        if(metadata){
            var parts = $A();
            metadata.each(function(pair){
                parts.push(pair.key+':'+pair.value);
            });
            searchQuery = parts.join(" AND ");
        }else{
            searchQuery = text;
        }
		if(searchQuery == '') return;
		this.crtText = searchQuery;
        if(!skipClear){
		    this.updateStateSearching();
    		this.clearResults();
        }
		var folder = ajaxplorer.getContextNode().getPath();
		if(folder == "/") folder = "";
		window.setTimeout(function(){
			this.searchFolderContent(folder, ajaxplorer.getContextNode().getMetadata().get("remote_indexation"), limit);
		}.bind(this), 0);		
	},
	/**
	 * stop search
	 */
	interrupt : function(){
		// Interrupt current search
		if(this._state == 'idle') return;
		this._state = 'interrupt';
		this._queue = $A();
	},
	/**
	 * Update GUI for indicating state
	 */
	updateStateSearching : function (){
		this._state = 'searching';
		$(this._searchButtonName).addClassName("disabled");
		$('stop_'+this._searchButtonName).removeClassName("disabled");
        if(this._ajxpOptions.toggleResultsVisibility){
            if(!$(this._ajxpOptions.toggleResultsVisibility).down("div.panelHeader.toggleResults")){
                $(this._ajxpOptions.toggleResultsVisibility).insert({top:"<div class='panelHeader toggleResults'><span class='results_string'>Results</span><span class='close_results icon-remove-sign'></span><div id='display_toolbar'></div></div>"});
                this.tb = new ActionsToolbar($(this._ajxpOptions.toggleResultsVisibility).down("#display_toolbar"), {submenuClassName:"panelHeaderMenu",submenuPosition:"bottom right",toolbarsList:["ajxp-search-result-bar"],skipBubbling:true, skipCarousel:true,submenuOffsetTop:2});
                this.tb.actionsLoaded({memo:ajaxplorer.actionBar.actions});
                this.tb.element.select('a').invoke('show');
                this.resultsDraggable = new Draggable(this._ajxpOptions.toggleResultsVisibility, {
                    handle:"panelHeader",
                    zindex:999,
                    starteffect : function(element){},
                    endeffect : function(element){}
                });
            }
            if($(this._ajxpOptions.toggleResultsVisibility).down("span.close_results")){
                $(this._ajxpOptions.toggleResultsVisibility).down("span.close_results").observe("click", function(){
                    if(this._ajxpOptions.openSearchInput) this.closeSearchInput();
                    else this.showToggleResult(false);
                    if(this._closeAdvancedPanel) this._closeAdvancedPanel();
                }.bind(this));
            }

            if(!$(this._ajxpOptions.toggleResultsVisibility).visible()){
                $(this._ajxpOptions.toggleResultsVisibility).setStyle({position: "absolute"});
                this.showToggleResult(true);
            }
            this.resize();
        }
	},

    updateSearchResultPosition:function(panel){
        var top = (this._inputBox.positionedOffset().top + this._inputBox.getHeight() + (this._ajxpOptions.toggleResultsOffsetTop!==undefined?this._ajxpOptions.toggleResultsOffsetTop:3));
        var left = (this._inputBox.positionedOffset().left);
        if((left + this._fileList.htmlElement.getWidth()) > document.viewport.getWidth() + 10){
            left = document.viewport.getWidth() - this._fileList.htmlElement.getWidth() - 15;
        }
        panel.setStyle({top: top + 'px', left: left + 'px'});
    },

	/**
	 * Search is finished
	 * @param interrupt Boolean
	 */
	updateStateFinished : function (interrupt){
		this._state = 'idle';
		this._inputBox.disabled = false;
		$(this._searchButtonName).removeClassName("disabled");
		$('stop_'+this._searchButtonName).addClassName("disabled");
	},
	/**
	 * Clear all results and input box
	 */
	clear: function(){
		this.clearResults();
		if(this._inputBox){
			this._inputBox.value = "";
		}
	},
	/**
	 * Clear all results
	 */
	clearResults : function(){
		// Clear the results
        this.hasResults = false;
        this._rootNode.clear();
        this._even = false;
	},
	/**
	 * Add a result to the list - Highlight search term
	 * @param folderName String
	 * @param ajxpNode AjxpNode
	 * @param metaFound String
	 */
	addResult : function(folderName, ajxpNode, metaFound){

        var noRes =  $(this._resultsBoxId).down('#no-results-found');
        if(noRes) noRes.remove();

        if(this._rootNode){
            this._rootNode.addChild(ajxpNode);
            return;
        }

		var fileName = ajxpNode.getLabel();
		var icon = ajxpNode.getIcon();
		// Display the result in the results box.
		if(folderName == "") folderName = "/";
        if(this._searchMode == "remote"){
            folderName = getRepName(ajxpNode.getPath());
        }
		var isFolder = false;
		if(icon == null) // FOLDER CASE
		{
			isFolder = true;
			icon = 'folder.png';
			if(folderName != "/") folderName += "/";
			folderName += fileName;
		}
        var imgPath = resolveImageSource(icon, '/images/mimes/16', 16);
		var imageString = '<img align="absmiddle" width="16" height="16" src="'+imgPath+'"> ';
		var stringToDisplay;
		if(metaFound){
			stringToDisplay = fileName + ' (' + this.highlight(metaFound, this.crtText, 20)+ ') ';
		}else{
			stringToDisplay = this.highlight(fileName, this.crtText);
		}
		
		var divElement = new Element('div', {title:MessageHash[224]+' '+ folderName, className:(this._even?'even':'')}).update(imageString+stringToDisplay);
        this._even = !this._even;
		$(this._resultsBoxId).insert(divElement);
        if(this._searchMode == 'remote' && ajxpNode.getMetadata().get("search_score")){
            /*divElement.insert(new Element('a', {className:"searchUnindex"}).update("X"));*/
            divElement.insert(new Element('span', {className:"searchScore"}).update("SCORE "+ajxpNode.getMetadata().get("search_score")));
        }
		if(isFolder)
		{
			divElement.observe("click", function(e){
				ajaxplorer.goTo(folderName);
			});
		}
		else
		{
			divElement.observe("click", function(e){
				ajaxplorer.goTo(folderName+"/"+fileName);
			});
		}
        this.hasResults = true;
	},
    addNoResultString : function(){
        if(!$(this._resultsBoxId).down('#no-results-found') && !(this._rootNode && this._rootNode.getChildren().length)){
            $(this._resultsBoxId).insert({top: new Element('div', {id:'no-results-found'}).update(MessageHash[478])});
        }
    },
    /**
     * Put a folder to search in the queue
     * @param path String
     * @param remoteIndexation
     */
	appendFolderToQueue : function(path, remoteIndexation){
		this._queue.push({path:path,remoteIndexation:remoteIndexation?remoteIndexation:false});
	},
	/**
	 * Process the next element of the queue, or finish
	 */
	searchNext : function(){
		if(this._queue.length){
			var element = this._queue.first();
			this._queue.shift();
			this.searchFolderContent(element.path, element.remoteIndexation);
		}else{
			this.updateStateFinished();
		}
	},

    buildNodeProviderProperties: function(currentFolder, remote_indexation){

        var props = {};
        if(this._searchMode == "remote"){
            /* REMOTE INDEXER CASE */
            props.get_action = 'search';
            props.query = this.crtText;
            if(this.hasMetaSearch()){
                props.fields =  this.getSearchColumns().join(',');
            }
        }else{

            if(remote_indexation){

                props.get_action = remote_indexation;
                props.query = this.crtText;
                if(this.hasMetaSearch()){
                    props.fields =  this.getSearchColumns().join(',');
                }
                props.dir = currentFolder;

            }else{

                props.get_action = 'ls';
                props.options = 'a' + (this.hasMetaSearch()?'l':'');
                props.dir = currentFolder;

            }

        }
        return props;

    },

	/**
	 * Get a folder content and searches its children 
	 * Should reference the IAjxpNodeProvider instead!! Still a "ls" here!
	 * @param currentFolder String
     * @param remote_indexation Boolean
     * @param limit integer
	 */
	searchFolderContent : function(currentFolder, remote_indexation, limit){
		if(this._state == 'interrupt') {
			this.updateStateFinished();
			return;
		}
        var connexion;
        if(this._searchMode == "remote"){
            /* REMOTE INDEXER CASE */
            connexion = new Connexion();
            connexion.discrete = true;
            connexion.addParameter('get_action', 'search');
            connexion.addParameter('query', this.crtText);
            if(limit){
                connexion.addParameter('limit', limit);
            }
            if(this.hasMetaSearch()){
                connexion.addParameter('fields', this.getSearchColumns().join(','));
            }
            connexion.onComplete = function(transport){
                ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                this.removeOnLoad($(this._resultsBoxId));
                this._parseResults(transport.responseXML, currentFolder);
                this.updateStateFinished();
            }.bind(this);
            this.setOnLoad($(this._resultsBoxId));
            connexion.sendAsync();
        }else{

            if(remote_indexation){

                connexion = new Connexion();
                connexion.addParameter('get_action', remote_indexation);
                connexion.addParameter('query', this.crtText);
                connexion.addParameter('dir', currentFolder);
                if(this.hasMetaSearch()){
                    connexion.addParameter('fields', this.getSearchColumns().join(','));
                }
                connexion.onComplete = function(transport){
                    this.removeOnLoad($(this._resultsBoxId));
                    this._parseResults(transport.responseXML, currentFolder);
                    this.searchNext();
                }.bind(this);
                this.setOnLoad($(this._resultsBoxId));
                connexion.sendAsync();

            }else{

                /* LIST CONTENT, SEARCH CLIENT SIDE, AND RECURSE */
                connexion = new Connexion();
                connexion.addParameter('get_action', 'ls');
                connexion.addParameter('options', 'a' + (this.hasMetaSearch()?'l':''));
                connexion.addParameter('dir', currentFolder);
                connexion.onComplete = function(transport){
                    this._parseXmlAndSearchString(transport.responseXML, currentFolder);
                    this.searchNext();
                }.bind(this);
                connexion.sendAsync();

            }

        }
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
						this.appendFolderToQueue(newPath, node.getMetadata().get("remote_indexation"));
					}
				}
			}		
		}
	},
	
	_parseResults : function(oXmlDoc, currentFolder){
		if(this._state == 'interrupt' || oXmlDoc == null || oXmlDoc.documentElement == null){
			this.updateStateFinished();
			return;
		}
		var nodes = XPathSelectNodes(oXmlDoc.documentElement, "tree");
        if(!nodes.length){
            this.addNoResultString();
        }else{
            var noRes =  $(this._resultsBoxId).down('#no-results-found');
            if(noRes) noRes.remove();
        }
		for (var i = 0; i < nodes.length; i++) 
		{
			if (nodes[i].tagName == "tree")
			{
				var ajxpNode = this.parseAjxpNode(nodes[i]);
                if(this.hasMetaSearch()){
                    var searchCols = this.getSearchColumns();
                    var added = false;
                    for(var k=0;k<searchCols.length;k++){
                        var meta = ajxpNode.getMetadata().get(searchCols[k]);
                        if(meta && meta.toLowerCase().indexOf(this.crtText) != -1){
                            this.addResult(currentFolder, ajxpNode, meta);
                            added = true;
                        }
                    }
                    if(!added){
                        this.addResult(currentFolder, ajxpNode);
                    }
                }else{
				    this.addResult(currentFolder, ajxpNode);
                }
			}
		}		
		if(this._fileList){
            //this._fileList.reload();
            this._fileList._sortableTable.sort(0);
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
            if(this._fileList){
                //this._fileList.reload();
                this._fileList._sortableTable.sort(0);
            }
            return;
		}
		if(!searchCols) return;
		for(var i=0;i<searchCols.length;i++){
			var meta = ajxpNode.getMetadata().get(searchCols[i]);
			if(meta && meta.toLowerCase().indexOf(this.crtText) != -1){
				this.addResult(currentFolder, ajxpNode, meta);
                if(this._fileList){
                    //this._fileList.reload();
                    this._fileList._sortableTable.sort(0);
                }
                return;
			}
		}
	},
	/**
	 * Parses an XMLNode and create an AjxpNode
	 * @param xmlNode XMLNode
	 * @returns AjxpNode
	 */
	parseAjxpNode : function(xmlNode){
		var node = new AjxpNode(
			xmlNode.getAttribute('filename'), 
			(xmlNode.getAttribute('is_file') == "1" || xmlNode.getAttribute('is_file') == "true"), 
			xmlNode.getAttribute('text'),
			xmlNode.getAttribute('icon'));
		var metadata = new Hash();
		for(var i=0;i<xmlNode.attributes.length;i++)
		{
			metadata.set(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].value);
			if(Prototype.Browser.IE && xmlNode.attributes[i].nodeName == "ID"){
				metadata.set("ajxp_sql_"+xmlNode.attributes[i].nodeName, xmlNode.attributes[i].value);
			}
		}
		node.setMetadata(metadata);
		return node;
	},
	/**
	 * Highlights a string with the search term
	 * @param haystack String
	 * @param needle String
	 * @param truncate Integer
	 * @returns String
	 */
	highlight : function(haystack, needle, truncate){
		var start = haystack.toLowerCase().indexOf(needle);
        if(start == -1) return haystack;
		var end = start + needle.length;
		if(truncate && haystack.length > truncate){
			var newStart = Math.max(Math.round((end + start) / 2 - truncate / 2), 0);
			var newEnd = Math.min(Math.round((end + start) / 2 + truncate / 2),haystack.length);
			haystack = haystack.substring(newStart, newEnd);
			if(newStart > 0) haystack = '...' + haystack;
			if(newEnd < haystack.length) haystack = haystack + '...';
			// recompute
			start = haystack.toLowerCase().indexOf(needle);
			end = start + needle.length;
		}
		return haystack.substring(0, start)+'<em>'+haystack.substring(start, end)+'</em>'+haystack.substring(end);
	},

    /**
     * Add a loading image to the given element
     * @param element Element dom node
     */
    setOnLoad : function(element){
        addLightboxMarkupToElement(element);
        var img = new Element("img", {src : ajxpResourcesFolder+"/images/loadingImage.gif", style:"margin-top: 10px;"});
        $(element).down("#element_overlay").insert(img);
        this.loading = true;
    },
    /**
     * Removes the image from the element
     * @param element Element dom node
     */
    removeOnLoad : function(element){
        removeLightboxFromElement(element);
        this.loading = false;
    }
		
});