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

/**
 * Implementation of the IAjxpNodeProvider interface based on a remote server access.
 * Default for all repositories.
 */
'use strict';

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

var RemoteNodeProvider = (function () {

    /**
     * Constructor
     */

    function RemoteNodeProvider() {
        _classCallCheck(this, RemoteNodeProvider);

        this.discrete = false;
    }

    /**
     * Initialize properties
     * @param properties Object
     */

    RemoteNodeProvider.prototype.initProvider = function initProvider(properties) {
        this.properties = new Map();
        for (var p in properties) {
            if (properties.hasOwnProperty(p)) this.properties.set(p, properties[p]);
        }
        if (this.properties && this.properties.has('connexion_discrete')) {
            this.discrete = true;
            this.properties['delete']('connexion_discrete');
        }
        if (this.properties && this.properties.has('cache_service')) {
            this.cacheService = this.properties.get('cache_service');
            this.properties['delete']('cache_service');
            MetaCacheService.getInstance().registerMetaStream(this.cacheService['metaStreamName'], this.cacheService['expirationPolicy']);
        }
    };

    /**
     * Load a node
     * @param node AjxpNode
     * @param nodeCallback Function On node loaded
     * @param childCallback Function On child added
     * @param recursive
     * @param depth
     * @param optionalParameters
     */

    RemoteNodeProvider.prototype.loadNode = function loadNode(node) {
        var nodeCallback = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
        var childCallback = arguments.length <= 2 || arguments[2] === undefined ? null : arguments[2];
        var recursive = arguments.length <= 3 || arguments[3] === undefined ? false : arguments[3];
        var depth = arguments.length <= 4 || arguments[4] === undefined ? -1 : arguments[4];
        var optionalParameters = arguments.length <= 5 || arguments[5] === undefined ? null : arguments[5];

        var params = {
            get_action: 'ls',
            options: 'al'
        };
        if (recursive) {
            params['recursive'] = true;
            params['depth'] = depth;
        }
        var path = node.getPath();
        // Double encode # character
        var paginationHash;
        if (node.getMetadata().has("paginationData")) {
            paginationHash = "%23" + node.getMetadata().get("paginationData").get("current");
            path += paginationHash;
            params['remote_order'] = 'true';
            if (node.getMetadata().get("remote_order")) {
                node.getMetadata().get("remote_order").forEach(function (value, key) {
                    params[key] = value;
                });
            }
        }
        params['dir'] = path;
        if (this.properties) {
            this.properties.forEach(function (value, key) {
                params[key] = value + (key == 'dir' && paginationHash ? paginationHash : '');
            });
        }
        if (optionalParameters) {
            params = LangUtils.objectMerge(params, optionalParameters);
        }
        var parser = (function (transport) {
            this.parseNodes(node, transport, nodeCallback, childCallback);
            return node;
        }).bind(this);
        if (this.cacheService) {
            var loader = (function (ajxpNode, cacheCallback) {
                PydioApi.getClient().request(params, cacheCallback, null, { discrete: this.discrete });
            }).bind(this);
            var cacheLoader = (function (newNode) {
                node.replaceBy(newNode);
                nodeCallback(node);
            }).bind(this);
            MetaCacheService.getInstance().metaForNode(this.cacheService['metaStreamName'], node, loader, parser, cacheLoader);
        } else {
            PydioApi.getClient().request(params, parser, null, { discrete: this.discrete });
        }
    };

    /**
     * Load a node
     * @param node AjxpNode
     * @param nodeCallback Function On node loaded
     * @param aSync bool
     * @param additionalParameters object
     */

    RemoteNodeProvider.prototype.loadLeafNodeSync = function loadLeafNodeSync(node, nodeCallback) {
        var aSync = arguments.length <= 2 || arguments[2] === undefined ? false : arguments[2];
        var additionalParameters = arguments.length <= 3 || arguments[3] === undefined ? {} : arguments[3];

        var params = {
            get_action: 'ls',
            options: 'al',
            dir: PathUtils.getDirname(node.getPath()),
            file: PathUtils.getBasename(node.getPath())
        };
        for (var k in additionalParameters) {
            if (!additionalParameters.hasOwnProperty(k)) continue;
            params[k] = additionalParameters[k];
        }
        if (this.properties) {
            this.properties.forEach(function (value, key) {
                params[key] = value;
            });
        }
        var complete = (function (transport) {
            try {
                if (node.isRoot()) {
                    this.parseNodes(node, transport, nodeCallback, null, true);
                } else {
                    this.parseNodes(node, transport, null, nodeCallback, true);
                }
            } catch (e) {
                Logger.error('Loading error :' + e.message);
            }
        }).bind(this);
        PydioApi.getClient().request(params, complete, null, { async: aSync });
    };

    RemoteNodeProvider.prototype.refreshNodeAndReplace = function refreshNodeAndReplace(node, onComplete) {

        var params = {
            get_action: 'ls',
            options: 'al',
            dir: PathUtils.getDirname(node.getPath()),
            file: PathUtils.getBasename(node.getPath())
        };
        if (this.properties) {
            this.properties.forEach(function (value, key) {
                params[key] = value;
            });
        }

        var nodeCallback = function nodeCallback(newNode) {
            node.replaceBy(newNode, "override");
            if (onComplete) onComplete(node);
        };
        PydioApi.getClient().request(params, (function (transport) {
            try {
                if (node.isRoot()) {
                    this.parseNodes(node, transport, nodeCallback, null, true);
                } else {
                    this.parseNodes(node, transport, null, nodeCallback, true);
                }
            } catch (e) {
                Logger.error(e);
            }
        }).bind(this));
    };

    /**
     * Parse the answer and create AjxpNodes
     * @param origNode AjxpNode
     * @param transport Ajax.Response
     * @param nodeCallback Function
     * @param childCallback Function
     * @param childrenOnly
     */

    RemoteNodeProvider.prototype.parseNodes = function parseNodes(origNode, transport, nodeCallback, childCallback, childrenOnly) {
        if (!transport.responseXML || !transport.responseXML.documentElement) {
            Logger.debug('Loading node ' + origNode.getPath() + ' has wrong response: ' + transport.responseText);
            if (nodeCallback) nodeCallback(origNode);
            origNode.setLoaded(false);
            if (!transport.responseText) {
                throw new Error('Empty response!');
            }
            throw new Error('Invalid XML Document (see console)');
        }
        var rootNode = transport.responseXML.documentElement;
        if (!childrenOnly) {
            var contextNode = this.parseAjxpNode(rootNode);
            origNode.replaceBy(contextNode, "merge");
        }

        // CHECK FOR MESSAGE OR ERRORS
        var errorNode = XMLUtils.XPathSelectSingleNode(rootNode, "error|message");
        if (errorNode) {
            var type;
            if (errorNode.nodeName == "message") type = errorNode.getAttribute('type');
            if (type == "ERROR") {
                origNode.notify("error", errorNode.firstChild.nodeValue + '(Source:' + origNode.getPath() + ')');
            }
        }

        // CHECK FOR PAGINATION DATA
        var paginationNode = XMLUtils.XPathSelectSingleNode(rootNode, "pagination");
        if (paginationNode) {
            var paginationData = new Map();
            Array.from(paginationNode.attributes).forEach((function (att) {
                paginationData.set(att.nodeName, att.value);
            }).bind(this));
            origNode.getMetadata().set('paginationData', paginationData);
        } else if (origNode.getMetadata().get('paginationData')) {
            origNode.getMetadata()['delete']('paginationData');
        }

        // CHECK FOR COMPONENT CONFIGS CONTEXTUAL DATA
        var configs = XMLUtils.XPathSelectSingleNode(rootNode, "client_configs");
        if (configs) {
            origNode.getMetadata().set('client_configs', configs);
        }

        // NOW PARSE CHILDREN
        var children = XMLUtils.XPathSelectNodes(rootNode, "tree");
        children.forEach((function (childNode) {
            var child = this.parseAjxpNode(childNode);
            if (!childrenOnly) origNode.addChild(child);
            var cLoaded;
            if (XMLUtils.XPathSelectNodes(childNode, 'tree').length) {
                XMLUtils.XPathSelectNodes(childNode, 'tree').forEach((function (c) {
                    var newChild = this.parseAjxpNode(c);
                    if (newChild) {
                        child.addChild(newChild);
                    }
                }).bind(this));
                cLoaded = true;
            }
            if (childCallback) {
                childCallback(child);
            }
            if (cLoaded) child.setLoaded(true);
        }).bind(this));

        if (nodeCallback) {
            nodeCallback(origNode);
        }
    };

    RemoteNodeProvider.prototype.parseAjxpNodesDiffs = function parseAjxpNodesDiffs(xmlElement, targetDataModel) {
        var setContextChildrenSelected = arguments.length <= 2 || arguments[2] === undefined ? false : arguments[2];

        var removes = XMLUtils.XPathSelectNodes(xmlElement, "remove/tree");
        var adds = XMLUtils.XPathSelectNodes(xmlElement, "add/tree");
        var updates = XMLUtils.XPathSelectNodes(xmlElement, "update/tree");
        var notifyServerChange = [];
        if (removes && removes.length) {
            removes.forEach(function (r) {
                var p = r.getAttribute("filename");
                var imTime = parseInt(r.getAttribute("ajxp_im_time"));
                targetDataModel.removeNodeByPath(p, imTime);
                notifyServerChange.push(p);
            });
        }
        if (adds && adds.length && targetDataModel.getAjxpNodeProvider().parseAjxpNode) {
            adds.forEach(function (tree) {
                var newNode = targetDataModel.getAjxpNodeProvider().parseAjxpNode(tree);
                targetDataModel.addNode(newNode, setContextChildrenSelected);
                notifyServerChange.push(newNode.getPath());
            });
        }
        if (updates && updates.length && targetDataModel.getAjxpNodeProvider().parseAjxpNode) {
            updates.forEach(function (tree) {
                var newNode = targetDataModel.getAjxpNodeProvider().parseAjxpNode(tree);
                var original = newNode.getMetadata().get("original_path");
                targetDataModel.updateNode(newNode, setContextChildrenSelected);
                notifyServerChange.push(newNode.getPath());
                if (original) notifyServerChange.push(original);
            });
        }
        if (notifyServerChange.length) {
            targetDataModel.notify("server_update", notifyServerChange);
        }
    };

    /**
     * Parses XML Node and create AjxpNode
     * @param xmlNode XMLNode
     * @returns AjxpNode
     */

    RemoteNodeProvider.prototype.parseAjxpNode = function parseAjxpNode(xmlNode) {
        var node = new AjxpNode(xmlNode.getAttribute('filename'), xmlNode.getAttribute('is_file') == "1" || xmlNode.getAttribute('is_file') == "true", xmlNode.getAttribute('text'), xmlNode.getAttribute('icon'));
        var metadata = new Map();
        for (var i = 0; i < xmlNode.attributes.length; i++) {
            metadata.set(xmlNode.attributes[i].nodeName, xmlNode.attributes[i].value);
        }
        node.setMetadata(metadata);
        return node;
    };

    return RemoteNodeProvider;
})();
