'use strict';

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ('value' in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _get = function get(_x5, _x6, _x7) { var _again = true; _function: while (_again) { var object = _x5, property = _x6, receiver = _x7; desc = parent = getter = undefined; _again = false; if (object === null) object = Function.prototype; var desc = Object.getOwnPropertyDescriptor(object, property); if (desc === undefined) { var parent = Object.getPrototypeOf(object); if (parent === null) { return undefined; } else { _x5 = parent; _x6 = property; _x7 = receiver; _again = true; continue _function; } } else if ('value' in desc) { return desc.value; } else { var getter = desc.get; if (getter === undefined) { return undefined; } return getter.call(receiver); } } };

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

function _inherits(subClass, superClass) { if (typeof superClass !== 'function' && superClass !== null) { throw new TypeError('Super expression must either be null or a function, not ' + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var AjxpNode = (function (_Observable) {
    _inherits(AjxpNode, _Observable);

    /**
     *
     * @param path String
     * @param isLeaf Boolean
     * @param label String
     * @param icon String
     * @param iNodeProvider IAjxpNodeProvider
     */

    function AjxpNode(path) {
        var isLeaf = arguments.length <= 1 || arguments[1] === undefined ? false : arguments[1];
        var label = arguments.length <= 2 || arguments[2] === undefined ? '' : arguments[2];
        var icon = arguments.length <= 3 || arguments[3] === undefined ? '' : arguments[3];
        var iNodeProvider = arguments.length <= 4 || arguments[4] === undefined ? null : arguments[4];

        _classCallCheck(this, AjxpNode);

        _get(Object.getPrototypeOf(AjxpNode.prototype), 'constructor', this).call(this);
        this._path = path;
        if (this._path && this._path.length && this._path.length > 1) {
            if (this._path[this._path.length - 1] == "/") {
                this._path = this._path.substring(0, this._path.length - 1);
            }
        }
        this._isLeaf = isLeaf;
        this._label = label;
        this._icon = icon;
        this._isRoot = false;

        this._metadata = new Map();
        this._children = new Map();

        this._isLoaded = false;
        this.fake = false;
        this._iNodeProvider = iNodeProvider;
    }

    /**
     * The node is loaded or not
     * @returns Boolean
     */

    _createClass(AjxpNode, [{
        key: 'isLoaded',
        value: function isLoaded() {
            return this._isLoaded;
        }

        /**
         * Changes loaded status
         * @param bool Boolean
         */
    }, {
        key: 'setLoaded',
        value: function setLoaded(bool) {
            this._isLoaded = bool;
        }

        /**
         * Loads the node using its own provider or the one passed
         * @param iAjxpNodeProvider IAjxpNodeProvider Optionnal
         */
    }, {
        key: 'load',
        value: function load(iAjxpNodeProvider) {
            if (this._isLoading) return;
            if (!iAjxpNodeProvider) {
                if (this._iNodeProvider) {
                    iAjxpNodeProvider = this._iNodeProvider;
                } else {
                    iAjxpNodeProvider = new RemoteNodeProvider();
                }
            }
            this._isLoading = true;
            this.notify("loading");
            if (this._isLoaded) {
                this._isLoading = false;
                this.notify("loaded");
                return;
            }
            iAjxpNodeProvider.loadNode(this, (function (node) {
                this._isLoaded = true;
                this._isLoading = false;
                this.notify("loaded");
                this.notify("first_load");
            }).bind(this));
        }

        /**
         * Remove children and reload node
         * @param iAjxpNodeProvider IAjxpNodeProvider Optionnal
         */
    }, {
        key: 'reload',
        value: function reload(iAjxpNodeProvider) {
            this._children.forEach(function (child, key) {
                child.notify("node_removed");
                child._parentNode = null;
                this._children['delete'](key);
                this.notify("child_removed", child);
            }, this);
            this._isLoaded = false;
            this.load(iAjxpNodeProvider);
        }

        /**
         * Unload child and notify "force_clear"
         */
    }, {
        key: 'clear',
        value: function clear() {
            this._children.forEach(function (child, key) {
                child.notify("node_removed");
                child._parentNode = null;
                this._children['delete'](key);
                this.notify("child_removed", child);
            }, this);
            this._isLoaded = false;
            this.notify("force_clear");
        }

        /**
         * Sets this AjxpNode as being the root parent
         */
    }, {
        key: 'setRoot',
        value: function setRoot() {
            this._isRoot = true;
        }

        /**
         * Set the node children as a bunch
         * @param ajxpNodes AjxpNodes[]
         */
    }, {
        key: 'setChildren',
        value: function setChildren(ajxpNodes) {
            this._children = new Map();
            ajxpNodes.forEach((function (value) {
                this._children.set(value.getPath(), value);
                value.setParent(this);
            }).bind(this));
        }

        /**
         * Get all children as a bunch
         * @returns AjxpNode[]
         */
    }, {
        key: 'getChildren',
        value: function getChildren() {
            return this._children;
        }
    }, {
        key: 'getFirstChildIfExists',
        value: function getFirstChildIfExists() {
            if (this._children.size) {
                return this._children.values().next().value;
            }
            return null;
        }

        /**
         * Adds a child to children
         * @param ajxpNode AjxpNode The child
         */
    }, {
        key: 'addChild',
        value: function addChild(ajxpNode) {
            ajxpNode.setParent(this);
            if (this._iNodeProvider) ajxpNode._iNodeProvider = this._iNodeProvider;
            var existingNode = this.findChildByPath(ajxpNode.getPath());
            if (existingNode && !(existingNode instanceof String)) {
                existingNode.replaceBy(ajxpNode, "override");
            } else {
                this._children.set(ajxpNode.getPath(), ajxpNode);
                this.notify("child_added", ajxpNode.getPath());
            }
        }

        /**
         * Removes the child from the children
         * @param ajxpNode AjxpNode
         */
    }, {
        key: 'removeChild',
        value: function removeChild(ajxpNode) {
            var removePath = ajxpNode.getPath();
            ajxpNode.notify("node_removed");
            ajxpNode._parentNode = null;
            this._children['delete'](ajxpNode.getPath());
            this.notify("child_removed", removePath);
        }

        /**
         * Replaces the current node by a new one. Copy all properties deeply
         * @param ajxpNode AjxpNode
         * @param metaMerge
         */
    }, {
        key: 'replaceBy',
        value: function replaceBy(ajxpNode, metaMerge) {
            this._isLeaf = ajxpNode._isLeaf;
            if (ajxpNode.getPath() && this._path != ajxpNode.getPath()) {
                var originalPath = this._path;
                if (this.getParent()) {
                    var parentChildrenIndex = this.getParent()._children;
                    parentChildrenIndex.set(ajxpNode.getPath(), this);
                    parentChildrenIndex['delete'](originalPath);
                }
                this._path = ajxpNode.getPath();
                var pathChanged = true;
            }
            if (ajxpNode._label) {
                this._label = ajxpNode._label;
            }
            if (ajxpNode._icon) {
                this._icon = ajxpNode._icon;
            }
            if (ajxpNode._iNodeProvider) {
                this._iNodeProvider = ajxpNode._iNodeProvider;
            }
            //this._isRoot = ajxpNode._isRoot;
            this._isLoaded = ajxpNode._isLoaded;
            this.fake = ajxpNode.fake;
            var meta = ajxpNode.getMetadata();
            if (metaMerge == "override") this._metadata = new Map();
            meta.forEach((function (value, key) {
                if (metaMerge == "override") {
                    this._metadata.set(key, value);
                } else {
                    if (this._metadata.has(key) && value === "") {
                        return;
                    }
                    this._metadata.set(key, value);
                }
            }).bind(this));
            if (pathChanged && !this._isLeaf && this.getChildren().size) {
                window.setTimeout((function () {
                    this.reload(this._iNodeProvider);
                }).bind(this), 100);
                return;
            }
            ajxpNode.getChildren().forEach((function (child) {
                this.addChild(child);
            }).bind(this));
            this.notify("node_replaced", this);
        }

        /**
         * Finds a child node by its path
         * @param path String
         * @returns AjxpNode
         */
    }, {
        key: 'findChildByPath',
        value: function findChildByPath(path) {
            return this._children.get(path);
        }

        /**
         * Sets the metadata as a bunch
         * @param data Map A Map
         */
    }, {
        key: 'setMetadata',
        value: function setMetadata(data) {
            this._metadata = data;
        }

        /**
         * Gets the metadat
         * @returns Map
         */
    }, {
        key: 'getMetadata',
        value: function getMetadata() {
            return this._metadata;
        }

        /**
         * Is this node a leaf
         * @returns Boolean
         */
    }, {
        key: 'isLeaf',
        value: function isLeaf() {
            return this._isLeaf;
        }

        /**
         * @returns String
         */
    }, {
        key: 'getPath',
        value: function getPath() {
            return this._path;
        }

        /**
         * @returns String
         */
    }, {
        key: 'getLabel',
        value: function getLabel() {
            return this._label;
        }

        /**
         * @returns String
         */
    }, {
        key: 'getIcon',
        value: function getIcon() {
            return this._icon;
        }

        /**
         * @returns Boolean
         */
    }, {
        key: 'isRecycle',
        value: function isRecycle() {
            return this.getAjxpMime() == 'ajxp_recycle';
        }

        /**
         * Search the mime type in the parent branch
         * @param ajxpMime String
         * @returns Boolean
         */
    }, {
        key: 'hasAjxpMimeInBranch',
        value: function hasAjxpMimeInBranch(ajxpMime) {
            if (this.getAjxpMime() == ajxpMime.toLowerCase()) return true;
            var parent,
                crt = this;
            while (parent = crt._parentNode) {
                if (parent.getAjxpMime() == ajxpMime.toLowerCase()) {
                    return true;
                }
                crt = parent;
            }
            return false;
        }

        /**
         * Search the mime type in the parent branch
         * @returns Boolean
         * @param metadataKey
         * @param metadataValue
         */
    }, {
        key: 'hasMetadataInBranch',
        value: function hasMetadataInBranch(metadataKey, metadataValue) {
            if (this.getMetadata().has(metadataKey)) {
                if (metadataValue) {
                    return this.getMetadata().get(metadataKey) == metadataValue;
                } else {
                    return true;
                }
            }
            var parent,
                crt = this;
            while (parent = crt._parentNode) {
                if (parent.getMetadata().has(metadataKey)) {
                    if (metadataValue) {
                        return parent.getMetadata().get(metadataKey) == metadataValue;
                    } else {
                        return true;
                    }
                }
                crt = parent;
            }
            return false;
        }

        /**
         * Sets a reference to the parent node
         * @param parentNode AjxpNode
         */
    }, {
        key: 'setParent',
        value: function setParent(parentNode) {
            this._parentNode = parentNode;
        }

        /**
         * Gets the parent Node
         * @returns AjxpNode
         */
    }, {
        key: 'getParent',
        value: function getParent() {
            return this._parentNode;
        }

        /**
         * Finds this node by path if it already exists in arborescence
         * @param rootNode AjxpNode
         * @param fakeNodes AjxpNode[]
         * @returns AjxpNode|undefined
         */
    }, {
        key: 'findInArbo',
        value: function findInArbo(rootNode, fakeNodes) {
            if (!this.getPath()) return;
            var pathParts = this.getPath().split("/");
            var crtPath = "";
            var crtNode,
                crtParentNode = rootNode;
            for (var i = 0; i < pathParts.length; i++) {
                if (pathParts[i] == "") continue;
                crtPath = crtPath + "/" + pathParts[i];
                var node = crtParentNode.findChildByPath(crtPath);
                if (node && !(node instanceof String)) {
                    crtNode = node;
                } else {
                    if (fakeNodes === undefined) return undefined;
                    crtNode = new AjxpNode(crtPath, false, PathUtils.getBasename(crtPath));
                    crtNode.fake = true;
                    crtNode.getMetadata().set("text", PathUtils.getBasename(crtPath));
                    fakeNodes.push(crtNode);
                    crtParentNode.addChild(crtNode);
                }
                crtParentNode = crtNode;
            }
            return crtNode;
        }

        /**
         * @returns Boolean
         */
    }, {
        key: 'isRoot',
        value: function isRoot() {
            return this._isRoot;
        }

        /**
         * Check if it's the parent of the given node
         * @param node AjxpNode
         * @returns Boolean
         */
    }, {
        key: 'isParentOf',
        value: function isParentOf(node) {
            var childPath = node.getPath();
            var parentPath = this.getPath();
            return childPath.substring(0, parentPath.length) == parentPath;
        }

        /**
         * Check if it's a child of the given node
         * @param node AjxpNode
         * @returns Boolean
         */
    }, {
        key: 'isChildOf',
        value: function isChildOf(node) {
            var childPath = this.getPath();
            var parentPath = node.getPath();
            return childPath.substring(0, parentPath.length) == parentPath;
        }

        /**
         * Gets the current's node mime type, either by ajxp_mime or by extension.
         * @returns String
         */
    }, {
        key: 'getAjxpMime',
        value: function getAjxpMime() {
            if (this._metadata && this._metadata.has("ajxp_mime")) return this._metadata.get("ajxp_mime").toLowerCase();
            if (this._metadata && this.isLeaf()) return PathUtils.getAjxpMimeType(this._metadata).toLowerCase();
            return "";
        }
    }]);

    return AjxpNode;
})(Observable);
