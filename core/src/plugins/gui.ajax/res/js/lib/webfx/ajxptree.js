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

webFXTreeConfig.loadingText = "Loading...";

function splitOverlayIcons(ajxpNode){
    if(!ajxpNode.getMetadata().get("overlay_icon")  || !Modernizr.multiplebgs) return false;
    var ret = [];
    $A(ajxpNode.getMetadata().get("overlay_icon").split(",")).each(function(el){
        ret.push(resolveImageSource(el, "/images/overlays/ICON_SIZE", 8));
    });
    return ret;
}

function splitOverlayClasses(ajxpNode){
    if(!ajxpNode.getMetadata().get("overlay_class")  || ! window.ajaxplorer.currentThemeUsesIconFonts) return false;
    return ajxpNode.getMetadata().get("overlay_class").split(",");
}

function AJXPTree(rootNode, sAction, filter) {
	this.WebFXTree = WebFXTree;
	this.loaded = true;
	this.ajxpNode = rootNode;
	var icon = rootNode.getIcon();
	if(icon.indexOf(ajxpResourcesFolder+"/") != 0){
		icon = resolveImageSource(icon, "/images/mimes/ICON_SIZE", 16);
	}
	var openIcon = rootNode.getMetadata().get("openicon");
	if(openIcon){
		if(openIcon.indexOf(ajxpResourcesFolder+"/") != 0){
			openIcon = resolveImageSource(openIcon, "/images/mimes/ICON_SIZE", 16);
		}
	}else{
		openIcon = icon;
	}
	
	this.WebFXTree(rootNode.getLabel(), sAction, 'explorer', icon, openIcon);
	// setup default property values
	this.loading = false;
	this.loaded = false;
	this.errorText = "";
	if(filter){
		this.filter = filter;
 	}
    this.overlayIcon = splitOverlayIcons(rootNode);
    this.overlayClasses = splitOverlayClasses(rootNode);

	this._loadingItem = new WebFXTreeItem(MessageHash?MessageHash[466]:webFXTreeConfig.loadingText);
	if(this.open) this.ajxpNode.load();
	else{
		this.add(this._loadingItem);
	}
}

AJXPTree.prototype = new WebFXTree;

AJXPTree.prototype._webfxtree_expand = WebFXTree.prototype.expand;
AJXPTree.prototype.expand = function() {
	if(!this.ajxpNode.fake){
		this.ajxpNode.load();
	}
	this._webfxtree_expand();
};

AJXPTree.prototype.destroy = function(){
    if(this.ajxpNode) this.ajxpNode.stopObserving();
};

AJXPTree.prototype.setAjxpRootNode = function(rootNode){
	if(this.ajxpNode){
		var oldNode = this.ajxpNode;
	}
	this.ajxpNode = rootNode;	
	var clear = function(){
		this.open = false;
		while (this.childNodes.length > 0)
			this.childNodes[this.childNodes.length - 1].remove();
		this.loaded = false;
	};
	this.ajxpNode.observe("force_clear",  clear.bind(this));
	this.ajxpNode.observe("node_replaced",  clear.bind(this));
	this.attachListeners(this, rootNode);
	if(oldNode){
		oldNode.notify("node_replaced");
	}
	//this.ajxpNode.load();
};

AJXPTree.prototype.attachListeners = function(jsNode, ajxpNode){
	ajxpNode.observe("child_added", function(childPath){
		if(ajxpNode.getMetadata().get('paginationData')){
			var pData = ajxpNode.getMetadata().get('paginationData');
			if(!this.paginated){
				this.paginated = true;
				if(pData.get('dirsCount')!="0"){
                    var message = pData.get('overflowMessage');
                    if(MessageHash[message]) message = MessageHash[message];
					this.updateLabel(this.text + " (" + message+ ")");
				}
			}
			//return;
		}else if(this.paginated){
			this.paginated = false;
			this.updateLabel(this.text);
		}
		var child = ajxpNode.findChildByPath(childPath);
		if(child){
			var jsChild = _ajxpNodeToTree(child, this);
			if(jsChild){
				this.attachListeners(jsChild, child);
			}
		}
	}.bind(jsNode));
	ajxpNode.observe("node_replaced", function(newNode){
		// Should refresh label / icon
		if(jsNode.updateIcon){ 
			var ic = resolveImageSource(ajxpNode.getIcon(), "/images/mimes/ICON_SIZE", 16);
			var oic = ic;
			if(ajxpNode.getMetadata().get("openicon")){
				oic = resolveImageSource(ajxpNode.getMetadata().get("openicon"), "/images/mimes/ICON_SIZE", 16);
			}
			jsNode.updateIcon(ic, oic);
            jsNode.overlayIcon = splitOverlayIcons(ajxpNode);
            jsNode.overlayClasses = splitOverlayClasses(ajxpNode);
		}
		if(jsNode.updateLabel) jsNode.updateLabel(ajxpNode.getLabel());
	}.bind(jsNode));
    var remover = function(e){
        jsNode.remove();
        window.setTimeout(function(){
            ajxpNode.stopObserving("node_removed", remover);
        }, 200);
    };
	ajxpNode.observe("node_removed", remover);
	ajxpNode.observe("loading", function(){
		//this.add(this._loadingItem);
	}.bind(jsNode) );
	ajxpNode.observe("loaded", function(){
		this._loadingItem.remove();
		if(this.childNodes.length){
			this._webfxtree_expand();
		}
	}.bind(jsNode) );
};

function AJXPTreeItem(ajxpNode, sAction, eParent) {
	this.WebFXTreeItem = WebFXTreeItem;
	this.ajxpNode = ajxpNode;
	var icon = ajxpNode.getIcon();
	if(icon.indexOf(ajxpResourcesFolder+"/") != 0){
		icon = resolveImageSource(icon, "/images/mimes/ICON_SIZE", 16);
	}
	var openIcon = ajxpNode.getMetadata().get("openicon");
	if(openIcon){
		if(openIcon.indexOf(ajxpResourcesFolder+"/") != 0){
			openIcon = resolveImageSource(openIcon, "/images/mimes/ICON_SIZE", 16);
		}
	}else{
		openIcon = icon;
	}
	
	this.folder = true;
	this.WebFXTreeItem(
        ajxpNode.getLabel(),
        sAction,
        eParent,
        icon,
        (openIcon?openIcon:resolveImageSource("folder_open.png", "/images/mimes/ICON_SIZE", 16)),
        splitOverlayIcons(ajxpNode),
        splitOverlayClasses(ajxpNode)
    );

	this.loading = false;
	this.loaded = false;
	this.errorText = "";

	this._loadingItem = new WebFXTreeItem(MessageHash?MessageHash[466]:webFXTreeConfig.loadingText);
	if (this.open) {
		this.ajxpNode.load();
	}else{
		this.add(this._loadingItem);
	}
	webFXTreeHandler.all[this.id] = this;
}

AJXPTreeItem.prototype = new WebFXTreeItem;

AJXPTreeItem.prototype._webfxtree_expand = WebFXTreeItem.prototype.expand;
AJXPTreeItem.prototype.expand = function() {
	this.ajxpNode.load();
	this._webfxtree_expand();
};

AJXPTreeItem.prototype.attachListeners = AJXPTree.prototype.attachListeners;


/*
 * Helper functions
 */
// Converts an xml tree to a js tree. See article about xml tree format
function _ajxpNodeToTree(ajxpNode, parentNode) {
	if(parentNode.filter && !parentNode.filter(ajxpNode)){
		return false;
	}
	var jsNode = new AJXPTreeItem(ajxpNode, null, parentNode);	
	if(ajxpNode.isLoaded())
	{
		jsNode.loaded = true;
	}
	jsNode.filename = ajxpNode.getPath();	
	if(parentNode.filter){
		jsNode.filter = parentNode.filter;
	}
    jsNode.overlayIcon = splitOverlayIcons(ajxpNode);

	ajxpNode.getChildren().each(function(child){
		var newNode = _ajxpNodeToTree(child, jsNode);
		if(newNode){
			if(jsNode.filter){
				newNode.filter = jsNode.filter;
			}
            newNode.overlayIcon = splitOverlayIcons(child);
            newNode.overlayClasses = splitOverlayClasses(child);
			jsNode.add( newNode , false );
		}
	});	
	return jsNode;	
}