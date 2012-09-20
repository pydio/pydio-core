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

Class.create("AjxpSimpleTabs", AjxpPane, {

    panes:null,
    tabRow:null,

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param htmlElement HTMLElement Anchor of this pane
	 * @param tabulatorOptions Object Widget options
	 */
	initialize : function($super, htmlElement, tabulatorOptions){
		$super(htmlElement);
        if(!htmlElement.down("div.tabpanes")){
            htmlElement.insert(new Element("div", {className:"tabpanes"}));
        }
        this.panes = htmlElement.down("div.tabpanes");
        fitHeightToBottom(this.panes, this.element);
        if(htmlElement.down("ul.tabrow")){
            this.tabRow = htmlElement.down("ul.tabrow");
            htmlElement.down("ul.tabrow").select("li").each(function(tab){
                var paneId = tab.getAttribute("data-PaneID");
                if(htmlElement.down("#"+paneId)){
                    this.addTab(tab, htmlElement.down("#"+paneId));
                }else{
                    this.addTab(tab);
                }
            }.bind(this));
            window.setTimeout( function(){
                this.selectTabByIndex(0);
            }.bind(this), 100);
        }else{
            htmlElement.insert(new Element("ul", {className:"tabrow"}));
            this.tabRow = htmlElement.down("ul.tabrow");
        }
	},

    addTab: function (tab, pane){
        if(tab instanceof String){
            tab = new Element("li", {className:""}).update(tab);
            this.tabRow.insert(tab);
        }
        if(!pane){
            pane = new Element("div");
        }
        pane.addClassName("tabPane");
        tab.tabPANE = pane;
        this.panes.insert(pane);
        fitHeightToBottom(pane, this.panes);
        pane.setStyle({overflowY:"auto"});
        tab.setSelected = function(){
            this.panes.childElements("div.tabPane").invoke("hide");
            tab.tabPANE.show();
            this.tabRow.select("li").invoke("removeClassName", "selected");
            tab.addClassName("selected");
            pane.setStyle({height:parseInt(this.panes.getHeight())+"px"});
            if(tab.tabPANE.resizeOnShow){
                tab.tabPANE.resizeOnShow(tab,tab.tabPANE);
            }
        }.bind(this);
        tab.observe("click", function(){
            tab.setSelected();
        }.bind(this));
        tab.setSelected();
    },

    selectTabByIndex : function(index){
        try{
            this.tabRow.select("li")[index].setSelected();
        }catch(e){}
    },

	/**
	 * Resizes the widget
	 */
	resize : function(){
        fitHeightToBottom(this.panes, this.element);
        this.tabRow.select("li").each(function(tab){
            if(tab.tabPANE){
                fitHeightToBottom(tab.tabPANE, this.panes);
                if(tab.hasClassName("selected") && tab.tabPANE.resizeOnShow){
                    tab.tabPANE.resizeOnShow(tab, tab.tabPANE);
                }
            }
        }.bind(this) );
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
        /*
		this.tabulatorData.each(function(tabInfo){
			var ajxpObject = this.getAndSetAjxpObject(tabInfo);
			tabInfo.headerElement.stopObserving("click");
			ajxpObject.destroy();
		}.bind(this));
		this.htmlElement.update("");
        if(window[this.htmlElement.id]){
            delete window[this.htmlElement.id];
        }
        */
		this.htmlElement = null;
	}
});