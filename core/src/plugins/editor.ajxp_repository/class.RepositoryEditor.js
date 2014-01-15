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
Class.create("RepositoryEditor", AbstractEditor, {

    tab : null,
    repositoryId: null,
    formManager : null,
    infoPane: null,
    metaPane: null,

    initialize: function($super, oFormObject, editorOptions)
    {
        editorOptions = Object.extend({
            fullscreen:false
        }, editorOptions);
        $super(oFormObject, editorOptions);
        fitHeightToBottom(this.element.down("#repositoryTabulator"));
        this.contentMainContainer = this.element.down("#repositoryTabulator");
        // INIT TAB
        var infoPane = this.element.down("#pane-infos");
        var metaPane = this.element.down("#pane-metas");

        var oElement = this.element;
        infoPane.setStyle({position:"relative"});
        infoPane.resizeOnShow = function(tab){
            fitHeightToBottom(infoPane, oElement);
        };
        metaPane.resizeOnShow = function(tab){
            fitHeightToBottom(metaPane, oElement);
        };
        this.tab = new AjxpSimpleTabs(oFormObject.down("#repositoryTabulator"));
        this.tab.observe("switch", this.resize.bind(this));
        this.actions.get("saveButton").observe("click", this.save.bind(this) );
        if(!modal._editorOpener) {
            modal.setCloseValidation(this.validateClose.bind(this));
        }
        oFormObject.down(".action_bar").select("a").invoke("addClassName", "css_gradient");
        this.infoPane = infoPane;
        this.metaPane = metaPane;
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

        var toSubmit = new Hash();
        toSubmit.set("action", "edit");
        toSubmit.set("sub_action", "edit_repository_data");
        toSubmit.set("repository_id", this.repositoryId);
        var missing = this.formManager.serializeParametersInputs(this.infoPane, toSubmit, 'DRIVER_OPTION_', this.currentRepoIsTemplate);
        if(missing && ! this.currentRepoIsTemplate){
            ajaxplorer.displayMessage("ERROR", MessageHash['ajxp_conf.36']);
        }else{
            var conn = new Connexion();
            conn.setParameters(toSubmit);
            conn.setMethod("post");
            conn.onComplete = function(transport){
                ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                this.loadRepository(this.repositoryId);
                ajaxplorer.fireNodeRefresh(this.node);

                this.setClean();
            }.bind(this);
            conn.sendAsync();
        }

    },

    open : function($super, node){
        $super(node);
        if(Object.isString(node)){
            this.repositoryId = node;
            this.updateTitle("New Repository");
        }else{
            this.repositoryId = getBaseName(node.getPath());
            this.updateTitle(node.getMetadata().get("text"));
            var icon = resolveImageSource(node.getIcon(), "/images/mimes/64");
            this.element.down("span.header_label").setStyle(
                {
                    backgroundImage:"url('"+icon+"')",
                    backgroundSize : '34px'
                });

        }
        this.node = node;
        this.formManager = this.getFormManager();
        this.loadRepository(this.repositoryId);
    },

    updateTitle: function(label){
        this.element.down("span.header_label").update("<span class='icon-hdd'></span> "+label);
        this.element.fire("editor:updateTitle", "<span class='icon-hdd'></span> "+label);
    },

    loadRepository : function(repId, metaTab){
        var params = new Hash();
        params.set("get_action", "edit");
        params.set("sub_action", "edit_repository");
        params.set("repository_id", repId);
        var connexion = new Connexion();
        connexion.setParameters(params);
        connexion.onComplete = function(transport){
            this.feedRepositoryForm(transport.responseXML, metaTab);
            modal.refreshDialogPosition();
            modal.refreshDialogAppearance();
            ajaxplorer.blurAll();
        }.bind(this);
        connexion.sendAsync();
    },

    feedRepositoryForm: function(xmlData, metaTab){

        this.infoPane.update("");
        var repo = XPathSelectSingleNode(xmlData, "admin_data/repository");
        var driverParams = XPathSelectNodes(xmlData, "admin_data/ajxpdriver/param");
        var tplParams = XPathSelectNodes(xmlData, "admin_data/template/option");
        this.currentRepoIsTemplate = (repo.getAttribute("isTemplate") === "true");

        if(tplParams.length){
            var tplParamNames = $A();
            for(var k=0;k<tplParams.length;k++) {
                if(tplParams[k].getAttribute("name")){
                    tplParamNames.push(tplParams[k].getAttribute("name"));
                }
            }
        }

        var driverParamsHash = $A([]);
        for(var i=0;i<driverParams.length;i++){
            var hashedParams = this.formManager.parameterNodeToHash(driverParams[i]);
            if(tplParamNames && tplParamNames.include(hashedParams.get('name'))) continue;
            if(this.currentRepoIsTemplate && driverParams[i].getAttribute('no_templates') == 'true'){
                continue;
            }else if(!this.currentRepoIsTemplate && driverParams[i].getAttribute('templates_only') == 'true'){
                continue;
            }
            driverParamsHash.push(hashedParams);
        }

        var paramsValues = new Hash();
        $A(repo.childNodes).each(function(child){
            if(child.nodeName != 'param') return;
            if(child.getAttribute("cdatavalue")){
                paramsValues.set(child.getAttribute("name"), child.firstChild.nodeValue);
            }else{
                paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
            }
        });
        this.currentRepoWriteable = (repo.getAttribute("writeable")?(repo.getAttribute("writeable")=="true"):false);
        this.infoPane.ajxpPaneObject = this;
        this.formManager.createParametersInputs(
            this.infoPane,
            driverParamsHash,
            false,
            paramsValues,
            !this.currentRepoWriteable,
            false,
            this.currentRepoIsTemplate
        );
        this.formManager.disableShortcutsOnForm(this.infoPane);
        if(this.infoPane.SF_accordion){
            this.infoPane.SF_accordion.openAll();
            this.formManager.observeFormChanges(this.infoPane, this.setDirty.bind(this));
            var toggles = this.infoPane.select(".accordion_toggle");
            toggles.invoke("removeClassName", "accordion_toggle");
            toggles.invoke("removeClassName", "accordion_toggle_active");
            toggles.invoke("addClassName", "innerTitle");
        }
        if(!tplParams.length){
            if(this.currentRepoWriteable){
                this.feedMetaSourceForm(xmlData, this.metaPane);
            }else{
                this.metaPane.update(MessageHash['ajxp_repository_editor.15']);
            }
        }

    },

    feedMetaSourceForm : function(xmlData, metaPane){
        var metaTabHead = this.metaPane.down('.tabrow');
        var metaTabBody = this.metaPane.down('.tabpanes');
        metaTabHead.update("");
        metaTabBody.update("");
        var data = XPathSelectSingleNode(xmlData, 'admin_data/repository/param[@name="META_SOURCES"]');
        if(data && data.firstChild && data.firstChild.nodeValue){
            var metaSourcesData = data.firstChild.nodeValue.evalJSON();
            for(var plugId in metaSourcesData){
                var metaLabel = XPathSelectSingleNode(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/@label').nodeValue;
                var metaDefNodes = XPathSelectNodes(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/param');

                var id = this.repositoryId+"-meta-"+plugId.replace(".", "-");
                var title = new Element('li',{tabIndex:0, "data-PaneID":id}).update(metaLabel);
                var accordionContent = new Element("div", {className:"tabPane", id:id, style:"padding-bottom: 10px;"});
                var form = new Element("div", {className:'meta_form_container'});
                title._plugId = plugId;
                form._plugId = plugId;
                var insertSave = false;
                if(metaDefNodes.length){
                    var driverParamsHash = $A([]);
                    for(var i=0;i<metaDefNodes.length;i++){
                        driverParamsHash.push(this.formManager.parameterNodeToHash(metaDefNodes[i]));
                    }
                    var paramsValues = new Hash(metaSourcesData[plugId]);
                    this.formManager.createParametersInputs(form, driverParamsHash, true, paramsValues, false, true);
                    this.formManager.disableShortcutsOnForm(form);

                    insertSave = true;
                }else{
                    form.update('<div>No parameters</div>');
                }
                accordionContent.insert(form);
                var saveButton = null;
                accordionContent.insert(new Element('div',{className:'tabPaneButtons', style:'clear:both; padding-right: 20px;'}));
                if(insertSave){
                    accordionContent.down(".tabPaneButtons").insert("<div tabindex='0' name='meta_source_edit' class='largeButton SF_disabled' style='min-width:70px; margin-top: 20px;margin-right: 0;float: right;'><span class='icon-save'></span> <span class=\"title\">Save</span></div>");
                    saveButton = accordionContent.down("div[name='meta_source_edit']");
                }
                accordionContent.down(".tabPaneButtons").insert("<div  tabindex='0' name='meta_source_delete' class='largeButton' style='min-width:70px; margin-top: 20px;margin-right: 0;float: right;'><span class='icon-trash'></span> <span class=\"title\">Remove</span></div>");
                metaTabHead.insert(title);
                metaTabBody.insert(accordionContent);
                if(saveButton){
                    form.select("div.SF_element").each(function(element){
                        element.select("input,textarea,select").invoke("observe", "change", function(event){
                            var but = Event.findElement(event, "div.tabPane").down("div[name='meta_source_edit']");
                            but.removeClassName("SF_disabled");
                            this.setDirty();
                        }.bind(this));
                        element.select("input,textarea").invoke("observe", "keydown", function(event){
                            var but = Event.findElement(event, "div.tabPane").down("div[name='meta_source_edit']");
                            but.removeClassName("SF_disabled");
                            this.setDirty();
                        }.bind(this));
                    }.bind(this) );
                }
            }
            this.metaTab = new AjxpSimpleTabs(this.metaPane.down("#metaTabulator"), {autoHeight:true, saveState:true});
            metaPane.select("div.largeButton").invoke("observe", "click", this.metaActionClick.bind(this));
        }
        if(!metaPane.down('div.metaPane')){

            var addForm = new Element("div", {className:"metaPane"});
            var formEl = new Element("div", {className:"SF_element"}).update("<div class='SF_label'>"+MessageHash['ajxp_repository_editor.12']+" :</div>");
            this.metaSelector = new Element("select", {name:'new_meta_source', className:'SF_input'});
            var choices = XPathSelectNodes(xmlData, 'admin_data/metasources/meta');
            this.metaSelector.insert(new Element("option", {value:"", selected:"true"}));
            var prevType = "";
            var currentGroup;
            for(var i=0;i<choices.length;i++){
                var id = choices[i].getAttribute("id");
                var type = id.split(".").shift();
                var label = choices[i].getAttribute("label");
                if(!currentGroup || type != prevType){
                    currentGroup = new Element("optgroup", {label:MessageHash["ajxp_repository_editor.9"].replace("%s", type)});
                    this.metaSelector.insert(currentGroup);
                }
                prevType = type;
                currentGroup.insert(new Element("option",{value:id}).update(label));
            }
            addForm.insert(formEl);
            addForm.insert('<div style="clear: both"></div>');
            formEl.insert(this.metaSelector);
            metaPane.insert({top:addForm});
            addForm.insert({before: new Element("div", {className:"dialogLegend"}).update(MessageHash["ajxp_repository_editor.7"])});
            var addFormDetail = new Element("div");
            addForm.insert(addFormDetail);

        }

        this.metaSelector.observe("change", function(){
            var plugId = this.metaSelector.getValue();
            addFormDetail.update("");
            if(plugId){
                var metaDefNodes = XPathSelectNodes(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/param');
                var driverParamsHash = $A([]);
                for(var i=0;i<metaDefNodes.length;i++){
                    driverParamsHash.push(this.formManager.parameterNodeToHash(metaDefNodes[i]));
                }
                this.formManager.createParametersInputs(addFormDetail, driverParamsHash, true, null, null, true);
                this.formManager.disableShortcutsOnForm(addFormDetail);

            }
            modal.refreshDialogAppearance();
            modal.refreshDialogPosition();
            addFormDetail.insert("<div class='largeButton' style='width:100px;margin-top: 20px;margin-left: 0; float: right;'><span class='icon-plus-sign'></span> <span>"+MessageHash['ajxp_repository_editor.11']+"</span></div>");
            addFormDetail.down(".largeButton")._form = addForm;
            addFormDetail.down(".largeButton").observe("click", this.metaActionClick.bind(this));
            this.resize();
        }.bind(this));

        this.metaPane.setStyle({overflowY:'auto'});
        this.metaPane.resizeOnShow = function(tab){
            this.metaTab.resize();
        }.bind(this);
        this.metaTab.resize();
    },

    metaActionClick : function(event){
        var img = Event.findElement(event, 'div');
        Event.stop(event);
        var action;
        if(img && img._form){
            var form = img._form;
            action = "meta_source_add";
        }else{
            var button = Event.findElement(event, 'div.largeButton');
            var form = button.up('div.tabPaneButtons').previous('div.meta_form_container');
            if(button.getAttribute("name")){
                action = button.getAttribute("name");
            }else{
                action = "meta_source_edit";
            }
        }

        var params = new Hash();
        params.set('get_action', action);
        if(form._plugId){
            params.set('plugId', form._plugId);
        }
        params.set('repository_id', this.repositoryId);
        this.formManager.serializeParametersInputs(form, params, "DRIVER_OPTION_");
        if(params.get('get_action') == 'meta_source_add' && params.get('DRIVER_OPTION_new_meta_source') == ''){
            alert(MessageHash['ajxp_repository_editor.14']);
            return;
        }
        if(params.get('DRIVER_OPTION_new_meta_source')){
            params.set('new_meta_source', params.get('DRIVER_OPTION_new_meta_source'));
            params.unset('DRIVER_OPTION_new_meta_source');
        }
        if(params.get('get_action') == 'meta_source_delete'){
            var res = confirm(MessageHash['ajxp_repository_editor.13']);
            if(!res) return;
        }

        var conn = new Connexion();
        conn.setParameters(params);
        conn.onComplete = function(transport){
            ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
            this.loadRepository(this.repositoryId, true);
            if(button && action == "meta_source_edit"){
                button.addClassName("SF_disabled");
                this.setClean();
            }
        }.bind(this);
        conn.sendAsync();

    },


    /**
     * Resizes the main container
     * @param size int|null
     */
    resize : function(size){
        if(size){
            this.contentMainContainer.setStyle({height:(size - parseInt(this.element.down('.editor_header').getHeight())) +"px"});
        }else{
            fitHeightToBottom(this.contentMainContainer, this.element.up(".dialogBox"));
        }
        this.tab.resize();
        if(this.metaTab){
            this.metaTab.resize();
        }
        this.element.fire("editor:resize", size);
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