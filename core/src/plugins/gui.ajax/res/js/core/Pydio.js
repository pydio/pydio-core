'use strict';

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } };

var _inherits = function (subClass, superClass) { if (typeof superClass !== 'function' && superClass !== null) { throw new TypeError('Super expression must either be null or a function, not ' + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) subClass.__proto__ = superClass; };

var Pydio = (function (_Observable) {

    /**
     *
     * @param parameters {Map}
     */

    function Pydio(parameters) {
        _classCallCheck(this, Pydio);

        _Observable.call(this);
        this.Parameters = parameters;
        this._initLoadRep = parameters.get('initLoadRep') || null;
        this.usersEnabled = parameters.get('usersEnabled') || null;
        this.currentLanguage = parameters.get('currentLanguage') || null;
        this.appTitle = 'Pydio';
        if (this.Parameters.has('customWording')) {
            this.appTitle = this.Parameters.get('customWording').title || 'Pydio';
        }
        this.MessageHash = {};
        if (window.MessageHash) this.MessageHash = window.MessageHash;
        this.ApiClient = PydioApi.getClient();
        this.ApiClient.setPydioObject(this);
        this.Registry = new Registry(this);
        this._rootNode = new AjxpNode('/', 'Root');
        this._dataModel = this._contextHolder = new PydioDataModel(false);
        this._dataModel.setAjxpNodeProvider(new RemoteNodeProvider());
        this._dataModel.setRootNode(this._rootNode);
        // Must happen AFTER datamodel initization.
        this.Controller = new Controller(this);
    }

    _inherits(Pydio, _Observable);

    Pydio.prototype.fire = function fire(eventName, data) {
        this.notify(eventName, data);
        // Backward compatibility
        if (document.fire) {
            document.fire('ajaxplorer:' + eventName, data);
        }
    };

    /**
     * Real initialisation sequence. Will Trigger the whole GUI building.
     * Event ajaxplorer:loaded is fired at the end.
     */

    Pydio.prototype.init = function init() {
        if (!this.Parameters.has('SECURE_TOKEN')) {
            PydioApi.getClient().getBootConf((function () {
                this.init();
            }).bind(this));
            return;
        }

        if (window.PydioUI) {
            this.UI = new PydioUI(this);
        } else {
            // FAKE CLASSE
            this.UI = {
                guiLoaded: true,
                modal: {
                    setLoadingStepCounts: function setLoadingStepCounts() {},
                    refreshDialogAppearance: function refreshDialogAppearance() {},
                    displayMessage: function displayMessage() {},
                    initForms: function initForms() {},
                    updateLoadingProgress: function updateLoadingProgress(progress) {
                        Logger.log(progress);
                    },
                    prepareHeader: function prepareHeader() {},
                    showModalDialog: function showModalDialog() {}
                },
                refreshTemplateParts: function refreshTemplateParts() {},
                initTemplates: function initTemplates() {},
                initObjects: function initObjects() {},
                updateI18nTags: function updateI18nTags() {},
                insertForm: function insertForm(formId, formCode) {},
                removeForm: function removeForm(formId) {}
            };
        }

        this.observe('registry_loaded', (function () {
            this.Registry.refreshExtensionsRegistry();
            this.Registry.logXmlUser(false);
            if (this.user) {
                var repId = this.user.getActiveRepository();
                var repList = this.user.getRepositoriesList();
                var repositoryObject = repList.get(repId);
                if (repositoryObject) repositoryObject.loadResources();
            }
            if (this.UI.guiLoaded) {
                this.UI.refreshTemplateParts();
                this.Registry.refreshExtensionsRegistry();
                this.Controller.loadActionsFromRegistry(this.getXmlRegistry());
            } else {
                this.observe('gui_loaded', (function () {
                    this.UI.refreshTemplateParts();
                    this.Registry.refreshExtensionsRegistry();
                    this.Controller.loadActionsFromRegistry(this.getXmlRegistry());
                }).bind(this));
            }
            this.loadActiveRepository();
            if (this.Parameters.has('USER_GUI_ACTION')) {
                var a = this.Parameters.get('USER_GUI_ACTION');
                this.Parameters['delete']('USER_GUI_ACTION');
                var aBar = this.Controller;
                window.setTimeout(function () {
                    aBar.fireAction(a);
                }, 2000);
            }
        }).bind(this));

        if (this.UI.modal) this.UI.modal.setLoadingStepCounts(5);

        var starterFunc = (function () {

            this.UI.initTemplates();
            if (this.UI.modal) this.UI.modal.initForms();
            this.UI.initObjects();

            this.tryLogUserFromCookie();
            this.fire('registry_loaded', this.Registry.getXML());

            window.setTimeout((function () {
                this.fire('loaded');
            }).bind(this), 200);

            this.Router = new Router(this);
        }).bind(this);

        if (this.Parameters.get('PRELOADED_REGISTRY')) {
            this.Registry.loadFromString(this.Parameters.get('PRELOADED_REGISTRY'));
            this.Parameters['delete']('PRELOADED_REGISTRY');
            if (this.UI.modal) this.UI.modal.updateLoadingProgress('XML Registry loaded');
            starterFunc();
        } else {
            this.loadXmlRegistry(false, null, starterFunc);
        }
        this.observe('server_message', (function (xml) {
            if (XMLUtils.XPathSelectSingleNode(xml, 'tree/require_registry_reload')) {
                this.repositoryId = null;
                this.loadXmlRegistry(false);
            }
        }).bind(this));
    };

    /**
     * Loads the XML Registry, an image of the application in its current state
     * sent by the server.
     * @param sync Boolean Whether to send synchronously or not.
     * @param xPath String An XPath to load only a subpart of the registry
     */

    Pydio.prototype.loadXmlRegistry = function loadXmlRegistry(sync) {
        var xPath = arguments[1] === undefined ? null : arguments[1];
        var completeFunc = arguments[2] === undefined ? null : arguments[2];

        this.Registry.load(sync, xPath, completeFunc);
    };

    /**
     * Get the XML Registry
     * @returns Document
     */

    Pydio.prototype.getXmlRegistry = function getXmlRegistry() {
        return this.Registry.getXML();
    };

    /**
     * Try reading the cookie and sending it to the server
     */

    Pydio.prototype.tryLogUserFromCookie = function tryLogUserFromCookie() {};

    /**
     * Find the current repository (from the current user) and load it.
     */

    Pydio.prototype.loadActiveRepository = function loadActiveRepository() {
        var repositoryObject = new Repository(null);
        if (this.user != null) {
            var repId = this.user.getActiveRepository();
            var repList = this.user.getRepositoriesList();
            repositoryObject = repList.get(repId);
            if (!repositoryObject) {
                if (this.user.lock) {
                    this.Controller.loadActionsFromRegistry(this.getXmlRegistry());
                    window.setTimeout((function () {
                        this.Controller.fireAction(this.user.lock);
                    }).bind(this), 50);
                    return;
                }
                alert('No active repository found for user!');
            }
            if (this.user.getPreference('pending_folder') && this.user.getPreference('pending_folder') != '-1') {
                this._initLoadRep = this.user.getPreference('pending_folder');
                this.user.setPreference('pending_folder', '-1');
                this.user.savePreference('pending_folder');
            } else if (this.user.getPreference('ls_history', true)) {
                var data = this.user.getPreference('ls_history', true);
                this._initLoadRep = data[repId];
            }
        }
        this.loadRepository(repositoryObject);
        if (repList && repId) {
            this.fire('repository_list_refreshed', { list: repList, active: repId });
        } else {
            this.fire('repository_list_refreshed', { list: false, active: false });
        }
    };

    /**
     * Refresh the repositories list for the current user
     */

    Pydio.prototype.reloadRepositoriesList = function reloadRepositoriesList() {
        if (!this.user) {
            return;
        }this.observeOnce('registry_part_loaded', (function (data) {
            if (data != 'user/repositories') return;
            this.Registry.logXmlUser(true);
            document.fire('ajaxplorer:repository_list_refreshed', {
                list: this.user.getRepositoriesList(),
                active: this.user.getActiveRepository() });
        }).bind(this));
        this.loadXmlRegistry(false, 'user/repositories');
    };

    /**
     * Load a Repository instance
     * @param repository Repository
     */

    Pydio.prototype.loadRepository = function loadRepository(repository) {

        if (this.repositoryId != null && this.repositoryId == repository.getId()) {
            Logger.debug('Repository already loaded, do nothing');
        }
        this._contextHolder.setSelectedNodes([]);
        if (repository == null) {
            return;
        }repository.loadResources();
        var repositoryId = repository.getId();
        var newIcon = repository.getIcon();

        this.skipLsHistory = true;

        var providerDef = repository.getNodeProviderDef();
        var rootNode;
        if (providerDef != null) {
            var provider = eval('new ' + providerDef.name + '()');
            if (providerDef.options) {
                provider.initProvider(providerDef.options);
            }
            this._contextHolder.setAjxpNodeProvider(provider);
            rootNode = new AjxpNode('/', false, repository.getLabel(), newIcon, provider);
        } else {
            rootNode = new AjxpNode('/', false, repository.getLabel(), newIcon);
            // Default
            this._contextHolder.setAjxpNodeProvider(new RemoteNodeProvider());
        }
        this._contextHolder.setRootNode(rootNode);
        rootNode.observeOnce('first_load', (function () {
            this._contextHolder.notify('context_changed', rootNode);
        }).bind(this));
        this.repositoryId = repositoryId;

        if (this._initLoadRep) {
            if (this._initLoadRep != '' && this._initLoadRep != '/') {
                var copy = this._initLoadRep.valueOf();
                this._initLoadRep = null;
                rootNode.observeOnce('first_load', (function () {
                    setTimeout((function () {
                        this.goTo(copy);
                        this.skipLsHistory = false;
                    }).bind(this), 1000);
                }).bind(this));
            } else {
                this.skipLsHistory = false;
            }
        } else {
            this.skipLsHistory = false;
        }

        rootNode.load();
    };

    /**
     * Require a context change to the given path
     * @param nodeOrPath AjxpNode|String A node or a path
     */

    Pydio.prototype.goTo = function goTo(nodeOrPath) {
        var path;
        if (typeof nodeOrPath == 'string') {
            path = nodeOrPath;
        } else {
            path = nodeOrPath.getPath();
            if (nodeOrPath.getMetadata().has('repository_id') && nodeOrPath.getMetadata().get('repository_id') != this.repositoryId && nodeOrPath.getAjxpMime() != 'repository' && nodeOrPath.getAjxpMime() != 'repository_editable') {
                if (this.user) {
                    this.user.setPreference('pending_folder', nodeOrPath.getPath());
                }
                this.triggerRepositoryChange(nodeOrPath.getMetadata().get('repository_id'));
                return;
            }
        }

        var current = this._contextHolder.getContextNode();
        if (current && current.getPath() == path) {
            return;
        }
        var gotoNode;
        if (path == '' || path == '/') {
            gotoNode = new AjxpNode('/');
            this._contextHolder.requireContextChange(gotoNode);
            return;
        }
        this._contextHolder.loadPathInfoAsync(path, (function (foundNode) {
            if (foundNode.isLeaf() && foundNode.getAjxpMime() != 'ajxp_browsable_archive') {
                this._contextHolder.setPendingSelection(PathUtils.getBasename(path));
                gotoNode = new AjxpNode(PathUtils.getDirname(path));
            } else {
                gotoNode = foundNode;
            }
            this._contextHolder.requireContextChange(gotoNode);
        }).bind(this));
    };

    /**
     * Change the repository of the current user and reload list and current.
     * @param repositoryId String Id of the new repository
     */

    Pydio.prototype.triggerRepositoryChange = function triggerRepositoryChange(repositoryId) {
        this.fire('trigger_repository_switch');
        var onComplete = (function (transport) {
            if (transport.responseXML) {
                this.Controller.parseXmlMessage(transport.responseXML);
            }
            this.repositoryId = null;
            this.loadXmlRegistry();
        }).bind(this);
        var root = this._contextHolder.getRootNode();
        if (root) {
            this.skipLsHistory = true;
            root.clear();
        }
        this.ApiClient.switchRepository(repositoryId, onComplete);
    };

    Pydio.prototype.getPluginConfigs = function getPluginConfigs(pluginQuery) {
        return this.Registry.getPluginConfigs(pluginQuery);
    };

    /**
     * Reload all messages from server and trigger updateI18nTags
     * @param newLanguage String
     */

    Pydio.prototype.loadI18NMessages = function loadI18NMessages(newLanguage) {
        var onComplete = (function (transport) {
            if (transport.responseJSON) {
                this.MessageHash = transport.responseJSON;
                for (var key in this.MessageHash) {
                    if (this.MessageHash.hasOwnProperty(key)) {
                        this.MessageHash[key] = this.MessageHash[key].replace('\\n', '\n');
                    }
                }
                this.UI.updateI18nTags();
                this.Controller.refreshGuiActionsI18n();

                this.loadXmlRegistry();
                this.fireContextRefresh();
                this.currentLanguage = newLanguage;
            }
        }).bind(this);
        this.ApiClient.switchLanguage(newLanguage, onComplete);
    };

    /**
     * Get the main controller
     * @returns ActionManager
     */

    Pydio.prototype.getController = function getController() {
        return this.Controller;
    };

    /**
     * Display an information or error message to the user
     * @param messageType String ERROR or SUCCESS
     * @param message String the message
     */

    Pydio.prototype.displayMessage = function displayMessage(messageType, message) {
        var urls = LangUtils.parseUrl(message);
        if (urls.length && this.user && this.user.repositories) {
            urls.forEach((function (match) {
                var repo = this.user.repositories.get(match.host);
                if (!repo) return;
                message = message.replace(match.url, repo.label + ':' + match.path + match.file);
            }).bind(this));
        }
        if (messageType == 'ERROR') Logger.error(message);else Logger.log(message);
        if (this.UI.modal) this.UI.modal.displayMessage(messageType, message);
    };

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

    Pydio.prototype.updateContextData = function updateContextData(ajxpContextNode, ajxpSelectedNodes, selectionSource) {
        if (ajxpContextNode) {
            this._contextHolder.requireContextChange(ajxpContextNode);
        }
        if (ajxpSelectedNodes) {
            this._contextHolder.setSelectedNodes(ajxpSelectedNodes, selectionSource);
        }
    };

    /**
     * @returns AjxpDataModel
     */

    Pydio.prototype.getContextHolder = function getContextHolder() {
        return this._contextHolder;
    };

    /**
     * @returns AjxpNode
     */

    Pydio.prototype.getContextNode = function getContextNode() {
        return this._contextHolder.getContextNode() || new AjxpNode('');
    };

    /**
     * @returns AjxpDataModel
     */

    Pydio.prototype.getUserSelection = function getUserSelection() {
        return this._contextHolder;
    };

    /**
     * Accessor for datamodel.requireContextChange()
     */

    Pydio.prototype.fireContextRefresh = function fireContextRefresh() {
        this.getContextHolder().requireContextChange(this.getContextNode(), true);
    };

    /**
     * Accessor for datamodel.requireContextChange()
     */

    Pydio.prototype.fireNodeRefresh = function fireNodeRefresh(nodePathOrNode, completeCallback) {
        this.getContextHolder().requireNodeReload(nodePathOrNode, completeCallback);
    };

    /**
     * Accessor for datamodel.requireContextChange()
     */

    Pydio.prototype.fireContextUp = function fireContextUp() {
        if (this.getContextNode().isRoot()) {
            return;
        }this.updateContextData(this.getContextNode().getParent());
    };

    return Pydio;
})(Observable);

// TODO: to grab from somewhere else
/*
var connexion = new Connexion();
var rememberData = retrieveRememberData();
if(rememberData!=null){
    connexion.addParameter('get_action', 'login');
    connexion.addParameter('userid', rememberData.user);
    connexion.addParameter('password', rememberData.pass);
    connexion.addParameter('cookie_login', 'true');
    connexion.onComplete = function(transport){
        hideLightBox();
        this.Controller.parseXmlMessage(transport.responseXML);
    }.bind(this);
    connexion.sendSync();
}
*/