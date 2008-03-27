FoldersTree = Class.create({

	initialize: function (oElement, rootFolderName, rootFolderSrc, oAjaxplorer)
	{
		this._htmlElement = $(oElement);
		this.tree = new WebFXLoadTree(rootFolderName, rootFolderSrc, "javascript:ajaxplorer.foldersTree.clickNode(CURRENT_ID)", 'explorer');
		this._htmlElement.innerHTML = this.tree.toString();	
		AjxpDroppables.add(this.tree.id);
		if(!this.tree.open && !this.tree.loading) this.tree.toggle();		
		this._htmlElement.observe("click", function(){
			this.focus();
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
			ajaxplorer.actionBar.fireDefaultAction("dir", path);
		}
	},
		
	setCurrentNodeName: function(newId){
		this.currentNodeName = newId;
		//alert(newId);
		//alert(webFXTreeHandler.all[currentNodeName].text);
		for(var i=0; i<webFXTreeHandler.all.length;i++)
		{
			webFXTreeHandler.all[i].deSelect();
		}
		webFXTreeHandler.all[this.currentNodeName].select();
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
	
	setTreeInDestMode: function(){
		this.treeInDestMode = true;
	},
	
	setTreeInNormalMode: function(){
		this.treeInDestMode = false;
	},
	
	openCurrentAndGoToNext: function(url){
		//alert(this.currentNodeName);
		if(this.currentNodeName == null) return;
		webFXTreeHandler.all[this.currentNodeName].expand();
		this.goToNextWhenLoaded = url;
		firstTry = this.getTreeChildNodeByName(url);	
		if(firstTry)
		{
			this.setCurrentNodeName(firstTry);
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
				}
			}
			
		}
	},	
	
	asyncExpandAndSelect: function(){
		if(this.goToNextWhenLoaded != null)
		{		
			secondTry = this.getTreeChildNodeByName(this.goToNextWhenLoaded);
			if(secondTry) this.setCurrentNodeName(secondTry);
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
				}
			}
			else
			{
				this.goToNextWhenLoaded = null;
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
	
	reloadFullTree: function(repositoryLabel){
		webFXTreeHandler.recycleNode = null;
		this.setCurrentToRoot();
		this.changeRootLabel(repositoryLabel);
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
		if(!this.currentNodeName) this.setCurrentToRoot();
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
			this.setCurrentNodeName(this.getRootNodeId());
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
	
	setCurrentToRoot: function(){
		this.setCurrentNodeName(this.getRootNodeId());
	},
	
	changeRootLabel: function(newLabel){
		this.changeNodeLabel(this.getRootNodeId(), newLabel);	
	},
	
	changeNodeLabel: function(nodeId, newLabel){	
		var node = $(nodeId+'-anchor');
		node.firstChild.nodeValue = newLabel;
	}
});