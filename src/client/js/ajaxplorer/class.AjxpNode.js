/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Abstract container for data
 */
Class.create("AjxpNode", {
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
	isLoaded : function(){
		return this._isLoaded;
	},
	setLoaded : function(bool){
		this._isLoaded = bool;
	},
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
	reload : function(iAjxpNodeProvider){
		this._children.each(function(child){
			this.removeChild(child);
		}.bind(this));
		this._isLoaded = false;		
		this.load(iAjxpNodeProvider);
	},
	clear : function(){
		this._children.each(function(child){
			this.removeChild(child);
		}.bind(this));
		this._isLoaded = false;		
		this.notify("force_clear");
	},
	setRoot : function(){
		this._isRoot = true;
	},
	setChildren : function(ajxpNodes){
		this._children = $A(ajxpNodes);
		this._children.invoke('setParent', this);
	},
	getChildren : function(){
		return this._children;
	},
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
	removeChild : function(ajxpNode){
		var removePath = ajxpNode.getPath();
		ajxpNode.notify("node_removed");
		this._children = this._children.without(ajxpNode);
		this.notify("child_removed", removePath);
	},
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
	findChildByPath : function(path){
		return $A(this._children).find(function(child){
			return (child.getPath() == path);
		});
	},
	setMetadata : function(data){
		this._metadata = data;
	},
	getMetadata : function(data){
		return this._metadata;
	},
	isLeaf : function(){
		return this._isLeaf;
	},
	getPath : function(){
		return this._path;
	},
	getLabel : function(){
		return this._label;
	},
	getIcon : function(){
		return this._icon;
	},
	isRecycle : function(){
		return (this.getAjxpMime() == 'ajxp_recycle');
	},
	inZip : function(){
		
	},
	hasAjxpMimeInBranch: function(ajxpMime){
		if(this.getAjxpMime() == ajxpMime.toLowerCase()) return true;
		var parent, crt = this;
		while(parent =crt._parentNode){
			if(parent.getAjxpMime() == ajxpMime.toLowerCase()){return true;}
			crt = parent;
		}
		return false;
	},	
	setParent : function(parentNode){
		this._parentNode = parentNode;
	},
	getParent : function(){
		return this._parentNode;
	},
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
	isRoot : function(){
		return this._isRoot;
	},
	isParentOf : function(node){
		var childPath = node.getPath();
		var parentPath = this.getPath();
		return (childPath.substring(0,parentPath.length) == parentPath);
	},
	isChildOf : function(node){
		var childPath = this.getPath();
		var parentPath = node.getPath();
		return (childPath.substring(0,parentPath.length) == parentPath);
	},	
	getAjxpMime : function(){
		if(this._metadata && this._metadata.get("ajxp_mime")) return this._metadata.get("ajxp_mime").toLowerCase();
		if(this._metadata && this.isLeaf()) return getAjxpMimeType(this._metadata).toLowerCase();
		return "";
	}
});