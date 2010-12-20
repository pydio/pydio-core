/**
 * @package info.ajaxplorer
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
 * Description : Simple tab switcher
 */
Class.create("AjxpTabulator", AjxpPane, {
	initialize : function($super, htmlElement, tabulatorOptions){
		$super(htmlElement);
		this.tabulatorData = tabulatorOptions.tabInfos;		
		// Tabulator Data : array of tabs infos
		// { id , label, icon and element : tabElement }.
		// tab Element must implement : showElement() and resize() methods.
		var table = new Element('table', {cellpadding:0,cellspacing:0,border:0,width:'100%',style:'height:24px;'});		
		$(this.htmlElement).insert({top:table});
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
	
	resize : function(){
		if(!this.selectedTabInfo) return;
		var ajxpObject = this.getAndSetAjxpObject(this.selectedTabInfo);
		if(ajxpObject){
			ajxpObject.resize();
		}
	},
	
	getAndSetAjxpObject : function(tabInfo){
		var ajxpObject = tabInfo.ajxpObject || null;
		if($(tabInfo.element).ajxpPaneObject && (!ajxpObject || ajxpObject != $(tabInfo.element).ajxpPaneObject) ){
			ajxpObject = tabInfo.ajxpObject = $(tabInfo.element).ajxpPaneObject;
		}
		return ajxpObject;		
	}
	
});