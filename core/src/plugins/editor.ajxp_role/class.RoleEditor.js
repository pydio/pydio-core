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
    roleParent : null,

    roleRead: null,
    roleWrite: null,

    pluginsData : null,
    roleId : null,

	initialize: function($super, oFormObject)
	{
		$super(oFormObject, {fullscreen:false});
        fitHeightToBottom(oFormObject.down("#roleTabulator"), oFormObject.up(".dialogBox"));
        // INIT TAB
        $("pane-infos").resizeOnShow = function(tab){
            fitHeightToBottom($("pane-infos"), $("role_edit_box"));
        }
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
        oFormObject.down(".action_bar").select("a").invoke("addClassName", "css_gradient");
    },

    isInherited : function(parameterKeys){
        if(!this.roleParent || !this.roleWrite) return;
        // Child role is roleWrite
        var test = this.roleWrite;
        for(var i=0;i<parameterKeys.length;i++){
            if(test[parameterKeys[i]] == undefined) return true;
            if(test[parameterKeys[i]]) test = test[parameterKeys[i]];
        }
        return false;
    },

    computeRoleRead : function(){
        if(!this.roleParent) {
            this.roleRead = this.roleData.ROLE;
        }else{
            // MERGE roleParent & roleData
            this.roleRead = this.mergeObjectsRecursive(this.roleParent, this.roleData.ROLE);
        }
    },

    mergeObjectsRecursive : function(source, destination){
        var newObject = {};
        for (var property in source) {
            if (source.hasOwnProperty(property)) {
                if( source[property] === null ) continue;
                if( destination.hasOwnProperty(property)){
                    if(source[property] instanceof Object && destination instanceof Object){
                        newObject[property] = this.mergeObjectsRecursive(source[property], destination[property]);
                    }else{
                        newObject[property] = destination[property];
                    }
                }else{
                    if(source[property] instanceof Object) {
                        newObject[property] = this.mergeObjectsRecursive(source[property], {});
                    }else{
                        newObject[property] = source[property];
                    }
                }
            }
        }
        for (var property in destination){
            if(destination.hasOwnProperty(property) && !newObject.hasOwnProperty(property) && destination[property]!==null){
                if(destination[property] instanceof Object) {
                    newObject[property] = this.mergeObjectsRecursive(destination[property], {});
                }else{
                    newObject[property] = destination[property];
                }
            }
        }
        return newObject;
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
                parametersHash.each(function(pair){
                    var pName = pair.key.replace("ROLE_PARAM_", "");
                    if(pName.endsWith("_ajxptype") || pName.endsWith("_replication") || pName.endsWith("_checkbox")) return;
                    if(this.isInherited(['PARAMETERS', repoScope, pluginId, pName])){
                        if(this.roleParent['PARAMETERS'][repoScope][pluginId][pName] == pair.value){
                            parametersHash.unset(pair.key);
                        }
                    }
                }.bind(this) );
                fullPostData['FORMS'][repoScope][pluginId] = parametersHash;
            }.bind(this) );
        }.bind(this) );
        fullPostData['ROLE'] = this.roleWrite;
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
                response.REPOSITORIES = this.roleData.REPOSITORIES;
                this.initJSONResponse(response);
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
        var node = userSelection.getUniqueNode();
        var mime = node.getAjxpMime();
        var scope = mime;
        if(mime == "role"){
            this.roleId = getBaseName(node.getPath());
        }else if(mime == "group"){
            this.roleId = "AJXP_GRP_" + node.getPath().replace("/data/users", "");
        }else if(mime == "user" || mime == "user_editable"){
            this.roleId = "AJXP_USR_/" + getBaseName(node.getPath());
            scope = "user";
        }
        this.element.down("span.header_label").update(this.roleId);
        this.buildInfoPane(node, scope);
        var conn = new Connexion();
        conn.setParameters({
            get_action:"edit",
            sub_action:"edit_role",
            role_id: this.roleId,
            format:'json'
        });
        conn.onComplete = function(transport){
            this.initJSONResponse(transport.responseJSON);
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

    buildInfoPane : function(node, scope){
        var f = new FormManager();
        if(scope == "user"){
            // MAIN INFO
            var defs = [
                $H({"name":"login",label:"User identifier","type":"string", default:getBaseName(node.getPath()), readonly:true}),
                $H({"name":"pass",label:"Password","type":"password"}),
                $H({"name":"pass_confirm",label:"Confirm Password","type":"password"}),
                $H({"name":"rights",label:"Specific Rights","type":"select", choices:"admin|Administrator,shared|Shared,guest|Guest"}),
                $H({"name":"roles",label:"Roles (use Ctrl to select many)","type":"select", multiple:true, choices:"role1|Role1,role2|Role2"})
            ];
            defs = $A(defs);
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_infos"), defs, true, false, false, true);

            // BUTTONS
            var buttonPane = this.element.down("#pane-infos").down("#account_actions");
            var b1 = new Element("a", {}).update("Kill current session");
            buttonPane.insert(b1);
            var b2 = new Element("a", {}).update("Lock out account");
            buttonPane.insert(b2);
            var b3 = new Element("a", {}).update("Force Pass Change");
            buttonPane.insert(b3);

        }else if(scope == "role"){
            // MAIN INFO
            var defs = [
                $H({"name":"roleId",label:"Role identifier","type":"string", default:getBaseName(node.getPath()), readonly:true}),
                $H({"name":"rights",label:"Apply automatically to users with the right...","type":"select", multiple:true, choices:"admin|Administrator,shared|Shared,guest|Guest"})
            ];
            defs = $A(defs);
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_infos"), defs, true, false, false, true);

            // REMOVE BUTTONS
            this.element.down("#pane-infos").down("#account_actions").remove();

        }else if(scope == "group"){
            // MAIN INFO
            var defs = [
                $H({"name":"groupId",label:"Group Label","type":"string", default:getBaseName(node.getPath())})
            ];
            defs = $A(defs);
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_infos"), defs, true, false, false, true);

            // REMOVE BUTTONS
            this.element.down("#pane-infos").down("#account_actions").remove();
        }

        // CUSTOM DATA
        var definitions = f.parseParameters(ajaxplorer.getXmlRegistry(), "//param[contains(@scope,'"+scope+"')]");
        definitions.each(function(param){
            if(param.get("readonly"))param.set("readonly", false);
        });
        if(!definitions.length){
            this.element.down("#pane-infos").down("#account_custom").previous().remove();
        }else{
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_custom"), definitions, true, false, false, true);
        }
    },

    initJSONResponse : function(responseJSON){

        this.roleData = responseJSON;
        this.testArray(this.roleData.ROLE, "ACL");
        this.testArray(this.roleData.ROLE, "ACTIONS");
        this.testArray(this.roleData.ROLE, "PARAMETERS");

        this.roleWrite = this.roleData.ROLE;
        if(responseJSON.PARENT_ROLE){
            this.roleParent = responseJSON.PARENT_ROLE;
            this.testArray(this.roleParent, "ACL");
            this.testArray(this.roleParent, "ACTIONS");
            this.testArray(this.roleParent, "PARAMETERS");
        }
        this.computeRoleRead();
        this.generateRightsTable();
        this.populateActionsPane();
        this.populateParametersPane();
        this.feedRepositoriesSelectors();

    },

    testArray : function(container, value){
        if(Object.isArray(container[value])){
            var copy = container[value].clone();
            container[value] = {};
            for(var i = 0; i<copy.length; i++){
                container[value][i] = copy[i];
            }
        }
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
            this.bindRightCheckBox(this.roleWrite, ["ACL", repoId], readBox);
            this.bindRightCheckBox(this.roleWrite, ["ACL", repoId], writeBox);
            this.bindRightCheckBox(this.roleWrite, ["ACL", repoId], blockBox);
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
            if(this.isInherited(["ACL", repoId])) {
                theLabel.insert(" (inherited)");
                theLabel.addClassName("inherited");
            }
               /*
               theLabel.observe("click", this.changeUserDefaultRepository.bind(this));
               if(defaultRepository && repoId == defaultRepository){
                  theLabel.setStyle({fontWeight:"bold"});
               }
               */
   			tr.insert(titleCell);
   			tr.insert(rightsCell);
   			rightsTable.insert({bottom:tr});

   			blockBox.checked = (this.roleRead.ACL[repoId] && this.roleRead.ACL[repoId].indexOf("AJXP_VALUE_CLEAR") !== -1);
            if(!blockBox.checked){
                // FOR IE, set checkboxes state AFTER dom insertion.
                readBox.checked = (this.roleRead.ACL[repoId] && this.roleRead.ACL[repoId].indexOf("r") !== -1);
                writeBox.checked = (this.roleRead.ACL[repoId] && this.roleRead.ACL[repoId].indexOf("w") !== -1);
            }else{
                readBox.disabled = writeBox.disabled;
            }
   		}
   		// rightsTable.down('#loading_row').remove();
   	},

    populateActionsPane : function(){
        var actionsPane = this.element.down("#actions-selected");
        actionsPane.select("*").invoke("remove");
        var actionsData = this.roleRead.ACTIONS;
        if(!Object.keys(actionsData).length) return;
        for(var repoScope in actionsData){
            for(var pluginId in actionsData[repoScope]){
                for(var actionName in actionsData[repoScope][pluginId]){
                    if(repoScope != "AJXP_REPO_SCOPE_ALL" && ! this.roleData.REPOSITORIES[repoScope]){
                        continue;
                    }
                    var el = new Element("div");
                    var remove = new Element("span", {className:"list_remove_item"}).update("Remove");
                    el.insert(remove);
                    var repoLab = (repoScope == "AJXP_REPO_SCOPE_ALL" ? "All Repositories" : this.roleData.REPOSITORIES[repoScope]);
                    var pluginLab = (pluginId == "all_plugins" ? "All Plugins" : pluginId);
                    var state = actionsData[repoScope][pluginId][actionName] === false ? "disabled":"enabled";
                    el.insert(repoLab + " &gt; " + pluginLab + " &gt; " + actionName + " - "+ state);
                    if(this.isInherited(['ACTIONS', repoScope, pluginId, actionName])){
                        el.insert(" (inherited)");
                        el.addClassName("inherited");
                        if(state == 'disabled'){
                            remove.update("Enable");
                            remove.setAttribute("data-ajxpEnable", "true");
                        }else{
                            remove.update("Disable");
                        }
                    }
                    actionsPane.insert(el);
                    remove.observeOnce("click", this.actionListRemoveObserver(repoScope, pluginId, actionName) );
                }
            }
        }
    },

    populateParametersPane : function(){
        var parametersPane = this.element.down("#parameters-selected");
        var actionsData = this.roleRead.PARAMETERS;
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
                for(var k=0;k<formParams.length;k++){
                    var h = formParams[k];
                    if(this.isInherited(["PARAMETERS", id, h.get("group"), h.get("name")])){
                        h.set("label", '<span class="inherited">' + h.get("label") + ' (inherited)' + '</span>');
                    }
                }
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
                if(!element.down("span.inherited")){
                    var removeLink = new Element("span", {className:"list_remove_item"}).update("Remove");
                    element.insert(removeLink);
                    removeLink.observe("click", this.parameterListRemoveObserver(element) );
                }
                element.select("input,textarea,select").invoke("observe", "change", this.setDirty.bind(this));
                element.select("input,textarea").invoke("observe", "keydown", this.setDirty.bind(this));
            }.bind(this) );

        }.bind(this);
        conn.sendAsync();
    },

    addParameterToList: function(plugin, parameter, scope){
        if(!this.roleWrite.PARAMETERS) this.roleWrite.PARAMETERS = {};
        if(!this.roleWrite.PARAMETERS[scope]) this.roleWrite.PARAMETERS[scope] = {};
        if(!this.roleWrite.PARAMETERS[scope][plugin]) this.roleWrite.PARAMETERS[scope][plugin] = {};
        this.roleWrite.PARAMETERS[scope][plugin][parameter] = "";
        this.computeRoleRead();
        this.populateParametersPane();
        this.setDirty();
    },

    addActionToList: function(plugin, action, scope, value){
        if(!this.roleWrite.ACTIONS) this.roleWrite.ACTIONS = {};
        if(!this.roleWrite.ACTIONS[scope]) this.roleWrite.ACTIONS[scope] = {};
        if(!this.roleWrite.ACTIONS[scope][plugin] || Object.isArray(this.roleWrite.ACTIONS[scope][plugin]) ) {
            this.roleWrite.ACTIONS[scope][plugin] = {};
        }
        if(!value) value = false;
        this.roleWrite.ACTIONS[scope][plugin][action] = value;
        this.computeRoleRead();
        this.populateActionsPane();
        this.element.down("#actions-selected").scrollTop = 10000;
        this.setDirty();
    },


    actionListRemoveObserver : function(scope, plugin, action){
        return function(event){
            try{
                if(event.target.getAttribute("data-ajxpEnable")){
                    this.addActionToList(plugin, action, scope, true);
                    return;
                }else{
                    delete this.roleWrite.ACTIONS[scope][plugin][action];
                }
                this.computeRoleRead();
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
                delete this.roleWrite.PARAMETERS[scope][plugin][parameter];
                this.computeRoleRead();
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