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
 * Description : The tree object. Encapsulate the webfx tree.
 */
FoldersTree = Class.create({

	initialize: function (oElement, rootFolderName, rootFolderSrc, oAjaxplorer, dontLoad)
	{
		this._htmlElement = $(oElement);
		this.tree = new WebFXLoadTree(rootFolderName, rootFolderSrc, "javascript:ajaxplorer.foldersTree.clickNode(CURRENT_ID)", 'explorer');
		this._htmlElement.innerHTML = this.tree.toString();	
		$(this.tree.id).observe("click", function(e){
			ajaxplorer.focusOn(this);
			this.clickNode(this.tree.id);
			Event.stop(e);
		}.bind(this));
		AjxpDroppables.add(this.tree.id);
		if(!this.tree.open && !this.tree.loading && !dontLoad) this.tree.toggle();		
		this._htmlElement.observe("click", function(){			
			ajaxplorer.focusOn(this);
		}.bind(this));
		this.setCurrentNodeName(this.tree.id);
	
		this.rootNodeId = this.tree.id;
		this.currentNodeName;
		this.goToNextWhenLoaded;
		
		this.currentDeepPath;
		this.currentDeepIndex;
		
		this.treeInDestMode = false;	
		this._ajaxplorer = oAjaxplorer;	
		this.hasFocus;
	},
	
	focus: function(){
		if(webFXTreeHandler.selected)
		{
			webFXTreeHandler.selected.focus();
		}
		webFXTreeHandler.setFocus(true);
		this.hasFocus = true;
	},
	
	blur: function(){
		if(webFXTreeHandler.selected)
		{
			webFXTreeHandler.selected.blur();
		}
		webFXTreeHandler.setFocus(false);
		this.hasFocus = false;
	},
	
	setContextualMenu: function(protoMenu){
		Event.observe(this.rootNodeId+'-anchor', 'contextmenu', function(e){eval(this.action);}.bind(webFXTreeHandler.all[this.rootNodeId]));
		protoMenu.addElements('#'+this.rootNodeId+'-anchor');
		webFXTreeHandler.contextMenu = protoMenu;
	},
	
	clickNode: function(nodeId){		
		var path = webFXTreeHandler.all[nodeId].url;
		if(path){
			if(ajaxplorer.actionBar.treeCopyActive){
	  			if(ajaxplorer.actionBar.treeCopyActionDest) 
	  				ajaxplorer.actionBar.treeCopyActionDest.each(function(element){element.value = path});
 				if(ajaxplorer.actionBar.treeCopyActionDestNode) 
 					ajaxplorer.actionBar.treeCopyActionDestNode.each(function(element){element.value = nodeId});
 				return;
 			}
			this.setCurrentNodeName(nodeId);
			if(this.getCurrentNodeProperty("pagination_anchor")){
				path = path + "#" + this.getCurrentNodeProperty("pagination_anchor");
			}
			ajaxplorer.actionBar.fireDefaultAction("dir", path);
		}
	},
		
	setCurrentNodeName: function(newId, skipSelect){
		this.currentNodeName = newId;
		if(!skipSelect) this.selectCurrentNodeName();
		if(this.goToNextWhenLoaded != null)
		{
			this.goToNextWhenLoaded = null;
		}
	},
	
	selectCurrentNodeName: function(){
		for(var i=0; i<webFXTreeHandler.all.length;i++)
		{
			webFXTreeHandler.all[i].deSelect();
		}
		webFXTreeHandler.all[this.currentNodeName].select();	
	},
	
	setCurrentNodeProperty : function(key, value){
		if(webFXTreeHandler.all[this.currentNodeName]){
			webFXTreeHandler.all[this.currentNodeName].key = value;
		}
	},
	
	getCurrentNodeProperty : function(key){
		if(webFXTreeHandler.all[this.currentNodeName]){
			return webFXTreeHandler.all[this.currentNodeName].key;
		}
		return null;
	},
	
	setTreeInDestMode: function(){
		this.treeInDestMode = true;
	},
	
	setTreeInNormalMode: function(){
		this.treeInDestMode = false;
	},
	
	openCurrentAndGoToNext: function(url){		
		if(this.currentNodeName == null) return;
		webFXTreeHandler.all[this.currentNodeName].expand();
		this.goToNextWhenLoaded = url;
		firstTry = this.getTreeChildNodeByName(url);	
		if(firstTry)
		{
			this.setCurrentNodeName(firstTry, true);
			this.goToNextWhenLoaded = null;
			if(this.currentDeepPath != null && this.currentDeepIndex != null)
			{
				if(this.currentDeepIndex < this.currentDeepPath.length-1)
				{
					this.currentDeepIndex ++;
					//alert(currentDeepPath[currentDeepIndex]);				
					this.openCurrentAndGoToNext(this.currentDeepPath[this.currentDeepIndex]);
				}
				else
				{
					this.currentDeepPath = null;
					this.currentDeepIndex = null;
					this.selectCurrentNodeName();
				}
			}
			else
			{
				this.selectCurrentNodeName();
			}
			
		}
	},	
	
	asyncExpandAndSelect: function(){
		if(this.goToNextWhenLoaded != null)
		{		
			secondTry = this.getTreeChildNodeByName(this.goToNextWhenLoaded);
			if(secondTry) this.setCurrentNodeName(secondTry, true);
			//
			if(this.currentDeepPath != null && this.currentDeepIndex != null)
			{
				if(this.currentDeepIndex < this.currentDeepPath.length-1)
				{
					this.currentDeepIndex ++;
					this.openCurrentAndGoToNext(this.currentDeepPath[this.currentDeepIndex]);
				}
				else
				{
					this.currentDeepPath = null;
					this.currentDeepIndex = null;
					//goToNextWhenLoaded = null;
					this.selectCurrentNodeName();
				}
			}
			else
			{
				this.goToNextWhenLoaded = null;
				this.selectCurrentNodeName();
			}
		}	
	},
	
	goToParentNode: function(){
		if(this.currentNodeName == null || this.currentNodeName == this.getRootNodeId()) return;
		this.setCurrentNodeName(webFXTreeHandler.all[this.currentNodeName].parentNode.id);		
	},
	
	reloadCurrentNode: function(){
		this.reloadNode(this.currentNodeName);
		return;
	},
	
	reloadFullTree: function(repositoryLabel, newIcon){		
		webFXTreeHandler.recycleNode = null;
		this.setCurrentToRoot();
		this.changeRootLabel(repositoryLabel, newIcon);		
		this.reloadCurrentNode();
	},
	
	reloadNode: function(nodeName){
		if (nodeName == null)
		{		
			return;
		}
		if(nodeName == this.getRootNodeId())
		{
			this.tree.reload();
		}
		else
		{
			if(nodeName == 'AJAXPLORER_RECYCLE_NODE' && webFXTreeHandler.recycleNode){
				nodeName = webFXTreeHandler.recycleNode;
			}
			else if(webFXTreeHandler.ajxpNodes.nodeName){
				nodeName = webFXTreeHandler.ajxpNodes.nodeName;
			}
			if(webFXTreeHandler.all[nodeName] && webFXTreeHandler.all[nodeName].reload) webFXTreeHandler.all[nodeName].reload();
		}	
	},
	
	getTreeChildNodeByName: function(childName){
		if (this.currentNodeName == null)
		{
			return;		
		}
		if(webFXTreeHandler.recycleNode){
			rec = webFXTreeHandler.all[webFXTreeHandler.recycleNode];
			if(getBaseName(childName) == getBaseName(rec.filename))
			{
				return webFXTreeHandler.recycleNode;
			}
		}		
		if(webFXTreeHandler.ajxpNodes[getBaseName(childName)]){
			return webFXTreeHandler.ajxpNodes[getBaseName(childName)];
		}
		if(childName.lastIndexOf("/") != -1)
		{
			childName = childName.substr(childName.lastIndexOf("/")+1, childName.length);
		}
		var currentNodeObject = webFXTreeHandler.all[this.currentNodeName];
		for(i=0; i<currentNodeObject.childNodes.length ; i++)
		{
			if(currentNodeObject.childNodes[i].text && currentNodeObject.childNodes[i].text == childName)
			{
				return currentNodeObject.childNodes[i].id;
			}
		}	
	},
		
	goToDeepPath: function(url){
		var currentPath = "/";
		if(!this.currentNodeName) this.setCurrentToRoot(true);
		if(this.currentNodeName && webFXTreeHandler.all[this.currentNodeName] && webFXTreeHandler.all[this.currentNodeName].url){
			currentPath = webFXTreeHandler.all[this.currentNodeName].url;		
		}
		var currentSplit = currentPath.split("/");
		currentSplit.shift();
		var isChild = false;
		var path = this.cleanPathToArray(url);
				
		if(currentPath!= "/" && url.substring(0, currentPath.length) == currentPath){
			isChild = true;			
			for(var i=0;i<currentSplit.length; i++){				
				path.shift();				
			}
		}

		this.currentDeepPath = path;
		this.currentDeepIndex = 0;
		if(!isChild){
			this.setCurrentNodeName(this.getRootNodeId(), true);
		}
		if(this.currentDeepPath.length > 0){
			this.openCurrentAndGoToNext(this.currentDeepPath[0]);
		}
		return false;	
	},
	
	cleanPathToArray: function(url){
		var splitPath = url.split("/");
		var path = new Array();
		var j = 0;
		for(i=0; i<splitPath.length; i++)
		{
			if(splitPath[i] != '') 
			{
				path[j] = splitPath[i];
				j++;
			}
		}
		return path;		
	},
	
	getRootNodeId: function(){
		return this.rootNodeId;
	},
	
	currentIsRoot: function(){
		return (this.rootNodeId == this.currentNodeName);
	},
	
	recycleEnabled: function(){
		if(webFXTreeHandler.recycleNode) return true;
		return false;
	},
	
	currentIsRecycle: function(){
		if(webFXTreeHandler.recycleNode && this.currentNodeName == webFXTreeHandler.recycleNode)
		{
			return true;
		}
		
		return false;
	},
	
	currentInZip : function(){
		if(this.currentNodeName 
		&& webFXTreeHandler.all[this.currentNodeName] 
		&& webFXTreeHandler.all[this.currentNodeName].inZip){
			return true;
		}
		return false;		
	},
	
	getCurrentNodeMime : function(){
		if((this.rootNodeId == this.currentNodeName)){
			return "ajxp_root";
		}
		if(this.currentNodeName && webFXTreeHandler.all[this.currentNodeName]){
			return webFXTreeHandler.all[this.currentNodeName].ajxpMime;
		}
		return null;
	},
	
	setCurrentToRoot: function(skipSelect){
		this.setCurrentNodeName(this.getRootNodeId(), skipSelect);
	},
	
	changeRootLabel: function(newLabel, newIcon){
		this.changeNodeLabel(this.getRootNodeId(), newLabel, newIcon);	
	},
	
	changeNodeLabel: function(nodeId, newLabel, newIcon){	
		var node = $(nodeId+'-anchor');
		node.firstChild.nodeValue = newLabel;
		if(newIcon){
			var realNode = webFXTreeHandler.all[nodeId];
			realNode.icon = newIcon;
			realNode.openIcon = newIcon;
		}
	}
});