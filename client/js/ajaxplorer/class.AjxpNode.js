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
	initialize : function(path, isLeaf, label, icon){
		this._path = path;
		if(this._path && this._path.length){
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
		
	},
	isLoaded : function(){
		return this._isLoaded;
	},
	setLoaded : function(bool){
		this._isLoaded = bool;
	},
	load : function(iAjxpNodeProvider){		
		if(!iAjxpNodeProvider){
			iAjxpNodeProvider = new RemoteNodeProvider();
		}
		if(this._isLoaded){
			this.notify("loaded");
			return;
		}
		iAjxpNodeProvider.loadNode(this, function(node){
			this._isLoaded = true;			
			this.notify("loaded");
		}.bind(this));
	},
	reload : function(iAjxpNodeProvider){
		this._children.each(function(child){
			this.removeChild(child);
		}.bind(this));
		this._isLoaded = false;
		this.load(iAjxpNodeProvider);
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
		if(existingNode = this.findChildByPath(ajxpNode.getPath())){
			existingNode.replaceBy(ajxpNode);
		}else{
			this._children.push(ajxpNode);
			this.notify("node_added", ajxpNode.getPath());
		}
	},
	removeChild : function(ajxpNode){
		var removePath = ajxpNode.getPath();
		for(i=0;i<this._children.length;i++){
			if(ajxpNode == this._children[i]){
				this._children.splice(i, 1);
			}
		}
		this.notify("node_removed", removePath);
	},
	replaceBy : function(ajxpNode){
		this._isLeaf = ajxpNode._isLeaf;
		this._label = ajxpNode._label;
		this._icon = ajxpNode._icon;
		this._isRoot = ajxpNode._isRoot;
		this._isLoaded = ajxpNode._isLoaded;
		this.fake = ajxpNode.fake;
		this.setChildren(ajxpNode.getChildren());
		var meta = ajxpNode.getMetadata();		
		meta.each(function(pair){
			this._metadata.set(pair.key, pair.value);
		}.bind(this) );
		this.notify("node_replaced", this.getPath());		
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
		return (this._metadata && this._metadata.getAttribute("is_recycle") && this._metadata.getAttribute("is_recycle") == "true");
	},
	inZip : function(){
		
	},
	hasAjxpMimeInBranch: function(ajxpMime){
		if(this.getAjxpMime() == ajxpMime) return true;
		var parent, crt = this;
		while(parent =crt._parentNode){
			if(parent.getAjxpMime() == ajxpMime){return true;}
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
	getAjxpMime : function(){
		if(this._metadata && this._metadata.get("ajxp_mime")) return this._metadata.get("ajxp_mime");		
		if(this._metadata && this.isLeaf()) return getAjxpMimeType(this._metadata);
		return "";
	}
});

Object.Event.extend(AjxpNode);