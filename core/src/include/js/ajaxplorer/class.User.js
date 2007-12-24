User = Class.create({

	id : undefined,
	activeRepository:undefined,
	read:false,
	write:false,
	preferences:undefined,
	repositories: undefined,

	initialize:function(id, xmlDef){
		this.id = id;
		this.preferences = new Hash();
		this.repositories = new Hash();
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
	
	savePreferences : function(newPass, onCompleteFunc){
		var conn = new Connexion();
		conn.addParameter("get_action", "save_user_pref");
		var i=0;
		this.preferences.each(function(pair){
			conn.addParameter("pref_name_"+i, pair.key);
			conn.addParameter("pref_value_"+i, pair.value);
			i++;
		});
		if(newPass)
		{
			conn.addParameter("pref_name_"+i, "password");
			conn.addParameter("pref_value_"+i, newPass);
		}
		conn.onComplete = onCompleteFunc;
		conn.sendAsync();
	}, 
	
	loadFromXml: function(userNodes){
	
		var repositories = new Hash();
		for(var i=0; i<userNodes.length;i++)
		{
			if(userNodes[i].tagName == "active_repo")
			{
				this.setActiveRepository(userNodes[i].getAttribute('id'), 
										userNodes[i].getAttribute('read'), 
										userNodes[i].getAttribute('write'));
			}
			else if(userNodes[i].tagName == "repositories")
			{
				for(j=0;j<userNodes[i].childNodes.length;j++)
				{
					var repoChild = userNodes[i].childNodes[j];
					if(repoChild.tagName == "repo") repositories.set(repoChild.getAttribute("id"), repoChild.firstChild.nodeValue);
				}
				this.setRepositoriesList(repositories);
			}
			else if(userNodes[i].tagName == "preferences")
			{
				for(j=0;j<userNodes[i].childNodes.length;j++)
				{
					var prefChild = userNodes[i].childNodes[j];
					if(prefChild.tagName == "pref") {
						this.setPreference(prefChild.getAttribute("name"), 
							 				prefChild.getAttribute("value"));
					}
				}					
			}
		}
			
	}
});