import Observable from '../lang/Observable'

export default class AjxpNode extends Observable{

    /**
     *
     * @param path String
     * @param isLeaf Boolean
     * @param label String
     * @param icon String
     * @param iNodeProvider IAjxpNodeProvider
     */
        constructor(path, isLeaf=false, label='', icon='', iNodeProvider=null){
        super();
        this._path = path;
        if(this._path && this._path.length && this._path.length > 1){
            if(this._path[this._path.length-1] == "/"){
                this._path = this._path.substring(0, this._path.length-1);
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
     isLoaded(){
        return this._isLoaded;
    }
    /**
     * Changes loaded status
     * @param bool Boolean
     */
    setLoaded(bool){
        this._isLoaded = bool;
    }

    /**
     * Update node provider
     * @param iAjxpNodeProvider
     */
    updateProvider(iAjxpNodeProvider){
        this._iNodeProvider = iAjxpNodeProvider;
    }

    /**
     * Loads the node using its own provider or the one passed
     * @param iAjxpNodeProvider IAjxpNodeProvider Optionnal
     * @param additionalParameters Object of optional parameters
     */
    load(iAjxpNodeProvider, additionalParameters=null){
        if(this._isLoading) return;
        if(!iAjxpNodeProvider){
            if(this._iNodeProvider){
                iAjxpNodeProvider = this._iNodeProvider;
            }else{
                iAjxpNodeProvider = new RemoteNodeProvider();
            }
        }
        this._isLoading = true;
        this.notify("loading");
        if(this._isLoaded){
            this._isLoading = false;
            this.notify("loaded");
            return;
        }
        iAjxpNodeProvider.loadNode(this, function(node){
            this._isLoaded = true;
            this._isLoading = false;
            this.notify("loaded");
            this.notify("first_load");
        }.bind(this), null, false, -1, additionalParameters);
    }
    /**
     * Remove children and reload node
     * @param iAjxpNodeProvider IAjxpNodeProvider Optionnal
     */
    reload(iAjxpNodeProvider, silentClear = false){
        this._children.forEach(function(child,key){
            if(!silentClear) child.notify("node_removed");
            child._parentNode = null;
            this._children.delete(key);
            if(!silentClear) this.notify("child_removed", child);
        }, this);
        this._isLoaded = false;
        this.load(iAjxpNodeProvider);
    }
    /**
     * Unload child and notify "force_clear"
     */
    clear(){
        this._children.forEach(function(child,key){
            child.notify("node_removed");
            child._parentNode = null;
            this._children.delete(key);
            this.notify("child_removed", child);
        }, this);
        this._isLoaded = false;
        this.notify("force_clear");
    }
    /**
     * Sets this AjxpNode as being the root parent
     */
    setRoot(){
        this._isRoot = true;
    }
    /**
     * Set the node children as a bunch
     * @param ajxpNodes AjxpNodes[]
     */
    setChildren(ajxpNodes){
        this._children = new Map();
        ajxpNodes.forEach(function(value){
            this._children.set(value.getPath(), value);
            value.setParent(this);
        }.bind(this));
    }
    /**
     * Get all children as a bunch
     * @returns AjxpNode[]
     */
    getChildren(){
        return this._children;
    }

    getFirstChildIfExists(){
        if(this._children.size){
            return this._children.values().next().value;
        }
        return null;
    }

    isMoreRecentThan(otherNode){
        return otherNode.getMetadata().get("ajxp_im_time") && this.getMetadata().get("ajxp_im_time")
            && parseInt(this.getMetadata().get("ajxp_im_time")) >= parseInt(otherNode.getMetadata().get("ajxp_im_time"));
    }

    /**
     * Adds a child to children
     * @param ajxpNode AjxpNode The child
     */
    addChild(ajxpNode){
        ajxpNode.setParent(this);
        if(this._iNodeProvider) ajxpNode._iNodeProvider = this._iNodeProvider;
        const existingNode = this.findChildByPath(ajxpNode.getPath());
        if(existingNode && !(existingNode instanceof String)){
            if(!existingNode.isMoreRecentThan(ajxpNode)){
                existingNode.replaceBy(ajxpNode, "override");
                return existingNode;
            }else{
                return false;
            }
        }else{
            this._children.set(ajxpNode.getPath(), ajxpNode);
            this.notify("child_added", ajxpNode.getPath());
        }
        return ajxpNode;
    }
    /**
     * Removes the child from the children
     * @param ajxpNode AjxpNode
     */
    removeChild(ajxpNode){
        var removePath = ajxpNode.getPath();
        ajxpNode.notify("node_removed");
        ajxpNode._parentNode = null;
        this._children.delete(ajxpNode.getPath());
        this.notify("child_removed", removePath);
    }

    replaceMetadata(newMeta){
        this._metadata = newMeta;
        this.notify("meta_replaced", this);
    }

    /**
     * Replaces the current node by a new one. Copy all properties deeply
     * @param ajxpNode AjxpNode
     * @param metaMerge
     */
    replaceBy(ajxpNode, metaMerge){
        this._isLeaf = ajxpNode._isLeaf;
        if(ajxpNode.getPath() && this._path != ajxpNode.getPath()){
            var originalPath = this._path;
            if(this.getParent()){
                var parentChildrenIndex = this.getParent()._children;
                parentChildrenIndex.set(ajxpNode.getPath(), this);
                parentChildrenIndex.delete(originalPath);
            }
            this._path = ajxpNode.getPath();
            var pathChanged = true;
        }
        if(ajxpNode._label){
            this._label = ajxpNode._label;
        }
        if(ajxpNode._icon){
            this._icon = ajxpNode._icon;
        }
        if(ajxpNode._iNodeProvider){
            this._iNodeProvider = ajxpNode._iNodeProvider;
        }
        //this._isRoot = ajxpNode._isRoot;
        this._isLoaded = ajxpNode._isLoaded;
        this.fake = ajxpNode.fake;
        var meta = ajxpNode.getMetadata();
        if(metaMerge == "override") this._metadata = new Map();
        meta.forEach(function(value, key){
            if(metaMerge == "override"){
                this._metadata.set(key, value);
            }else{
                if(this._metadata.has(key) && value === ""){
                    return;
                }
                this._metadata.set(key, value);
            }
        }.bind(this) );
        if(pathChanged && !this._isLeaf && this.getChildren().size){
            window.setTimeout(function(){
                this.reload(this._iNodeProvider);
            }.bind(this), 100);
            return;
        }
        ajxpNode.getChildren().forEach(function(child){
            this.addChild(child);
        }.bind(this) );
        this.notify("node_replaced", this);
    }
    /**
     * Finds a child node by its path
     * @param path String
     * @returns AjxpNode
     */
    findChildByPath(path){
        return this._children.get(path);
    }
    /**
     * Sets the metadata as a bunch
     * @param data Map A Map
     */
    setMetadata(data){
        this._metadata = data;
    }
    /**
     * Gets the metadat
     * @returns Map
     */
    getMetadata(){
        return this._metadata;
    }
    /**
     * Is this node a leaf
     * @returns Boolean
     */
    isLeaf(){
        return this._isLeaf;
    }
    /**
     * @returns String
     */
    getPath(){
        return this._path;
    }
    /**
     * @returns String
     */
    getLabel(){
        return this._label;
    }
    /**
     * @returns String
     */
    getIcon(){
        return this._icon;
    }
    /**
     * @returns Boolean
     */
    isRecycle(){
        return (this.getAjxpMime() == 'ajxp_recycle');
    }
    /**
     * @returns String
     */
    getSvgSource() {
        return this.getMetadata().get("fonticon");
    }

    /**
     * Search the mime type in the parent branch
     * @param ajxpMime String
     * @returns Boolean
     */
    hasAjxpMimeInBranch(ajxpMime){
        if(this.getAjxpMime() == ajxpMime.toLowerCase()) return true;
        var parent, crt = this;
        while(parent =crt._parentNode){
            if(parent.getAjxpMime() == ajxpMime.toLowerCase()){return true;}
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
    hasMetadataInBranch(metadataKey, metadataValue){
        if(this.getMetadata().has(metadataKey)) {
            if(metadataValue) {
                return this.getMetadata().get(metadataKey) == metadataValue;
            }else {
                return true;
            }
        }
        var parent, crt = this;
        while(parent =crt._parentNode){
            if(parent.getMetadata().has(metadataKey)){
                if(metadataValue){
                    return (parent.getMetadata().get(metadataKey) == metadataValue);
                }else{
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
    setParent(parentNode){
        this._parentNode = parentNode;
    }
    /**
     * Gets the parent Node
     * @returns AjxpNode
     */
    getParent(){
        return this._parentNode;
    }
    /**
     * Finds this node by path if it already exists in arborescence
     * @param rootNode AjxpNode
     * @param fakeNodes AjxpNode[]
     * @returns AjxpNode|undefined
     */
    findInArbo(rootNode, fakeNodes){
        if(!this.getPath()) return;
        var pathParts = this.getPath().split("/");
        var crtPath = "";
        var crtNode, crtParentNode = rootNode;
        for(var i=0;i<pathParts.length;i++){
            if(pathParts[i] == "") continue;
            crtPath = crtPath + "/" + pathParts[i];
            var node = crtParentNode.findChildByPath(crtPath);
            if(node && !(node instanceof String)){
                crtNode = node;
            }else{
                if(fakeNodes === undefined) return undefined;
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
    isRoot(){
        return this._isRoot;
    }
    /**
     * Check if it's the parent of the given node
     * @param node AjxpNode
     * @returns Boolean
     */
    isParentOf(node){
        var childPath = node.getPath();
        var parentPath = this.getPath();
        return (childPath.substring(0,parentPath.length) == parentPath);
    }
    /**
     * Check if it's a child of the given node
     * @param node AjxpNode
     * @returns Boolean
     */
    isChildOf(node){
        var childPath = this.getPath();
        var parentPath = node.getPath();
        return (childPath.substring(0,parentPath.length) == parentPath);
    }
    /**
     * Gets the current's node mime type, either by ajxp_mime or by extension.
     * @returns String
     */
    getAjxpMime(){
        if(this._metadata && this._metadata.has("ajxp_mime")) return this._metadata.get("ajxp_mime").toLowerCase();
        if(this._metadata && this.isLeaf()) return PathUtils.getAjxpMimeType(this._metadata).toLowerCase();
        return "";
    }

}
