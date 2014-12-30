/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
Class.create("RoleEditor", AbstractEditor, {

    tab : null,
    roleData : null,
    roleParent : null,

    roleRead: null,
    roleWrite: null,

    pluginsData : null,
    roleId : null,

    initialize: function($super, oFormObject, editorOptions)
	{
        editorOptions = Object.extend({
            fullscreen:false
        }, editorOptions);
        $super(oFormObject, editorOptions);
        fitHeightToBottom(this.element.down("#roleTabulator"), this.element.up("div").up("div"));
        this.contentMainContainer = this.element.down("#roleTabulator");

        var paneInfo = this.element.down("#pane-infos");
        var paneActions = this.element.down("#pane-actions");
        var paneParameters = this.element.down("#pane-parameters");
        var paneAcls = this.element.down("#pane-acls");
        var oElement = this.element;

        // INIT TAB
        this.element.down("#pane-infos").setStyle({position:"relative"});

        paneInfo.resizeOnShow = function(tab){
            fitHeightToBottom(paneInfo, oElement, Prototype.Browser.IE ? 40 : 0);
        };
        paneActions.resizeOnShow = function(tab){
            fitHeightToBottom(paneActions.down("#actions-selected"), paneActions, 0);
        };
        paneParameters.resizeOnShow = function(tab){
            fitHeightToBottom(paneParameters.down("#parameters-selected"), paneParameters, 0);
            paneParameters.down("#parameters-selected").select("div.tabPane").each(function(subTab){
                if(subTab.resizeOnShow) subTab.resizeOnShow(null, subTab);
            });
        };
        paneAcls.resizeOnShow = function(tab){
            fitHeightToBottom(paneAcls.down("#acls-selected"), paneAcls, 0);
        };
        this.tab = new AjxpSimpleTabs(oFormObject.down("#roleTabulator"));
        this.pluginsData = {};
        this.actions.get("saveButton").observe("click", this.save.bind(this) );
        if(modal._editorOpener){
            modal.setCloseValidation(this.validateClose.bind(this));
        }
        oFormObject.down(".action_bar").select("a").invoke("addClassName", "css_gradient");
    },

    validateClose: function(){
        if(this.isDirty()){
            var confirm = window.confirm(MessageHash["ajxp_role_editor.19"]);
            if(!confirm) return false;
        }
        return true;
    },

    save : function(){
        if(!this.isDirty()) return;
        var fullPostData = {};
        var fManager = this.getFormManager();
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
                        if(this.roleParent['PARAMETERS'][repoScope] &&
                            this.roleParent['PARAMETERS'][repoScope][pluginId]
                            && this.roleParent['PARAMETERS'][repoScope][pluginId][pName] == pair.value){
                            parametersHash.unset(pair.key);
                        }
                    }
                }.bind(this) );
                fullPostData['FORMS'][repoScope][pluginId] = parametersHash;
            }.bind(this) );
        }.bind(this) );
        var customFieldsHash = new $H();
        fManager.serializeParametersInputs(this.element.down("#pane-infos").down("#account_custom"), customFieldsHash, "ROLE_CUSTOM_");
        customFieldsHash.each(function(pair){
            var pName = pair.key.replace("ROLE_CUSTOM_", "");
            if(pName.endsWith("_ajxptype") || pName.endsWith("_replication") || pName.endsWith("_checkbox")) return;
            var objectValue = pair.value.evalJSON();
            var repoScope = pName;
            for(var pluginId in objectValue){
                if(!objectValue.hasOwnProperty(pluginId)) continue;
                var pluginData = objectValue[pluginId];
                for(var paramName in pluginData){
                    if(!pluginData.hasOwnProperty(paramName)) continue;
                    // update roleWrite
                    if(!this.roleWrite.PARAMETERS[repoScope]) this.roleWrite.PARAMETERS[repoScope] = {};
                    if(!this.roleWrite.PARAMETERS[repoScope][pluginId]) this.roleWrite.PARAMETERS[repoScope][pluginId] = {};
                    this.roleWrite.PARAMETERS[repoScope][pluginId][paramName] = pluginData[paramName];
                    // update FORMS
                    if(!fullPostData['FORMS'][repoScope]) fullPostData['FORMS'][repoScope] = {};
                    if(!fullPostData['FORMS'][repoScope][pluginId]) fullPostData['FORMS'][repoScope][pluginId] = $H({});
                    fullPostData['FORMS'][repoScope][pluginId].set("ROLE_PARAM_"+paramName, pluginData[paramName]);
                    if(customFieldsHash.get(pName + "_ajxptype")){
                        fullPostData['FORMS'][repoScope][pluginId].set("ROLE_PARAM_"+paramName+"_ajxptype", customFieldsHash.get(pName + "_ajxptype"));
                    }
                    if(customFieldsHash.get(pName + "_checkbox")){
                        fullPostData['FORMS'][repoScope][pluginId].set("ROLE_PARAM_"+paramName+"_checkbox", customFieldsHash.get(pName + "_checkbox"));
                    }
                    if(customFieldsHash.get(pName + "_replication")){
                        fullPostData['FORMS'][repoScope][pluginId].set("ROLE_PARAM_"+paramName+"_replication", customFieldsHash.get(pName + "_replication"));
                    }
                }
            }

        }.bind(this));

        fullPostData['ROLE'] = this.roleWrite;

        if(this.roleData.USER){
            this.roleData.USER.PROFILE = this.element.down("#account_infos").down("select[name='profile']").getValue();
            this.roleData.USER.ROLES = this.element.down("#account_infos").down("select[name='roles']").getValue();
            fullPostData["USER"] = this.roleData.USER;
        }else if(this.roleData.GROUP){
            this.roleData.GROUP.LABEL = this.element.down("#account_infos").down("input[name='groupLabel']").getValue();
            fullPostData["GROUP_LABEL"] = this.roleData.GROUP.LABEL;
        }

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
            if(response.SUCCESS){
                ajaxplorer.displayMessage("SUCCESS", MessageHash["ajxp_role_editor.20"]);
                response.ALL = this.roleData.ALL;
                if(this.roleData.USER)response.USER = this.roleData.USER;
                if(this.roleData.GROUP)response.GROUP = this.roleData.GROUP;
                this.initJSONResponse(response);
                ajaxplorer.fireContextRefresh();
                this.setClean();
            }else{
                ajaxplorer.displayMessage("ERROR", response.ERROR);
            }

        }.bind(this);
        conn.sendAsync();

    },

    updateTitle: function(label){
        var pref = '';
        if(this.scope == 'role') pref = '<span class="icon-th"></span> ';
        else if(this.scope == 'user') pref = '<span class="icon-user"></span> ';
        else if(this.scope == 'group') pref = '<span class="icon-group"></span> ';
        this.element.down("span.header_label").update(pref+label);
        this.element.fire("editor:updateTitle", pref+label);
    },

	open : function($super, node){
		$super(node);
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
        this.node = node;
        this.scope = scope;
        this.updateTitle(getBaseName(node.getPath()));
        this.loadRoleData(true);
	},


    /**
     * Resizes the main container
     * @param size int|null
     */
    resize : function(size){
        if(size){
            this.contentMainContainer.setStyle({height:(size - parseInt(this.element.down('.editor_header').getHeight())) +"px"});
        }else{
            fitHeightToBottom(this.contentMainContainer, this.element.up("div").up("div"));
        }
        this.tab.resize();
        this.element.fire("editor:resize", size);
    },


    loadRoleData : function(withInfoPane){
        this.setOnLoad(this.element.down("#pane-infos"));
        var conn = new Connexion();
        conn.setParameters({
            get_action:"edit",
            sub_action:"edit_role",
            role_id: this.roleId,
            format:'json'
        });
        conn.onComplete = function(transport){
            this.initJSONResponse(transport.responseJSON);
            if(withInfoPane) this.buildInfoPane(this.node, this.scope);
            this.removeOnLoad(this.element.down("#pane-infos"));
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

    updateRoles : function(selection){
        // DIFF
        var orig = this.roleData.USER.ROLES || $A();
        var currentUserId = this.roleId.replace("AJXP_USR_/", "");
        orig.each(function(el){
            if(!selection[el] && !el.startsWith('AJXP_GRP_/') && !el.startsWith('AJXP_USR_/')) {
                var conn = new Connexion();
                conn.setParameters({
                    get_action:"edit",
                    sub_action:"user_delete_role",
                    user_id : currentUserId,
                    role_id : el
                });
                conn.sendSync();
            }
        });
        selection.each(function(el){
            if(!orig[el]) {
                var conn = new Connexion();
                conn.setParameters({
                    get_action:"edit",
                    sub_action:"user_add_role",
                    user_id : currentUserId,
                    role_id : el
                });
                conn.sendSync();
            }
        });
        this.loadRoleData(false);
        this.setClean();
    },

    buildInfoPane : function(node, scope){
        var f = this.getFormManager();
        this.element.ajxpPaneObject = this;
        if(scope == "user"){
            // MAIN INFO
            var rolesChoicesString = this.roleData.ALL.ROLES.join(",");
            var profilesChoices = this.roleData.ALL.PROFILES.join(",");
            var repos = [];
            $H(this.roleData.ALL.REPOSITORIES).each(function(pair){
                repos.push(pair.key+"|"+pair.value);
            });
            var defs = [
                $H({"name":"login",label:MessageHash["ajxp_role_editor.21"],"type":"string", default:getBaseName(node.getPath()), readonly:true}),
                $H({"name":"profile",label:MessageHash["ajxp_role_editor.22"],"type":"select", choices:profilesChoices, default:this.roleData.USER.PROFILE}),
                $H({"name":"roles",label:MessageHash["ajxp_role_editor.24"],"type":"select", multiple:true, choices:rolesChoicesString, default:this.roleData.USER.ROLES.join(",")})
            ];
            defs = $A(defs);
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_infos"), defs, true, false, false, true);
            f.disableShortcutsOnForm(this.element.down("#pane-infos").down("#account_infos"));
            var rolesSelect = this.element.down("#pane-infos").down("#account_infos").down('select[name="roles"]');
            var updateRoleButton = new Element('span', {id:'user_roles_update_button'}).update('<span class="icon-save"></span> UPDATE');
            rolesSelect.insert({after:updateRoleButton});
            updateRoleButton.hide();
            rolesSelect.observe("change", function(){
                this.setDirty();
                updateRoleButton.show();
            }.bind(this) );
            updateRoleButton.observe("click", function(){
                this.updateRoles(rolesSelect.getValue());
                updateRoleButton.hide();
            }.bind(this));
            new Chosen(rolesSelect, {placeholder_text_multiple:MessageHash["ajxp_role_editor.43"]});

            // BUTTONS
            var buttonPane = this.element.down("#pane-infos").down("#account_actions");
            var b0 = new Element("span", {className:'m-2'}).update(MessageHash["ajxp_role_editor.25"]);
            buttonPane.insert(b0);
            var userId = this.roleId.replace("AJXP_USR_/", "");
            b0.observe("click", function(){
                var pane = new Element("div", {style:"width:400px;"});
                pane.insert(new Element("div", {className:"dialogLegend"}).update(MessageHash["ajxp_role_editor.29"]));
                var passEl1 = new Element("div", {className:"SF_element"});
                passEl1.insert(new Element("div",{className:"SF_label"}).update(MessageHash[182]+": "));
                passEl1.insert(new Element("input",{type:"password",name:"password",className:"SF_input",id:"pass"}));
                pane.insert(passEl1);
                var passEl2 = passEl1.cloneNode(true);passEl2.down("div").update(MessageHash["ajxp_role_editor.30"] + ": "); passEl2.down("input").setAttribute("name", "pass_confirm");
                pane.insert(passEl2);
                pane.insert('<div class="SF_element" id="pwd_strength_container"></div>');
                pane.select('input').invoke('observe', 'focus', function(){
                    ajaxplorer.disableAllKeyBindings();
                }).invoke('observe', 'blur', function(){
                    ajaxplorer.enableAllKeyBindings();
                });
                modal.showSimpleModal(this.element.down("#pane-infos"),pane, function(){
                    var p1 = passEl1.down("input").getValue();
                    var p2 = passEl2.down("input").getValue();
                    if(p2 != p1){
                        alert(MessageHash[238]);
                        return false;
                    }
                    if(p1.length < strength.options.minchar){
                        alert(MessageHash[378]);
                        return false;
                    }
                    var conn = new Connexion();
                    conn.setParameters({
                        get_action:"edit",
                        sub_action:"update_user_pwd",
                        user_id : userId,
                        user_pwd : this.encodePassword(p1)
                    });
                    conn.sendAsync();
                    return true;
                }.bind(this), function(){return true;});
                var strength = new Protopass(passEl1.down("input"), {
                    barContainer:pane.down('#pwd_strength_container')
                });
                modal.currentLightBoxModal.setStyle({display:'block'});
            }.bind(this));
            var locked = this.roleData.USER.LOCK ? true : false;
            var b1 = new Element("span", {className:'m-2'}).update((locked?MessageHash["ajxp_role_editor.27"]:MessageHash["ajxp_role_editor.26"]));
            buttonPane.insert(b1);
            var userId = this.roleId.replace("AJXP_USR_/", "");
            b1.observe("click", function(){
                var conn = new Connexion();
                conn.setParameters({
                    get_action:"edit",
                    sub_action:"user_set_lock",
                    user_id : userId,
                    lock : (locked?"false":"true")
                });
                if(!locked) conn.addParameter("lock_type", "logout");
                conn.onComplete = function(transport){
                    locked = !locked;
                    b1.update((locked?MessageHash["ajxp_role_editor.27"]:MessageHash["ajxp_role_editor.26"]));
                }.bind(this);
                conn.sendAsync();
            }.bind(this) );
            var b2 = new Element("span", {className:'m-2'}).update(MessageHash["ajxp_role_editor.28"]);
            buttonPane.insert(b2);
            var userId = this.roleId.replace("AJXP_USR_/", "");
            b2.observe("click", function(){
                var conn = new Connexion();
                conn.setParameters({
                    get_action:"edit",
                    sub_action:"user_set_lock",
                    user_id : userId,
                    lock : "true",
                    lock_type : "pass_change"
                });
                conn.sendAsync();
            });

        }else if(scope == "role"){
            // MAIN INFO
            var defs = [
                $H({"name":"roleId",label:MessageHash["ajxp_role_editor.31"],"type":"string", default:getBaseName(node.getPath()), readonly:true}),
                $H({"name":"applies",label:MessageHash["ajxp_role_editor.33"],"type":"select", multiple:true, choices:"standard|All users,admin|Administrator,shared|Shared,guest|Guest"})
            ];
            defs = $A(defs);
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_infos"), defs, true, false, false, true);
            f.disableShortcutsOnForm(this.element.down("#pane-infos").down("#account_infos"));

            // REMOVE BUTTONS
            this.element.down("#pane-infos").down("#account_actions").remove();

            var appliesSelect = this.element.down("#pane-infos").down("#account_infos").down('select[name="applies"]');
            appliesSelect.setValue(this.roleRead.APPLIES);
            appliesSelect.observe("change", function(){
                this.roleWrite.APPLIES = appliesSelect.getValue();
            }.bind(this) );
            new Chosen(appliesSelect, {placeholder_text_multiple:MessageHash["ajxp_role_editor.43"]});

        }else if(scope == "group"){
            // MAIN INFO
            var defs = [
                $H({"name":"groupId",label:MessageHash["ajxp_role_editor.34"],"type":"string", default:getBaseName(node.getPath()), readonly:true}),
                $H({"name":"groupLabel",label:MessageHash["ajxp_role_editor.35"],"type":"string", default:this.roleData.GROUP.LABEL})
            ];
            defs = $A(defs);
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_infos"), defs, true, false, false, true);
            f.disableShortcutsOnForm(this.element.down("#pane-infos").down("#account_infos"));
            // UPDATE MAIN HEADER
            this.updateTitle(this.roleData.GROUP.LABEL);

            // REMOVE BUTTONS
            this.element.down("#pane-infos").down("#account_actions").remove();
        }

        // CUSTOM DATA
        var definitions = $A(this.roleData["SCOPE_PARAMS"]);
        var updatedDefs = $A();
        definitions.each(function(paramObject){
            var param = $H(paramObject);
            if(param.get("readonly")){
                param.set("readonly", false);
            }
            if(param.get("type") == "image"){
                this.updateBinaryContext(param);
            }
            var paramName = param.get("name");
            var parts = paramName.split("/");
            try{
                param.set("default", this.roleRead.PARAMETERS[parts[0]][parts[1]][parts[2]]);
            }catch(e){}
            if(param.get("name").endsWith("DISPLAY_NAME") && param.get("default")){
                var display = param.get("default");
                if(this.roleData.USER && this.roleData.USER.LOCK) display += " ("+ MessageHash["ajxp_role_editor.36"] +")";
                this.updateTitle(display);
            }
            updatedDefs.push(param);
        }.bind(this));
        if(!updatedDefs.length){
            this.element.down("#pane-infos").down("#account_custom").previous().remove();
        }else{
            if(scope == "role"){
                this.element.down("#pane-infos").down("#account_custom").previous("div.innerTitle").update(MessageHash["ajxp_role_editor.42"]);
            }
            f.createParametersInputs(this.element.down("#pane-infos").down("#account_custom"), updatedDefs, true, false, false, true);
            f.disableShortcutsOnForm(this.element.down("#pane-infos").down("#account_custom"));
        }


        f.observeFormChanges(this.element,  this.setDirty.bind(this));
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
        window.jsd = jsonData;
        for(var key in jsonData.LIST){
            if(!jsonData.LIST.hasOwnProperty(key)) continue;
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
            nextSelect.insert(new Element("option", {value:-1}).update((type == "action" ? MessageHash["ajxp_role_editor.12a"]:MessageHash["ajxp_role_editor.12b"])));
            for(var key in actions){
                if(!actions.hasOwnProperty(key) || !actions[key][type]) continue;
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
                lastSelect.down("option").update(MessageHash["ajxp_role_editor.12c"]);
                lastSelect.observeOnce("change", function(){
                    button.removeClassName("disabled");
                } );
            } );
        } );
        select.disabled = false;
    },

    feedRepositoriesSelectors : function(){
        var repositories = this.roleData.ALL.REPOSITORIES;
        this.element.select("select.repository_selector").each(function(select){
            select.select("option").invoke("remove");
            select.insert(new Element("option", {value:-1}).update(""));
            select.insert(new Element("option", {value:"AJXP_REPO_SCOPE_ALL"}).update(MessageHash["ajxp_role_editor.12d"]));
            select.insert(new Element("option", {value:"AJXP_REPO_SCOPE_SHARED"}).update(MessageHash["ajxp_role_editor.12e"]));
            for(var key in repositories){
                if(!repositories.hasOwnProperty(key)) continue;
                select.insert(new Element("option", {value:key}).update(repositories[key]));
            }
            //select.disabled = false;
        }.bind(this));
    },

    generateRightsTable : function(){
   		var rightsPane = this.element.down('#pane-acls');
   		var rightsTable = rightsPane.down('#acls-selected');
        rightsTable.update("");
        var repositories = this.roleData.ALL.REPOSITORIES;
        if(!Object.keys(repositories).length) return;
        //repositories.sortBy(function(element) {return XPathGetSingleNodeText(element, "label");});
   		for(var repoId in repositories){
            if(!repositories.hasOwnProperty(repoId)) continue;
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
            rightsCell.insert('<label for="chck_'+repoId+'_block">' + MessageHash["ajxp_role_editor.37"] + '</label> ');
   			var tr = new Element('div', {className:"repositoryEntry"});
   			var titleCell = new Element('div', {className:"repositoryLabel"}).update('<img src="'+ajxpResourcesFolder+'/images/mimes/16/folder_red.png" style="float:left;margin-right:5px;">');
            var theLabel = new Element("span",{style:'cursor:pointer;', 'data-repoId':repoId}).update(repoLabel);
            titleCell.insert(theLabel);
            if(this.isInherited(["ACL", repoId])) {
                theLabel.insert(" (inherited)");
                theLabel.addClassName("inherited");
            }
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
                    if(repoScope != "AJXP_REPO_SCOPE_ALL" && repoScope != "AJXP_REPO_SCOPE_SHARED"
                        && ! this.roleData.ALL.REPOSITORIES[repoScope]){
                        continue;
                    }
                    if(Object.isArray(actionsData[repoScope][pluginId])) {
                        continue;
                    }
                    var el = new Element("div", {className:"list_remove_div"});
                    var remove = new Element("span", {className:"list_remove_item"}).update('<span class="icon-minus-sign"></span> ' + MessageHash["ajxp_role_editor.41"]);
                    el.insert(remove);
                    var repoLab;
                    if(repoScope == "AJXP_REPO_SCOPE_ALL") repoLab = MessageHash["ajxp_role_editor.12d"];
                    else if(repoScope == "AJXP_REPO_SCOPE_SHARED") repoLab = MessageHash["ajxp_role_editor.12e"];
                    else repoLab = this.roleData.ALL.REPOSITORIES[repoScope];
                    var pluginLab = (pluginId == "all_plugins" ? "All Plugins" : pluginId);
                    var state = actionsData[repoScope][pluginId][actionName] === false ? "disabled":"enabled";
                    el.insert(repoLab + " &gt; " + pluginLab + " &gt; " + actionName + " - "+ state);
                    if(this.isInherited(['ACTIONS', repoScope, pluginId, actionName])){
                        el.insert(" ("+MessageHash["ajxp_role_editor.38"]+")");
                        el.addClassName("inherited");
                        if(state == 'disabled'){
                            remove.update(MessageHash["ajxp_role_editor.40"]);
                            remove.setAttribute("data-ajxpEnable", "true");
                        }else{
                            remove.update(MessageHash["ajxp_role_editor.39"]);
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
            parametersPane.insert(new Element("ul", {className:"tabrow innerTabRow"}));
            return;
        }
        var conn = new Connexion();
        conn.setMethod('post');
        conn.setParameters({
            get_action:'parameters_to_form_definitions',
            json_parameters : Object.toJSON(actionsData)
        });
        conn.onComplete = function(transport){

            parametersPane.update("");
            parametersPane.removeClassName("non_empty");
            parametersPane.insert(new Element("ul", {className:"tabrow innerTabRow"}));

            // Parse result as a standard form
            var xml = transport.responseXML;
            var formManager = this.getFormManager();
            var scopes = XPathSelectNodes(xml, "standard_form/repoScope");
            if(!scopes.length) return;
            for(var i=0;i<scopes.length;i++){
                var id = scopes[i].getAttribute("id");
                var scopeLabel;
                var setTop = false;
                if(id == "AJXP_REPO_SCOPE_ALL") {
                    scopeLabel = MessageHash["ajxp_role_editor.12d"];
                    setTop = true;
                }else if(id == "AJXP_REPO_SCOPE_SHARED"){
                    scopeLabel = MessageHash["ajxp_role_editor.12e"];
                    setTop = true;
                }
                else scopeLabel = this.roleData.ALL.REPOSITORIES[id];
                var tab = new Element("li", {"data-PaneID":"params-form-" + id}).update('<span>'+scopeLabel+'</span>');
                if(setTop){
                    parametersPane.down("ul.tabrow").insert({top:tab});
                }else{
                    parametersPane.down("ul.tabrow").insert(tab);
                }

                var pane = new Element("div", {id:"params-form-" + id, className:"role_edit-params-form"});
                parametersPane.insert(pane);
                var paneParameters = this.element.down('#parameters-selected');
                pane.resizeOnShow = function(passedTab, passedPane){
                    fitHeightToBottom(passedPane, paneParameters);
                };
                var formParams = formManager.parseParameters(xml, 'standard_form/repoScope[@id="'+id+'"]/*');
                for(var k=0;k<formParams.length;k++){
                    var h = formParams[k];
                    if(this.isInherited(["PARAMETERS", id, h.get("group"), h.get("name")])){
                        h.set("label", '<span class="inherited">' + h.get("label") + ' ('+ MessageHash["ajxp_role_editor.38"] +')' + '</span>');
                    }
                }
                var formElement = new Element('form', {style:'display:inline;'});
                pane.insert(formElement);
                formManager.createParametersInputs(formElement, formParams, true, null, false, false, false);
                if(pane.SF_accordion){
                    pane.SF_accordion.openAll();
                }
                formManager.disableShortcutsOnForm(pane);
            }
            pane.select("div.accordion_content").invoke("setStyle", {display:"block"});
            new AjxpSimpleTabs(parametersPane);
            parametersPane.addClassName("non_empty");
            parametersPane.down(".tabpanes").addClassName("innerContainer").setStyle({margin:'3px 10px'});

            // UPDATE FORMS ELEMENTS
            parametersPane.select("div.SF_element").each(function(element){
                if(!element.down("span.inherited")){
                    var removeLink = new Element("span", {className:"list_remove_item"}).update('<span class="icon-minus-sign"></span> ' + MessageHash["ajxp_role_editor.41"]);
                    element.insert(removeLink);
                    removeLink.observe("click", this.parameterListRemoveObserver(element) );
                }
                element.select("input,textarea,select").invoke("observe", "change", this.setDirty.bind(this));
                element.select("input,textarea").invoke("observe", "keydown", this.setDirty.bind(this));
            }.bind(this) );

        }.bind(this);
        conn.sendAsync();
    },

    getFormManager : function(){
        return new FormManager(this.element.down(".tabpanes"));
    },

    updateBinaryContext : function(parameter){
        if(this.roleData.USER){
            parameter.set("binary_context", "user_id="+this.roleId.replace("AJXP_USR_/", ""));
        }else if(this.roleData.GROUP){
            parameter.set("binary_context", "group_id="+this.roleId.replace("AJXP_GRP_/", ""));
        }else{
            parameter.set("binary_context", "role_id="+this.roleId);
        }
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
                    if(!$H(this.roleWrite.ACTIONS[scope][plugin]).size()){
                        delete this.roleWrite.ACTIONS[scope][plugin];
                        if(!$H(this.roleWrite.ACTIONS[scope]).size()){
                            delete this.roleWrite.ACTIONS[scope];
                        }
                    }
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
                if(!$H(this.roleWrite.PARAMETERS[scope][plugin]).size()){
                    delete this.roleWrite.PARAMETERS[scope][plugin];
                    if(!$H(this.roleWrite.PARAMETERS[scope]).size()){
                        delete this.roleWrite.PARAMETERS[scope];
                    }
                }
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

    encodePassword : function(password){
   		// First get a seed to check whether the pass should be encoded or not.
   		var sync = new Connexion();
   		var seed;
   		sync.addParameter('get_action', 'get_seed');
   		sync.onComplete = function(transport){
   			seed = transport.responseText;
   		};
   		sync.sendSync();
   		var encoded;
   		if(seed != '-1'){
   			encoded = hex_md5(password);
   		}else{
   			encoded = password;
   		}
   		return encoded;

   	}


});