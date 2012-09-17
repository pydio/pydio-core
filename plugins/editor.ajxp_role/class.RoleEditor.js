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
    roleId : null,

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
            $("parameters-selected").select("div.tabPane").each(function(subTab){
                if(subTab.resizeOnShow) subTab.resizeOnShow(null, subTab);
            });
        }
        $("pane-acls").resizeOnShow = function(tab){
            fitHeightToBottom($("acls-selected"), $("pane-acls"), 20);
        }
        this.tab = new AjxpSimpleTabs(oFormObject.down("#roleTabulator"));
        this.pluginsData = {};
        this.actions.get("saveButton").observe("click", this.save.bind(this) );
        modal.setCloseValidation(function(){
            if(this.isDirty()){
                var confirm = window.confirm("There are unsaved changes, are you sure you want to close?");
                if(!confirm) return false;
            }
            return true;
        }.bind(this) );
    },

    save : function(){
        if(!this.isDirty()) return;
        var fullPostData = {};
        var fManager = new FormManager();
        fullPostData['FORMS'] = {};
        this.element.down("#parameters-selected").select("div.role_edit-params-form").each(function(repoForm){
            var repoScope = repoForm.id.replace("params-form-", "");
            fullPostData['FORMS'][repoScope] = {};
            repoForm.select("div.accordion_toggle").each(function(toggle){
                var pluginId = toggle.innerHTML;
                var formPane = toggle.next("div.accordion_content");
                var parametersHash = new $H();
                fManager.serializeParametersInputs(formPane, parametersHash, "ROLE_PARAM_");
                fullPostData['FORMS'][repoScope][pluginId] = parametersHash;
            }.bind(this) );
        }.bind(this) );
        fullPostData['ROLE'] = this.roleData.ROLE;
        var conn = new Connexion();
        conn.setParameters({
            get_action:'edit',
            sub_action:'post_json_role',
            role_id   : this.roleId,
            json_data : Object.toJSON(fullPostData)
        });
        conn.setMethod("post");
        conn.onComplete = function(transport){

            var response = transport.responseJSON;
            var updatedJSON = response.ROLE;
            if(response.SUCCESS){
                ajaxplorer.displayMessage("SUCCESS", "Role updated successfully");
                this.roleData.ROLE = updatedJSON;
                this.generateRightsTable();
                this.populateActionsPane();
                this.populateParametersPane();
                this.feedRepositoriesSelectors();
                ajaxplorer.fireContextRefresh();
                this.setClean();
            }else{
                ajaxplorer.displayMessage("ERROR", response.ERROR);
            }

        }.bind(this);
        conn.sendAsync();

    },

	open : function($super, userSelection){
		$super(userSelection);
		var fileName = userSelection.getUniqueFileName();
        var base = getBaseName(fileName);
        this.roleId = base;
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
            if(this.roleData.ROLE.PARAMETERS.length == 0){
                this.roleData.ROLE.PARAMETERS = {};
            }
            this.generateRightsTable();
            this.populateActionsPane();
            this.populateParametersPane();
            this.feedRepositoriesSelectors();
        }.bind(this);
        conn.sendAsync();

        var conn2 = new Connexion();
        conn2.setParameters({get_action:"list_all_plugins_actions"});
        conn2.onComplete = function(transport){
            this.feedPluginsSelectors(transport.responseJSON, this.element.select("select.plugin_selector")[0]);
        }.bind(this);
        conn2.sendAsync();

        var conn3 = new Connexion();
        conn3.setParameters({get_action:"list_all_plugins_parameters"});
        conn3.onComplete = function(transport){
            this.feedPluginsSelectors(transport.responseJSON, this.element.select("select.plugin_selector")[1]);
        }.bind(this);
        conn3.sendAsync();
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

    feedPluginsSelectors : function(jsonData, select){
        var oManager = this;
        for(var key in jsonData.LIST){
            select.insert(new Element("option", {value:key}).update(key));
        }
        var nextSelect = select.up("div.SF_element").next().down("select");
        var lastSelect = nextSelect.up("div.SF_element").next().down("select");
        var button = lastSelect.next("div.add_button");
        var type = nextSelect.hasClassName("action_selector") ?  "action" : "parameter";
        this.pluginsData[type] = jsonData.LIST;

        button.observe("click", function(){
            if(button.hasClassName("disabled")) return;
            if(type == "action"){
                oManager.addActionToList(select.getValue(), nextSelect.getValue(), lastSelect.getValue());
            }else if(type == "parameter"){
                oManager.addParameterToList(select.getValue(), nextSelect.getValue(), lastSelect.getValue());
            }
            select.setValue(-1);
            nextSelect.disabled = true;
            lastSelect.disabled = true;
            lastSelect.setValue(-1);
            button.addClassName("disabled");
        });

        select.observe("change", function(){
            var actions = oManager.pluginsData[type][select.getValue()];
            nextSelect.select("*").invoke("remove");
            nextSelect.insert(new Element("option", {value:-1}).update((type == "action" ? "Select an action...":"Select a parameter")));
            for(var key in actions){
                if(!actions[key][type]) continue;
                var label = actions[key]['label'];
                if(label){
                    if(MessageHash[label]) label = actions[key][type] +" (" +MessageHash[label] +")";
                    else label = actions[key][type] +" (" +label +")";
                }else{
                    label = actions[key][type];
                }
                nextSelect.insert(new Element("option", {value:actions[key][type]}).update(label));
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
    },

    feedRepositoriesSelectors : function(){
        var repositories = this.roleData.REPOSITORIES;
        this.element.select("select.repository_selector").each(function(select){
            select.select("option").invoke("remove");
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
        rightsTable.update("");
        var repositories = this.roleData.REPOSITORIES;
        //repositories.sortBy(function(element) {return XPathGetSingleNodeText(element, "label");});
        //var defaultRepository = XPathGetSingleNodeText(xmlData, '//pref[@name="force_default_repository"]/@value');
   		for(var repoId in repositories){
   			var repoLabel = repositories[repoId];
   			var readBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_read'});
   			var writeBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_write'});
   			var blockBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_block'});
            this.bindRightCheckBox(this.roleData, ["ROLE", "ACL", repoId], readBox);
            this.bindRightCheckBox(this.roleData, ["ROLE", "ACL", repoId], writeBox);
            this.bindRightCheckBox(this.roleData, ["ROLE", "ACL", repoId], blockBox);
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

   			blockBox.checked = (this.roleData.ROLE.ACL[repoId] && this.roleData.ROLE.ACL[repoId].indexOf("AJXP_VALUE_CLEAR") !== -1);
            if(!blockBox.checked){
                // FOR IE, set checkboxes state AFTER dom insertion.
                readBox.checked = (this.roleData.ROLE.ACL[repoId] && this.roleData.ROLE.ACL[repoId].indexOf("r") !== -1);
                writeBox.checked = (this.roleData.ROLE.ACL[repoId] && this.roleData.ROLE.ACL[repoId].indexOf("w") !== -1);
            }else{
                readBox.disabled = writeBox.disabled;
            }
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

    populateParametersPane : function(){
        var parametersPane = this.element.down("#parameters-selected");
        var actionsData = this.roleData.ROLE.PARAMETERS;
        if(!Object.keys(actionsData).length){
            parametersPane.update("");
            parametersPane.removeClassName("nonempty");
            parametersPane.insert(new Element("ul", {className:"tabrow"}));
            return;
        }
        var conn = new Connexion();
        conn.setParameters({
            get_action:'parameters_to_form_definitions',
            json_parameters : Object.toJSON(actionsData)
        });
        conn.onComplete = function(transport){

            parametersPane.update("");
            parametersPane.removeClassName("non_empty");
            parametersPane.insert(new Element("ul", {className:"tabrow"}));

            // Parse result as a standard form
            var xml = transport.responseXML;
            var formManager = new FormManager();
            var scopes = XPathSelectNodes(xml, "standard_form/repoScope");
            if(!scopes.length) return;
            for(var i=0;i<scopes.length;i++){
                var id = scopes[i].getAttribute("id");
                var scopeLabel;
                if(id == "AJXP_REPO_SCOPE_ALL") scopeLabel = "All Repositories";
                else scopeLabel = this.roleData.REPOSITORIES[id];
                var tab = new Element("li", {"data-PaneID":"params-form-" + id}).update('<span>'+scopeLabel+'</span>');
                parametersPane.down("ul.tabrow").insert(tab);

                var pane = new Element("div", {id:"params-form-" + id, className:"role_edit-params-form"});
                parametersPane.insert(pane);
                pane.resizeOnShow = function(passedTab, passedPane){
                    fitHeightToBottom(passedPane, $("parameters-selected"));
                };
                var formParams = formManager.parseParameters(xml, 'standard_form/repoScope[@id="'+id+'"]/*');
                formManager.createParametersInputs(pane, formParams, true, null, false, false, false);
                if(pane.SF_accordion){
                    pane.SF_accordion.openAll();
                }
            }
            pane.select("div.accordion_content").invoke("setStyle", {display:"block"});
            new AjxpSimpleTabs(parametersPane);
            parametersPane.addClassName("non_empty");

            // UPDATE FORMS ELEMENTS
            parametersPane.select("div.SF_element").each(function(element){
                var removeLink = new Element("span", {className:"list_remove_item"}).update("Remove");
                element.insert(removeLink);
                removeLink.observe("click", this.parameterListRemoveObserver(element) );
                element.select("input,textarea,select").invoke("observe", "change", this.setDirty.bind(this));
                element.select("input,textarea").invoke("observe", "keydown", this.setDirty.bind(this));
            }.bind(this) );

        }.bind(this);
        conn.sendAsync();
    },

    addParameterToList: function(plugin, parameter, scope){
        if(!this.roleData.ROLE.PARAMETERS) this.roleData.ROLE.PARAMETERS = {};
        if(!this.roleData.ROLE.PARAMETERS[scope]) this.roleData.ROLE.PARAMETERS[scope] = {};
        if(!this.roleData.ROLE.PARAMETERS[scope][plugin]) this.roleData.ROLE.PARAMETERS[scope][plugin] = {};
        this.roleData.ROLE.PARAMETERS[scope][plugin][parameter] = "";
        this.populateParametersPane();
        this.setDirty();
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
    },

    parameterListRemoveObserver : function(element){
        var scope,plugin,parameter;
        scope = element.up("div.tabPane").getAttribute("id").replace("params-form-", "");
        plugin = element.up("div.accordion_content").previous("div.accordion_toggle").innerHTML;
        parameter = element.down("[name]").getAttribute("name");
        return function(){
            try{
                delete this.roleData.ROLE.PARAMETERS[scope][plugin][parameter];
            }catch(e){}
            this.populateParametersPane();
            this.setDirty();
        }.bind(this);
    },

    bindRightCheckBox:function(structure, keys, checkbox){
        checkbox.observe("change", function(){
            var siblings = checkbox.up(".repositoryRights").select('input[type="checkbox"]');
            var right = "";
            var r = siblings[0];
            var w = siblings[1];
            var d = siblings[2];
            if(d.checked){
                r.checked = w.checked = false;
                r.disabled = w.disabled = true;
                right = "AJXP_VALUE_CLEAR";
            }else{
                r.disabled = w.disabled = false;
            }
            if(r.checked) right += "r";
            if(w.checked) right += "w";
            if(keys.length>1){
                if(!structure[keys[0]]) structure[keys[0]] = {};
                var c = structure[keys[0]];
                for(var i=1;i<keys.length;i++){
                    if(i == keys.length -1) {
                        if(right) c[keys[i]] = right;
                        else delete c[keys[i]];
                    }else{
                        if(!c[keys[i]]) c[keys[i]] = {};
                        c = c[keys[i]];
                    }
                }
            }else{
                if(right) structure[keys[0]] = right;
                else delete structure[keys[0]];
            }
            this.setDirty();
        }.bind(this) );
    }


});