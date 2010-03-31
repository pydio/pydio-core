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

	initialize: function ($super, oElement, options)
	{
		$super(oElement);
		this.treeContainer = new Element('div', {id:'tree_container', style:'overflow:auto;height:100%;width:100%;'});
		oElement.insert(this.treeContainer);
		disableTextSelection(this.treeContainer);
		this.options = {};
		if(options){
			this.options = options;
		}
		var thisObject = this;
		var action = function(e){
			if(!ajaxplorer) return;
			ajaxplorer.focusOn(thisObject);
			if(this.ajxpNode){
				ajaxplorer.actionBar.fireDefaultAction("dir", this.ajxpNode);
			}
		};
		
		var filter = this.createFilter();
		var fakeRootNode = new AjxpNode("/", true, "No Repository", "folder.png");
		fakeRootNode._isLoaded = true;
		this.tree = new AJXPTree(fakeRootNode,  action, filter);		
				
		this.treeContainer.update(this.tree.toString());
		$(this.tree.id).ajxpNode = this.tree.ajxpNode;	
		$(this.tree.id).observe("click", function(e){
			this.action(e);
			Event.stop(e);
		}.bind(this.tree));
		AjxpDroppables.add(this.tree.id);
		if(!this.tree.open && !this.tree.loading) {
			this.tree.toggle();		
		}
		this.treeContainer.observe("click", function(){			
			ajaxplorer.focusOn(this);
		}.bind(this));
	
		this.rootNodeId = this.tree.id;
		this.hasFocus;
		
		document.observe("ajaxplorer:context_changed", function(event){
			var path = event.memo.getPath();
			window.setTimeout(function(e){
				this.setSelectedPath(path);
			}.bind(this), 100);			
		}.bind(this) );
				
		document.observe("ajaxplorer:root_node_changed", function(event){
			var ajxpRootNode = event.memo;
			this.tree.setAjxpRootNode(ajxpRootNode);
			this.changeRootLabel(ajxpRootNode.getLabel(), ajxpRootNode.getIcon());		
		}.bind(this));
		
		document.observe("ajaxplorer:component_config_changed", function(event){
			if(event.memo.className == "FoldersTree"){
				var config = event.memo.classConfig.get('all');
				var options = XPathSelectNodes(config, 'property');
				for(var i=0;i<options.length;i++){
					this.options[options[i].getAttribute('name')] = options[i].getAttribute('value');
				}
				if(this.tree){
					this.tree.filter = this.createFilter();
				}
			}
		}.bind(this) );		
		
	},
	
	createFilter : function(){
		var displayOptions = this.options.display || "dz";
		if(displayOptions.indexOf("a") > -1) displayOptions = "dzf";
		if(displayOptions.indexOf("z") > -1 && window.zipEnabled === false) displayOptions = displayOptions.split("z").join("");
		this.options.display  = displayOptions;

		var d = (displayOptions.indexOf("d") > -1);
		var z = (displayOptions.indexOf("z") > -1);
		var f = (displayOptions.indexOf("f") > -1);
		var filter = function(ajxpNode){
			return (((d && !ajxpNode.isLeaf()) || (f && ajxpNode.isLeaf()) || (z && (ajxpNode.getAjxpMime()=="zip" || ajxpNode.getAjxpMime()=="ajxp_browsable_archive"))) && (ajxpNode.getParent().getAjxpMime() != "ajxp_recycle"));
		};
		return filter;		
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
		fitHeightToBottom(this.treeContainer, null);
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
			if(node && node.childNodes.length){
				node._webfxtree_expand();
			}			
		}
		if(node){
			node.select();
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
		var node = $(nodeId+'-label').update(newLabel);
		if(newIcon){
			var realNode = webFXTreeHandler.all[nodeId];
			realNode.icon = newIcon;
			realNode.openIcon = newIcon;
		}
	}
});