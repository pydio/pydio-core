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

Class.create("AjxpSimpleTabs", AjxpPane, {

    panes:null,
    tabRow:null,
    fitHeight: true,

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param htmlElement HTMLElement Anchor of this pane
	 * @param tabulatorOptions Object Widget options
	 */
	initialize : function($super, htmlElement, tabulatorOptions){
		$super(htmlElement, tabulatorOptions);
        if(tabulatorOptions && tabulatorOptions.autoHeight){
            this.fitHeight = false;
        }
        if(!htmlElement.down("div.tabpanes")){
            htmlElement.insert(new Element("div", {className:"tabpanes"}));
        }
        this.panes = htmlElement.down("div.tabpanes");
        if(this.fitHeight) fitHeightToBottom(this.panes, this.htmlElement);
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
                if(this.options.saveState){
                    var test = this.loadState();
                    if(test !== undefined) {
                        this.selectTabByIndex(test);
                        return;
                    }
                }
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
        if(this.fitHeight) fitHeightToBottom(pane, this.panes);
        pane.setStyle({overflowY:"auto"});
        attachMobileScroll(pane, "vertical");
        tab.setSelected = function(){
            this.panes.childElements("div.tabPane").invoke("hide");
            tab.tabPANE.show();
            this.tabRow.select("li").invoke("removeClassName", "selected");
            tab.addClassName("selected");
            if(this.fitHeight) pane.setStyle({height:parseInt(this.panes.getHeight())+"px"});
            if(tab.tabPANE.resizeOnShow){
                tab.tabPANE.resizeOnShow(tab,tab.tabPANE);
            }
        }.bind(this);
        tab.observe("click", function(){
            tab.setSelected();
            if(this.options.saveState){
                var index = this.tabRow.select('li').indexOf(tab);
                this.saveState(index);
            }
        }.bind(this));
        tab.setSelected();
    },

    selectTabByIndex : function(index){
        try{
            this.tabRow.select("li")[index].setSelected();
            this.notify("switch");
            if(this.options.saveState){
                this.saveState(index);
            }
        }catch(e){}
    },

    saveState : function(index){
        this.setUserPreference("tabs_state", "selected_"+index);
    },

    loadState : function(){
        var pref = this.getUserPreference("tabs_state");
        if(pref && pref.startsWith("selected_")){
            return parseInt(pref.replace("selected_", ""));
        }
        return undefined;
    },

	/**
	 * Resizes the widget
	 */
	resize : function(){
        if(this.fitHeight) fitHeightToBottom(this.panes, this.htmlElement);
        var tRW = this.tabRow.getWidth();
        var padding = 0;
        var lis = this.tabRow.select("li");
        var currentSum = 0;
        lis.each(function(t){
            t.setStyle({width:'auto', maxWidth:'none'});
            currentSum += t.getWidth();
        });
        if(currentSum > tRW){
            if(lis.size()){
                padding = parseInt(lis.first().getStyle('paddingLeft')) + parseInt(this.tabRow.down('li').getStyle('paddingRight'));
            }
            var maxWidth = Math.round(tRW / lis.size()) - padding - 2;
        }

        this.tabRow.select("li").each(function(tab){
            if(maxWidth) {
                tab.setStyle({maxWidth:maxWidth+'px'});
            }
            if(tab.tabPANE){
                if(this.fitHeight) fitHeightToBottom(tab.tabPANE, this.panes);
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