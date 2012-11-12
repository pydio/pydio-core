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
		$super(htmlElement);
		this.tabulatorData 	= tabulatorOptions.tabInfos;		
		// Tabulator Data : array of tabs infos
		// { id , label, icon and element : tabElement }.
		// tab Element must implement : showElement() and resize() methods.
		// Add drop shadow here, otherwise the negative value gets stuck in the CSS compilation...
		var div = new Element('div', {className:'tabulatorContainer', style:'box-shadow: inset 0px -1px 2px #999999;-webkit-box-shadow: inset 0px -1px 2px #999999;-moz-box-shadow: inset 0px -1px 2px #999999;'});
		var table = new Element('table', {cellpadding:0,cellspacing:0,border:0,width:'100%',style:'height:25px;'});		
		$(this.htmlElement).insert({top:div});
		div.update(table);
		var tBody = new Element('tBody');
		var tr = new Element('tr');
		table.update(tBody);
		tBody.update(tr);
		this.tabulatorData.each(function(tabInfo){
			var td = new Element('td').addClassName('toggleHeader');
			td.addClassName('panelHeader');
			td.update('<img width="16" height="16" align="absmiddle" src="'+resolveImageSource(tabInfo.icon, '/images/actions/ICON_SIZE', 16)+'"><span ajxp_message_id="'+tabInfo.label+'">'+MessageHash[tabInfo.label]+'</a>');
			td.observe('click', function(){
				this.switchTabulator(tabInfo.id);
			}.bind(this) );
			tr.insert(td);
			tabInfo.headerElement = td;
			disableTextSelection(td);
			this.selectedTabInfo = tabInfo; // select last one by default
		}.bind(this));
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
				tabInfo.headerElement.select('img')[0].show();
				if(ajxpObject){
					toShow = ajxpObject;
				}
				this.selectedTabInfo = tabInfo;
			}else{
				tabInfo.headerElement.addClassName("toggleInactive");
				tabInfo.headerElement.select('img')[0].hide();
				if(ajxpObject){
					ajxpObject.showElement(false);
				}
			}
		}.bind(this));
		if(toShow){
			toShow.showElement(true);
			toShow.resize();
		}
	},
	
	/**
	 * Resizes the widget
	 */
	resize : function(){
		if(!this.selectedTabInfo) return;
		var ajxpObject = this.getAndSetAjxpObject(this.selectedTabInfo);
		if(ajxpObject){
			ajxpObject.resize();
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
            delete window[this.htmlElement.id];
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