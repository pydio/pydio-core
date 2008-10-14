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