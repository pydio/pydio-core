/**
TODO : I18N THIS STRING
 */
webFXTreeConfig.loadingText = "Loading...";

function AJXPTree(rootNode, sAction) {
	this.WebFXTree = WebFXTree;
	this.ajxpNode = rootNode;
	var icon = rootNode.getIcon();
	if(icon.indexOf(ajxpResourcesFolder+"/") != 0){
		icon = resolveImageSource(icon, "/images/crystal/mimes/ICON_SIZE", 16);
	}
	var openIcon = rootNode.getMetadata().get("openicon");
	if(openIcon){
		if(openIcon.indexOf(ajxpResourcesFolder+"/") != 0){
			openIcon = resolveImageSource(openIcon, "/images/crystal/mimes/ICON_SIZE", 16);
		}
	}else{
		openIcon = icon;
	}
	
	this.WebFXTree(rootNode.getLabel(), sAction, 'explorer', icon, openIcon);
	// setup default property values
	this.loading = false;
	this.loaded = false;
	this.errorText = "";

	this._loadingItem = new WebFXTreeItem(webFXTreeConfig.loadingText);		
	if(this.open) this.ajxpNode.load();
	else{
		this.add(this._loadingItem);
	}
};

AJXPTree.prototype = new WebFXTree;

AJXPTree.prototype._webfxtree_expand = WebFXTree.prototype.expand;
AJXPTree.prototype.expand = function() {
	if(!this.ajxpNode.fake){
		this.ajxpNode.load();
	}
	this._webfxtree_expand();
};

AJXPTree.prototype.setAjxpRootNode = function(rootNode){
	this.ajxpNode = rootNode;	
	this.ajxpNode.observe("force_clear", function(){
		this.open = false;
		while (this.childNodes.length > 0)
			this.childNodes[this.childNodes.length - 1].remove();
		this.loaded = false;
	}.bind(this) );
	this.attachListeners(this, rootNode);
	this.ajxpNode.load();
};

AJXPTree.prototype.attachListeners = function(jsNode, ajxpNode){
	ajxpNode.observe("child_added", function(childPath){
		if(ajxpNode.getMetadata().get('paginationData')){
			if(!this.paginated){
				this.paginated = true;
				this.updateLabel(this.text + " (" + MessageHash[ajxpNode.getMetadata().get('paginationData').get('overflowMessage')]+ ")");
			}
			return;
		}else if(this.paginated){
			this.paginated = false;
			this.updateLabel(this.text);
		}
		var child = ajxpNode.findChildByPath(childPath);
		if(child){
			var jsChild = _ajxpNodeToTree(child, this);
			if(jsChild){
				this.attachListeners(jsChild, child);
			}
		}
	}.bind(jsNode));
	ajxpNode.observe("node_replaced", function(newNode){
		//console.log(this, newNode);
		// Should refresh label / icon
	}.bind(jsNode));
	ajxpNode.observeOnce("node_removed", function(e){
		jsNode.remove();
	});
	ajxpNode.observe("loading", function(){		
		this.add(this._loadingItem);
	}.bind(jsNode) );
	ajxpNode.observe("loaded", function(){
		this._loadingItem.remove();
		this._webfxtree_expand();
	}.bind(jsNode) );
};

function AJXPTreeItem(ajxpNode, sAction, eParent) {
	this.WebFXTreeItem = WebFXTreeItem;
	this.ajxpNode = ajxpNode;
	var icon = ajxpNode.getIcon();
	if(icon.indexOf(ajxpResourcesFolder+"/") != 0){
		icon = resolveImageSource(icon, "/images/crystal/mimes/ICON_SIZE", 16);
	}
	var openIcon = ajxpNode.getMetadata().get("openicon");
	if(openIcon){
		if(openIcon.indexOf(ajxpResourcesFolder+"/") != 0){
			openIcon = resolveImageSource(openIcon, "/images/crystal/mimes/ICON_SIZE", 16);
		}
	}else{
		openIcon = icon;
	}
	
	this.folder = true;
	this.WebFXTreeItem(ajxpNode.getLabel(), sAction, eParent, icon, (openIcon?openIcon:resolveImageSource("folder_open.png", "/images/crystal/mimes/ICON_SIZE", 16)));

	this.loading = false;
	this.loaded = false;
	this.errorText = "";

	this._loadingItem = new WebFXTreeItem(webFXTreeConfig.loadingText);
	if (this.open) {
		this.ajxpNode.load();
	}else{
		this.add(this._loadingItem);
	}
	webFXTreeHandler.all[this.id] = this;
};

AJXPTreeItem.prototype = new WebFXTreeItem;

AJXPTreeItem.prototype._webfxtree_expand = WebFXTreeItem.prototype.expand;
AJXPTreeItem.prototype.expand = function() {
	this.ajxpNode.load();
	this._webfxtree_expand();
};

// reloads the src file if already loaded
/*
AJXPTree.prototype.reload =
AJXPTreeItem.prototype.reload = function () {
	// if loading do nothing
	if (this.loaded) {
		var open = this.open;
		// remove
		while (this.childNodes.length > 0)
			this.childNodes[this.childNodes.length - 1].remove();

		this.loaded = false;

		this._loadingItem = new WebFXTreeItem(webFXTreeConfig.loadingText);
		this.add(this._loadingItem);

		if (open)
			this.expand();
	}
	else if (this.open && !this.loading)	
		this.ajxpNode.load();
		
	if(!this.open && !this.loading) this.toggle();
};
*/
AJXPTreeItem.prototype.attachListeners = AJXPTree.prototype.attachListeners;


/*
 * Helper functions
 */
// Converts an xml tree to a js tree. See article about xml tree format
function _ajxpNodeToTree(ajxpNode, parentNode) {
	/*
	TODO : PAGINATION
	if(oNode.tagName == "pagination"){
		text = MessageHash[oNode.getAttribute("overflowMessage")] + ' ('+oNode.getAttribute("count")+')';
		action = function(e){};
	}
	TODO : AJXPNODES IN TREEHANDLER?
	webFXTreeHandler.ajxpNodes[getBaseName(folderFullName)] = jsNode.id;
	TODO : ajxpmime attribute?
	if(oNode.getAttribute('ajxp_mime')){
		jsNode.ajxpMime = oNode.getAttribute('ajxp_mime');
	}
	*/
	if(ajxpNode.isLeaf()){
		return false;
	}
	var jsNode = new AJXPTreeItem(ajxpNode, null, parentNode);	
	if(ajxpNode.isLoaded())
	{
		jsNode.loaded = true;
	}
	jsNode.filename = ajxpNode.getPath();	
	
	ajxpNode.getChildren().each(function(child){
		var newNode = _ajxpNodeToTree(child, jsNode);
		if(newNode){
			jsNode.add( newNode , true );
		}
	}.bind(this) );	
	return jsNode;	
};