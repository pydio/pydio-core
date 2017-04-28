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
 * The latest code can be found at <https://pydio.com>.
 */
"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var Registry = (function () {
    function Registry(pydioObject) {
        _classCallCheck(this, Registry);

        this._registry = null;
        this._extensionsRegistry = { "editor": [], "uploader": [] };
        this._resourcesRegistry = {};
        this._pydioObject = pydioObject;
        this._stateLoading = false;
    }

    Registry.prototype.loadFromString = function loadFromString(s) {
        this._registry = XMLUtils.parseXml(s).documentElement;
    };

    Registry.prototype.load = function load(sync, xPath, completeFunc, repositoryId) {
        if (this._stateLoading) {
            return;
        }
        this._stateLoading = true;
        var onComplete = (function (transport) {
            this._stateLoading = false;
            if (transport.responseXML == null || transport.responseXML.documentElement == null) return;
            if (transport.responseXML.documentElement.nodeName == "ajxp_registry") {
                this._registry = transport.responseXML.documentElement;
                //modal.updateLoadingProgress('XML Registry loaded');
                if (!sync && !completeFunc) {
                    this._pydioObject.fire("registry_loaded", this._registry);
                }
            } else if (transport.responseXML.documentElement.nodeName == "ajxp_registry_part") {
                this.refreshXmlRegistryPart(transport.responseXML.documentElement);
            }
            if (completeFunc) completeFunc(this._registry);
        }).bind(this);
        var params = { get_action: 'get_xml_registry' };
        if (xPath) {
            params['xPath'] = xPath;
        }
        if (repositoryId) {
            params['ws_id'] = repositoryId; // for caching only
        }
        PydioApi.getClient().request(params, onComplete, null, {
            async: !sync,
            method: 'get'
        });
    };

    /**
     * Inserts a document fragment retrieved from server inside the full tree.
     * The node must contains the xPath attribute to locate it inside the registry.
     * Event ajaxplorer:registry_part_loaded is triggerd once this is done.
     * @param documentElement DOMNode
     */

    Registry.prototype.refreshXmlRegistryPart = function refreshXmlRegistryPart(documentElement) {
        var xPath = documentElement.getAttribute("xPath");
        var existingNode = XMLUtils.XPathSelectSingleNode(this._registry, xPath);
        var parentNode;
        if (existingNode && existingNode.parentNode) {
            parentNode = existingNode.parentNode;
            parentNode.removeChild(existingNode);
            if (documentElement.firstChild) {
                parentNode.appendChild(documentElement.firstChild.cloneNode(true));
            }
        } else if (xPath.indexOf("/") > -1) {
            // try selecting parentNode
            var parentPath = xPath.substring(0, xPath.lastIndexOf("/"));
            parentNode = XMLUtils.XPathSelectSingleNode(this._registry, parentPath);
            if (parentNode && documentElement.firstChild) {
                parentNode.appendChild(documentElement.firstChild.cloneNode(true));
            }
        } else {
            if (documentElement.firstChild) this._registry.appendChild(documentElement.firstChild.cloneNode(true));
        }
        this._pydioObject.fire("registry_part_loaded", xPath);
    };

    /**
     * Translate the XML answer to a new User object
     * @param skipEvent Boolean Whether to skip the sending of ajaxplorer:user_logged event.
     */

    Registry.prototype.logXmlUser = function logXmlUser(skipEvent) {
        this._pydioObject.user = null;
        var userNode;
        if (this._registry) {
            userNode = XMLUtils.XPathSelectSingleNode(this._registry, "user");
        }
        if (userNode) {
            var userId = userNode.getAttribute('id');
            var children = userNode.childNodes;
            if (userId) {
                this._pydioObject.user = new User(userId, children);
            }
        }
        if (!skipEvent) {
            this._pydioObject.fire("user_logged", this._pydioObject.user);
        }
    };

    Registry.prototype.getXML = function getXML() {
        return this._registry;
    };

    /**
     * Find Extension initialisation nodes (activeCondition, onInit, etc), parses
     * the XML and execute JS.
     * @param xmlNode DOMNode The extension node
     * @param extensionDefinition Object Information already collected about this extension
     * @returns Boolean
     */

    Registry.prototype.initExtension = function initExtension(xmlNode, extensionDefinition) {
        var activeCondition = XMLUtils.XPathSelectSingleNode(xmlNode, 'processing/activeCondition');
        if (activeCondition && activeCondition.firstChild) {
            try {
                var func = new Function(activeCondition.firstChild.nodeValue.trim());
                if (func() === false) return false;
            } catch (e) {}
        }
        if (xmlNode.nodeName == 'editor') {
            var properties = {
                openable: xmlNode.getAttribute("openable") == "true",
                modalOnly: xmlNode.getAttribute("modalOnly") == "true",
                previewProvider: xmlNode.getAttribute("previewProvider") == "true",
                order: xmlNode.getAttribute("order") ? parseInt(xmlNode.getAttribute("order")) : 0,
                formId: xmlNode.getAttribute("formId") || null,
                text: this._pydioObject.MessageHash[xmlNode.getAttribute("text")],
                title: this._pydioObject.MessageHash[xmlNode.getAttribute("title")],
                icon: xmlNode.getAttribute("icon"),
                icon_class: xmlNode.getAttribute("iconClass"),
                editorClass: xmlNode.getAttribute("className"),
                mimes: xmlNode.getAttribute("mimes").split(","),
                write: xmlNode.getAttribute("write") && xmlNode.getAttribute("write") == "true" ? true : false,
                canWrite: xmlNode.getAttribute("canWrite") && xmlNode.getAttribute("canWrite") == "true" ? true : false
            };
            for (var k in properties) {
                if (properties.hasOwnProperty(k)) {
                    extensionDefinition[k] = properties[k];
                }
            }
        } else if (xmlNode.nodeName == 'uploader') {
            var th = this._pydioObject.Parameters.get('theme');
            var clientForm = XMLUtils.XPathSelectSingleNode(xmlNode, 'processing/clientForm[@theme="' + th + '"]');
            if (!clientForm) {
                clientForm = XMLUtils.XPathSelectSingleNode(xmlNode, 'processing/clientForm');
            }
            if (clientForm && clientForm.firstChild && clientForm.getAttribute('id')) {
                extensionDefinition.formId = clientForm.getAttribute('id');
                this._pydioObject.UI.insertForm(clientForm.getAttribute('id'), clientForm.firstChild.nodeValue);
            }
            if (xmlNode.getAttribute("order")) {
                extensionDefinition.order = parseInt(xmlNode.getAttribute("order"));
            } else {
                extensionDefinition.order = 0;
            }
            var extensionOnInit = XMLUtils.XPathSelectSingleNode(xmlNode, 'processing/extensionOnInit');
            if (extensionOnInit && extensionOnInit.firstChild) {
                try {
                    // @TODO: THIS WILL LIKELY TRIGGER PROTOTYPE CODE
                    eval(extensionOnInit.firstChild.nodeValue);
                } catch (e) {
                    Logger.error("Ignoring Error in extensionOnInit code:");
                    Logger.error(extensionOnInit.firstChild.nodeValue);
                    Logger.error(e.message);
                }
            }
            var dialogOnOpen = XMLUtils.XPathSelectSingleNode(xmlNode, 'processing/dialogOnOpen');
            if (dialogOnOpen && dialogOnOpen.firstChild) {
                extensionDefinition.dialogOnOpen = dialogOnOpen.firstChild.nodeValue;
            }
            var dialogOnComplete = XMLUtils.XPathSelectSingleNode(xmlNode, 'processing/dialogOnComplete');
            if (dialogOnComplete && dialogOnComplete.firstChild) {
                extensionDefinition.dialogOnComplete = dialogOnComplete.firstChild.nodeValue;
            }
        }
        return true;
    };

    /**
     * Refresh the currently active extensions
     * Extensions are editors and uploaders for the moment.
     */

    Registry.prototype.refreshExtensionsRegistry = function refreshExtensionsRegistry() {
        this._extensionsRegistry = { "editor": [], "uploader": [] };
        var extensions = XMLUtils.XPathSelectNodes(this._registry, "plugins/editor|plugins/uploader");
        for (var i = 0; i < extensions.length; i++) {
            var extensionDefinition = {
                id: extensions[i].getAttribute("id"),
                xmlNode: extensions[i],
                resourcesManager: new ResourcesManager()
            };
            this._resourcesRegistry[extensionDefinition.id] = extensionDefinition.resourcesManager;
            var resourceNodes = XMLUtils.XPathSelectNodes(extensions[i], "client_settings/resources|dependencies|clientForm");
            for (var j = 0; j < resourceNodes.length; j++) {
                var child = resourceNodes[j];
                extensionDefinition.resourcesManager.loadFromXmlNode(child);
            }
            if (this.initExtension(extensions[i], extensionDefinition)) {
                this._extensionsRegistry[extensions[i].nodeName].push(extensionDefinition);
            }
        }
        ResourcesManager.loadAutoLoadResources(this._registry);
    };

    /**
     * Find the currently active extensions by type
     * @param extensionType String "editor" or "uploader"
     * @returns {array}
     */

    Registry.prototype.getActiveExtensionByType = function getActiveExtensionByType(extensionType) {
        return this._extensionsRegistry[extensionType];
    };

    /**
     * Find a given editor by its id
     * @param editorId String
     * @returns AbstractEditor
     */

    Registry.prototype.findEditorById = function findEditorById(editorId) {
        return this._extensionsRegistry.editor.find(function (el) {
            return el.id == editorId;
        });
    };

    /**
     * Find Editors that can handle a given mime type
     * @param mime String
     * @returns AbstractEditor[]
     * @param restrictToPreviewProviders
     */

    Registry.prototype.findEditorsForMime = function findEditorsForMime(mime, restrictToPreviewProviders) {
        var editors = [];
        var checkWrite = false;
        if (this._pydioObject.user != null && !this._pydioObject.user.canWrite()) {
            checkWrite = true;
        }
        this._extensionsRegistry.editor.forEach(function (el) {
            if (el.mimes.indexOf(mime) !== -1 || el.mimes.indexOf('*') !== -1) {
                if (restrictToPreviewProviders && !el.previewProvider) return;
                if (!checkWrite || !el.write) editors.push(el);
            }
        });
        if (editors.length && editors.length > 1) {
            editors = editors.sort(function (a, b) {
                return (a.order || 0) - (b.order || 0);
            });
        }
        return editors;
    };

    /**
     * Trigger the load method of the resourcesManager.
     * @param resourcesManager ResourcesManager
     */

    Registry.prototype.loadEditorResources = function loadEditorResources(resourcesManager) {
        resourcesManager.load(this._resourcesRegistry);
    };

    /**
     *
     * @param pluginQuery
     * @returns {Map}
     */

    Registry.prototype.getPluginConfigs = function getPluginConfigs(pluginQuery) {
        var xpath = 'plugins/*[@id="core.' + pluginQuery + '"]/plugin_configs/property | plugins/*[@id="' + pluginQuery + '"]/plugin_configs/property';
        if (pluginQuery.indexOf('.') == -1) {
            xpath = 'plugins/' + pluginQuery + '/plugin_configs/property |' + xpath;
        }
        var properties = XMLUtils.XPathSelectNodes(this._registry, xpath);
        var configs = new Map();
        properties.forEach(function (propNode) {
            configs.set(propNode.getAttribute("name"), JSON.parse(propNode.firstChild.nodeValue));
        });
        return configs;
    };

    /**
     *
     * @param pluginId
     * @param paramName
     * @returns {string}
     */

    Registry.prototype.getDefaultImageFromParameters = function getDefaultImageFromParameters(pluginId, paramName) {
        var node = XMLUtils.XPathSelectSingleNode(this._registry, "plugins/*[@id='" + pluginId + "']/server_settings/global_param[@name='" + paramName + "']");
        if (!node) return '';
        return node.getAttribute("defaultImage") || '';
    };

    /**
     *
     * @param type
     * @param name
     * @returns {bool}
     */

    Registry.prototype.hasPluginOfType = function hasPluginOfType(type, name) {
        var node;
        if (name == null) {
            node = XMLUtils.XPathSelectSingleNode(this._registry, 'plugins/ajxp_plugin[contains(@id, "' + type + '.")] | plugins/' + type + '[@id]');
        } else {
            node = XMLUtils.XPathSelectSingleNode(this._registry, 'plugins/ajxp_plugin[@id="' + type + '.' + name + '"] | plugins/' + type + '[@id="' + type + '.' + name + '"]');
        }
        return node != undefined;
    };

    return Registry;
})();
