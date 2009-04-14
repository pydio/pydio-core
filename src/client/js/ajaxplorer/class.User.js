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
 * Description : Abstraction of the currently logged user.
 */
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