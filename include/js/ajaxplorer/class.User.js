User = Class.create({

	id : undefined,
	activeRepository:undefined,
	read:false,
	write:false,
	preferences:undefined,
	repositories: undefined,

	initialize:function(id){
		this.id = id;
		this.preferences = new Hash();
		this.repositories = new Hash();
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
});