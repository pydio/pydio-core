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
            this.generateRightsTable(transport.responseJSON);
            this.generateActionsPane(transport.responseJSON);
        }.bind(this);
        conn.sendAsync();
	},

    generateRightsTable : function(jsonData){
   		var rightsPane = this.element.down('#pane-acls');
   		var rightsTable = rightsPane.down('#acls-selected');
   		var repositories = jsonData.REPOSITORIES;
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
   			readBox.checked = (jsonData.ROLE.ACL[repoId] && jsonData.ROLE.ACL[repoId].indexOf("r") !== -1);
   			writeBox.checked = (jsonData.ROLE.ACL[repoId] && jsonData.ROLE.ACL[repoId].indexOf("w") !== -1);
   		}
   		// rightsTable.down('#loading_row').remove();
   	},

    generateActionsPane : function(jsonData){
        var actionsPane = this.element.down("#actions-selected");
        actionsPane.select("*").invoke("remove");
        var actionsData = jsonData.ROLE.ACTIONS;
        if(!Object.keys(actionsData).length) return;
        for(var repoScope in actionsData){
            for(var pluginId in actionsData[repoScope]){
                for(var actionName in actionsData[repoScope][pluginId]){
                    var el = new Element("div");
                    var repoLab = (repoScope == "AJXP_REPO_SCOPE_ALL" ? "All Repositories" : jsonData.REPOSITORIES[repoScope]);
                    var pluginLab = (pluginId == "all_plugins" ? "All Plugins" : pluginId);
                    el.update(repoLab + " &gt; " + pluginLab + " &gt; " + actionName + " (disabled)");
                    actionsPane.insert(el);
                }
            }
        }
    }

});