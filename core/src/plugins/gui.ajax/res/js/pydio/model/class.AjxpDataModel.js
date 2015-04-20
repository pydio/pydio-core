/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

/**
 * Full container of the data tree. Contains the SelectionModel as well.
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

    _globalEvents : true,

	/**
	 * Constructor
	 */
	initialize: function(localEvents){
		this._currentRep = '/';
		this._selectedNodes = $A([]);
		this._bEmpty = true;
        if(localEvents) this._globalEvents = false;
	},
	
	/**
	 * Sets the data source that will feed the nodes with children.
	 * @param iAjxpNodeProvider IAjxpNodeProvider 
	 */
	setAjxpNodeProvider : function(iAjxpNodeProvider){
		this._iAjxpNodeProvider = iAjxpNodeProvider;
	},

    /**
     * Return the current data source provider
     * @return IAjxpNodeProvider
     */
	getAjxpNodeProvider : function(){
		return this._iAjxpNodeProvider;
	},

	/**
	 * Changes the current context node.
	 * @param ajxpNode AjxpNode Target node, either an existing one or a fake one containing the target part.
	 * @param forceReload Boolean If set to true, the node will be reloaded even if already loaded.
	 */
	requireContextChange : function(ajxpNode, forceReload){
        if(ajxpNode == null) return;
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
				this.publish("context_loading");
				firstFake.load(this._iAjxpNodeProvider);
				return;
			}
		}		
		ajxpNode.observeOnce("loaded", function(){
			this.setContextNode(ajxpNode, true);			
			this.publish("context_loaded");
            if(this.getPendingSelection()){
                var selPath = ajxpNode.getPath() + (ajxpNode.getPath() == "/" ? "" : "/" ) +this.getPendingSelection();
                var selNode =  ajxpNode.findChildByPath(selPath);
                if(selNode) {
                    this.setSelectedNodes([selNode], this);
                }else{
                    if(ajxpNode.getMetadata().get("paginationData") && arguments.length < 3){
                        var newPage;
                        var currentPage = ajxpNode.getMetadata().get("paginationData").get("current");
                        this.loadPathInfoSync(selPath, function(foundNode){
                            newPage = foundNode.getMetadata().get("page_position");
                        });
                        if(newPage && newPage != currentPage){
                            ajxpNode.getMetadata().get("paginationData").set("new_page", newPage);
                            this.requireContextChange(ajxpNode, true, true);
                            return;
                        }
                    }
                }
                this.clearPendingSelection();
            }
		}.bind(this));
		ajxpNode.observeOnce("error", function(message){
			ajaxplorer.displayMessage("ERROR", message);
			this.publish("context_loaded");
		}.bind(this));
		this.publish("context_loading");
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
			this.publish("context_loaded");
		}
	},

    requireNodeReload: function(nodeOrPath, completeCallback){
        if(Object.isString(nodeOrPath)){
            nodeOrPath = new AjxpNode(nodeOrPath);
        }
        var onComplete = null;
        if(this._selectedNodes.length) {
            var found = false;
            this._selectedNodes.each(function(node){
                if(node.getPath() == nodeOrPath.getPath()) found = node;
            });
            if(found){
                // TODO : MAKE SURE SELECTION IS OK AFTER RELOAD
                this._selectedNodes = this._selectedNodes.without(found);
                this.publish("selection_changed", this);
                onComplete = function(newNode){
                    this._selectedNodes.push(newNode);
                    this._selectionSource = {};
                    this.publish("selection_changed", this);
                    if(completeCallback) completeCallback(newNode);
                }.bind(this);
            }
        }
        this._iAjxpNodeProvider.refreshNodeAndReplace(nodeOrPath, onComplete);
    },

    loadPathInfoSync: function (path, callback){
        this._iAjxpNodeProvider.loadLeafNodeSync(new AjxpNode(path), callback);
    },

    loadPathInfoAsync: function (path, callback){
        this._iAjxpNodeProvider.loadLeafNodeSync(new AjxpNode(path), callback, true);
    },

	/**
	 * Sets the root of the data store
	 * @param ajxpRootNode AjxpNode The parent node
	 */
	setRootNode : function(ajxpRootNode){
		this._rootNode = ajxpRootNode;
		this._rootNode.setRoot();
		this._rootNode.observe("child_added", function(c){
				//console.log(c);
		});
		this.publish("root_node_changed", this._rootNode);
		this.setContextNode(this._rootNode);
	},
	
	/**
	 * Gets the current root node
	 * @returns AjxpNode
	 */
	getRootNode : function(){
		return this._rootNode;
	},
	
	/**
	 * Sets the current context node
	 * @param ajxpDataNode AjxpNode
	 * @param forceEvent Boolean If set to true, event will be triggered even if the current node is already the same.
	 */
	setContextNode : function(ajxpDataNode, forceEvent){
		if(this._contextNode && this._contextNode == ajxpDataNode && this._currentRep  == ajxpDataNode.getPath() && !forceEvent){
			return; // No changes
		}
        if(!ajxpDataNode) return;
        if(this._contextNodeReplacedObserver && this._contextNode){
            this._contextNode.stopObserving("node_replaced", this._contextNodeReplacedObserver);
        }
        this._contextNode = ajxpDataNode;
		this._currentRep = ajxpDataNode.getPath();
        this.publish("context_changed", ajxpDataNode);
        if(!this._contextNodeReplacedObserver) this._contextNodeReplacedObserver = this.contextNodeReplaced.bind(this);
        ajxpDataNode.observe("node_replaced", this._contextNodeReplacedObserver);
	},

    contextNodeReplaced: function(newNode){
        this.setContextNode(newNode);
    },

    /**
     *
     */
    publish:function(eventName, optionalData){
        var args = $A(arguments).slice(1);
        //args.unshift(this);
        if(this._globalEvents){
            args.unshift("ajaxplorer:"+eventName);
            document.fire.apply(document, args);
        }else{
            if(args.length){
                args = [eventName, {memo:args[0]}];
            }else{
                args.unshift(eventName);
            }
            //args.unshift(eventName);
            this.notify.apply(this,args);
        }
    },

	/**
	 * Get the current context node
	 * @returns AjxpNode
	 */
	getContextNode : function(){
		return this._contextNode;
	},
	
	/**
	 * After a copy or move operation, many nodes may have to be reloaded
	 * This function tries to reload them in the right order and if necessary.
	 * @param nodes AjxpNodes[] An array of nodes
	 */
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
	
	/**
	 * Add a node to the queue of nodes to reload.
	 * @param node AjxpNode
	 */
	queueNodeReload : function(node){
		if(!this.queue) this.queue = [];
		if(node){
			this.queue.push(node);
		}
	},
	
	/**
	 * Queue processor for the nodes to reload
	 */
	nextNodeReloader : function(){
		if(!this.queue.length) {
			window.setTimeout(function(){
				this.publish("context_changed", this._contextNode);
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
	
	/**
	 * Sets an array of nodes to be selected after the context is (re)loaded
	 * @param selection AjxpNode[]
	 */
	setPendingSelection : function(selection){
		this._pendingSelection = selection;
	},
	
	/**
	 * Gets the array of nodes to be selected after the context is (re)loaded
	 * @returns AjxpNode[]
	 */
	getPendingSelection : function(){
		return this._pendingSelection;
	},
	
	/**
	 * Clears the nodes to be selected
	 */
	clearPendingSelection : function(){
		this._pendingSelection = null;
	},
	
	/**
	 * Set an array of nodes as the current selection
	 * @param ajxpDataNodes AjxpNode[] The nodes to select
	 * @param source String The source of this selection action
	 */
	setSelectedNodes : function(ajxpDataNodes, source){
		if(!source){
			this._selectionSource = {};
		}else{
			this._selectionSource = source;
		}
        //ajxpDataNodes = $A(ajxpDataNodes).without(this._rootNode);
		this._selectedNodes = $A(ajxpDataNodes);
		this._bEmpty = ((ajxpDataNodes && ajxpDataNodes.length)?false:true);
		this._bFile = this._bDir = this._isRecycle = false;
		if(!this._bEmpty)
		{
			this._bUnique = (ajxpDataNodes.length == 1);
			for(var i=0; i<ajxpDataNodes.length; i++)
			{
				var selectedNode = ajxpDataNodes[i];
				if(selectedNode.isLeaf()) this._bFile = true;
				else this._bDir = true;
				if(selectedNode.isRecycle()) this._isRecycle = true;
			}
		}
		this.publish("selection_changed", this);
	},
	
	/**
	 * Gets the currently selected nodes
	 * @returns AjxpNode[]
	 */
	getSelectedNodes : function(){
		return this._selectedNodes;
	},
	
	/**
	 * Gets the source of the last selection action
	 * @returns String
	 */
	getSelectionSource : function(){
		return this._selectionSource;
	},

    /**
     * Manually sets the source of the selection
     * @param object
     */
    setSelectionSource : function(object){
        this._selectionSource = object;
    },

	/**
	 * DEPRECATED
	 */
	getSelectedItems : function(){
		throw new Error("Deprecated : use getSelectedNodes() instead");
	},
	
	/**
	 * Select all the children of the current context node
	 */
	selectAll : function(){
		this.setSelectedNodes(this._contextNode.getChildren(), "dataModel");
	},
	
	/**
	 * Whether the selection is empty
	 * @returns Boolean
	 */
	isEmpty : function (){
		return (this._selectedNodes?(this._selectedNodes.length==0):true);
	},

    hasReadOnly : function(){
        var test = false;
        this._selectedNodes.each(function(node){
            if(node.hasMetadataInBranch("ajxp_readonly", "true")) {
                test = true;
                throw $break;
            }
        });
        return test;
    },

    selectionHasRootNode : function(){
        return (null != this._selectedNodes.detect(function(el){
            return el.isRoot();
        }));

    },

	/**
	 * Whether the selection is unique
	 * @returns Boolean
	 */
	isUnique : function (){
		return this._bUnique;
	},
	
	/**
	 * Whether the selection has a file selected.
	 * Should be hasLeaf
	 * @returns Boolean
	 */
	hasFile : function (){
		return this._bFile;
	},
	
	/**
	 * Whether the selection has a dir selected
	 * @returns Boolean
	 */
	hasDir : function (){
		return this._bDir;
	},
			
	/**
	 * Whether the current context is the recycle bin
	 * @returns Boolean
	 */
	isRecycle : function (){
		return this._isRecycle;
	},
	
	/**
	 * Whether the selection has more than one node selected
	 * @returns Boolean
	 */
	isMultiple : function(){
		return this._selectedNodes && this._selectedNodes.length > 1;
	},
	
	/**
	 * Whether the selection has a file with one of the mimes
	 * @param mimeTypes Array Array of mime types
	 * @returns Boolean
	 */
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
	
	/**
	 * Get all selected filenames as an array.
	 * @param separator String Is a separator, will return a string joined
	 * @returns Array|String|bool
	 */
	getFileNames : function(separator){
		if(!this._selectedNodes.length)
		{
			alert('Please select a file!');
			return false;
		}
		var tmp = new Array(this._selectedNodes.length);
		for(var i=0;i<this._selectedNodes.length;i++)
		{
			tmp[i] = this._selectedNodes[i].getPath();
		}
		if(separator){
			return tmp.join(separator);
		}else{
			return tmp;
		}
	},
	
	/**
	 * Get all the filenames of the current context node children
	 * @param separator String If passed, will join the array as a string
	 * @return Array|String|bool
	 */
	getContextFileNames : function(separator){
		var allItems = this._contextNode.getChildren();
		if(!allItems.length)
		{		
			return false;
		}
		var names = $A([]);
		for(var i=0;i<allItems.length;i++)
		{
			names.push(getBaseName(allItems[i].getPath()));
		}
		if(separator){
			return names.join(separator);
		}else{
			return names;
		}
	},

    /**
     * Whether the context node has a child with this basename
     * @param newFileName String The name to check
     * @returns Boolean
     * @param local
     * @param contextNode
     */
	fileNameExists: function(newFileName, local, contextNode)
	{
        if(!contextNode){
            contextNode = this._contextNode;
        }
        if(local){
            var test = (contextNode.getPath()=="/"?"":contextNode.getPath()) + "/" + newFileName;
            return contextNode.getChildren().detect(function(c){
                return c.getPath() == test;
            });
        }else{
            var nodeExists = false;
            this.loadPathInfoSync(contextNode.getPath() + "/" + newFileName, function(foundNode){
                nodeExists = true;
            });
            return nodeExists;
        }

	},

    applyCheckHook : function(node){
        "use strict";
        var conn = new Connexion();
        conn.setParameters(new Hash({
            get_action : "apply_check_hook",
            file       : node.getPath(),
            hook_name  : "before_create",
            hook_arg   : node.getMetadata().get("filesize") || -1
        }));
        var result;
        conn.onComplete = function(transport){
            result = ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
        };
        conn.sendSync();
        if(result === false){
            throw new Error("Check failed" + result);
        }
    },
	
	/**
	 * Gets the first name of the current selection
	 * @returns String
	 */
	getUniqueFileName : function(){	
		if(this.getFileNames().length) return this.getFileNames()[0];
		return null;	
	},
	
	/**
	 * Gets the first node of the selection, or Null
	 * @returns AjxpNode
	 */
	getUniqueNode : function(){
		if(this._selectedNodes.length){
			return this._selectedNodes[0];
		}
		return null;
	},

    /**
     * Gets a node from the current selection
     * @param i Integer the node index
     * @returns AjxpNode
     */
    getNode : function(i) {
        return this._selectedNodes[i];
    },
	
    /**
     * Will add the current selection nodes as serializable data to the element passed : 
     * either as hidden input elements if it's a form, or as query parameters if it's an url
     * @param oFormElement HTMLForm The form
     * @param sUrl String An url to complete
     * @returns String
     */
	updateFormOrUrl : function (oFormElement, sUrl){
		// CLEAR FROM PREVIOUS ACTIONS!
		if(oFormElement)	
		{
			$(oFormElement).select('input[type="hidden"]').each(function(element){
				if(element.name == "nodes[]" || element.name == "file")element.remove();
			});
		}
		// UPDATE THE 'DIR' FIELDS
		if(oFormElement && oFormElement['rep']) oFormElement['rep'].value = this._currentRep;
		sUrl += '&dir='+encodeURIComponent(this._currentRep);
		
		// UPDATE THE 'file' FIELDS
		if(this.isEmpty()) return sUrl;
		var fileNames = this.getFileNames();
        for(var i=0;i<fileNames.length;i++)
        {
            sUrl += '&'+'nodes[]='+encodeURIComponent(fileNames[i]);
            if(oFormElement) this._addHiddenField(oFormElement, 'nodes[]', fileNames[i]);
        }
        if(fileNames.length == 1){
            sUrl += '&'+'file='+encodeURIComponent(fileNames[0]);
            if(oFormElement) this._addHiddenField(oFormElement, 'file', fileNames[0]);
        }
		return sUrl;
	},
	
	_addHiddenField : function(oFormElement, sFieldName, sFieldValue){
        oFormElement.insert(new Element('input', {type:'hidden', name:sFieldName, value:sFieldValue}));
	}
});
