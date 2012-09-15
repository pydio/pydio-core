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
Class.create("RoleEditor", AbstractEditor, {

    tab : null,
    roleData : null,
    pluginsData : null,

	initialize: function($super, oFormObject)
	{
		$super(oFormObject, {fullscreen:false});
        fitHeightToBottom(oFormObject.down("#roleTabulator"), oFormObject.up(".dialogBox"));
        // INIT TAB
        $("pane-actions").resizeOnShow = function(tab){
            fitHeightToBottom($("actions-selected"), $("pane-actions"), 20);
        }
        $("pane-parameters").resizeOnShow = function(tab){
            fitHeightToBottom($("parameters-selected"), $("pane-parameters"), 20);
        }
        $("pane-acls").resizeOnShow = function(tab){
            fitHeightToBottom($("acls-selected"), $("pane-acls"), 20);
        }
        this.tab = new AjxpSimpleTabs(oFormObject.down("#roleTabulator"));
    },


	open : function($super, userSelection){
		$super(userSelection);
		var fileName = userSelection.getUniqueFileName();
        var base = getBaseName(fileName);
        this.element.down("span.header_label").update(base);
        var conn = new Connexion();
        conn.setParameters({
            get_action:"edit",
            sub_action:"edit_role",
            role_id: base,
            format:'json'
        });
        conn.onComplete = function(transport){
            this.roleData = transport.responseJSON;
            if(this.roleData.ROLE.ACTIONS.length == 0){
                this.roleData.ROLE.ACTIONS = {};
            }
            this.generateRightsTable();
            this.populateActionsPane();
            this.feedRepositoriesSelectors();
        }.bind(this);
        conn.sendAsync();

        var conn2 = new Connexion();
        conn2.setParameters({get_action:"list_all_plugins_actions"});
        conn2.onComplete = function(transport){
            this.feedPluginsSelectors(transport.responseJSON);
        }.bind(this);
        conn2.sendAsync();
	},

    setDirty : function(){
        this.actions.get("saveButton").removeClassName("disabled");
    },

    setClean : function(){
        this.actions.get("saveButton").addClassName("disabled");
    },

    isDirty : function(){
        return !this.actions.get("saveButton").hasClassName("disabled");
    },

    feedPluginsSelectors : function(jsonData){
        this.pluginsData = jsonData.LIST;
        var oManager = this;
        this.element.select("select.plugin_selector").each(function(select){
            for(var key in jsonData.LIST){
                select.insert(new Element("option", {value:key}).update(key));
            }
            var nextSelect = select.up("div.SF_element").next().down("select");
            var lastSelect = nextSelect.up("div.SF_element").next().down("select");
            var button = lastSelect.next("div.add_button");
            var type = nextSelect.hasClassName("action_selector") ?  "action" : "parameter";

            button.observe("click", function(){
                if(button.hasClassName("disabled")) return;
                if(type == "action"){
                    oManager.addActionToList(select.getValue(), nextSelect.getValue(), lastSelect.getValue());
                }
                nextSelect.disabled = true;
                lastSelect.disabled = true;
                lastSelect.setValue(-1);
                button.addClassName("disabled");
            });

            select.observe("change", function(){
                if(type == "action"){
                    var actions = oManager.pluginsData[select.getValue()];
                    nextSelect.select("*").invoke("remove");
                    nextSelect.insert(new Element("option", {value:-1}).update("Select an action..."));
                    for(var key in actions){
                        if(!actions[key]["action"]) continue;
                        var label = actions[key]["label"];
                        if(label && MessageHash[label]){
                            label = actions[key]["action"] +" (" +MessageHash[label] +")";
                        }else{
                            label = actions[key]["action"];
                        }
                        nextSelect.insert(new Element("option", {value:actions[key]["action"]}).update(label));
                    }
                }
                nextSelect.disabled = false;
                lastSelect.disabled = true;
                nextSelect.observeOnce("change", function(){
                    lastSelect.disabled = false;
                    lastSelect.focus();
                    lastSelect.down("option").update("Select one or all repositories...");
                    lastSelect.observeOnce("change", function(){
                        button.removeClassName("disabled");
                    } );
                } );
            } );
            select.disabled = false;
        } );
    },

    feedRepositoriesSelectors : function(){
        var repositories = this.roleData.REPOSITORIES;
        this.element.select("select.repository_selector").each(function(select){
            select.insert(new Element("option", {value:-1}).update(""));
            select.insert(new Element("option", {value:"AJXP_REPO_SCOPE_ALL"}).update("All Repositories"));
            for(var key in repositories){
                select.insert(new Element("option", {value:key}).update(repositories[key]));
            }
            //select.disabled = false;
        }.bind(this));
    },

    generateRightsTable : function(){
   		var rightsPane = this.element.down('#pane-acls');
   		var rightsTable = rightsPane.down('#acls-selected');
   		var repositories = this.roleData.REPOSITORIES;
        //repositories.sortBy(function(element) {return XPathGetSingleNodeText(element, "label");});
        //var defaultRepository = XPathGetSingleNodeText(xmlData, '//pref[@name="force_default_repository"]/@value');
   		for(var repoId in repositories){
   			var repoLabel = repositories[repoId];
   			var readBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_read'});
   			var writeBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_write'});
   			var blockBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_block'});
   			var rightsCell = new Element('div', {className:"repositoryRights"});
            rightsCell.insert(readBox);
            rightsCell.insert('<label for="chck_'+repoId+'_read">' + MessageHash['ajxp_conf.29'] + '</label> ');
            rightsCell.insert(writeBox);
            rightsCell.insert('<label for="chck_'+repoId+'_write">' + MessageHash['ajxp_conf.30'] + '</label> ');
            rightsCell.insert(blockBox);
            rightsCell.insert('<label for="chck_'+repoId+'_block">' + 'Deny' + '</label> ');
   			var tr = new Element('div', {className:"repositoryEntry"});
   			var titleCell = new Element('div', {className:"repositoryLabel"}).update('<img src="'+ajxpResourcesFolder+'/images/mimes/16/folder_red.png" style="float:left;margin-right:5px;">');
            var theLabel = new Element("span",{style:'cursor:pointer;', 'data-repoId':repoId}).update(repoLabel);
            titleCell.insert(theLabel);
               /*
               theLabel.observe("click", this.changeUserDefaultRepository.bind(this));
               if(defaultRepository && repoId == defaultRepository){
                  theLabel.setStyle({fontWeight:"bold"});
               }
               */
   			tr.insert(titleCell);
   			tr.insert(rightsCell);
   			rightsTable.insert({bottom:tr});

   			// FOR IE, set checkboxes state AFTER dom insertion.
   			readBox.checked = (this.roleData.ROLE.ACL[repoId] && this.roleData.ROLE.ACL[repoId].indexOf("r") !== -1);
   			writeBox.checked = (this.roleData.ROLE.ACL[repoId] && this.roleData.ROLE.ACL[repoId].indexOf("w") !== -1);
   		}
   		// rightsTable.down('#loading_row').remove();
   	},

    populateActionsPane : function(){
        var actionsPane = this.element.down("#actions-selected");
        actionsPane.select("*").invoke("remove");
        var actionsData = this.roleData.ROLE.ACTIONS;
        if(!Object.keys(actionsData).length) return;
        for(var repoScope in actionsData){
            for(var pluginId in actionsData[repoScope]){
                for(var actionName in actionsData[repoScope][pluginId]){
                    var el = new Element("div");
                    var remove = new Element("span", {className:"list_remove_item"}).update("Remove");
                    el.insert(remove);
                    var repoLab = (repoScope == "AJXP_REPO_SCOPE_ALL" ? "All Repositories" : this.roleData.REPOSITORIES[repoScope]);
                    var pluginLab = (pluginId == "all_plugins" ? "All Plugins" : pluginId);
                    el.insert(repoLab + " &gt; " + pluginLab + " &gt; " + actionName + " (disabled)");
                    actionsPane.insert(el);
                    remove.observeOnce("click", this.actionListRemoveObserver(repoScope, pluginId, actionName) );
                }
            }
        }
    },

    addActionToList: function(plugin, action, scope){
        if(!this.roleData.ROLE.ACTIONS) this.roleData.ROLE.ACTIONS = {};
        if(!this.roleData.ROLE.ACTIONS[scope]) this.roleData.ROLE.ACTIONS[scope] = {};
        if(!this.roleData.ROLE.ACTIONS[scope][plugin]) this.roleData.ROLE.ACTIONS[scope][plugin] = {};
        this.roleData.ROLE.ACTIONS[scope][plugin][action] = false;
        this.populateActionsPane();
        this.element.down("#actions-selected").scrollTop = 10000;
        this.setDirty();
    },


    actionListRemoveObserver : function(scope, plugin, action){
        return function(){
            try{
                delete this.roleData.ROLE.ACTIONS[scope][plugin][action];
            }catch(e){}
            this.populateActionsPane();
            this.setDirty();
        }.bind(this);
    }


});