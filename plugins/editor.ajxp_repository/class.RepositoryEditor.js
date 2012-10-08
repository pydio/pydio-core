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
Class.create("RepositoryEditor", AbstractEditor, {

    tab : null,
    repositoryId: null,
    formManager : null,
    infoPane: null,
    metaPane: null,

    initialize: function($super, oFormObject)
    {
        $super(oFormObject, {fullscreen:false});
        fitHeightToBottom(this.element.down("#repositoryTabulator"), this.element.up(".dialogBox"));
        this.contentMainContainer = this.element.down("#repositoryTabulator");
        // INIT TAB
        var infoPane = this.element.down("#pane-infos");
        var metaPane = this.element.down("#pane-metas");

        infoPane.setStyle({position:"relative"});
        infoPane.resizeOnShow = function(tab){
            fitHeightToBottom(infoPane, $("repository_edit_box"));
        }
        metaPane.resizeOnShow = function(tab){
            fitHeightToBottom(metaPane, $("repository_edit_box"));
        }
        this.tab = new AjxpSimpleTabs(oFormObject.down("#repositoryTabulator"));
        this.actions.get("saveButton").observe("click", this.save.bind(this) );
        modal.setCloseValidation(function(){
            if(this.isDirty()){
                var confirm = window.confirm(MessageHash["ajxp_role_editor.19"]);
                if(!confirm) return false;
            }
            return true;
        }.bind(this) );
        oFormObject.down(".action_bar").select("a").invoke("addClassName", "css_gradient");
        this.infoPane = infoPane;
        this.metaPane = metaPane;
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
                this.setClean();
            }.bind(this);
            conn.sendAsync();
        }

    },

    open : function($super, node){
        $super(node);
        if(Object.isString(node)){
            this.repositoryId = node;
            this.element.down("span.header_label").update("New Repository");
        }else{
            this.repositoryId = getBaseName(node.getPath());
            this.element.down("span.header_label").update(node.getMetadata().get("text"));
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
            paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
        });
        this.currentRepoWriteable = (repo.getAttribute("writeable")?(repo.getAttribute("writeable")=="true"):false);
        this.formManager.createParametersInputs(
            this.infoPane,
            driverParamsHash,
            false,
            paramsValues,
            !this.currentRepoWriteable,
            false,
            this.currentRepoIsTemplate
        );

        if(this.infoPane.SF_accordion){
            this.infoPane.SF_accordion.openAll();
            this.infoPane.select("div.SF_element").each(function(element){
                element.select("input,textarea,select").invoke("observe", "change", this.setDirty.bind(this));
                element.select("input,textarea").invoke("observe", "keydown", this.setDirty.bind(this));
            }.bind(this) );
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
        metaPane.update("");
        var data = XPathSelectSingleNode(xmlData, 'admin_data/repository/param[@name="META_SOURCES"]');
        if(data && data.firstChild && data.firstChild.nodeValue){
            var metaSourcesData = data.firstChild.nodeValue.evalJSON();
            for(var plugId in metaSourcesData){
                var metaLabel = XPathSelectSingleNode(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/@label').nodeValue;
                var metaDefNodes = XPathSelectNodes(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/param');

                //var titleString = "<img name=\"delete_meta_source\" src=\""+ajxpResourcesFolder+"/images/actions/16/editdelete.png\" style='float:right;' class='metaPaneTitle'>"+(metaDefNodes.length?"<img src=\""+ajxpResourcesFolder+"/images/actions/16/filesave.png\" style='float:right;' class='metaPaneTitle'>":"")+"<span class=\"title\">"+metaLabel+"</span>";
                var title = new Element('div',{className:'accordion_toggle', tabIndex:0}).update("<span class=\"title\">"+metaLabel+"</span>");
                var accordionContent = new Element("div", {className:"accordion_content", style:"padding-bottom: 10px;"});
                var form = new Element("div");
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
                    insertSave = true;
                }else{
                    form.update('<div>No parameters</div>');
                }
                accordionContent.insert(form);
                var saveButton = null;
                if(insertSave){
                    accordionContent.insert("<div tabindex='0' name='edit_meta_source' class='largeButton SF_disabled' style='min-width:70px;clear:both;margin-top: 7px;margin-left: 0'><img src=\""+ajxpResourcesFolder+"/images/actions/16/filesave.png\"><span class=\"title\">Save</span></div>");
                    saveButton = accordionContent.down("div[name='edit_meta_source']");
                }
                accordionContent.insert("<div  tabindex='0' name='delete_meta_source' class='largeButton' style='min-width:70px;clear:both;margin-top: 7px;margin-left: 0'><img src=\""+ajxpResourcesFolder+"/images/actions/16/editdelete.png\"><span class=\"title\">Remove</span></div>");
                metaPane.insert(title);
                metaPane.insert(accordionContent);
                if(saveButton){
                    form.select("div.SF_element").each(function(element){
                        element.select("input,textarea,select").invoke("observe", "change", function(event){
                            var but = Event.findElement(event, "div.accordion_content").down("div[name='edit_meta_source']");
                            but.removeClassName("SF_disabled");
                            this.setDirty();
                        }.bind(this));
                        element.select("input,textarea").invoke("observe", "keydown", function(event){
                            var but = Event.findElement(event, "div.accordion_content").down("div[name='edit_meta_source']");
                            but.removeClassName("SF_disabled");
                            this.setDirty();
                        }.bind(this));
                    }.bind(this) );
                }
                title.observe('focus', function(event){
                    if(metaPane.SF_accordion && metaPane.SF_accordion.showAccordion!=event.target.next(0)) {
                        metaPane.SF_accordion.activate(event.target);
                    }
                });
            }
            metaPane.SF_accordion = new accordion(metaPane, {
                classNames : {
                    toggle : 'accordion_toggle',
                    toggleActive : 'accordion_toggle_active',
                    content : 'accordion_content'
                },
                defaultSize : {
                    width : '360px',
                    height: null
                },
                direction : 'vertical'
            });
            metaPane.select("div.largeButton").invoke("observe", "click", this.metaActionClick.bind(this));
        }

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
        formEl.insert(this.metaSelector);
        metaPane.insert({top:addForm});
        addForm.insert({before: new Element("div", {className:"innerTitle"}).update(MessageHash["ajxp_repository_editor.5"])});
        addForm.insert({before: new Element("div", {className:"dialogLegend"}).update(MessageHash["ajxp_repository_editor.7"])});
        addForm.insert({after : new Element("div", {className:"dialogLegend"}).update(MessageHash["ajxp_repository_editor.8"])});
        addForm.insert({after : new Element("div", {className:"innerTitle"}).update(MessageHash["ajxp_repository_editor.6"])});
        var addFormDetail = new Element("div");
        addForm.insert(addFormDetail);

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
            }
            modal.refreshDialogAppearance();
            modal.refreshDialogPosition();
            addFormDetail.insert("<div class='largeButton' style='width:100px;clear:both;margin-top: 7px;margin-left: 0'><img src=\""+ajxpResourcesFolder+"/images/actions/16/filesave.png\"><span class=\"title\">"+MessageHash['ajxp_repository_editor.11']+"</span></div>");
            addFormDetail.down(".largeButton")._form = addForm;
            addFormDetail.down(".largeButton").observe("click", this.metaActionClick.bind(this));
        }.bind(this));

    },

    metaActionClick : function(event){
        var img = Event.findElement(event, 'div');
        Event.stop(event);
        var action;
        if(img && img._form){
            var form = img._form;
            action = "add_meta_source";
        }else{
            var button = Event.findElement(event, 'div.largeButton');
            var form = button.previous();
            if(button.getAttribute("name")){
                action = button.getAttribute("name");
            }else{
                action = "edit_meta_source";
            }
        }

        var params = new Hash();
        params.set('get_action', action);
        if(form._plugId){
            params.set('plugId', form._plugId);
        }
        params.set('repository_id', this.repositoryId);
        this.formManager.serializeParametersInputs(form, params, "DRIVER_OPTION_");
        if(params.get('get_action') == 'add_meta_source' && params.get('DRIVER_OPTION_new_meta_source') == ''){
            alert(MessageHash['ajxp_repository_editor.14']);
            return;
        }
        if(params.get('DRIVER_OPTION_new_meta_source')){
            params.set('new_meta_source', params.get('DRIVER_OPTION_new_meta_source'));
            params.unset('DRIVER_OPTION_new_meta_source');
        }
        if(params.get('get_action') == 'delete_meta_source'){
            var res = confirm(MessageHash['ajxp_repository_editor.13']);
            if(!res) return;
        }

        var conn = new Connexion();
        conn.setParameters(params);
        conn.onComplete = function(transport){
            ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
            this.loadRepository(this.repositoryId, true);
            if(button && action == "edit_meta_source"){
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
            this.contentMainContainer.setStyle({height:size+"px"});
        }else{
            fitHeightToBottom(this.contentMainContainer, this.element.up(".dialogBox"));
            this.tab.resize();
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