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
 * The tree object. Encapsulate the webfx tree.
 */
Class.create("FoldersTree", AjxpPane, {
	
	__implements : ["IFocusable", "IContextMenuable"],

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param oElement HTMLElement
	 * @param options Object
	 */
	initialize: function ($super, oElement, options)
	{
		$super(oElement, options);
		this.treeContainer = new Element('div', {id:'tree_container', style:'overflow:auto;height:100%;width:100%;'});
        if(this.options.replaceScroller){
            this.scroller = new Element('div', {id:'tree_scroller', className:'scroller_track', style:"right:"+(parseInt(oElement.getStyle("marginRight"))-parseInt(oElement.getStyle("paddingRight")))+"px"});
            this.scroller.insert('<div id="scrollbar_handle" class="scroller_handle"></div>');
            oElement.insert(this.scroller);
            this.treeContainer.setStyle({overflow:"hidden"});
        }
        this.registeredObservers = $H();
		oElement.insert(this.treeContainer);
		disableTextSelection(this.treeContainer);
        if(this.options.replaceScroller){
            this.scrollbar = new Control.ScrollBar('tree_container','tree_scroller');
            var scrollbarLayoutObserver = this.scrollbar.recalculateLayout.bind(this.scrollbar);
            document.observe("ajaxplorer:tree_change",  scrollbarLayoutObserver);
            this.registeredObservers.set("ajaxplorer:tree_change", scrollbarLayoutObserver);
        }

		this.options = {};
		if(options){
			this.options = options;
		}
		var thisObject = this;
		var action = function(e){
			if(!ajaxplorer) return;
			ajaxplorer.focusOn(thisObject);
			if(this.ajxpNode){
                if(ajaxplorer.getUserSelection().getContextNode() != this.ajxpNode){
                    ajaxplorer.actionBar.fireDefaultAction("dir", this.ajxpNode);
                }
                ajaxplorer.getUserSelection().setSelectedNodes([this.ajxpNode], thisObject);
			}
		};
		
		var filter = this.createFilter();
		var fakeRootNode = new AjxpNode("/", true, MessageHash[391], "folder.png");
		fakeRootNode._isLoaded = true;
		this.tree = new AJXPTree(fakeRootNode,  action, filter);		
				
		this.treeContainer.update(this.tree.toString());
		$(this.tree.id).ajxpNode = this.tree.ajxpNode;	
		$(this.tree.id).observe("click", function(e){
			this.action(e);
			Event.stop(e);
		}.bind(this.tree));

		AjxpDroppables.add(this.tree.id, this.tree.ajxpNode);
		if(!this.tree.open && !this.tree.loading) {
			this.tree.toggle();		
		}
		this.treeContainer.observe("click", function(){			
			ajaxplorer.focusOn(this);
		}.bind(this));
	
		this.rootNodeId = this.tree.id;

        var ctxChangedObs =function(event){
			var path = event.memo.getPath();
			window.setTimeout(function(e){
				this.setSelectedPath(path);
			}.bind(this), 100);
		}.bind(this);
		document.observe("ajaxplorer:context_changed",  ctxChangedObs);
        this.registeredObservers.set("ajaxplorer:context_changed", ctxChangedObs);

        var rootNodeObs = function(event){
			var ajxpRootNode = event.memo;
			this.tree.setAjxpRootNode(ajxpRootNode);
			this.changeRootLabel(ajxpRootNode.getLabel(), ajxpRootNode.getIcon());
		}.bind(this);
		document.observe("ajaxplorer:root_node_changed", rootNodeObs);
        this.registeredObservers.set("ajaxplorer:root_node_changed", rootNodeObs);

        var compConfChanged = function(event){
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
		}.bind(this);
		document.observe("ajaxplorer:component_config_changed",  compConfChanged);
        this.registeredObservers.set("ajaxplorer:component_config_changed", compConfChanged);

	},

    destroy : function($super){
        this.registeredObservers.each(function (pair){
            document.stopObserving(pair.key, pair.value);
        });
        if(this.scrollbar) this.scrollbar.destroy();
        if(this.tree) this.tree.destroy();
        if(window[this.htmlElement.id]){
            try{delete window[this.htmlElement.id];}catch(e){}
        }
        $super();
    },

	/**
	 * Create a filtering function based on the options display
	 * @returns Function
	 */
	createFilter : function(){
		var displayOptions = this.options.display || "dz";
		if(displayOptions.indexOf("a") > -1) displayOptions = "dzf";
		if(displayOptions.indexOf("z") > -1 && window.zipEnabled === false) displayOptions = displayOptions.split("z").join("");
		this.options.display  = displayOptions;

		var d = (displayOptions.indexOf("d") > -1);
		var z = (displayOptions.indexOf("z") > -1);
		var f = (displayOptions.indexOf("f") > -1);
		return function(ajxpNode){
			return (((d && !ajxpNode.isLeaf()) || (f && ajxpNode.isLeaf()) || (z && (ajxpNode.getAjxpMime()=="zip" || ajxpNode.getAjxpMime()=="ajxp_browsable_archive"))) && (ajxpNode.getParent().getAjxpMime() != "ajxp_recycle"));
		};
	},
	
	/**
	 * Focus implementation of IAjxpWidget
	 */
	focus: function(){
		if(webFXTreeHandler.selected)
		{
			webFXTreeHandler.selected.focus();
            if(webFXTreeHandler.selected.ajxpNode){
                ajaxplorer.getUserSelection().setSelectedNodes([webFXTreeHandler.selected.ajxpNode], this);
            }
		}
		webFXTreeHandler.setFocus(true);
		this.hasFocus = true;
	},
	
	/**
	 * Blur implementation of IAjxpWidget
	 */
	blur: function(){
		if(webFXTreeHandler.selected)
		{
			webFXTreeHandler.selected.blur();
		}
		webFXTreeHandler.setFocus(false);
		this.hasFocus = false;
	},
		
	/**
	 * Resize implementation of IAjxpWidget
	 */
	resize : function(){
        if(!this.options['fit'] || this.options['fit'] != 'content'){
            fitHeightToBottom(this.treeContainer, this.options['fitParent']);
        }
        if(this.scrollbar){
            this.scroller.setStyle({height:parseInt(this.treeContainer.getHeight())+'px'});
            this.scrollbar.recalculateLayout();
        }
        document.fire("ajaxplorer:resize-FoldersTree-" + this.htmlElement.id, this.htmlElement.getDimensions());
	},
	
	/**
	 * ShowElement implementation of IAjxpWidget
	 */
	showElement : function(show){
		if (show) this.treeContainer.show();
		else this.treeContainer.hide();
	},
	
	/**
	 * Sets the contextual menu
	 * @param protoMenu Proto.Menu 
	 */
	setContextualMenu: function(protoMenu){
        Event.observe(this.rootNodeId+'','contextmenu', function(event){
            this.select();
            this.action();
            Event.stop(event);
        }.bind(webFXTreeHandler.all[this.rootNodeId]));
         protoMenu.addElements('#'+this.rootNodeId+'');
		webFXTreeHandler.contextMenu = protoMenu;
	},
	
	/**
	 * Find a tree node by its path
	 * @param path String
	 * @returns WebFXTreeItem
	 */
	getNodeByPath : function(path){
		for(var key in webFXTreeHandler.all){
			if(webFXTreeHandler.all[key] && webFXTreeHandler.all[key].ajxpNode && webFXTreeHandler.all[key].ajxpNode.getPath() == path){
				return webFXTreeHandler.all[key];
			}
		}
        return undefined;
	},
	
	/**
	 * Finds the node and select it
	 * @param path String
	 */
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
		
	/**
	 * Transforms url to a path array
	 * @param url String
	 * @returns Array
	 */
	cleanPathToArray: function(url){
		var splitPath = url.split("/");
		var path = $A();
		var j = 0;
		for(var i=0; i<splitPath.length; i++)
		{
			if(splitPath[i] != '') 
			{
				path[j] = splitPath[i];
				j++;
			}
		}
		return path;		
	},
		
	/**
	 * Change the root node label
	 * @param newLabel String
	 * @param newIcon String
	 */
	changeRootLabel: function(newLabel, newIcon){
		this.changeNodeLabel(this.tree.id, newLabel, newIcon);	
	},
	
	/**
	 * Change a node label
	 * @param nodeId String the Id of the node (webFX speaking)
	 * @param newLabel String
	 * @param newIcon String
	 */
	changeNodeLabel: function(nodeId, newLabel, newIcon){	
		$(nodeId+'-label').update(newLabel);
		if(newIcon){
			var realNode = webFXTreeHandler.all[nodeId];
			realNode.icon = newIcon;
			realNode.openIcon = newIcon;
		}
	},


    /**
   	 * Inline Editing of label
   	 * @param callback Function Callback after the label is edited.
   	 */
   	switchCurrentLabelToEdition : function(callback){
   		var sel = webFXTreeHandler.selected;
        if(!sel) return;
        var nodeId = webFXTreeHandler.selected.id;
   		var item = this.treeContainer.down('#' + nodeId); // We assume this action was triggered with a single-selection active.
   		var offset = {top:0,left:0};
   		var scrollTop;

        var span = item.down('a');
        offset.top=1;
        offset.left=43;
        scrollTop = this.treeContainer.scrollTop;

   		var pos = item.cumulativeOffset();
   		var text = span.innerHTML;
   		var edit = new Element('input', {value:item.ajxpNode.getLabel('text'), id:'editbox'}).setStyle({
   			zIndex:5000,
   			position:'absolute',
   			marginLeft:'0px',
   			marginTop:'0px',
   			height:'24px',
               padding: 0
   		});
   		$(document.getElementsByTagName('body')[0]).insert({bottom:edit});
   		modal.showContent('editbox', (item.getWidth()-offset.left)+'', '20', true, false, {opacity:0.25, backgroundColor:'#fff'});
   		edit.setStyle({left:(pos.left+offset.left)+'px', top:(pos.top+offset.top-scrollTop)+'px'});
   		window.setTimeout(function(){
   			edit.focus();
   			var end = edit.getValue().lastIndexOf("\.");
   			if(end == -1){
   				edit.select();
   			}else{
   				var start = 0;
   				if(edit.setSelectionRange)
   				{
   					edit.setSelectionRange(start,end);
   				}
   				else if (edit.createTextRange) {
   					var range = edit.createTextRange();
   					range.collapse(true);
   					range.moveStart('character', start);
   					range.moveEnd('character', end);
   					range.select();
   				}
   			}

   		}, 300);
   		var onOkAction = function(){
   			var newValue = edit.getValue();
   			hideLightBox();
   			modal.close();
   			callback(item.ajxpNode, newValue);
   		};
   		edit.observe("keydown", function(event){
   			if(event.keyCode == Event.KEY_RETURN){
   				Event.stop(event);
   				onOkAction();
   			}
   		}.bind(this));
   		// Add ok / cancel button, for mobile devices among others
   		var buttons = modal.addSubmitCancel(edit, null, false, "after");
        buttons.addClassName("inlineEdition");
   		var ok = buttons.select('input[name="ok"]')[0];
   		ok.observe("click", onOkAction);
   		var origWidth = edit.getWidth()-44;
   		var newWidth = origWidth;
   		if(origWidth < 70){
   			// Offset edit box to be sure it's always big enough.
   			edit.setStyle({left:pos.left+offset.left - 70 + origWidth});
   			newWidth = 70;
   		}
   		edit.setStyle({width:newWidth+'px'});

   		buttons.select('input').invoke('setStyle', {
   			margin:0,
   			width:'22px',
   			border:0,
   			backgroundColor:'transparent'
   		});
   		buttons.setStyle({
   			position:'absolute',
   			width:'46px',
   			zIndex:2500,
   			left:(pos.left+offset.left+origWidth)+'px',
   			top:((pos.top+offset.top-scrollTop)-1)+'px'
   		});
   		var closeFunc = function(){
   			span.setStyle({color:''});
   			edit.remove();
   			buttons.remove();
   		};
   		span.setStyle({color:'#ddd'});
   		modal.setCloseAction(closeFunc);
   	}

});