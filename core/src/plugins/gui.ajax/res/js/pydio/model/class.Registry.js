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
Class.create("Registry", {

    initialize: function(){
        this._registry = null;
        this._extensionsRegistry = {"editor":$A([]), "uploader":$A([])};
        this._resourcesRegistry = {};
    },

    loadFromString: function(s){
        this._registry = parseXml(s).documentElement;
    },

    load:function(sync, xPath){

        var connexion = new Connexion();
        connexion.onComplete = function(transport){
            if(transport.responseXML == null || transport.responseXML.documentElement == null) return;
            if(transport.responseXML.documentElement.nodeName == "ajxp_registry"){
                this._registry = transport.responseXML.documentElement;
                modal.updateLoadingProgress('XML Registry loaded');
                if(!sync) {
                    //console.log('firing registry_loaded');
                    document.fire("ajaxplorer:registry_loaded", this._registry);
                }
            }else if(transport.responseXML.documentElement.nodeName == "ajxp_registry_part"){
                this.refreshXmlRegistryPart(transport.responseXML.documentElement);
            }
        }.bind(this);
        connexion.addParameter('get_action', 'get_xml_registry');
        if(xPath){
            connexion.addParameter('xPath', xPath);
        }
        if(sync){
            connexion.sendSync();
        }else{
            connexion.sendAsync();
        }
    },

    /**
     * Inserts a document fragment retrieved from server inside the full tree.
     * The node must contains the xPath attribute to locate it inside the registry.
     * Event ajaxplorer:registry_part_loaded is triggerd once this is done.
     * @param documentElement DOMNode
     */
    refreshXmlRegistryPart : function(documentElement){
        var xPath = documentElement.getAttribute("xPath");
        var existingNode = XPathSelectSingleNode(this._registry, xPath);
        var parentNode;
        if(existingNode && existingNode.parentNode){
            parentNode = existingNode.parentNode;
            parentNode.removeChild(existingNode);
            if(documentElement.firstChild){
                parentNode.appendChild(documentElement.firstChild.cloneNode(true));
            }
        }else if(xPath.indexOf("/") > -1){
            // try selecting parentNode
            var parentPath = xPath.substring(0, xPath.lastIndexOf("/"));
            parentNode = XPathSelectSingleNode(this._registry, parentPath);
            if(parentNode && documentElement.firstChild){
                //parentNode.ownerDocument.importNode(documentElement.firstChild);
                parentNode.appendChild(documentElement.firstChild.cloneNode(true));
            }
        }else{
            if(documentElement.firstChild) this._registry.appendChild(documentElement.firstChild.cloneNode(true));
        }
        document.fire("ajaxplorer:registry_part_loaded", xPath);
    },

    /**
     * Translate the XML answer to a new User object
     * @param skipEvent Boolean Whether to skip the sending of ajaxplorer:user_logged event.
     */
    logXmlUser: function(skipEvent){
        pydio.user = null;
        var userNode;
        if(this._registry){
            userNode = XPathSelectSingleNode(this._registry, "user");
        }
        if(userNode){
            var userId = userNode.getAttribute('id');
            var children = userNode.childNodes;
            if(userId){
                pydio.user = new User(userId, children);
            }
        }
        if(!skipEvent){
            document.fire("ajaxplorer:user_logged", pydio.user);
        }
    },

    getXML:function(){
        return this._registry;
    },

    /**
     * Find Extension initialisation nodes (activeCondition, onInit, etc), parses
     * the XML and execute JS.
     * @param xmlNode DOMNode The extension node
     * @param extensionDefinition Object Information already collected about this extension
     * @returns Boolean
     */
    initExtension : function(xmlNode, extensionDefinition){
        var activeCondition = XPathSelectSingleNode(xmlNode, 'processing/activeCondition');
        if(activeCondition && activeCondition.firstChild){
            try{
                var func = new Function(activeCondition.firstChild.nodeValue.strip());
                if(func() === false) return false;
            }catch(e){}
        }
        if(xmlNode.nodeName == 'editor'){
            Object.extend(extensionDefinition, {
                openable : (xmlNode.getAttribute("openable") == "true"),
                modalOnly : (xmlNode.getAttribute("modalOnly") == "true"),
                previewProvider: (xmlNode.getAttribute("previewProvider") == "true"),
                order: (xmlNode.getAttribute("order")?parseInt(xmlNode.getAttribute("order")):0),
                formId : xmlNode.getAttribute("formId") || null,
                text : MessageHash[xmlNode.getAttribute("text")],
                title : MessageHash[xmlNode.getAttribute("title")],
                icon : xmlNode.getAttribute("icon"),
                icon_class : xmlNode.getAttribute("iconClass"),
                editorClass : xmlNode.getAttribute("className"),
                mimes : $A(xmlNode.getAttribute("mimes").split(",")),
                write : (xmlNode.getAttribute("write") && xmlNode.getAttribute("write")=="true"?true:false)
            });
        }else if(xmlNode.nodeName == 'uploader'){
            var th = ajxpBootstrap.parameters.get('theme');
            var clientForm = XPathSelectSingleNode(xmlNode, 'processing/clientForm[@theme="'+th+'"]');
            if(!clientForm){
                clientForm = XPathSelectSingleNode(xmlNode, 'processing/clientForm');
            }
            if(clientForm && clientForm.firstChild && clientForm.getAttribute('id'))
            {
                extensionDefinition.formId = clientForm.getAttribute('id');
                if(!$('all_forms').select('[id="'+clientForm.getAttribute('id')+'"]').length){
                    $('all_forms').insert(clientForm.firstChild.nodeValue);
                }
            }
            if(xmlNode.getAttribute("order")){
                extensionDefinition.order = parseInt(xmlNode.getAttribute("order"));
            }else{
                extensionDefinition.order = 0;
            }
            var extensionOnInit = XPathSelectSingleNode(xmlNode, 'processing/extensionOnInit');
            if(extensionOnInit && extensionOnInit.firstChild){
                try{eval(extensionOnInit.firstChild.nodeValue);}catch(e){}
            }
            var dialogOnOpen = XPathSelectSingleNode(xmlNode, 'processing/dialogOnOpen');
            if(dialogOnOpen && dialogOnOpen.firstChild){
                extensionDefinition.dialogOnOpen = dialogOnOpen.firstChild.nodeValue;
            }
            var dialogOnComplete = XPathSelectSingleNode(xmlNode, 'processing/dialogOnComplete');
            if(dialogOnComplete && dialogOnComplete.firstChild){
                extensionDefinition.dialogOnComplete = dialogOnComplete.firstChild.nodeValue;
            }
        }
        return true;
    },

    /**
     * Refresh the currently active extensions
     * Extensions are editors and uploaders for the moment.
     */
    refreshExtensionsRegistry : function(){
        this._extensionsRegistry = {"editor":$A([]), "uploader":$A([])};
        var extensions = XPathSelectNodes(this._registry, "plugins/editor|plugins/uploader");
        for(var i=0;i<extensions.length;i++){
            var extensionDefinition = {
                id : extensions[i].getAttribute("id"),
                xmlNode : extensions[i],
                resourcesManager : new ResourcesManager()
            };
            this._resourcesRegistry[extensionDefinition.id] = extensionDefinition.resourcesManager;
            var resourceNodes = XPathSelectNodes(extensions[i], "client_settings/resources|dependencies|clientForm");
            for(var j=0;j<resourceNodes.length;j++){
                var child = resourceNodes[j];
                extensionDefinition.resourcesManager.loadFromXmlNode(child);
            }
            if(this.initExtension(extensions[i], extensionDefinition)){
                this._extensionsRegistry[extensions[i].nodeName].push(extensionDefinition);
            }
        }
        ResourcesManager.prototype.loadAutoLoadResources(this._registry);
    },


    /**
     * Find the currently active extensions by type
     * @param extensionType String "editor" or "uploader"
     * @returns $A()
     */
    getActiveExtensionByType : function(extensionType){
        return this._extensionsRegistry[extensionType];
    },

    /**
     * Find a given editor by its id
     * @param editorId String
     * @returns AbstractEditor
     */
    findEditorById : function(editorId){
        return this._extensionsRegistry.editor.detect(function(el){return(el.id == editorId);});
    },

    /**
     * Find Editors that can handle a given mime type
     * @param mime String
     * @returns AbstractEditor[]
     * @param restrictToPreviewProviders
     */
    findEditorsForMime : function(mime, restrictToPreviewProviders){
        var editors = $A([]);
        var checkWrite = false;
        if(this.user != null && !this.user.canWrite()){
            checkWrite = true;
        }
        this._extensionsRegistry.editor.each(function(el){
            if(el.mimes.include(mime) || el.mimes.include('*')) {
                if(restrictToPreviewProviders && !el.previewProvider) return;
                if(!checkWrite || !el.write) editors.push(el);
            }
        });
        if(editors.length && editors.length > 1){
            editors = editors.sortBy(function(ed){
                return ed.order||0;
            });
        }
        return editors;
    },

    /**
     * Trigger the load method of the resourcesManager.
     * @param resourcesManager ResourcesManager
     */
    loadEditorResources : function(resourcesManager){
        var registry = this._resourcesRegistry;
        resourcesManager.load(registry);
    },


    getPluginConfigs : function(pluginQuery){
        var xpath = 'plugins/*[@id="core.'+pluginQuery+'"]/plugin_configs/property | plugins/*[@id="'+pluginQuery+'"]/plugin_configs/property';
        if(pluginQuery.indexOf('.') == -1){
            xpath = 'plugins/'+pluginQuery+'/plugin_configs/property |' + xpath;
        }
        var properties = XPathSelectNodes(this._registry, xpath );
        var configs = $H();
        for(var i = 0; i<properties.length; i++){
            var propNode = properties[i];
            configs.set(propNode.getAttribute("name"), propNode.firstChild.nodeValue.evalJSON());
        }
        return configs;
    },

    getDefaultImageFromParameters: function(pluginId, paramName){
        var node = XPathSelectSingleNode(this._registry, "plugins/*[@id='"+pluginId+"']/server_settings/global_param[@name='"+paramName+"']");
        if(node) return node.getAttribute("defaultImage");
        return '';
    },

    hasPluginOfType : function(type, name){
        var node;
        if(name == null){
            node = XPathSelectSingleNode(this._registry, 'plugins/ajxp_plugin[contains(@id, "'+type+'.")] | plugins/' + type + '[@id]');
        }else{
            node = XPathSelectSingleNode(this._registry, 'plugins/ajxp_plugin[@id="'+type+'.'+name+'"] | plugins/' + type + '[@id="'+type+'.'+name+'"]');
        }
        return (node != undefined);

    },


});