FoldersTree = Class.create({

	initialize: function (oElement, rootFolderName, rootFolderSrc, oAjaxplorer)
	{
		this._htmlElement = $(oElement);
		this.tree = new WebFXLoadTree(rootFolderName, rootFolderSrc, "javascript:ajaxplorer.clickDir(\'/\',\'/\',CURRENT_ID)", 'explorer');
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
		webFXTreeHandler.contextMenu = protoMenu;
	},
	
	clickDir: function(url, parent_url, objectName)	{
		if(this.treeInDestMode)
		{
			alert('TODO / Tree In Dest Mode!');
			/*
			copymoveForm = getFrame('panel').document.getElementById('copymove_form');
			copymoveForm.dest.value = url;
			copymoveForm.dest_node.value = objectName;
			*/
			return;
		}
		
		currentParentUrl = parent_url;	
		if(objectName != null)
		{
			this.setCurrentNodeName(objectName);
		}
		else
		{
			this.openCurrentAndGoToNext(url);
			//alert(res);
		}
		if(WebFXtimer) clearTimeout(WebFXtimer);
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
		else
		{
			//alert('not loaded!');
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
		//this._ajaxplorergetActionBar().update(true);
	},
	
	reloadCurrentNode: function(){
		this.reloadNode(this.currentNodeName);
		return;
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
			if(childName == rec.filename)
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
		//alert(url);
		var splitPath = url.split("/");
		var path = new Array();
		var j = 0;
		var openUrl, parentUrl;
		openUrl = parentUrl = '';
		for(i=0; i<splitPath.length; i++)
		{
			if(splitPath[i] != '') 
			{
				path[j] = splitPath[i];
				j++;
				openUrl = openUrl + '/' + splitPath[i];
				if (i < splitPath.length - 1)
				{
					parentUrl = parentUrl + '/' + splitPath[i];
				}
			}
		}
		this.currentDeepPath = path;
		this.currentDeepIndex = 0;
		this.setCurrentNodeName(this.getRootNodeId());
		
		
		currentParentUrl = parentUrl;	
		if(this.currentDeepPath.length == 0)
		{
			this.setCurrentNodeName(this.getRootNodeId());		
		}
		else
		{
			this.openCurrentAndGoToNext(this.currentDeepPath[0]);
		}
		return false;	
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
	
	changeNodeLabel: function(nodeId, newLabel){	
		var node = $(nodeId+'-anchor');
		node.firstChild.nodeValue = newLabel;
	}
});