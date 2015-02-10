/*----------------------------------------------------------------------------\
|                       Cross Browser Tree Widget 1.17                        |
|-----------------------------------------------------------------------------|
|                          Created by Emil A Eklund                           |
|                  (http://webfx.eae.net/contact.html#emil)                   |
|                      For WebFX (http://webfx.eae.net/)                      |
|-----------------------------------------------------------------------------|
| An object based tree widget,  emulating the one found in microsoft windows, |
| with persistence using cookies. Works in IE 5+, Mozilla and konqueror 3.    |
|-----------------------------------------------------------------------------|
|          Copyright (c) 2000, 2001, 2002, 2003, 2006 Emil A Eklund           |
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
| Dependencies: xtree.css (To set up the CSS of the tree classes)             |
|-----------------------------------------------------------------------------|
| 2001-01-10 | Original Version Posted.                                       |
| 2001-03-18 | Added getSelected and get/setBehavior  that can make it behave |
|            | more like windows explorer, check usage for more information.  |
| 2001-09-23 | Version 1.1 - New features included  keyboard  navigation (ie) |
|            | and the ability  to add and  remove nodes dynamically and some |
|            | other small tweaks and fixes.                                  |
| 2002-01-27 | Version 1.11 - Bug fixes and improved mozilla support.         |
| 2002-06-11 | Version 1.12 - Fixed a bug that prevented the indentation line |
|            | from  updating correctly  under some  circumstances.  This bug |
|            | happened when removing the last item in a subtree and items in |
|            | siblings to the remove subtree where not correctly updated.    |
| 2002-06-13 | Fixed a few minor bugs cased by the 1.12 bug-fix.              |
| 2002-08-20 | Added usePersistence flag to allow disable of cookies.         |
| 2002-10-23 | (1.14) Fixed a plus icon issue                                 |
| 2002-10-29 | (1.15) Last changes broke more than they fixed. This version   |
|            | is based on 1.13 and fixes the bugs 1.14 fixed withou breaking |
|            | lots of other things.                                          |
| 2003-02-15 | The  selected node can now be made visible even when  the tree |
|            | control  loses focus.  It uses a new class  declaration in the |
|            | css file '.webfx-tree-item a.selected-inactive', by default it |
|            | puts a light-gray rectangle around the selected node.          |
| 2003-03-16 | Adding target support after lots of lobbying...                |
| 2006-05-26 | Changed license to Apache Software License 2.0.                |
|-----------------------------------------------------------------------------|
| Created 2000-12-11 | All changes are in the log above. | Updated 2006-05-26 |
\----------------------------------------------------------------------------*/
var	webFXTreeConfig = {
		rootIcon        : '/images/foldericon.png',
		openRootIcon    : '/images/openfoldericon.png',
		folderIcon      : '/images/foldericon.png',
		openFolderIcon  : '/images/openfoldericon.png',
		fileIcon        : '/images/foldericon.png',
		iIcon           : '/images/I.png',
		lIcon           : '/images/L.png',
		lMinusIcon      : '/images/Lminus.png',
		lPlusIcon       : '/images/Lplus.png',
		lMinusIconActive: '/images/Lminus-active.png',
		lPlusIconActive : '/images/Lplus-active.png',
		tIcon           : '/images/T.png',
		tMinusIcon      : '/images/Tminus.png',
		tPlusIcon       : '/images/Tplus.png',
		tMinusIconActive: '/images/Tminus-active.png',
		tPlusIconActive : '/images/Tplus-active.png',
		blankIcon       : '/images/blank.png',
		defaultText     : 'Tree Item',
		defaultAction   : function(e){},
		defaultBehavior : 'classic',
		zipRegexp		: new RegExp(/\.zip$/),
		usePersistence	: false
};
Event.observe(document, 'ajaxplorer:boot_loaded', function(){
	var resourcesFolder = window.ajxpResourcesFolder;
	webFXTreeConfig.rootIcon        = resourcesFolder+'/images/foldericon.png';
	webFXTreeConfig.openRootIcon    = resourcesFolder+'/images/openfoldericon.png';
	webFXTreeConfig.folderIcon      = resourcesFolder+'/images/foldericon.png';
	webFXTreeConfig.openFolderIcon  = resourcesFolder+'/images/openfoldericon.png';
	webFXTreeConfig.fileIcon        = resourcesFolder+'/images/foldericon.png';
	webFXTreeConfig.iIcon           = resourcesFolder+'/images/I.png';
	webFXTreeConfig.lIcon           = resourcesFolder+'/images/L.png';
	webFXTreeConfig.lMinusIcon      = resourcesFolder+'/images/Lminus.png';
	webFXTreeConfig.lPlusIcon       = resourcesFolder+'/images/Lplus.png';
	webFXTreeConfig.lMinusIconActive= resourcesFolder+'/images/Lminus-active.png';
	webFXTreeConfig.lPlusIconActive = resourcesFolder+'/images/Lplus-active.png';
	webFXTreeConfig.tIcon           = resourcesFolder+'/images/T.png';
	webFXTreeConfig.tMinusIcon      = resourcesFolder+'/images/Tminus.png';
	webFXTreeConfig.tPlusIcon       = resourcesFolder+'/images/Tplus.png';
	webFXTreeConfig.tMinusIconActive= resourcesFolder+'/images/Tminus-active.png';
	webFXTreeConfig.tPlusIconActive = resourcesFolder+'/images/Tplus-active.png';
	webFXTreeConfig.blankIcon       = resourcesFolder+'/images/blank.png';
});

var webFXTreeHandler = {
	idCounter : 0,
	idPrefix  : "webfx-tree-object-",
	all       : {},
	behavior  : null,
	selected  : null,
	contextMenu: null,
	onSelect  : null, /* should be part of tree, not handler */
	getId     : function() { return this.idPrefix + this.idCounter++; },
	toggle    : function (oItem) { this.all[oItem.id.replace('-plus','')].toggle(); },
	select    : function (oItem) { this.all[oItem.id].select(); },
	hasFocus  : false,
	focus     : function (oItem) { if(this.all[oItem.id.replace('-anchor','')]) this.all[oItem.id.replace('-anchor','')].focus(); },
	blur      : function (oItem) { if(this.all[oItem.id.replace('-anchor','')]) this.all[oItem.id.replace('-anchor','')].blur();},
	setFocus  : function (bFocus){ this.hasFocus = bFocus;},
	keydown   : function (oItem, e) { return this.all[oItem.id].keydown(e.keyCode); },
	linkKeyPress : function(oItem, e){if(!this.hasFocus || e.keyCode == 9) return false;return true;},
	cookies   : new WebFXCookie(),
	insertHTMLBeforeEnd	:	function (oElement, sHTML) {
		if(!oElement) return;
		if (oElement && oElement.insertAdjacentHTML) {
			oElement.insertAdjacentHTML("BeforeEnd", sHTML) ;
			return;
		}
		var df;	// DocumentFragment
		var r = oElement.ownerDocument.createRange();
		r.selectNodeContents(oElement);
		r.collapse(false);
		df = r.createContextualFragment(sHTML);
		oElement.appendChild(df);
	}
};

/*
 * WebFXCookie class
 */

function WebFXCookie() {
	if (document.cookie.length) { this.cookies = ' ' + document.cookie; }
}

WebFXCookie.prototype.setCookie = function (key, value) {
	document.cookie = key + "=" + escape(value);
};

WebFXCookie.prototype.getCookie = function (key) {
	if (this.cookies) {
		var start = this.cookies.indexOf(' ' + key + '=');
		if (start == -1) { return null; }
		var end = this.cookies.indexOf(";", start);
		if (end == -1) { end = this.cookies.length; }
		end -= start;
		var cookie = this.cookies.substr(start,end);
		return unescape(cookie.substr(cookie.indexOf('=') + 1, cookie.length - cookie.indexOf('=') + 1));
	}
	else { return null; }
};

/*
 * WebFXTreeAbstractNode class
 */

function WebFXTreeAbstractNode(sText, sAction) {
	this.childNodes  = [];
	this.id     = webFXTreeHandler.getId();
	this.text   = sText || webFXTreeConfig.defaultText;
	this.action = sAction || null;
	this.url 	= "/";
	this._last  = false;
	webFXTreeHandler.all[this.id] = this;
}

function WebFXTreeBufferTreeChange(){
    if (window.webfxtreebufferTimer) {
        window.clearTimeout(window.webfxtreebufferTimer);
    }
    window.webfxtreebufferTimer = window.setTimeout(function(){
        document.fire("ajaxplorer:tree_change");
    }, 200);
}

/*
 * To speed thing up if you're adding multiple nodes at once (after load)
 * use the bNoIdent parameter to prevent automatic re-indentation and call
 * the obj.ident() method manually once all nodes has been added.
 */

WebFXTreeAbstractNode.prototype.add = function (node, bNoIdent) {
	node.parentNode = this;	
	var url = node.parentNode.url;
	if(node.parentNode.inZip) node.inZip = true;
	else{		
		if(webFXTreeConfig.zipRegexp.test(node.text) !== false){
			node.inZip = true;
		}
	}
	if(!node.action && node.parentNode.action){
		node.action = node.parentNode.action;
	}
	
	this.childNodes[this.childNodes.length] = node;
	var root = this;
	if (this.childNodes.length >= 2) {
		this.childNodes[this.childNodes.length - 2]._last = false;
	}
	while (root.parentNode) { root = root.parentNode; }
	if (root.rendered) {
		if (this.childNodes.length >= 2) {
			$(this.childNodes[this.childNodes.length - 2].id + '-plus').src = ((this.childNodes[this.childNodes.length -2].folder)?((this.childNodes[this.childNodes.length -2].open)?webFXTreeConfig.tMinusIcon:webFXTreeConfig.tPlusIcon):webFXTreeConfig.tIcon);
			this.childNodes[this.childNodes.length - 2].plusIcon = webFXTreeConfig.tPlusIcon;
			this.childNodes[this.childNodes.length - 2].minusIcon = webFXTreeConfig.tMinusIcon;
			this.childNodes[this.childNodes.length - 2]._last = false;
		}
		this._last = true;
		var foo = this;
		while (foo.parentNode) {
			for (var i = 0; i < foo.parentNode.childNodes.length; i++) {
				if (foo.id == foo.parentNode.childNodes[i].id) { break; }
			}
			if (i == foo.parentNode.childNodes.length - 1) { foo.parentNode._last = true; }
			else { foo.parentNode._last = false; }
			foo = foo.parentNode;
		}
		$(this.id + '-cont').insert(node.toString());
		$(node.id).ajxpNode = node.ajxpNode;
		if(!node.inZip){
			AjxpDroppables.add(node.id, node.ajxpNode);
		}		
		//new Draggable(node.id, {revert:true,ghosting:true,constraint:'vertical'});
		if(webFXTreeHandler.contextMenu){
			Event.observe(node.id+'','contextmenu', function(event){
				this.select();
				this.action();
				Event.stop(event);
			}.bind(node));
			 webFXTreeHandler.contextMenu.addElements('#'+node.id+'');
		}
		Event.observe(node.id,'click', function(event){
			this.select();
			this.action();
			Event.stop(event);
		}.bind(node));
		Event.observe(node.id,'dblclick', function(event){
			this.toggle();
			Event.stop(event);
		}.bind(node));
		Event.observe(node.id+'-plus','click' , function(event){
			this.toggle();
			Event.stop(event);
		}.bind(node));
		if ((!this.folder) && (!this.openIcon)) {
			this.icon = webFXTreeConfig.folderIcon;
			this.openIcon = webFXTreeConfig.openFolderIcon;
		}
		if (!this.folder) { this.folder = true; this.collapse(true); }
		if (!bNoIdent) { this.indent(); }
		if (this.ajxpNode && this.ajxpNode.fake){
			if(this.parentNode){
				this.parentNode.expand();
			}
			this.expand();
		}
        if(Prototype.Browser.IE || Prototype.Browser.Opera){
            window.setTimeout(function(){
                var sum = 0;
                if($(node.id)) $(node.id).childElements().each(function(el){sum += el.getWidth();});
                if(sum) $(node.id).setStyle({width:Math.max(sum+50,$(node.id).parentNode.getWidth())+'px'});
            }, 100);
        }
	}
    WebFXTreeBufferTreeChange();
	return node;
};


WebFXTreeAbstractNode.prototype.updateLabel = function(label){
	if($(this.id+'-label')) $(this.id+'-label').update(label);	
};

WebFXTreeAbstractNode.prototype.setLabelIcon = function(icon){
    if(!$(this.id+'-label')) return;
    var label = $(this.id+'-label');
    var bgOverlayImage = "url('"+icon+"')";
    var bgOverlayPosition = '4px 1px';

    if(this.overlayClasses){

        var d = label.down('div.overlay_icon_div');
        if(!d) {
            d = new Element('div', {className:'overlay_icon_div'});
            label.insert(d);
        }else{
            d.update('');
        }
        this.overlayClasses.each(function(c){
            d.insert(new Element('span', {className: c+ ' overlay-class-span'}));
        });

    }else if(this.overlayIcon){
        switch(this.overlayIcon.length){
            case 1:
                bgOverlayPosition = '14px 11px, 4px 1px';
                bgOverlayImage = 'url("'+this.overlayIcon[0]+'"), ';
            break;
            case 2:
                bgOverlayPosition = '2px 11px, 14px 11px, 4px 1px';
                bgOverlayImage = 'url("'+this.overlayIcon[0]+'"), url("'+this.overlayIcon[1]+'"), ';
            break;
            case 3:
                bgOverlayPosition = '14px 2px, 2px 11px, 14px 11px, 4px 1px';
                bgOverlayImage = 'url("'+this.overlayIcon[0]+'"), url("'+this.overlayIcon[1]+'"), url("'+this.overlayIcon[2]+'"), ';
            break;
            case 4:
            default:
                bgOverlayPosition = '2px 2px, 14px 2px, 2px 11px, 14px 11px, 4px 1px';
                bgOverlayImage = 'url("'+this.overlayIcon[0]+'"), url("'+this.overlayIcon[1]+'"), url("'+this.overlayIcon[2]+'"), url("'+this.overlayIcon[3]+'"), ';
            break;
        }
        bgOverlayImage += " url('"+icon+"')";
    }

    label.setStyle({
        backgroundImage:bgOverlayImage,
        backgroundPosition:bgOverlayPosition
    });
};

WebFXTreeAbstractNode.prototype.toggle = function() {
	if (this.folder) {
		if (this.open) { this.collapse() ; }
		else { this.expand() ; }
	}
} ;

WebFXTreeAbstractNode.prototype.select = function() {
	if($(this.id + '-anchor')) {
        try{
            $(this.id + '-anchor').focus();
            webFXTreeHandler.focus(this);
            if(!this.scrollContainer){
                var root = this;
                while (root.parentNode) { root = root.parentNode; }
                this.rootOffset = $(root.id).offsetTop;
                this.scrollContainer = $(root.id).parentNode;
            }
            if(this.scrollContainer.scrollerInstance){
                this.scrollContainer.scrollerInstance.scrollTo(this.scrollContainer.scrollTop);
                var oEl = $(this.id);
                // CHECK THAT SCROLLING IS OK
                var elHeight = $(oEl).getHeight();
                var scrollOffset = oEl.offsetTop - this.rootOffset;
                var parentHeight = this.scrollContainer.getHeight();
                var parentScrollTop = this.scrollContainer.scrollTop;

                var sTop = -1;
                if(scrollOffset+elHeight > (parentHeight+parentScrollTop)){
                    sTop = scrollOffset-parentHeight+elHeight;
                }else if(scrollOffset < (parentScrollTop)){
                    sTop = scrollOffset-elHeight;
                }
                if(sTop != -1){
                    this.scrollContainer.scrollerInstance.scrollTo(sTop);
                }
            }
        }catch(e){
            if(console) console.log(e);
        }

    }
};

WebFXTreeAbstractNode.prototype.deSelect = function() {
	if($(this.id + '-anchor')) $(this.id + '-anchor').className = '';
	webFXTreeHandler.selected = null;
	if($(this.id)) $(this.id).className = 'webfx-tree-item';
} ;

WebFXTreeAbstractNode.prototype.focus = function() {
	if ((webFXTreeHandler.selected) && (webFXTreeHandler.selected != this)) { webFXTreeHandler.selected.deSelect(); }
	webFXTreeHandler.selected = this;
	if ((this.openIcon) && (webFXTreeHandler.behavior != 'classic')) { 
		this.setLabelIcon(this.openIcon);
	}
	try{
		if($(this.id + '-anchor')) $(this.id + '-anchor').focus();
	}catch(e){}
	$(this.id).className = 'webfx-tree-item selected-webfx-tree-item';
	if (webFXTreeHandler.onSelect) { webFXTreeHandler.onSelect(this); }	
} ;

WebFXTreeAbstractNode.prototype.blur = function() {
	if(!$(this.id)) return;
	if ((this.openIcon) && (webFXTreeHandler.behavior != 'classic')) { 
		this.setLabelIcon(this.icon);
	}
	if(webFXTreeHandler.selected == this)
	{		
		$(this.id).className = 'webfx-tree-item selected-webfx-tree-item-inactive';
	}
	else
	{
		$(this.id).className = 'webfx-tree-item';
	}
	if(Prototype.Browser.IE)
	{
		if($(this.id + '-anchor')) $(this.id + '-anchor').blur();
	}
} ;

WebFXTreeAbstractNode.prototype.doExpand = function() {
	if (webFXTreeHandler.behavior == 'classic') { 
		this.setLabelIcon(this.openIcon);
	}
	if (this.childNodes.length && $(this.id + '-cont')) {  $(this.id + '-cont').style.display = 'block'; }
	this.open = true;
	if (webFXTreeConfig.usePersistence) {
		webFXTreeHandler.cookies.setCookie(this.id.substr(18,this.id.length - 18), '1');
	}
    WebFXTreeBufferTreeChange();
} ;

WebFXTreeAbstractNode.prototype.doCollapse = function() {
	if (webFXTreeHandler.behavior == 'classic') {
		this.setLabelIcon(this.icon);
	}
	if (this.childNodes.length) { $(this.id + '-cont').style.display = 'none'; }
	this.open = false;
	if (webFXTreeConfig.usePersistence) {
		webFXTreeHandler.cookies.setCookie(this.id.substr(18,this.id.length - 18), '0');
	}
    WebFXTreeBufferTreeChange();
} ;

WebFXTreeAbstractNode.prototype.expandAll = function() {
	this.expandChildren();
	if ((this.folder) && (!this.open)) { this.expand(); }
} ;

WebFXTreeAbstractNode.prototype.expandChildren = function() {
	for (var i = 0; i < this.childNodes.length; i++) {
		this.childNodes[i].expandAll();
} } ;

WebFXTreeAbstractNode.prototype.collapseAll = function() {
	this.collapseChildren();
	if ((this.folder) && (this.open)) { this.collapse(true); }
};

WebFXTreeAbstractNode.prototype.collapseChildren = function() {
	for (var i = 0; i < this.childNodes.length; i++) {
		this.childNodes[i].collapseAll();
} };

WebFXTreeAbstractNode.prototype.indent = function(lvl, del, last, level, nodesLeft) {
	/*
	 * Since we only want to modify items one level below ourself,
	 * and since the rightmost indentation position is occupied by
	 * the plus icon we set this to -2
	 */
	if (lvl == null) { lvl = -2; }
	var state = 0;
	for (var i = this.childNodes.length - 1; i >= 0 ; i--) {
		state = this.childNodes[i].indent(lvl + 1, del, last, level);
		if (state) { return; }
	}
	if (del) {
		if ((level >= this._level) && ($(this.id + '-plus'))) {
			if (this.folder) {
				$(this.id + '-plus').src = (this.open)?webFXTreeConfig.lMinusIcon:webFXTreeConfig.lPlusIcon;
				this.plusIcon = webFXTreeConfig.lPlusIcon;
				this.minusIcon = webFXTreeConfig.lMinusIcon;
			}
			else if (nodesLeft) { $(this.id + '-plus').src = webFXTreeConfig.lIcon; }
			return 1;
	}	}
	var foo = $(this.id + '-indent-' + lvl);
	if (foo) {
		if ((foo._last) || ((del) && (last))) { foo.src =  webFXTreeConfig.blankIcon; }
		else { foo.src =  webFXTreeConfig.iIcon; }
	}
	return 0;
} ;

/*
 * WebFXTree class
 */

function WebFXTree(sText, sAction, sBehavior, sIcon, sOpenIcon) {
	this.base = WebFXTreeAbstractNode;
	this.base(sText, sAction);
	this.icon      = sIcon || webFXTreeConfig.rootIcon;
	this.openIcon  = sOpenIcon || webFXTreeConfig.openRootIcon;
	/* Defaults to open */
	if (webFXTreeConfig.usePersistence) {
		this.open  = (webFXTreeHandler.cookies.getCookie(this.id.substr(18,this.id.length - 18)) == '0')?false:true;
	} else { this.open  = true; }
	this.folder    = true;
	this.rendered  = false;
	this.onSelect  = null;	
	if (!webFXTreeHandler.behavior) {  webFXTreeHandler.behavior = sBehavior || webFXTreeConfig.defaultBehavior; }
}

WebFXTree.prototype = new WebFXTreeAbstractNode;

WebFXTree.prototype.setBehavior = function (sBehavior) {
	webFXTreeHandler.behavior =  sBehavior;
};

WebFXTree.prototype.getBehavior = function (sBehavior) {
	return webFXTreeHandler.behavior;
};

WebFXTree.prototype.getSelected = function() {
	if (webFXTreeHandler.selected) { return webFXTreeHandler.selected; }
	else { return null; }
} ;

WebFXTree.prototype.remove = function() { } ;

WebFXTree.prototype.expand = function() {
	this.doExpand();
} ;

WebFXTree.prototype.collapse = function(b) {
	if (!b) { try{this.focus();}catch(e){} }
	this.doCollapse();
} ;

WebFXTree.prototype.getFirst = function() {
	return null;
} ;

WebFXTree.prototype.getLast = function() {
	return null;
} ;

WebFXTree.prototype.getNextSibling = function() {
	return null;
} ;

WebFXTree.prototype.getPreviousSibling = function() {
	return null;
} ;

WebFXTree.prototype.keydown = function(key) {
	if(!webFXTreeHandler.hasFocus) return true;
	if( key == 9) return false;
	if (key == 39) {
		if (!this.open) { this.expand(); }
		else if (this.childNodes.length) { this.childNodes[0].select(); }
		return false;
	}
	if (key == 37) { this.collapse(); return false; }
	if ((key == 40) && (this.open) && (this.childNodes.length)) { 
		this.childNodes[0].select();
		var toExec = this.childNodes[0];
		if(WebFXtimer) clearTimeout(WebFXtimer);
		WebFXtimer = window.setTimeout(toExec.action.bind(toExec), 1000);
		return false; 		
	}	
	return true;
} ;

WebFXTree.prototype.toString = function() {
		
	var str = "<div id=\"" + this.id + "\" ondblclick=\"webFXTreeHandler.toggle(this);\" class=\"webfx-tree-item\" onkeydown=\"return webFXTreeHandler.keydown(this, event)\">" +
		"<a href=\"/\" id=\"" + this.id + "-anchor\" onkeydown=\"return webFXTreeHandler.linkKeyPress(this, event);\"  onfocus=\"webFXTreeHandler.focus(this);\" onblur=\"webFXTreeHandler.blur(this);\"" +
		(this.target ? " target=\"" + this.target + "\"" : "") +
		">" + '<span id=\"' +this.id+ '-label\" style="background-image:url(\''+ ((webFXTreeHandler.behavior == 'classic' && this.open)?this.openIcon:this.icon) +'\');">' + this.text + "</span>" + "</a></div>" +
		"<div id=\"" + this.id + "-cont\" class=\"webfx-tree-container first_container\" style=\"display: " + ((this.open)?'block':'none') + ";\">";
	var sb = [];
	for (var i = 0; i < this.childNodes.length; i++) {
		sb[i] = this.childNodes[i].toString(i, this.childNodes.length);
	}
	this.rendered = true;
	return str + sb.join("") + "</div>";
};

/*
 * WebFXTreeItem class
 */

function WebFXTreeItem(sText, sAction, eParent, sIcon, sOpenIcon, sOverlayIcon, sOverlayClasses) {
	this.base = WebFXTreeAbstractNode;
	this.base(sText, sAction);
	/* Defaults to close */
	if (webFXTreeConfig.usePersistence) {
		this.open = (webFXTreeHandler.cookies.getCookie(this.id.substr(18,this.id.length - 18)) == '1')?true:false;
	} else { this.open = false; }
	if (sIcon) { this.icon = sIcon; }
	if (sOpenIcon) { this.openIcon = sOpenIcon; }
    if (sOverlayIcon) { this.overlayIcon = sOverlayIcon; }
    if (sOverlayClasses) { this.overlayClasses = sOverlayClasses; }
	if (eParent) { eParent.add(this); }
}

WebFXTreeItem.prototype = new WebFXTreeAbstractNode;

WebFXTreeItem.prototype.updateIcon = function(icon, openIcon){
	if(openIcon) this.openIcon = openIcon;
	else this.openIcon = icon;
	this.icon = icon;
	this.setLabelIcon((this.open && webFXTreeHandler.behavior != 'classic'?this.openIcon:icon));
};


WebFXTreeItem.prototype.remove = function() {
	if(!$(this.id+'-plus')) return;
	var iconSrc = $(this.id + '-plus').src;
	var parentNode = this.parentNode;
	var prevSibling = this.getPreviousSibling(true);
	var nextSibling = this.getNextSibling(true);
	var folder = this.parentNode.folder;
	var last = ((nextSibling) && (nextSibling.parentNode) && (nextSibling.parentNode.id == parentNode.id))?false:true;
	//this.getPreviousSibling().focus();
	this._remove();
	Droppables.remove($(this.id));
	if(webFXTreeHandler.contextMenu) webFXTreeHandler.contextMenu.removeElements('#'+this.id);
	if (parentNode.childNodes.length == 0) {
		$(parentNode.id + '-cont').style.display = 'none';
		parentNode.doCollapse();
		parentNode.folder = false;
		parentNode.open = false;
	}
	if (!nextSibling || last) { parentNode.indent(null, true, last, this._level, parentNode.childNodes.length); }
	if ((prevSibling == parentNode) && !(parentNode.childNodes.length)) {
		prevSibling.folder = false;
		prevSibling.open = false;
		if($(prevSibling.id + '-plus'))
		{
            $(prevSibling.id + '-plus').src = this.zeroIcon;
            prevSibling.setLabelIcon((webFXTreeHandler.all[prevSibling.id].icon?webFXTreeHandler.all[prevSibling.id].icon:webFXTreeConfig.fileIcon));
		}
	}
	if ($(prevSibling.id + '-plus')) {
		if (parentNode == prevSibling.parentNode) {
            $(prevSibling.id + '-plus').src = this.zeroIcon;
		}
	}
} ;

WebFXTreeItem.prototype._remove = function() {
	for (var i = this.childNodes.length - 1; i >= 0; i--) {
		this.childNodes[i]._remove();
 	}
	for (var i = 0; i < this.parentNode.childNodes.length; i++) {
		if (this == this.parentNode.childNodes[i]) {
			for (var j = i; j < this.parentNode.childNodes.length; j++) {
				this.parentNode.childNodes[j] = this.parentNode.childNodes[j+1];
			}
			this.parentNode.childNodes.length -= 1;
			if (i + 1 == this.parentNode.childNodes.length) { this.parentNode._last = true; }
			break;
	}	}
	//webFXTreeHandler.all[this.id] = null;
	delete(webFXTreeHandler.all[this.id]);
	var tmp = $(this.id);
	if (tmp) { tmp.parentNode.removeChild(tmp); }
	tmp = $(this.id + '-cont');
	if (tmp) { tmp.parentNode.removeChild(tmp); }
};

WebFXTreeItem.prototype.expand = function() {
	this.doExpand();
	if($(this.id + '-plus')) $(this.id + '-plus').src = this.minusIcon;
};

WebFXTreeItem.prototype.collapse = function(b) {
	if (!b) { try{this.focus();}catch(e){} }
	this.doCollapse();
	if($(this.id + '-plus')) $(this.id + '-plus').src = this.plusIcon;
};

WebFXTreeItem.prototype.getFirst = function() {
	return this.childNodes[0];
};

WebFXTreeItem.prototype.getLast = function() {
	if (this.childNodes[this.childNodes.length - 1].open) { return this.childNodes[this.childNodes.length - 1].getLast(); }
	else { return this.childNodes[this.childNodes.length - 1]; }
};

WebFXTreeItem.prototype.getNextSibling = function() {
	for (var i = 0; i < this.parentNode.childNodes.length; i++) {
		if (this == this.parentNode.childNodes[i]) { break; }
	}
	if (++i == this.parentNode.childNodes.length) { return this.parentNode.getNextSibling(); }
	else { return this.parentNode.childNodes[i]; }
};

WebFXTreeItem.prototype.getPreviousSibling = function(b) {
	for (var i = 0; i < this.parentNode.childNodes.length; i++) {
		if (this == this.parentNode.childNodes[i]) { break; }
	}
	if (i == 0) { return this.parentNode; }
	else {
		if ((this.parentNode.childNodes[--i].open) || (b && this.parentNode.childNodes[i].folder)) { return this.parentNode.childNodes[i].getLast(); }
		else { return this.parentNode.childNodes[i]; }
} };

WebFXTreeItem.prototype.getCurrentPlusIcon  = function(){
	return ((this.folder)?((this.open)?((this.parentNode._last)?"lMinusIcon":"tMinusIcon"):((this.parentNode._last)?"lPlusIcon":"tPlusIcon")):((this.parentNode._last)?"lIcon":"tIcon"));	
};

var WebFXtimer;
WebFXTreeItem.prototype.keydown = function(key) {
	if(!webFXTreeHandler.hasFocus) return true;
	else if( key == 9) {return false;}
	if ((key == 39) && (this.folder)) {
		if (!this.open) { this.expand(); }
		else { this.getFirst().select(); }
		return false;
	}
	else if (key == 37) {
		if (this.open) { this.collapse(); }
		else { this.parentNode.select(); }
		return false;
	}
	else if (key == 40) {
		if (this.open) { 
			this.getFirst().select(); 
			var toExec = this.getFirst();
			if(WebFXtimer) clearTimeout(WebFXtimer);
			WebFXtimer = window.setTimeout(toExec.action.bind(toExec), 1000);
		}
		else {
			var sib = this.getNextSibling();
			if (sib) { 
				sib.select(); 
				if(WebFXtimer) clearTimeout(WebFXtimer);				
				WebFXtimer = window.setTimeout(sib.action.bind(sib), 1000);
			}
		}
		return false;
	}
	else if (key == 38) { 
		var sib = this.getPreviousSibling();
		sib.select(); 
		if(WebFXtimer) clearTimeout(WebFXtimer);
		WebFXtimer = window.setTimeout(sib.action.bind(sib), 1000);
		return false; 
	}		
	return true;
};

WebFXTreeItem.prototype.toString = function (nItem, nItemCount) {
	var foo = this.parentNode;
	var indent = '';
	if (nItem + 1 == nItemCount) { this.parentNode._last = true; }
	var i = 0;
	while (foo.parentNode) {
		foo = foo.parentNode;
		indent = "<img id=\"" + this.id + "-indent-" + i + "\" src=\"" + ((foo._last)?webFXTreeConfig.blankIcon:webFXTreeConfig.iIcon) + "\" width=\"19\" height=\"25\">" + indent;
		i++;
	}
	this._level = i;
	if (this.childNodes.length) { this.folder = 1; }
	else { this.open = false; }
	if ((this.folder) || (webFXTreeHandler.behavior != 'classic')) {
		if (!this.icon) { this.icon = webFXTreeConfig.folderIcon; }
		if (!this.openIcon) { this.openIcon = webFXTreeConfig.openFolderIcon; }
	}
	else if (!this.icon) { this.icon = webFXTreeConfig.fileIcon; }
    var bgOverlayImage = '';
    var bgOverlayPosition = '4px 1px';
    var d = '';
    if(this.overlayClasses){

        d = '<div class="overlay_icon_div">';
        this.overlayClasses.each(function(c){
            d+='<span class="overlay-class-span '+c+'"></span>';
        });
        d+='</div>';

    }else if(this.overlayIcon){
        switch(this.overlayIcon.length){
            case 1:
                bgOverlayPosition = '14px 11px, 4px 1px';
                bgOverlayImage = "url('"+this.overlayIcon[0]+"'), ";
            break;
            case 2:
                bgOverlayPosition = '2px 11px, 14px 11px, 4px 1px';
                bgOverlayImage = "url('"+this.overlayIcon[0]+"'), url('"+this.overlayIcon[1]+"'), ";
            break;
            case 3:
                bgOverlayPosition = '14px 2px, 2px 11px, 14px 11px, 4px 1px';
                bgOverlayImage = "url('"+this.overlayIcon[0]+"'), url('"+this.overlayIcon[1]+"'), url('"+this.overlayIcon[2]+"'), ";
            break;
            case 4:
            default:
                bgOverlayPosition = '2px 2px, 14px 2px, 2px 11px, 14px 11px, 4px 1px';
                bgOverlayImage = "url('"+this.overlayIcon[0]+"'), url('"+this.overlayIcon[1]+"'), url('"+this.overlayIcon[2]+"'), url('"+this.overlayIcon[3]+"'), ";
            break;
        }
    }
	var label = this.text.replace(/</g, '&lt;').replace(/>/g, '&gt;') + d;
	var str = "<div id=\"" + this.id + "\" class=\"webfx-tree-item\" onkeydown=\"return webFXTreeHandler.keydown(this, event)\" data-node-icon=\"" + getBaseName((this.open?this.openIcon:this.icon)) + "\">" +
		indent +
		"<img  width=\"19\" height=\"25\" id=\"" + this.id + "-plus\" src=\"" + ((this.folder)?((this.open)?((this.parentNode._last)?webFXTreeConfig.lMinusIcon:webFXTreeConfig.tMinusIcon):((this.parentNode._last)?webFXTreeConfig.lPlusIcon:webFXTreeConfig.tPlusIcon)):((this.parentNode._last)?webFXTreeConfig.lIcon:webFXTreeConfig.tIcon)) + "\">" +
		"<a href=\"" + this.url + "\" id=\"" + this.id + "-anchor\" onkeydown=\"return webFXTreeHandler.linkKeyPress(this, event);\" onfocus=\"webFXTreeHandler.focus(this);\" onblur=\"webFXTreeHandler.blur(this);\"" +
		(this.target ? " target=\"" + this.target + "\"" : "") +
		">" +
		'<span id=\"' +this.id+ '-label\" style="background-position:'+bgOverlayPosition+';background-image:'+bgOverlayImage+'url(\''+ ((webFXTreeHandler.behavior == 'classic' && this.open)?this.openIcon:this.icon) +'\');">' + label + "</span></a></div>" +
		"<div id=\"" + this.id + "-cont\" class=\"webfx-tree-container\" style=\"display: " + ((this.open)?'block':'none') + ";\">";
	var sb = [];
	for (var i = 0; i < this.childNodes.length; i++) {
		sb[i] = this.childNodes[i].toString(i,this.childNodes.length);
	}
    this.zeroIcon = ((this.parentNode._last)?webFXTreeConfig.lIcon:webFXTreeConfig.tIcon);
    this.plusIcon = ((this.parentNode._last)?webFXTreeConfig.lPlusIcon:webFXTreeConfig.tPlusIcon);
	this.minusIcon = ((this.parentNode._last)?webFXTreeConfig.lMinusIcon:webFXTreeConfig.tMinusIcon);
	return str + sb.join("") + "</div>";
};
