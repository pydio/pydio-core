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
 * Description : Selection Model.
 */
Class.create("AjxpDataModel", {

	_currentRep: undefined, 
	_bEmpty: undefined,
	_bUnique: false,
	_bFile: false,
	_bDir: false,
	_isRecycle: false,
	
	_pendingContextPath:null, 
	_pendingSelection:null,
	_selectionSource : {}, // fake object
	
	_rootNode : null,


	initialize: function(){
		this._currentRep = '/';
		this._selectedNodes = $A([]);
		this._bEmpty = true;
	},
	
	setAjxpNodeProvider : function(iAjxpNodeProvider){
		this._iAjxpNodeProvider = iAjxpNodeProvider;
	},
	
	requireContextChange : function(ajxpNode, forceReload){
		var path = ajxpNode.getPath();
		if((path == "" || path == "/") && ajxpNode != this._rootNode){
			ajxpNode = this._rootNode;
		}
		if(ajxpNode.getMetadata().get('paginationData') && ajxpNode.getMetadata().get('paginationData').get('new_page') 
			&& ajxpNode.getMetadata().get('paginationData').get('new_page') != ajxpNode.getMetadata().get('paginationData').get('current')){
				var paginationPage = ajxpNode.getMetadata().get('paginationData').get('new_page');
				forceReload = true;			
		}
		if(ajxpNode != this._rootNode && (!ajxpNode.getParent() || ajxpNode.fake)){
			// Find in arbo or build fake arbo
			var fakeNodes = [];
			ajxpNode = ajxpNode.findInArbo(this._rootNode, fakeNodes);
			if(fakeNodes.length){
				var firstFake = fakeNodes.shift();
				firstFake.observeOnce("first_load", function(e){					
					this.requireContextChange(ajxpNode);
				}.bind(this));
				firstFake.observeOnce("error", function(message){
					ajaxplorer.displayMessage("ERROR", message);
					firstFake.notify("node_removed");
					var parent = firstFake.getParent();
					parent.removeChild(firstFake);
					delete(firstFake);
					this.requireContextChange(parent);
				}.bind(this) );
				document.fire("ajaxplorer:context_loading");
				firstFake.load(this._iAjxpNodeProvider);
				return;
			}
		}		
		ajxpNode.observeOnce("loaded", function(){
			this.setContextNode(ajxpNode);
			document.fire("ajaxplorer:context_loaded");
		}.bind(this));
		ajxpNode.observeOnce("error", function(message){
			ajaxplorer.displayMessage("ERROR", message);
			document.fire("ajaxplorer:context_loaded");
		}.bind(this));
		document.fire("ajaxplorer:context_loading");
		try{
			if(forceReload){
				if(paginationPage){
					ajxpNode.getMetadata().get('paginationData').set('current', paginationPage);
				}
				ajxpNode.reload(this._iAjxpNodeProvider);
			}else{
				ajxpNode.load(this._iAjxpNodeProvider);
			}
		}catch(e){
			document.fire("ajaxplorer:context_loaded");
		}
	},
	
	setRootNode : function(ajxpRootNode){
		this._rootNode = ajxpRootNode;
		this._rootNode.setRoot();
		this._rootNode.observe("child_added", function(c){
				//console.log(c);
		});
		document.fire("ajaxplorer:root_node_changed", this._rootNode);
		this.setContextNode(this._rootNode);
	},
	
	getRootNode : function(ajxpRootNode){
		return this._rootNode;
	},
	
	setContextNode : function(ajxpDataNode){
		this._contextNode = ajxpDataNode;
		this._currentRep = ajxpDataNode.getPath();
		document.fire("ajaxplorer:context_changed", ajxpDataNode);
	},
		
	getContextNode : function(){
		return this._contextNode;
	},
	
	multipleNodesReload : function(nodes){
		nodes = $A(nodes);
		for(var i=0;i<nodes.length;i++){
			var nodePathOrNode = nodes[i];
			var node;
			if(Object.isString(nodePathOrNode)){
				node = new AjxpNode(nodePathOrNode);	
				if(node.getPath() == this._rootNode.getPath()) node = this._rootNode;
				else node = node.findInArbo(this._rootNode, []);
			}else{
				node = nodePathOrNode;
			}
			nodes[i] = node;		
		}
		var children = $A([]);
		nodes.sort(function(a,b){
			if(a.isParentOf(b)){
				children.push(b);
				return -1;
			}
			if(a.isChildOf(b)){
				children.push(a);
				return +1;
			}
			return 0;
		});
		children.each(function(c){
			nodes = nodes.without(c);
		});
		nodes.each(this.queueNodeReload.bind(this));
		this.nextNodeReloader();
	},
	
	queueNodeReload : function(node){
		if(!this.queue) this.queue = [];
		if(node){
			this.queue.push(node);
		}
	},
	
	nextNodeReloader : function(){
		if(!this.queue.length) {
			window.setTimeout(function(){
				document.fire("ajaxplorer:context_changed", this._contextNode);
			}.bind(this), 200);
			return;
		}
		var next = this.queue.shift();
		var observer = this.nextNodeReloader.bind(this);
		next.observeOnce("loaded", observer);
		next.observeOnce("error", observer);
		if(next == this._contextNode || next.isParentOf(this._contextNode)){
			this.requireContextChange(next, true);
		}else{
			next.reload(this._iAjxpNodeProvider);
		}
	},
	
	setPendingSelection : function(selection){
		this._pendingSelection = selection;
	},
	
	getPendingSelection : function(){
		return this._pendingSelection;
	},
	
	clearPendingSelection : function(){
		this._pendingSelection = null;
	},
	
	setSelectedNodes : function(ajxpDataNodes, source){
		if(!source){
			this._selectionSource = {};
		}else{
			this._selectionSource = source;
		}
		this._selectedNodes = $A(ajxpDataNodes);
		this._bEmpty = ((ajxpDataNodes && ajxpDataNodes.length)?false:true);
		this._bFile = this._bDir = this._isRecycle = false;
		if(!this._bEmpty)
		{
			this._bUnique = ((ajxpDataNodes.length == 1)?true:false);
			for(var i=0; i<ajxpDataNodes.length; i++)
			{
				var selectedNode = ajxpDataNodes[i];
				if(selectedNode.isLeaf()) this._bFile = true;
				else this._bDir = true;
				if(selectedNode.isRecycle()) this._isRecycle = true;
			}
		}
		document.fire("ajaxplorer:selection_changed", this);	
	},
	
	getSelectedNodes : function(){
		return this._selectedNodes;
	},
	
	getSelectionSource : function(){
		return this._selectionSource;
	},
	
	getSelectedItems : function(){
		throw new Error("Deprecated : use getSelectedNodes() instead");
	},
	
	selectAll : function(){
		this.setSelectedNodes(this._contextNode.getChildren(), "dataModel");
	},
	
	isEmpty : function (){
		return (this._selectedNodes?(this._selectedNodes.length==0):true);
	},
	
	isUnique : function (){
		return this._bUnique;
	},
	
	hasFile : function (){
		return this._bFile;
	},
	
	hasDir : function (){
		return this._bDir;
	},
			
	isRecycle : function (){
		return this._isRecycle;
	},
	
	getCurrentRep : function (){
		return this._currentRep;
	},
	
	isMultiple : function(){
		if(this._selectedNodes && this._selectedNodes.length > 1) return true;
		return false;
	},
	
	hasMime : function(mimeTypes){
		if(mimeTypes.length==1 && mimeTypes[0] == "*") return true;
		var has = false;
		mimeTypes.each(function(mime){
			if(has) return;
			has = this._selectedNodes.any(function(node){
				return (getAjxpMimeType(node) == mime);
			});
		}.bind(this) );
		return has;
	},
	
	getFileNames : function(separator){
		if(!this._selectedNodes.length)
		{
			alert('Please select a file!');
			return;
		}
		var tmp = new Array(this._selectedNodes.length);
		for(i=0;i<this._selectedNodes.length;i++)
		{
			tmp[i] = this._selectedNodes[i].getPath();
		}
		if(separator){
			return tmp.join(separator);
		}else{
			return tmp;
		}
	},
	
	getContextFileNames : function(separator){
		var allItems = this._contextNode.getChildren();
		if(!allItems.length)
		{		
			return false;
		}
		var names = $A([]);
		for(i=0;i<allItems.length;i++)
		{
			names.push(getBaseName(allItems[i].getPath()));
		}
		if(separator){
			return names.join(separator);
		}else{
			return names;
		}
	},
	
	fileNameExists: function(newFileName) 
	{	
		var allItems = this._contextNode.getChildren();
		if(!allItems.length)
		{		
			return false;
		}
		for(i=0;i<allItems.length;i++)
		{
			var meta = allItems[i].getMetadata();
			var crtFileName = getBaseName(meta.get('filename'));
			if(crtFileName && crtFileName.toLowerCase() == getBaseName(newFileName).toLowerCase()) 
				return true;
		}
		return false;
	},	
	
	getUniqueFileName : function(){	
		if(this.getFileNames().length) return this.getFileNames()[0];
		return null;	
	},
	
	getUniqueNode : function(){
		if(this._selectedNodes.length){
			return this._selectedNodes[0];
		}
		return null;
	},
	
	getUniqueItem : function(){
		throw new Error("getUniqueItem is deprecated, use getUniqueNode instead!");
	},

    getItem : function(i) {
        throw new Error("getItem is deprecated, use getNode instead!");
    },
	
    getNode : function(i) {
        return this._selectedNodes[i];
    },
	
	updateFormOrUrl : function (oFormElement, sUrl){
		// CLEAR FROM PREVIOUS ACTIONS!
		if(oFormElement)	
		{
			$(oFormElement).getElementsBySelector("input").each(function(element){
				if(element.name.indexOf("file_") != -1 || element.name=="file") element.value = "";
			});
		}
		// UPDATE THE 'DIR' FIELDS
		if(oFormElement && oFormElement.rep) oFormElement.rep.value = this._currentRep;
		sUrl += '&dir='+encodeURIComponent(this._currentRep);
		
		// UPDATE THE 'file' FIELDS
		if(this.isEmpty()) return sUrl;
		var fileNames = this.getFileNames();
		if(this.isUnique())
		{
			sUrl += '&'+'file='+encodeURIComponent(fileNames[0]);
			if(oFormElement) this._addHiddenField(oFormElement, 'file', fileNames[0]);
		}
		else
		{
			for(var i=0;i<fileNames.length;i++)
			{
				sUrl += '&'+'file_'+i+'='+encodeURIComponent(fileNames[i]);
				if(oFormElement) this._addHiddenField(oFormElement, 'file_'+i, fileNames[i]);
			}
		}
		return sUrl;
	},
	
	_addHiddenField : function(oFormElement, sFieldName, sFieldValue){
		if(oFormElement[sFieldName]) oFormElement[sFieldName].value = sFieldValue;
		else{
			var field = document.createElement('input');
			field.type = 'hidden';
			field.name = sFieldName;
			field.value = sFieldValue;
			oFormElement.appendChild(field);
		}
	}
});
