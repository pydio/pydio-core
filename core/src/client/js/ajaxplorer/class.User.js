User = Class.create({

	id : undefined,
	activeRepository:undefined,
	read:false,
	write:false,
	preferences:undefined,
	repositories: undefined,
	repoIcons:undefined,
	repoSearchEngines:undefined,
	isAdmin:false,

	initialize:function(id, xmlDef){
		this.id = id;
		this.preferences = new Hash();
		this.repositories = new Hash();
		this.repoIcon = new Hash();
		this.repoSearchEngines = new Hash();
		if(xmlDef) this.loadFromXml(xmlDef);
	},

	setActiveRepository : function (id, read, write){
		this.activeRepository = id;
		this.read = (read=="1"?true:false);
		this.write = (write=="1"?true:false);
	},
	
	getActiveRepository : function(){
		return this.activeRepository;
	},
	
	canRead : function(){
		return this.read;
	},
	
	canWrite : function(){
		return this.write;
	},
	
	getPreference : function(prefName){
	    return this.preferences.get(prefName);	
	},
	
	getRepositoriesList : function(){
		return this.repositories;
	},
	
	setPreference : function(prefName, prefValue){
		this.preferences.set(prefName, prefValue);
	},
	
	setRepositoriesList : function(repoHash){
		this.repositories = repoHash;
	},
	
	getRepositoryIcon : function(repoId){
		return this.repoIcon.get(repoId);
	},
	
	getRepoSearchEngine : function(repoId){
		return this.repoSearchEngines.get(repoId);
	},
	
	savePreferences : function(oldPass, newPass, seed, onCompleteFunc){
		var conn = new Connexion();
		conn.addParameter("get_action", "save_user_pref");
		var i=0;
		this.preferences.each(function(pair){
			conn.addParameter("pref_name_"+i, pair.key);
			conn.addParameter("pref_value_"+i, pair.value);
			i++;
		});
		if(oldPass && newPass)
		{
			conn.addParameter("pref_name_"+i, "password");
			conn.addParameter("pref_value_"+i, newPass);
			conn.addParameter("crt", oldPass);
			conn.addParameter("pass_seed", seed);
		}
		conn.onComplete = onCompleteFunc;
		conn.sendAsync();
	}, 
	
	loadFromXml: function(userNodes){
	
		var repositories = new Hash();
		for(var i=0; i<userNodes.length;i++)
		{
			if(userNodes[i].nodeName == "active_repo")
			{
				this.setActiveRepository(userNodes[i].getAttribute('id'), 
										userNodes[i].getAttribute('read'), 
										userNodes[i].getAttribute('write'));
			}
			else if(userNodes[i].nodeName == "repositories")
			{
				for(j=0;j<userNodes[i].childNodes.length;j++)
				{
					var repoChild = userNodes[i].childNodes[j];
					if(repoChild.nodeName == "repo") {
						repositories.set(repoChild.getAttribute("id"), repoChild.firstChild.nodeValue);
						if(repoChild.getAttribute("icon")){
							this.repoIcon.set(repoChild.getAttribute("id"), repoChild.getAttribute("icon"));
						}
						if(repoChild.getAttribute("search_engine")){
							this.repoSearchEngines.set(repoChild.getAttribute("id"), repoChild.getAttribute("search_engine"));
						}
					}
				}
				this.setRepositoriesList(repositories);
			}
			else if(userNodes[i].nodeName == "preferences")
			{
				for(j=0;j<userNodes[i].childNodes.length;j++)
				{
					var prefChild = userNodes[i].childNodes[j];
					if(prefChild.nodeName == "pref") {
						this.setPreference(prefChild.getAttribute("name"), 
							 				prefChild.getAttribute("value"));
					}
				}					
			}
			else if(userNodes[i].nodeName == "special_rights")
			{
				var attr = userNodes[i].getAttribute("is_admin");
				if(attr && attr == "1") this.isAdmin = true;
			}
		}
			
	}
});