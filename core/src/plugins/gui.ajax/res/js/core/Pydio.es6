import Observable from './lang/Observable'

class Pydio extends Observable{

    /**
     *
     * @param parameters {Map}
     */
    constructor(parameters){
        super();
        this.Parameters = parameters;
        this._initLoadRep = parameters.get('initLoadRep') || null;
        this.usersEnabled = parameters.get('usersEnabled') || null;
        this.currentLanguage = parameters.get('currentLanguage') || null;
        this.appTitle = "Pydio";
        if(this.Parameters.has("customWording")){
            this.appTitle = this.Parameters.get("customWording").title || "Pydio";
        }
        this.MessageHash = {};
        if(window.MessageHash) this.MessageHash = window.MessageHash;
        this.ApiClient = PydioApi.getClient();
        this.ApiClient.setPydioObject(this);
        this.Registry = new Registry(this);
        this._rootNode = new AjxpNode("/", "Root");
        this._dataModel = this._contextHolder = new PydioDataModel(false);
        this._dataModel.setAjxpNodeProvider(new RemoteNodeProvider());
        this._dataModel.setRootNode(this._rootNode);
        // Must happen AFTER datamodel initization.
        this.Controller = new Controller(this);
    }

    fire(eventName, data){
        this.notify(eventName, data);
    }

    /**
     * Real initialisation sequence. Will Trigger the whole GUI building.
     * Event ajaxplorer:loaded is fired at the end.
     */
    init(){
        if(!this.Parameters.has('SECURE_TOKEN')){
            PydioApi.getClient().getBootConf(function(){
                this.init();
            }.bind(this));
            return;
        }

        let modal = this.UI && this.UI.modal ? this.UI.modal : (window.modal ? window.modal : null);

        this.observe("registry_loaded", function(){

            this.Registry.refreshExtensionsRegistry();
            this.Registry.logXmlUser(false);
            if(this.user){
                var repId = this.user.getActiveRepository();
                var repList = this.user.getRepositoriesList();
                var repositoryObject = repList.get(repId);
                if(repositoryObject) repositoryObject.loadResources();
            }
            if(this.UI.guiLoaded) {
                this.UI.refreshTemplateParts();
                this.Registry.refreshExtensionsRegistry();
                this.Controller.loadActionsFromRegistry(this.getXmlRegistry());
            } else {
                this.observe("gui_loaded", function(){
                    this.UI.refreshTemplateParts();
                    this.Registry.refreshExtensionsRegistry();
                    this.Controller.loadActionsFromRegistry(this.getXmlRegistry());
                }.bind(this));
            }
            this.loadActiveRepository();
            if(this.Parameters.has("USER_GUI_ACTION")){
                var a= this.Parameters.get("USER_GUI_ACTION");
                this.Parameters.delete("USER_GUI_ACTION");
                var aBar = this.Controller;
                window.setTimeout(function(){
                    aBar.fireAction(a);
                }, 1000);
            }
        }.bind(this));

        if(modal) modal.setLoadingStepCounts(window.useReactPydioUI ? 1 : 5);

        var starterFunc = function(){

            ResourcesManager.loadClassesAndApply(["React", "PydioReactUI"], function(){

                this.UI = new PydioReactUI.Builder(this);
                this.UI.initTemplates();

                if(!this.user) {
                    PydioApi.getClient().tryToLogUserFromRememberData();
                }
                this.fire("registry_loaded", this.Registry.getXML());

                window.setTimeout(function(){
                    this.fire('loaded');
                }.bind(this), 200);

            }.bind(this));

        }.bind(this);


        if(this.Parameters.get("PRELOADED_REGISTRY")){
            this.Registry.loadFromString(this.Parameters.get("PRELOADED_REGISTRY"));
            this.Parameters.delete("PRELOADED_REGISTRY");
            if(modal) modal.updateLoadingProgress('XML Registry loaded');
            starterFunc();
        }else{
            this.loadXmlRegistry(false, null, starterFunc);
        }
        this.observe("server_message", function(xml){
            var reload = XMLUtils.XPathSelectSingleNode(xml, "tree/require_registry_reload");
            if(reload){
                if(reload.getAttribute("repositoryId") != this.repositoryId){
                    this.loadXmlRegistry(false, null, null, reload.getAttribute("repositoryId"));
                    this.repositoryId = null;
                }
            }
        }.bind(this));
    }

    /**
     * Loads the XML Registry, an image of the application in its current state
     * sent by the server.
     * @param sync Boolean Whether to send synchronously or not.
     * @param xPath String An XPath to load only a subpart of the registry
     */
    loadXmlRegistry (sync, xPath=null, completeFunc=null, targetRepositoryId=null){
        this.Registry.load(sync, xPath, completeFunc, (targetRepositoryId === null ? Math.random() : targetRepositoryId));
    }

    /**
     * Get the XML Registry
     * @returns Document
     */
    getXmlRegistry (){
        return this.Registry.getXML();
    }

    /**
     * Find the current repository (from the current user) and load it.
     */
    loadActiveRepository (){
        var repositoryObject = new Repository(null);
        if(this.user != null)
        {
            var repId = this.user.getActiveRepository();
            var repList = this.user.getRepositoriesList();
            repositoryObject = repList.get(repId);
            if(!repositoryObject){
                if(this.user.lock){
                    this.Controller.loadActionsFromRegistry(this.getXmlRegistry());
                    let lock = this.user.lock.split(",").shift();
                    window.setTimeout(function(){
                        this.Controller.fireAction(lock);
                    }.bind(this), 50);
                    return;
                }
                alert("No active repository found for user!");
            }
            if(this.user.getPreference("pending_folder") && this.user.getPreference("pending_folder") != "-1"){
                this._initLoadRep = this.user.getPreference("pending_folder");
                this.user.setPreference("pending_folder", "-1");
                this.user.savePreference("pending_folder");
            }else if(this.user.getPreference("ls_history", true)){
                var data = this.user.getPreference("ls_history", true);
                this._initLoadRep = data[repId];
            }
        }
        this.loadRepository(repositoryObject);
        if(repList && repId){
            this.fire("repository_list_refreshed", {list:repList,active:repId});
        }else{
            this.fire("repository_list_refreshed", {list:false,active:false});
        }
    }

    /**
     * Refresh the repositories list for the current user
     */
    reloadRepositoriesList (){
        if(!this.user) return;
        this.observeOnce("registry_part_loaded", function(data){
            if(data != "user/repositories") return;
            this.Registry.logXmlUser(true);
            this.fire("repository_list_refreshed", {
                list:this.user.getRepositoriesList(),
                active:this.user.getActiveRepository()});
        }.bind(this));
        this.loadXmlRegistry(false, "user/repositories");
    }

    /**
     * Load a Repository instance
     * @param repository Repository
     */
    loadRepository(repository){

        if(this.repositoryId != null && this.repositoryId == repository.getId()){
            Logger.debug('Repository already loaded, do nothing');
        }
        this._contextHolder.setSelectedNodes([]);
        if(repository == null) return;

        repository.loadResources();
        var repositoryId = repository.getId();
        var	newIcon = repository.getIcon();

        this.skipLsHistory = true;

        var providerDef = repository.getNodeProviderDef();
        var rootNode;
        if(providerDef != null){
            var provider = eval('new '+providerDef.name+'()');
            if(providerDef.options){
                provider.initProvider(providerDef.options);
            }
            this._contextHolder.setAjxpNodeProvider(provider);
            rootNode = new AjxpNode("/", false, repository.getLabel(), newIcon, provider);
        }else{
            rootNode = new AjxpNode("/", false, repository.getLabel(), newIcon);
            // Default
            this._contextHolder.setAjxpNodeProvider(new RemoteNodeProvider());
        }
        this._contextHolder.setRootNode(rootNode);
        rootNode.observeOnce('first_load', function(){
            this._contextHolder.notify('context_changed', rootNode);
        }.bind(this));
        this.repositoryId = repositoryId;

        if(this._initLoadRep){
            if(this._initLoadRep != "" && this._initLoadRep != "/"){
                var copy = this._initLoadRep.valueOf();
                this._initLoadRep = null;
                rootNode.observeOnce("first_load", function(){
                    setTimeout(function(){
                        this.goTo(copy);
                        this.skipLsHistory = false;
                    }.bind(this), 1000);
                }.bind(this));
            }else{
                this.skipLsHistory = false;
            }
        }else{
            this.skipLsHistory = false;
        }

        rootNode.load();
    }

    /**
     * Require a context change to the given path
     * @param nodeOrPath AjxpNode|String A node or a path
     */
    goTo(nodeOrPath){
        var path;
        if(typeof(nodeOrPath) == "string"){
            path = nodeOrPath;
        }else{
            path = nodeOrPath.getPath();
            if(nodeOrPath.getMetadata().has("repository_id") && nodeOrPath.getMetadata().get("repository_id") != this.repositoryId
                && nodeOrPath.getAjxpMime() != "repository" && nodeOrPath.getAjxpMime() != "repository_editable"){
                if(this.user){
                    this.user.setPreference("pending_folder", nodeOrPath.getPath());
                    this._initLoadRep = nodeOrPath.getPath();
                }
                this.triggerRepositoryChange(nodeOrPath.getMetadata().get("repository_id"));
                return;
            }
        }

        var current = this._contextHolder.getContextNode();
        if(current && current.getPath() == path){
            return;
        }
        var gotoNode;
        if(path === "" || path === "/") {
            this._contextHolder.requireContextChange(this._contextHolder.getRootNode());
            return;
        }else{
            gotoNode = new AjxpNode(path);
            gotoNode = gotoNode.findInArbo(this._contextHolder.getRootNode());
            if(gotoNode){
                // Node is already here
                this._contextHolder.requireContextChange(gotoNode);
            }else{
                // Check on server if it does exist, then load
                this._contextHolder.loadPathInfoAsync(path, function(foundNode){
                    if(foundNode.isLeaf() && foundNode.getAjxpMime()!='ajxp_browsable_archive') {
                        this._contextHolder.setPendingSelection(PathUtils.getBasename(path));
                        gotoNode = new AjxpNode(PathUtils.getDirname(path));
                    }else{
                        gotoNode = foundNode;
                    }
                    this._contextHolder.requireContextChange(gotoNode);
                }.bind(this));
            }
        }
    }

    /**
     * Change the repository of the current user and reload list and current.
     * @param repositoryId String Id of the new repository
     */
    triggerRepositoryChange(repositoryId, callback){
        this.fire("trigger_repository_switch");
        var onComplete = function(transport){
            if(transport.responseXML){
                this.ApiClient.parseXmlMessage(transport.responseXML);
            }
            this.loadXmlRegistry(false,  null, null, repositoryId);
            this.repositoryId = null;

            if (typeof callback == "function") callback()
        }.bind(this);

        var root = this._contextHolder.getRootNode();
        if(root){
            this.skipLsHistory = true;
            root.clear();
        }
        this.ApiClient.switchRepository(repositoryId, onComplete);
    }

    getPluginConfigs (pluginQuery){
        return this.Registry.getPluginConfigs(pluginQuery);
    }

    listLanguagesWithCallback(callback){
        let langs = this.Parameters.get("availableLanguages") || {"en":"Default"};
        let current = this.currentLanguage;
        Object.keys(langs).sort().map(function(key){
            callback(key, langs[key], (current === key));
        });
    }

    /**
     * Reload all messages from server and trigger updateI18nTags
     * @param newLanguage String
     */
    loadI18NMessages(newLanguage){
        this.ApiClient.switchLanguage(newLanguage, function(transport){
            if(transport.responseJSON){
                this.MessageHash = transport.responseJSON;
                if(window && window.MessageHash) {
                    window.MessageHash = this.MessageHash;
                }
                for(var key in this.MessageHash){
                    if(this.MessageHash.hasOwnProperty(key)){
                        this.MessageHash[key] = this.MessageHash[key].replace("\\n", "\n");
                    }
                }
                this.Controller.refreshGuiActionsI18n();
                this.loadXmlRegistry();
                this.fireContextRefresh();
                this.currentLanguage = newLanguage;
            }

        }.bind(this));
    }

    /**
     * Get the main controller
     * @returns ActionManager
     */
    getController(){
        return this.Controller;
    }

    /**
     * Display an information or error message to the user
     * @param messageType String ERROR or SUCCESS
     * @param message String the message
     */
    displayMessage(messageType, message){
        var urls = LangUtils.parseUrl(message);
        if(urls.length && this.user && this.user.repositories){
            urls.forEach(function(match){
                var repo = this.user.repositories.get(match.host);
                if(!repo) return;
                message = message.replace(match.url, repo.label+":" + match.path + match.file);
            }.bind(this));
        }
        if(messageType == 'ERROR') Logger.error(message);
        else Logger.log(message);
        if(this.UI) {
            this.UI.displayMessage(messageType, message);
        }
    }


    /*************************************************
     *
     *          PROXY METHODS FOR DATAMODEL
     *
     ************************************************/

    /**
     * Accessor for updating the datamodel context
     * @param ajxpContextNode AjxpNode
     * @param ajxpSelectedNodes AjxpNode[]
     * @param selectionSource String
     */
    updateContextData (ajxpContextNode, ajxpSelectedNodes, selectionSource){
        if(ajxpContextNode){
            this._contextHolder.requireContextChange(ajxpContextNode);
        }
        if(ajxpSelectedNodes){
            this._contextHolder.setSelectedNodes(ajxpSelectedNodes, selectionSource);
        }
    }

    /**
     * @returns AjxpDataModel
     */
    getContextHolder (){
        return this._contextHolder;
    }

    /**
     * @returns AjxpNode
     */
    getContextNode (){
        return this._contextHolder.getContextNode() || new AjxpNode("");
    }

    /**
     * @returns AjxpDataModel
     */
    getUserSelection (){
        return this._contextHolder;
    }

    /**
     * Accessor for datamodel.requireContextChange()
     */
    fireContextRefresh (){
        this.getContextHolder().requireContextChange(this.getContextNode(), true);
    }

    /**
     * Accessor for datamodel.requireContextChange()
     */
    fireNodeRefresh (nodePathOrNode, completeCallback){
        this.getContextHolder().requireNodeReload(nodePathOrNode, completeCallback);
    }

    /**
     * Accessor for datamodel.requireContextChange()
     */
    fireContextUp (){
        if(this.getContextNode().isRoot()) return;
        this.updateContextData(this.getContextNode().getParent());
    }

    /**
     * Proxy to ResourcesManager.requireLib for ease of writing
     * @param module
     * @param promise
     * @returns {*}
     */
    static requireLib(module, promise = false){
        return require('pydio/http/resources-manager').requireLib(module, promise);
    }

}

export {Pydio as default}