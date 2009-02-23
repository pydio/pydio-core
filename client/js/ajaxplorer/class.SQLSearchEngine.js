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
 * Description : Extension of the SearchEngine class for SQL search.
 */
SQLSearchEngine = Class.create(SearchEngine, {
	
	initGUI:function(){
		this.htmlElement.update('<div style="font-style:italic;color:#999;">Type your SQL query here :</div><textarea id="sql_query" style="width:100%; overflow:auto;"></textarea><div class="dialogButtons"><input type="button" id="search_button" value="Search" class="dialogButton" style="margin-top:5px;"/> <input type="button" id="clear_button" value="Clear" class="dialogButton" style="margin-top:5px;"/></div>');
		this.sqlQuery = $('sql_query');
		
		this.sqlQuery.observe("focus", function(e){
			ajaxplorer.disableShortcuts();
			this.hasFocus = true;
			//this.sqlQuery.select();
			Event.stop(e);
		}.bind(this));
		this.sqlQuery.observe("blur", function(e){
			ajaxplorer.enableShortcuts();
			this.hasFocus = false;
		}.bind(this) );
		this.sqlQuery.observe("keydown", function(e){
			if(e.keyCode == Event.KEY_RETURN && e["ctrlKey"]){
				this.performSearch(this.sqlQuery.getValue());
				Event.stop(e);
			}
		}.bind(this));

		this.searchButton = $('search_button');
		this.searchButton.observe('click', function(e){
			this.performSearch(this.sqlQuery.getValue());
		}.bind(this));
		this.clearButton = $('clear_button');
		this.clearButton.observe('click', function(e){
			this.sqlQuery.update("");
			this.sqlQuery.value = "";
		}.bind(this));
		
		this.resize();
	},
	
	performSearch:function(query){
		if(query == '') return;
		var connexion = new Connexion();
		var params = new Hash();
		params.set('get_action', 'set_query');
		params.set('query', query);
		connexion.setParameters(params);
		var res = connexion.sendSync();
		var path = "/ajxpmysqldriver_searchresults";
		ajaxplorer.getFoldersTree().goToDeepPath(path);
		ajaxplorer.filesList.loadXmlList(path);
		ajaxplorer.getActionBar().updateLocationBar(path);
	},
	
	resize:function(){
		fitHeightToBottom(this.sqlQuery, null, 43, true);
	},
	
	focus:function(){
		if(this.htmlElement.visible()){
			this.sqlQuery.focus();
			this.hasFocus = true;
		}		
	},
	
	blur: function(){
		this.sqlQuery.blur();
		this.hasFocus = false;
	}
});