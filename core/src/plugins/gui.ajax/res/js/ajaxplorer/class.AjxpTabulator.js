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

Class.create("AjxpTabulator", AjxpPane, {
	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param htmlElement HTMLElement Anchor of this pane
	 * @param tabulatorOptions Object Widget options
	 */
	initialize : function($super, htmlElement, tabulatorOptions){
		$super(htmlElement, tabulatorOptions);
		this.tabulatorData 	= tabulatorOptions.tabInfos;		
		// Tabulator Data : array of tabs infos
		// { id , label, icon and element : tabElement }.
		// tab Element must implement : showElement() and resize() methods.
		// Add drop shadow here, otherwise the negative value gets stuck in the CSS compilation...
		var div = new Element('div', {className:'tabulatorContainer panelHeader'});
		$(this.htmlElement).insert({top:div});
		this.tabulatorData.each(function(tabInfo){
			var td = new Element('span', {className:'toggleHeader', title:MessageHash[tabInfo.label]});
            if(tabInfo.icon){
                td.insert('<img width="16" height="16" align="absmiddle" src="'+resolveImageSource(tabInfo.icon, '/images/actions/ICON_SIZE', 16)+'">');
            }
            if(tabInfo.iconClass){
                td.insert(new Element('span', {className:tabInfo.iconClass}));
            }
            td.insert('<span class="tab_label" ajxp_message_id="'+tabInfo.label+'">'+MessageHash[tabInfo.label]+'</span>');
			td.observe('click', function(){
				this.switchTabulator(tabInfo.id);
			}.bind(this) );
			div.insert(td);
			tabInfo.headerElement = td;
			disableTextSelection(td);
			this.selectedTabInfo = tabInfo; // select last one by default
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
	
	/**
	 * Tab change
	 * @param tabId String The id of the target tab
	 */
	switchTabulator:function(tabId){
		var toShow ;
		this.tabulatorData.each(function(tabInfo){
			var ajxpObject = this.getAndSetAjxpObject(tabInfo);
			if(tabInfo.id == tabId){				
				tabInfo.headerElement.removeClassName("toggleInactive");
				if(tabInfo.headerElement.down('img')) tabInfo.headerElement.down('img').show();
				if(ajxpObject){
					toShow = ajxpObject;
				}
				this.selectedTabInfo = tabInfo;
			}else{
				tabInfo.headerElement.addClassName("toggleInactive");
                if(tabInfo.headerElement.down('img')) tabInfo.headerElement.down('img').hide();
				if(ajxpObject){
					ajxpObject.showElement(false);
				}
			}
		}.bind(this));
		if(toShow){
			toShow.showElement(true);
            if(this.htmlElement.up('div[ajxpClass="Splitter"]') && this.htmlElement.up('div[ajxpClass="Splitter"]').ajxpPaneObject){
                var splitter = this.htmlElement.up('div[ajxpClass="Splitter"]').ajxpPaneObject;
                if(splitter.splitbar.hasClassName('folded')){
                    splitter.unfold();
                }
            }
			toShow.resize();
		}
        this.resize();
	},
	
	/**
	 * Resizes the widget
	 */
	resize : function(){
		if(!this.selectedTabInfo) return;
		var ajxpObject = this.getAndSetAjxpObject(this.selectedTabInfo);
		if(ajxpObject){
			ajxpObject.resize();
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
                        header.setStyle({width:part - ( parseInt(header.getStyle('paddingRight')) + parseInt(header.getStyle('paddingLeft')) ) + 'px'});
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
		if($(tabInfo.element) && $(tabInfo.element).ajxpPaneObject && (!ajxpObject || ajxpObject != $(tabInfo.element).ajxpPaneObject) ){
			ajxpObject = tabInfo.ajxpObject = $(tabInfo.element).ajxpPaneObject;
		}
		return ajxpObject;		
	}
	
});