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
 * Description : Extension of the SearchEngine class for SQL search.
 */
Class.create("SQLSearchEngine", SearchEngine, {
	
	initGUI:function(){
		this.htmlElement.update('<div style="font-style:italic;color:#999;margin-top:5px;">'+MessageHash["sql.2"]+'</div><textarea id="sql_query" style="width:100%; overflow:auto;"></textarea><div class="dialogButtons"><img height="16" width="16" id="search_button" value="Search" style="margin-top:5px;cursor:pointer;" src="'+ajxpResourcesFolder+'/images/actions/16/search.png" title="'+MessageHash["sql.3"]+'"/> <img height="16" width="16" id="clear_button" value="Clear" style="margin-top:5px;cursor:pointer;margin-right:5px;" src="'+ajxpResourcesFolder+'/images/actions/16/fileclose.png" title="'+MessageHash["sql.4"]+'"/></div>');
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
		connexion.sendSync();
		var path = "/ajxpmysqldriver_searchresults";
		ajaxplorer.updateContextData(new AjxpNode(path));
	},
	
	resize:function(){
		fitHeightToBottom(this.sqlQuery, null, 27);
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