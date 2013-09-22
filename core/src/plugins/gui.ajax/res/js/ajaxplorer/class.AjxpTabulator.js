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

Class.create("AjxpTabulator", AjxpPane, {
	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param htmlElement HTMLElement Anchor of this pane
	 * @param tabulatorOptions Object Widget options
	 */
	initialize : function($super, htmlElement, tabulatorOptions){
		$super(htmlElement, tabulatorOptions);
		this.tabulatorData 	= $A(tabulatorOptions.tabInfos);
        if(tabulatorOptions.registerAsEditorOpener){
            ajaxplorer.registerEditorOpener(this);
        }
		// Tabulator Data : array of tabs infos
		// { id , label, icon and element : tabElement }.
		// tab Element must implement : showElement() and resize() methods.
		// Add drop shadow here, otherwise the negative value gets stuck in the CSS compilation...
		var div = new Element('div', {className:'tabulatorContainer panelHeader'});
		$(this.htmlElement).insert({top:div});
		this.tabulatorData.each(function(tabInfo){
            var tab = this._renderTab(tabInfo);
			div.insert(tab);
			this.selectedTabInfo = tabInfo; // select last one by default
            var paneObject = this.getAndSetAjxpObject(tabInfo);
            if(!tabInfo.label && paneObject){
                paneObject.getDomNode().observe("editor:updateTitle", function(event){
                    tabInfo.headerElement.down(".tab_label").update(event.memo);
                }.bind(modal));
                paneObject.getDomNode().observe("editor:updateIconClass", function(event){
                    tabInfo.headerElement.down("span").replace(new Element('span',{className:event.memo}));
                }.bind(modal));
            }
		}.bind(this));
        if(this.options.headerToolbarOptions){
            var tbD = new Element('div', {id:"display_toolbar"});
            div.insert({top:tbD});
            var tb = new ActionsToolbar(tbD, this.options.headerToolbarOptions);
        }
        if(tabulatorOptions.defaultTabId){
            this.switchTabulator(tabulatorOptions.defaultTabId);
        }

	},

    _renderTab:function(tabInfo){

        if(tabInfo.ajxpClass && tabInfo.ajxpOptions){
            var td = new Element('span', {className:'toggleHeader'});
            var klass = Class.getByName(tabInfo.ajxpClass);
            new klass(td, tabInfo.ajxpOptions);
            tabInfo.headerElement = td;
            if(tabInfo.closeable){
                td.insert(new Element('span', {className:'icon-remove tab_close_button'}));
                td.down('.tab_close_button').observe('click', function(){
                    this.closeTab(tabInfo.id);
                }.bind(this));
            }
            return td;
        }else{
            var label = "";
            if(tabInfo.label){
                label = MessageHash[tabInfo.label] || tabInfo.label;
            }
            var td = new Element('span', {className:'toggleHeader', title:MessageHash[tabInfo.title] || label.stripTags()});
            if(tabInfo.icon){
                td.insert('<img width="16" height="16" align="absmiddle" src="'+resolveImageSource(tabInfo.icon, '/images/actions/ICON_SIZE', 16)+'">');
            }
            if(tabInfo.iconClass){
                td.insert(new Element('span', {className:tabInfo.iconClass}));
            }
            td.insert('<span class="tab_label" ajxp_message_id="'+tabInfo.label+'">'+label+'</span>');
            td.observe('click', function(){
                this.switchTabulator(tabInfo.id);
            }.bind(this) );
            if(tabInfo.closeable){
                td.insert(new Element('span', {className:'icon-remove tab_close_button'}));
                td.down('.tab_close_button').observe('click', function(){
                    this.closeTab(tabInfo.id);
                }.bind(this));
            }
            tabInfo.headerElement = td;
            disableTextSelection(td);
            return td;
        }

    },

    openEditorForNode:function(ajxpNode, editorData){
        this.addTab({
            id:editorData.id + ":/" + ajxpNode.getPath(),
            label:editorData.text,
            iconClass:editorData.icon_class,
            closeable:true
        }, {
            type:"editor",
            editorData:editorData,
            node:ajxpNode
        });
    },


    /**
     *
     * @param tabInfo
     */
    addTab: function(tabInfo, paneInfo){
        var existing = this.tabulatorData.detect(function(internalInfo){return internalInfo.id == tabInfo.id;});
        if(existing) {
            this.switchTabulator(existing.id);
            return;
        }
        $(this.htmlElement).down('.tabulatorContainer').insert(this._renderTab(tabInfo));
        if(!tabInfo.element){
            // generate a random element id
            var randomId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random()*16|0, v = c == 'x' ? r : (r&0x3|0x8);
                return v.toString(16);
            });
            tabInfo.element = "dynamic-panel-" + randomId;
        }

        $(this.htmlElement).insert(new Element("div", {id:tabInfo.element}));
        fitHeightToBottom($(this.htmlElement).down("#"+tabInfo.element));

        if(paneInfo.type == 'editor' && paneInfo.node){
            var editorData;
            if(paneInfo.editorData){
                editorData = paneInfo.editorData;
            }else{
                var selectedMime = getAjxpMimeType(paneInfo.node);
                var editors = ajaxplorer.findEditorsForMime(selectedMime);
                if(editors.length && editors[0].openable){
                    editorData = editors[0];
                }
            }
            if(editorData){
                ajaxplorer.loadEditorResources(editorData.resourcesManager);
                var oForm = $("all_forms").down("#"+editorData.formId).cloneNode(true);
                $(tabInfo.element).insert(oForm);
                var editorOptions = {
                    closable: false,
                    context: this,
                    editorData: editorData
                };
                var editor = eval ( 'new '+editorData.editorClass+'(oForm, editorOptions)' );
                editor.getDomNode().observe("editor:updateTitle", function(event){
                    tabInfo.headerElement.down(".tab_label").update(event.memo);
                }.bind(modal));
                editor.getDomNode().observe("editor:updateIconClass", function(event){
                    tabInfo.headerElement.down("span").replace(new Element('span',{className:event.memo}));
                }.bind(modal));
                editor.open(paneInfo.node);
                tabInfo.ajxpObject = editor;
                editor.resize();
            }
        }else if(paneInfo.type == 'widget'){

        }
        this.tabulatorData.push(tabInfo);
        this.switchTabulator(tabInfo.id);
        this.resize();
        window.setTimeout(this.resize.bind(this), 750);
    },

    /**
     *
     * @param tabId
     */
    closeTab: function(tabId){
        var ti;
        var previousTab;
        this.tabulatorData.each(function(tabInfo){
            if(tabInfo.id == tabId){
                var ajxpObject = this.getAndSetAjxpObject(tabInfo);
                if(ajxpObject){
                    if(ajxpObject.validateClose){
                        var test = ajxpObject.validateClose();
                        if(!test) return;
                    }
                    ajxpObject.showElement(false);
                    ajxpObject.destroy();
                }
                var pane = $(this.htmlElement).down('#'+tabInfo.element);
                if(pane){
                    pane.remove();
                }
                tabInfo.headerElement.stopObserving("click");
                tabInfo.headerElement.remove();
                ti = tabInfo;
                throw $break;
            }
            previousTab = tabInfo;
        }.bind(this));
        if(ti){
            this.tabulatorData = this.tabulatorData.without(ti);
        }
        this.resize();
        if(previousTab) this.switchTabulator(previousTab.id);
        else if(this.tabulatorData.length) this.switchTabulator(this.tabulatorData.first().id);
    },

	/**
	 * Tab change
	 * @param tabId String The id of the target tab
	 */
	switchTabulator:function(tabId){
		var toShow ;
        var toShowElement;
		this.tabulatorData.each(function(tabInfo){
			var ajxpObject = this.getAndSetAjxpObject(tabInfo);
            tabInfo.headerElement.removeClassName("toggleInactiveBeforeActive");
			if(tabInfo.id == tabId){				
				tabInfo.headerElement.removeClassName("toggleInactive");
				if(tabInfo.headerElement.down('img')) tabInfo.headerElement.down('img').show();
				if(ajxpObject){
					toShow = ajxpObject;
                    toShowElement = tabInfo.element;
				}
				this.selectedTabInfo = tabInfo;
                if(tabInfo.headerElement.previous('.toggleHeader')){
                    tabInfo.headerElement.previous('.toggleHeader').addClassName('toggleInactiveBeforeActive');
                }
			}else{
				tabInfo.headerElement.addClassName("toggleInactive");
                if(tabInfo.headerElement.down('img')) tabInfo.headerElement.down('img').hide();
				if(ajxpObject){
					ajxpObject.showElement(false);
                    if($(tabInfo.element)) $(tabInfo.element).hide();
				}
			}
		}.bind(this));
		if(toShow){
            if($(toShowElement)) $(toShowElement).show();
			toShow.showElement(true);
            if(this.htmlElement.up('div[ajxpClass="Splitter"]') && this.htmlElement.up('div[ajxpClass="Splitter"]').ajxpPaneObject){
                var splitter = this.htmlElement.up('div[ajxpClass="Splitter"]').ajxpPaneObject;
                if(splitter.splitbar.hasClassName('folded') && (Element.descendantOf(this.htmlElement, splitter.paneA) || this.htmlElement == splitter.paneA ) ){
                    splitter.unfold();
                }
            }
			toShow.resize();
		}
        this.resize();
        this.notify("switch", tabId);
	},
	
	/**
	 * Resizes the widget
	 */
	resize : function(){
		if(!this.selectedTabInfo) return;
		var ajxpObject = this.getAndSetAjxpObject(this.selectedTabInfo);
		if(ajxpObject){
            var nodeElement = $(this.htmlElement).down("#"+this.selectedTabInfo.element);
            fitHeightToBottom(nodeElement);
            ajxpObject.resize(nodeElement?nodeElement.getHeight():this.htmlElement.getHeight());
            var left ;
            var total = 0;
            var cont = this.htmlElement.down('div.tabulatorContainer');
            var innerWidth = parseInt(this.htmlElement.getWidth()) - parseInt(cont.getStyle('paddingLeft')) - parseInt(cont.getStyle('paddingRight'));
            if(this.options.headerToolbarOptions){
                innerWidth -= parseInt(this.htmlElement.down('div#display_toolbar').getWidth());
            }
            cont.removeClassName('icons_only');
            this.htmlElement.removeClassName('tabulator-vertical');
            this.tabulatorData.each(function(tabInfo){
                var header = tabInfo.headerElement;
                header.setStyle({width:'auto'});
                var hWidth = parseInt(header.getWidth());
                if(tabInfo == this.selectedTabInfo){
                    left = innerWidth - hWidth;
                }
                total += hWidth;
            }.bind(this));
            if(total >= innerWidth){
                var part = parseInt( left / ( this.tabulatorData.length -1) ) ;
                if(part < 14){
                    cont.addClassName('icons_only');
                }
                if(innerWidth < 30 ){
                    this.htmlElement.addClassName('tabulator-vertical');
                }
                this.tabulatorData.each(function(tabInfo){
                    var header = tabInfo.headerElement;
                    if(tabInfo != this.selectedTabInfo){
                        try{
                            header.setStyle({width:part - ( parseInt(header.getStyle('paddingRight')) + parseInt(header.getStyle('paddingLeft')) +  parseInt(header.getStyle('borderRightWidth'))  +  parseInt(header.getStyle('borderLeftWidth')) ) + 'px'});
                        }catch(e){}
                    }
                }.bind(this));
            }
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
		this.tabulatorData.each(function(tabInfo){
			var ajxpObject = this.getAndSetAjxpObject(tabInfo);
			tabInfo.headerElement.stopObserving("click");
			ajxpObject.destroy();
		}.bind(this));
		this.htmlElement.update("");
        if(window[this.htmlElement.id]){
            try{delete window[this.htmlElement.id];}catch(e){}
        }
		this.htmlElement = null;
	},
	
	
	/**
	 * Getter/Setter of the Widget that will be attached to each tabInfo
	 * @param tabInfo Object
	 * @returns IAjxpWidget
	 */
	getAndSetAjxpObject : function(tabInfo){
		var ajxpObject = tabInfo.ajxpObject || null;
        var nodeElement = $(tabInfo.element);
		if(nodeElement && nodeElement.ajxpPaneObject && (!ajxpObject || ajxpObject != nodeElement.ajxpPaneObject) ){
			ajxpObject = tabInfo.ajxpObject = nodeElement.ajxpPaneObject;
		}
		return ajxpObject;		
	}
	
});