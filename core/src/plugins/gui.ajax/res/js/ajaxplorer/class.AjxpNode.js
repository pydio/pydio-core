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

/**
 * Abstract container for data
 */
Class.create("AjxpNode", {
	/**
	 * Constructor
	 * @param path String
	 * @param isLeaf Boolean
	 * @param label String
	 * @param icon String
	 * @param iNodeProvider IAjxpNodeProvider
	 */
	initialize : function(path, isLeaf, label, icon, iNodeProvider){
		this._path = path;
		if(this._path && this._path.length && this._path.length > 1){
			if(this._path[this._path.length-1] == "/"){
				this._path = this._path.substring(0, this._path.length-1);
			}
		}
		this._metadata = $H();
		this._isLeaf = isLeaf || false;
		this._label = label || '';
		this._icon = icon || '';
		this._children = $A([]);
		this._isRoot = false;
		
		this._isLoaded = false;
		this.fake = false;
		this._iNodeProvider = iNodeProvider;
		
	},
	/**
	 * The node is loaded or not
	 * @returns Boolean
	 */
	isLoaded : function(){
		return this._isLoaded;
	},
	/**
	 * Changes loaded status
	 * @param bool Boolean
	 */
	setLoaded : function(bool){
		this._isLoaded = bool;
	},
	/**
	 * Loads the node using its own provider or the one passed
	 * @param iAjxpNodeProvider IAjxpNodeProvider Optionnal
	 */
	load : function(iAjxpNodeProvider){		
		if(this.isLoading) return;		
		if(!iAjxpNodeProvider){
			if(this._iNodeProvider){
				iAjxpNodeProvider = this._iNodeProvider;
			}else{
				iAjxpNodeProvider = new RemoteNodeProvider();
			}
		}
		this.isLoading = true;
		this.notify("loading");
		if(this._isLoaded){
			this.isLoading = false;
			this.notify("loaded");
			return;
		}
		iAjxpNodeProvider.loadNode(this, function(node){
			this._isLoaded = true;
			this.isLoading = false;
			this.notify("loaded");
			this.notify("first_load");
		}.bind(this));		
	},
	/**
	 * Remove children and reload node
	 * @param iAjxpNodeProvider IAjxpNodeProvider Optionnal
	 */
	reload : function(iAjxpNodeProvider){
		this._children.each(function(child){
			this.removeChild(child);
		}.bind(this));
		this._isLoaded = false;		
		this.load(iAjxpNodeProvider);
	},
	/**
	 * Unload child and notify "force_clear"
	 */
	clear : function(){
		this._children.each(function(child){
			this.removeChild(child);
		}.bind(this));
		this._isLoaded = false;		
		this.notify("force_clear");
	},
	/**
	 * Sets this AjxpNode as being the root parent
	 */
	setRoot : function(){
		this._isRoot = true;
	},
	/**
	 * Set the node children as a bunch
	 * @param ajxpNodes AjxpNodes[]
	 */
	setChildren : function(ajxpNodes){
		this._children = $A(ajxpNodes);
		this._children.invoke('setParent', this);
	},
	/**
	 * Get all children as a bunch
	 * @returns AjxpNode[]
	 */
	getChildren : function(){
		return this._children;
	},
	/**
	 * Adds a child to children
	 * @param ajxpNode AjxpNode The child
	 */
	addChild : function(ajxpNode){
		ajxpNode.setParent(this);
		if(this._iNodeProvider) ajxpNode._iNodeProvider = this._iNodeProvider;
		if(existingNode = this.findChildByPath(ajxpNode.getPath())){
			existingNode.replaceBy(ajxpNode);
		}else{			
			this._children.push(ajxpNode);
			this.notify("child_added", ajxpNode.getPath());
		}
	},
	/**
	 * Removes the child from the children
	 * @param ajxpNode AjxpNode
	 */
	removeChild : function(ajxpNode){
		var removePath = ajxpNode.getPath();
		ajxpNode.notify("node_removed");
		this._children = this._children.without(ajxpNode);
		this.notify("child_removed", removePath);
	},
	/**
	 * Replaces the current node by a new one. Copy all properties deeply
	 * @param ajxpNode AjxpNode
	 */
	replaceBy : function(ajxpNode){
		this._isLeaf = ajxpNode._isLeaf;
		if(ajxpNode._label){
			this._label = ajxpNode._label;
		}
		if(ajxpNode._icon){
			this._icon = ajxpNode._icon;
		}
		if(ajxpNode._iNodeProvider){
			this._iNodeProvider = ajxpNode._iNodeProvider;
		}
		this._isRoot = ajxpNode._isRoot;
		this._isLoaded = ajxpNode._isLoaded;
		this.fake = ajxpNode.fake;
		ajxpNode.getChildren().each(function(child){
			this.addChild(child);
		}.bind(this) );		
		var meta = ajxpNode.getMetadata();		
		meta.each(function(pair){
			if(this._metadata.get(pair.key) && pair.value === ""){
				return;
			}
			this._metadata.set(pair.key, pair.value);
		}.bind(this) );
		this.notify("node_replaced", this);		
	},
	/**
	 * Finds a child node by its path
	 * @param path String
	 * @returns AjxpNode
	 */
	findChildByPath : function(path){
		return $A(this._children).find(function(child){
			return (child.getPath() == path);
		});
	},
	/**
	 * Sets the metadata as a bunch
	 * @param data $H() A prototype Hash
	 */
	setMetadata : function(data){
		this._metadata = data;
	},
	/**
	 * Gets the metadat
	 * @returns $H()
	 */
	getMetadata : function(data){
		return this._metadata;
	},
	/**
	 * Is this node a leaf
	 * @returns Boolean
	 */
	isLeaf : function(){
		return this._isLeaf;
	},
	/**
	 * @returns String
	 */
	getPath : function(){
		return this._path;
	},
	/**
	 * @returns String
	 */
	getLabel : function(){
		return this._label;
	},
	/**
	 * @returns String
	 */
	getIcon : function(){
		return this._icon;
	},
	/**
	 * @returns Boolean
	 */
	isRecycle : function(){
		return (this.getAjxpMime() == 'ajxp_recycle');
	},
	/**
	 * NOT IMPLEMENTED, USE hasAjxpMimeInBranch instead
	 */	
	inZip : function(){
		
	},
	/**
	 * Search the mime type in the parent branch
	 * @param ajxpMime String
	 * @returns Boolean
	 */
	hasAjxpMimeInBranch: function(ajxpMime){
		if(this.getAjxpMime() == ajxpMime.toLowerCase()) return true;
		var parent, crt = this;
		while(parent =crt._parentNode){
			if(parent.getAjxpMime() == ajxpMime.toLowerCase()){return true;}
			crt = parent;
		}
		return false;
	},	
	/**
	 * Sets a reference to the parent node
	 * @param parentNode AjxpNode
	 */
	setParent : function(parentNode){
		this._parentNode = parentNode;
	},
	/**
	 * Gets the parent Node
	 * @returns AjxpNode
	 */
	getParent : function(){
		return this._parentNode;
	},
	/**
	 * Finds this node by path if it already exists in arborescence 
	 * @param rootNode AjxpNode
	 * @param fakeNodes AjxpNode[]
	 */
	findInArbo : function(rootNode, fakeNodes){
		if(!this.getPath()) return;
		var pathParts = this.getPath().split("/");
		var parentNodes = $A();
		var crtPath = "";
		var crtNode, crtParentNode = rootNode;
		for(var i=0;i<pathParts.length;i++){
			if(pathParts[i] == "") continue;
			crtPath = crtPath + "/" + pathParts[i];
			if(node = crtParentNode.findChildByPath(crtPath)){
				crtNode = node;
			}else{
				crtNode = new AjxpNode(crtPath, false, getBaseName(crtPath));
				crtNode.fake = true;				
				fakeNodes.push(crtNode);
				crtParentNode.addChild(crtNode);
			}
			crtParentNode = crtNode;
		}
		return crtNode;
	},
	/**
	 * @returns Boolean
	 */
	isRoot : function(){
		return this._isRoot;
	},
	/**
	 * Check if it's the parent of the given node
	 * @param node AjxpNode
	 * @returns Boolean
	 */
	isParentOf : function(node){
		var childPath = node.getPath();
		var parentPath = this.getPath();
		return (childPath.substring(0,parentPath.length) == parentPath);
	},
	/**
	 * Check if it's a child of the given node
	 * @param node AjxpNode
	 * @returns Boolean
	 */
	isChildOf : function(node){
		var childPath = this.getPath();
		var parentPath = node.getPath();
		return (childPath.substring(0,parentPath.length) == parentPath);
	},	
	/**
	 * Gets the current's node mime type, either by ajxp_mime or by extension.
	 * @returns String
	 */
	getAjxpMime : function(){
		if(this._metadata && this._metadata.get("ajxp_mime")) return this._metadata.get("ajxp_mime").toLowerCase();
		if(this._metadata && this.isLeaf()) return getAjxpMimeType(this._metadata).toLowerCase();
		return "";
	}
});