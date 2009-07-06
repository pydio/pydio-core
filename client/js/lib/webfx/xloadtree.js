/*----------------------------------------------------------------------------\
|                               XLoadTree 1.11                                |
|-----------------------------------------------------------------------------|
|                         Created by Erik Arvidsson                           |
|                  (http://webfx.eae.net/contact.html#erik)                   |
|                      For WebFX (http://webfx.eae.net/)                      |
|-----------------------------------------------------------------------------|
| An extension to xTree that allows sub trees to be loaded at runtime by      |
| reading XML files from the server. Works with IE5+ and Mozilla 1.0+         |
|-----------------------------------------------------------------------------|
|             Copyright (c) 2001, 2002, 2003, 2006 Erik Arvidsson             |
|-----------------------------------------------------------------------------|
| Licensed under the Apache License, Version 2.0 (the "License"); you may not |
| use this file except in compliance with the License.  You may obtain a copy |
| of the License at http://www.apache.org/licenses/LICENSE-2.0                |
| - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - |
| Unless  required  by  applicable law or  agreed  to  in  writing,  software |
| distributed under the License is distributed on an  "AS IS" BASIS,  WITHOUT |
| WARRANTIES OR  CONDITIONS OF ANY KIND,  either express or implied.  See the |
| License  for the  specific language  governing permissions  and limitations |
| under the License.                                                          |
|-----------------------------------------------------------------------------|
| Dependencies: xtree.js     - original xtree library                         |
|               xtree.css    - simple css styling of xtree                    |
|               xmlextras.js - provides xml http objects and xml document     |
|                              objects                                        |
|-----------------------------------------------------------------------------|
| 2001-09-27 | Original Version Posted.                                       |
| 2002-01-19 | Added some simple error handling and string templates for      |
|            | reporting the errors.                                          |
| 2002-01-28 | Fixed loading issues in IE50 and IE55 that made the tree load  |
|            | twice.                                                         |
| 2002-10-10 | (1.1) Added reload method that reloads the XML file from the   |
|            | server.                                                        |
| 2003-05-06 | Added support for target attribute                             |
| 2006-05-28 | Changed license to Apache Software License 2.0.                |
|-----------------------------------------------------------------------------|
| Created 2001-09-27 | All changes are in the log above. | Updated 2006-05-28 |
\----------------------------------------------------------------------------*/


webFXTreeConfig.loadingText = "Loading...";
webFXTreeConfig.loadErrorTextTemplate = "Error loading \"%1%\"";
webFXTreeConfig.emptyErrorTextTemplate = "Error \"%1%\" does not contain any tree items";

/*
 * WebFXLoadTree class
 */

function WebFXLoadTree(sText, sXmlSrc, sAction, sBehavior, sIcon, sOpenIcon) {
	// call super
	this.WebFXTree = WebFXTree;
	this.WebFXTree(sText, sAction, sBehavior, sIcon, sOpenIcon);

	// setup default property values
	this.src = sXmlSrc;
	this.loading = false;
	this.loaded = false;
	this.errorText = "";

	// check start state and load if open
	if (this.open)
		_startLoadXmlTree(this.src, this);
	else {
		// and create loading item if not
		this._loadingItem = new WebFXTreeItem(webFXTreeConfig.loadingText);
		this.add(this._loadingItem);
	}	
}

WebFXLoadTree.prototype = new WebFXTree;

// override the expand method to load the xml file
WebFXLoadTree.prototype._webfxtree_expand = WebFXTree.prototype.expand;
WebFXLoadTree.prototype.expand = function() {
	if (!this.loaded && !this.loading) {
		// load
		_startLoadXmlTree(this.src, this);
	}
	this._webfxtree_expand();
};

/*
 * WebFXLoadTreeItem class
 */

function WebFXLoadTreeItem(sText, sXmlSrc, sAction, eParent, sIcon, sOpenIcon) {
	// call super
	this.WebFXTreeItem = WebFXTreeItem;
	this.WebFXTreeItem(sText, sAction, eParent, sIcon, sOpenIcon);

	// setup default property values
	this.src = sXmlSrc;
	this.loading = false;
	this.loaded = false;
	this.errorText = "";

	// check start state and load if open
	if (this.open)
		_startLoadXmlTree(this.src, this);
	else {
		// and create loading item if not
		this._loadingItem = new WebFXTreeItem(webFXTreeConfig.loadingText);
		this.add(this._loadingItem);
	}
	
}

WebFXLoadTreeItem.prototype = new WebFXTreeItem;

// override the expand method to load the xml file
WebFXLoadTreeItem.prototype._webfxtreeitem_expand = WebFXTreeItem.prototype.expand;
WebFXLoadTreeItem.prototype.expand = function() {
	if (!this.loaded && !this.loading) {
		// load
		_startLoadXmlTree(this.src, this);
	}
	this._webfxtreeitem_expand();
};

// reloads the src file if already loaded
WebFXLoadTree.prototype.reload =
WebFXLoadTreeItem.prototype.reload = function () {
	// if loading do nothing
	if (this.loaded) {
		var open = this.open;
		// remove
		while (this.childNodes.length > 0)
			this.childNodes[this.childNodes.length - 1].remove();

		this.loaded = false;

		this._loadingItem = new WebFXTreeItem(webFXTreeConfig.loadingText);
		this.add(this._loadingItem);

		if (open)
			this.expand();
	}
	else if (this.open && !this.loading)	
		_startLoadXmlTree(this.src, this);
		
	if(!this.open && !this.loading) this.toggle();
};

/*
 * Helper functions
 */

// creates the xmlhttp object and starts the load of the xml document
function _startLoadXmlTree(sSrc, jsNode) {
	if (jsNode.loading || jsNode.loaded)
		return;
	jsNode.loading = true;	
	var connexion = new Connexion(encodeURI(decodeURIComponent(sSrc)).replace('&amp;', '%26'));
	connexion.onComplete = function(transport){		
		_xmlFileLoaded(transport.responseXML, jsNode);
	};
	connexion.sendAsync();	
}


// Converts an xml tree to a js tree. See article about xml tree format
function _xmlTreeToJsTree(oNode) {
	// retreive attributes
	var text = oNode.getAttribute("text");
	var action = oNode.getAttribute("action");
	if(!action){
		action = "javascript:ajaxplorer.getFoldersTree().clickNode(CURRENT_ID)";
	}
	var parent = null;
	var icon = oNode.getAttribute("icon");
	if(icon.indexOf(ajxpResourcesFolder+"/") != 0){
		icon = resolveImageSource(icon, "/images/crystal/mimes/ICON_SIZE", 16);
	}
	var openIcon = oNode.getAttribute("openicon");
	if(openIcon){
		if(openIcon.indexOf(ajxpResourcesFolder+"/") != 0){
			openIcon = resolveImageSource(openIcon, "/images/crystal/mimes/ICON_SIZE", 16);
		}
	}else{
		openIcon = icon;
	}
	var src = oNode.getAttribute("src");
	var target = oNode.getAttribute("target");
	var preloaded = oNode.getAttribute("preloaded");
	var recycle = oNode.getAttribute("is_recycle");
	var folderFullName = oNode.getAttribute("filename");
	// create jsNode
	var jsNode;
	if (src != null && src != "")
		jsNode = new WebFXLoadTreeItem(text, src, action, parent, icon, openIcon);
	else
		jsNode = new WebFXTreeItem(text, action, parent, icon, openIcon);
		
	if (target != "")
		jsNode.target = target;
		
	if (recycle != null && !(ajaxplorer && ajaxplorer.actionBar && ajaxplorer.actionBar.treeCopyActive)){
		webFXTreeHandler.recycleNode = jsNode.id;
		jsNode.isRecycle = true;
	}

	if(oNode.getAttribute('ajxp_node') && oNode.getAttribute('ajxp_node')=="true"){
		jsNode.ajxpNode = true;
		webFXTreeHandler.ajxpNodes[getBaseName(folderFullName)] = jsNode.id;
	}
	
	if(oNode.getAttribute('ajxp_mime')){
		jsNode.ajxpMime = oNode.getAttribute('ajxp_mime');
	}
	
	if(src != null && src != "" && preloaded != null && preloaded == 'true')
	{
		jsNode.loaded = true;
	}
	jsNode.filename = folderFullName;
	// go through childNOdes
	var cs = oNode.childNodes;
	var l = cs.length;
	for (var i = 0; i < l; i++) {
		if (cs[i].tagName == "tree")
			jsNode.add( _xmlTreeToJsTree(cs[i]), true );
	}

	return jsNode;
}

// Inserts an xml document as a subtree to the provided node
function _xmlFileLoaded(oXmlDoc, jsParentNode) {
	if (jsParentNode.loaded)
		return;

	var bIndent = false;
	var bAnyChildren = false;
	jsParentNode.loaded = true;
	jsParentNode.loading = false;

	// check that the load of the xml file went well
	if( oXmlDoc == null || oXmlDoc.documentElement == null) {
		alert(oXmlDoc.xml);
		jsParentNode.errorText = parseTemplateString(webFXTreeConfig.loadErrorTextTemplate,
							jsParentNode.src);
	}
	else {
		// there is one extra level of tree elements
		var root = oXmlDoc.documentElement;

		// loop through all tree children
		var cs = root.childNodes;
		var l = cs.length;		
		for (var i = 0; i < l; i++) {			
			if (cs[i].tagName == "tree") {
				bAnyChildren = true;
				bIndent = true;
				jsParentNode.add( _xmlTreeToJsTree(cs[i]), true);
			}
			else if(cs[i].tagName == "error")
			{
				jsParentNode.errorText = cs[i].firstChild.nodeValue;
			}
			
		}

		// if no children we got an error
		//if (!bAnyChildren)
			//jsParentNode.errorText = parseTemplateString(webFXTreeConfig.emptyErrorTextTemplate,
			//							jsParentNode.src);
	}

	// remove dummy
	if (jsParentNode._loadingItem != null) {
		jsParentNode._loadingItem.remove();
		bIndent = true;
	}

	if (bIndent) {
		// indent now that all items are added
		jsParentNode.indent();
	}

	// show error in status bar
	if (jsParentNode.errorText != ""){
		window.status = jsParentNode.errorText;
	}

	if(ajaxplorer) ajaxplorer.foldersTree.asyncExpandAndSelect();
	if(modal.pageLoading) modal.updateLoadingProgress('Tree Loaded');
}

// parses a string and replaces %n% with argument nr n
function parseTemplateString(sTemplate) {
	var args = arguments;
	var s = sTemplate;

	s = s.replace(/\%\%/g, "%");

	for (var i = 1; i < args.length; i++)
		s = s.replace( new RegExp("\%" + i + "\%", "g"), args[i] ) ;

	return s;
}
