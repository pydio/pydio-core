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

/**
 * Abstraction of the currently logged user. Can be a "fake" user when users management
 * system is disabled
 */
Class.create("User", {

	/**
	 * @var String
	 */
	id : undefined,
	/**
	 * @var String
	 */
	activeRepository:undefined,
	/**
	 * @var Boolean
	 */
	read:false,
	/**
	 * @var Boolean
	 */
	write:false,
	/**
	 * @var Boolean
	 */	
	crossRepositoryCopy:false,	
	/**
	 * @var $H()
	 */
	preferences:undefined,
	/**
	 * @var $H()
	 */
	repositories: undefined,
	/**
	 * @var $H()
	 */
	crossRepositories:undefined,
	/**
	 * @var $H()
	 */
	repoIcons:undefined,
	/**
	 * @var $H()
	 */
	repoSearchEngines:undefined,
	/**
	 * @var Boolean
	 */
	isAdmin:false,

	/**
	 * Constructor
	 * @param id String The user unique id
	 * @param xmlDef XMLNode Registry Fragment
	 */
	initialize:function(id, xmlDef){
		this.id = id;
		this.preferences = new Hash();
		this.repositories = new Hash();
		this.crossRepositories = new Hash();
		this.repoIcon = new Hash();
		this.repoSearchEngines = new Hash();
		if(xmlDef) this.loadFromXml(xmlDef);
	},

	/**
	 * Set current repository
	 * @param id String
	 * @param read Boolean
	 * @param write Boolean
	 */
	setActiveRepository : function (id, read, write){
		this.activeRepository = id;
		this.read = (read=="1"?true:false);
		this.write = (write=="1"?true:false);		
		if(this.repositories.get(id)){
			this.crossRepositoryCopy = this.repositories.get(id).allowCrossRepositoryCopy;
		}
		if(this.crossRepositories.get(id)){
			this.crossRepositories.unset(id);
		}
	},
	/**
	 * Gets the current active repository
	 * @returns String
	 */
	getActiveRepository : function(){
		return this.activeRepository;
	},
	/**
	 * Whether current repo is allowed to be read
	 * @returns Boolean
	 */
	canRead : function(){
		return this.read;
	},
	
	/**
	 * Whether current repo is allowed to be written
	 * @returns Boolean
	 */
	canWrite : function(){
		return this.write;
	},
	
	/**
	 * Whether current repo is allowed to be cross-copied
	 * @returns Boolean
	 */
	canCrossRepositoryCopy : function(){
		return this.crossRepositoryCopy;
	},
	
	/**
	 * Get a user preference by its name
	 * @returns Mixed
	 */
	getPreference : function(prefName, fromJSON){
	    var value = this.preferences.get(prefName);	
	    if(fromJSON && value){
	    	try{
                if(typeof value == "object") return value;
		    	return value.evalJSON();
	    	}catch(e){
                if(console){
                    console.log("Error parsing JSON in preferences ("+prefName+"). You should contact system admin and clear user preferences.");
                }else{
                    alert("Error parsing JSON in preferences. You should contact system admin and clear user preferences.");
                }
	    	}
	    }
	    return value;
	},
	
	/**
	 * Get all repositories 
	 * @returns {Hash}
	 */
	getRepositoriesList : function(){
		return this.repositories;
	},
	
	/**
	 * Set a preference value
	 * @param prefName String
	 * @param prefValue Mixed
	 * @param toJSON Boolean Whether to convert the value to JSON representation
	 */
	setPreference : function(prefName, prefValue, toJSON){
		if(toJSON){
			prefValue = Object.toJSON(prefValue);
		}
		this.preferences.set(prefName, prefValue);
	},
	
	/**
	 * Set the repositories as a bunch
	 * @param repoHash $H()
	 */
	setRepositoriesList : function(repoHash){
		this.repositories = repoHash;
		// filter repositories once for all
		this.crossRepositories = new Hash();
		this.repositories.each(function(pair){
			if(pair.value.allowCrossRepositoryCopy){
				this.crossRepositories.set(pair.key, pair.value);
			}
		}.bind(this) );
	},
	/**
	 * Whether there are any repositories allowing crossCopy
	 * @returns Boolean
	 */
	hasCrossRepositories : function(){
		return (this.crossRepositories.size());
	},
	/**
	 * Get repositories allowing cross copy
	 * @returns {Hash}
	 */
	getCrossRepositories : function(){
		return this.crossRepositories;
	},
	/**
	 * Get the current repository Icon
	 * @param repoId String
	 * @returns String
	 */
	getRepositoryIcon : function(repoId){
		return this.repoIcon.get(repoId);
	},
	/**
	 * Get the repository search engine
	 * @param repoId String
	 * @returns String
	 */
	getRepoSearchEngine : function(repoId){
		return this.repoSearchEngines.get(repoId);
	},
	/**
	 * Send the preference to the server for saving
	 * @param prefName String
	 */
	savePreference : function(prefName){
		if(!this.preferences.get(prefName)) return;
		var conn = new Connexion();
        conn.setMethod('post');
		conn.addParameter("get_action", "save_user_pref");
		conn.addParameter("pref_name_" + 0, prefName);
		conn.addParameter("pref_value_" + 0, this.preferences.get(prefName));
		conn.sendAsync();
	},
	/**
	 * Send all preferences to the server. If oldPass, newPass and seed are set, also save pass.
	 * @param oldPass String
	 * @param newPass String
	 * @param seed String
	 * @param onCompleteFunc Function
	 */
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
	/**
	 * Parse the registry fragment to load this user
	 * @param userNodes DOMNode
	 */
	loadFromXml: function(userNodes){
	
		var repositories = new Hash();
		for(var i=0; i<userNodes.length;i++)
		{
			if(userNodes[i].nodeName == "active_repo")
			{
				var activeNode = userNodes[i];
			}
			else if(userNodes[i].nodeName == "repositories")
			{
				for(j=0;j<userNodes[i].childNodes.length;j++)
				{
					var repoChild = userNodes[i].childNodes[j];
					if(repoChild.nodeName == "repo") {	
						var repository = new Repository(repoChild.getAttribute("id"), repoChild);
						repositories.set(repoChild.getAttribute("id"), repository);
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
						var value = prefChild.getAttribute("value");
						if(!value && prefChild.firstChild){
							// Retrieve value from CDATA
							value = prefChild.firstChild.nodeValue;
						}
						this.setPreference(prefChild.getAttribute("name"), value);
					}
				}					
			}
			else if(userNodes[i].nodeName == "special_rights")
			{
				var attr = userNodes[i].getAttribute("is_admin");
				if(attr && attr == "1") this.isAdmin = true;
			}
		}
		// Make sure it happens at the end
		if(activeNode){
			this.setActiveRepository(activeNode.getAttribute('id'), 
									activeNode.getAttribute('read'), 
									activeNode.getAttribute('write'));
			
		}
			
	}
});