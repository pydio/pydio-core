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
Class.create("PluginEditor", AbstractEditor, {

    tab : null,
    repositoryId: null,
    formManager : null,
    infoPane: null,
    docPane: null,

    initialize: function($super, oFormObject)
    {
        $super(oFormObject, {fullscreen:false});
        fitHeightToBottom(this.element.down("#pluginTabulator"), this.element.up(".dialogBox"));
        this.contentMainContainer = this.element.down("#pluginTabulator");
        // INIT TAB
        var infoPane = this.element.down("#pane-infos");
        var docPane = this.element.down("#pane-docs");

        infoPane.setStyle({position:"relative"});
        infoPane.resizeOnShow = function(tab){
            fitHeightToBottom(infoPane, $("plugin_edit_box"));
        }
        docPane.resizeOnShow = function(tab){
            fitHeightToBottom(docPane, $("plugin_edit_box"));
        }
        this.tab = new AjxpSimpleTabs(oFormObject.down("#pluginTabulator"));
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
        this.docPane = docPane;
    },

    save : function(){
        if(!this.isDirty()) return;

        var toSubmit = new Hash();
        toSubmit.set("action", "edit");
        toSubmit.set("sub_action", "edit_plugin_options");
        toSubmit.set("plugin_id", this.pluginId);
        var missing = this.formManager.serializeParametersInputs(this.infoPane.down("div.driver_form"), toSubmit, 'DRIVER_OPTION_');
        if(missing){
            ajaxplorer.displayMessage("ERROR", MessageHash['ajxp_conf.36']);
        }else{
            var conn = new Connexion();
            conn.setParameters(toSubmit);
            conn.setMethod("post");
            conn.onComplete = function(transport){
                ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                this.loadPluginConfig();
                this.setClean();
            }.bind(this);
            conn.sendAsync();
        }


    },

    open : function($super, node){
        $super(node);
        this.pluginId = getBaseName(node.getMetadata().get("plugin_id"));
        this.element.down("span.header_label").update(node.getMetadata().get("text"));
        var icon = resolveImageSource(node.getIcon(), "/images/mimes/64");
        this.element.down("span.header_label").setStyle(
            {
                backgroundImage:"url('"+icon+"')",
                backgroundSize : '34px'
            });
        this.node = node;
        this.formManager = this.getFormManager();
        this.loadPluginConfig();
    },


    loadPluginConfig : function(){
        var params = new Hash();
        params.set("get_action", "get_plugin_manifest");
        params.set("plugin_id", this.pluginId);
        var connexion = new Connexion();
        connexion.setParameters(params);
        connexion.onComplete = function(transport){

            this.infoPane.update("");
            this.docPane.update("");

            var xmlData = transport.responseXML;
            var params = XPathSelectNodes(xmlData, "//global_param");
            var values = XPathSelectNodes(xmlData, "//plugin_settings_values/param");
            var documentation = XPathSelectSingleNode(xmlData, "//plugin_doc");

            var paramsValues = new Hash();
            $A(values).each(function(child){
                if(child.nodeName != 'param') return;
                paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
            });


            var driverParamsHash = $A([]);
            if(this.pluginId.split("\.")[0] != "core"){
                driverParamsHash.push($H({
                    name:'AJXP_PLUGIN_ENABLED',
                    type:'boolean',
                    label:MessageHash['ajxp_conf.104'],
                    description:""
                }));
            }
            for(var i=0;i<params.length;i++){
                var hashedParams = this.formManager.parameterNodeToHash(params[i]);
                driverParamsHash.push(hashedParams);
            }
            var form = new Element('div', {className:'driver_form'});
            if(documentation){
                var docDiv = new Element('div', {style:'height:100%;'}).insert("<div class='documentation'>" + documentation.firstChild.nodeValue + "</div>");
                docDiv.select('img').each(function(img){
                    img.setStyle({width:'220px'});
                    img.setAttribute('src', 'plugins/'+ this.pluginId+'/'+img.getAttribute('src'));
                });
                this.docPane.insert({bottom:docDiv});

                var pluginfo = docDiv.down("ul.pluginfo_list");
                var docum = docDiv.down("div.documentation");
                docum.insert({before:pluginfo});
                docum.setStyle({padding:'0 10px'});
                pluginfo.setStyle({padding:'0 10px 10px'});

                docDiv.down("ul.pluginfo_list").insert({before:new Element("div",{className:"innerTitle"}).update("Plugin Info")});
                docDiv.down("ul.pluginfo_list").insert({after:new Element("div",{className:"innerTitle"}).update("Plugin Documentation")});
            }

            this.infoPane.insert({bottom:form});

            if(driverParamsHash.size()){
                this.formManager.createParametersInputs(form, driverParamsHash, true, (paramsValues.size()?paramsValues:null));
            }else{
                form.update(MessageHash['ajxp_conf.105']);
            }

            if(form.SF_accordion){
                form.SF_accordion.openAll();
                var toggles = form.select(".accordion_toggle");
                toggles.invoke("removeClassName", "accordion_toggle");
                toggles.invoke("removeClassName", "accordion_toggle_active");
                toggles.invoke("addClassName", "innerTitle");
            }
            form.select("div.SF_element").each(function(element){
                element.select("input,textarea,select").invoke("observe", "change", this.setDirty.bind(this));
                element.select("input,textarea").invoke("observe", "keydown", this.setDirty.bind(this));
            }.bind(this) );

            ajaxplorer.blurAll();
        }.bind(this);
        connexion.sendAsync();
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