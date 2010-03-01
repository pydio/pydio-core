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
Class.create("FoldersTree", AjxpPane, {
	
	__implements : ["IFocusable", "IContextMenuable"],

	initialize: function ($super, oElement, rootFolderName, rootFolderSrc, oAjaxplorer, dontLoad)
	{
		$super(oElement);
		this.treeContainer = new Element('div', {id:'tree_container', style:'overflow:auto;height:100%;'});
		oElement.insert(this.treeContainer);
		disableTextSelection(this.treeContainer);
		var thisObject = this;
		var action = function(e){
			if(!ajaxplorer) return;
			ajaxplorer.focusOn(thisObject);
			if(this.ajxpNode){
				ajaxplorer.actionBar.fireDefaultAction("dir", this.ajxpNode);
			}
		};
		
		this.tree = new AJXPTree(new AjxpNode("/", true, "No Repository", "folder.png"),  action);
		// DISABLE LOADING
		this.tree.loaded = true;		
		this.treeContainer.update(this.tree.toString());
		$(this.tree.id).ajxpNode = this.tree.ajxpNode;	
		$(this.tree.id).observe("click", function(e){
			this.action(e);
			Event.stop(e);
		}.bind(this.tree));
		AjxpDroppables.add(this.tree.id);
		if(!this.tree.open && !this.tree.loading && !dontLoad) this.tree.toggle();		
		this.treeContainer.observe("click", function(){			
			ajaxplorer.focusOn(this);
		}.bind(this));
	
		this.rootNodeId = this.tree.id;
				
		this.hasFocus;
		
		document.observe("ajaxplorer:context_changed", function(event){
			var path = event.memo.getContextNode().getPath();
			window.setTimeout(function(e){
				this.setSelectedPath(path);
			}.bind(this), 100);			
		}.bind(this) );
		
		document.observe("ajaxplorer:context_refresh", function(event){
			//this.reloadCurrentNode();
		}.bind(this) );
		
		document.observe("ajaxplorer:root_node_changed", this.reloadFullTree.bind(this));
		
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
		
	resize : function(){
		fitHeightToBottom(this.treeContainer, null, (Prototype.Browser.IE?0:2), true);
	},
	
	showElement : function(show){
		if (show) this.treeContainer.show();
		else this.treeContainer.hide();
	},
	
	setContextualMenu: function(protoMenu){
		Event.observe(this.rootNodeId+'-anchor', 'contextmenu', function(e){eval(this.action);}.bind(webFXTreeHandler.all[this.rootNodeId]));
		protoMenu.addElements('#'+this.rootNodeId+'-anchor');
		webFXTreeHandler.contextMenu = protoMenu;
	},
	
	getNodeByPath : function(path){
		for(var key in webFXTreeHandler.all){
			if(webFXTreeHandler.all[key] && webFXTreeHandler.all[key].ajxpNode && webFXTreeHandler.all[key].ajxpNode.getPath() == path){
				return webFXTreeHandler.all[key];
			}
		}
	},
	
	setSelectedPath : function(path){
		if(path == "" || path == "/"){
			this.tree.select();
			return;
		}
		var parts = this.cleanPathToArray(path);
		var crtPath = "";
		for(var i=0;i<parts.length;i++){
			crtPath += "/" + parts[i];
			var node = this.getNodeByPath(crtPath);
			if(node){
				node._webfxtree_expand();
			}			
		}
		if(node){
			node.select();
		}
	},
	
	clickNode: function(nodeId){
		/*
		var path = webFXTreeHandler.all[nodeId].url;
		if(path){
			this.setCurrentNodeName(nodeId);
			var label = getBaseName(path);
			if(this.getCurrentNodeProperty("pagination_anchor")){
				path = path + "#" + this.getCurrentNodeProperty("pagination_anchor");
			}
			var node = new AjxpNode(path, !webFXTreeHandler.all[nodeId].folder, label, webFXTreeHandler.all[nodeId].icon);
			ajaxplorer.actionBar.fireDefaultAction("dir", node);
		}
		*/
	},
	
	reloadCurrentNode: function(){
		//this.reloadNode(this.currentNodeName);
		return;
	},
	
	reloadFullTree: function(event){		
		var ajxpRootNode = event.memo;
		webFXTreeHandler.recycleNode = null;
		this.tree.setAjxpRootNode(ajxpRootNode);
		this.changeRootLabel(ajxpRootNode.getLabel(), ajxpRootNode.getIcon());		
		
		// DISABLE LOADING (TMP)
		//this.reloadCurrentNode();
	},
	
	reloadNode: function(nodeName){
		if (nodeName == null)
		{		
			return;
		}
		if(nodeName == this.rootNodeId)
		{
			this.tree.doCollapse();
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
		
	changeRootLabel: function(newLabel, newIcon){
		this.changeNodeLabel(this.tree.id, newLabel, newIcon);	
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