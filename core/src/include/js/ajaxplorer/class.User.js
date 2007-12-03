function User(id){
	this.id = id;
	this.activeRepository;
	this.read = false;
	this.write = false;
	this.preferences = new Hash();
	this.repositories = new Hash();
}

User.prototype.setActiveRepository = function (id, read, write)
{
	this.activeRepository = id;
	this.read = (read=="1"?true:false);
	this.write = (write=="1"?true:false);
}

User.prototype.getActiveRepository = function(){
	return this.activeRepository;
}

User.prototype.canRead = function(){
	return this.read;
}

User.prototype.canWrite = function(){
	return this.write;
}

User.prototype.getPreference = function(prefName){
    return this.preferences[prefName];	
}

User.prototype.getRepositoriesList = function(){
	return this.repositories;
}

User.prototype.setPreference = function(prefName, prefValue)
{
	this.preferences[prefName] = prefValue;
}

User.prototype.setRepositoriesList = function(repoHash)
{
	this.repositories = repoHash;
}

User.prototype.savePreferences = function(newPass, onCompleteFunc)
{
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
}