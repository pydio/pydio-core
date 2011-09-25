/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

/**
 * Abstract implementation of an Editor. All editors should extend this one, as it provides
 * standard features for parsing actions, entering/exiting fullscreen, etc.. Events are triggered
 * at various moments of the editors lifecycle.
 * 
 * @package info.ajaxplorer.plugins 
 */
Class.create("AbstractEditor" , {
	
	/**
	 * @var Hash The default actions, initialized with fs, nofs and close
	 */
	defaultActions : new Hash(),
	/**
	 * @var String
	 */
	toolbarSeparator : '<div class="separator"></div>',
	/**
	 * @var Boolean Current state of the editor
	 */
	fullScreenMode : false,
	/**
	 * @var Hash For the moment supported options are "fullscreen", "closable", "floatingToolbar".
	 */
	editorOptions:null, 
	
	/**
	 * Standard contructor
	 * @param oContainer Element dom not to attach to
	 */
	initialize : function(oContainer, options){
		this.editorOptions = Object.extend({
			fullscreen:true, 
			closable:true, 
			floatingToolbar:false
		}, options || { });		
		this.element =  $(oContainer);
		this.defaultActions = new Hash({
			'fs' : '<a id="fsButton"><img src="'+ajxpResourcesFolder+'/images/actions/22/window_fullscreen.png"  width="22" height="22" alt="" border="0"><br><span message_id="235"></span></a>',
			'nofs' : '<a id="nofsButton" style="display:none;"><img src="'+ajxpResourcesFolder+'/images/actions/22/window_nofullscreen.png"  width="22" height="22" alt="" border="0"><br><span message_id="236"></span></a>',
			'close':'<a id="closeButton"><img src="'+ajxpResourcesFolder+'/images/actions/22/fileclose.png"  width="22" height="22" alt="" border="0"><br><span message_id="86"></span></a>'
		});
        if(this.editorOptions.actions){
            this.defaultActions = $H(Object.extend(this.defaultActions._object, this.editorOptions.actions));
        }
		this.createTitleSpans();
		this.initActions();
		modal.setCloseAction(function(){this.close();}.bind(this));
	},
	
	/**
	 * Initialize standards editor actions
	 */
	initActions : function(){
		this.actions = new Hash();
		this.registeredActions = new Hash();
		var actionBarSel = this.element.select('.action_bar');		
		if(!actionBarSel.length){
			this.actionBar = new Element('div', {className:'action_bar'});
			this.element.insert({top:this.actionBar});
		}else{
			this.actionBar = actionBarSel[0];
		}
        this.actionBar.addClassName('editor_action_bar');
		if(!this.editorOptions.fullscreen){
			this.defaultActions.unset("fs");
			this.defaultActions.unset("nofs");
		}
		this.actionBar.insert({top:this.toolbarSeparator});	
		this.actionBar.insert({bottom:this.toolbarSeparator});
		this.actionBar.insert({bottom:this.defaultActions.values().join('\n')});
		this.actionBar.select('a').each(function(link){
			link.onclick = function(){return false;};
			link.href = "#";
            link.select("br").invoke("remove");
            link.select("img").invoke("addClassName", "actionbar_button_icon");
            link.select("span").invoke("addClassName", "actionbar_button_label");
			var span = link.select('span[message_id]')[0];
            var title = MessageHash[span.readAttribute("message_id")];
			if(span) span.update(title);
			this.actions.set(link.id, link);
			if(link.getAttribute('access_key')){
				var aK = link.getAttribute('access_key');
				if(Event[aK]) aK = Event[aK];
				this.registeredActions.set(aK, link.id);
                if(!(!isNaN(parseFloat(aK)) && isFinite(aK))) title += " (" + aK + ")";
			}
            link.setAttribute("title", title);
		}, this);
		if(this.registeredActions.size()){
			this.keyObs = function(e){
                if(ajaxplorer.blockEditorShortcuts) return;
				if(this.registeredActions.get(e.keyCode)){
					this.actions.get(this.registeredActions.get(e.keyCode)).onclick();
				}else if(this.registeredActions.get(String.fromCharCode(e.keyCode).toLowerCase())){
					this.actions.get(this.registeredActions.get(String.fromCharCode(e.keyCode).toLowerCase())).onclick();
				}
			}.bind(this);
			Event.observe(document, "keydown", this.keyObs);
			this.element.observe("editor:close", function(){
				Event.stopObserving(document, "keydown", this.keyObs);
			}.bind(this));
		}
		
		if(this.actions.get("closeButton")){
			this.actions.get("closeButton").observe("click", function(){
				hideLightBox(true);
			}.bind(this) );
			modal.setCloseValidation(function(){
				if(this.isModified && !window.confirm(MessageHash[201])){
					return false;
				}
				return true;
			}.bind(this) );			
			if(window.ajxpMobile){
				// Make sure "Close" is the first.
				this.actionBar.insert({top:this.actions.get("closeButton")});
			}
		}
		if(this.actions.get("fsButton")){
			this.actions.get("fsButton").observe("click", this.setFullScreen.bind(this));
			this.actions.get("nofsButton").observe("click", this.exitFullScreen.bind(this));
			this.actions.get("fsButton").show();
			this.actions.get("nofsButton").hide();
		}
		
		if(this.editorOptions.floatingToolbar){
			this.makeToolbarFloatable();
		}

        if(this.editorOptions.toolbarStyle){
            this.actionBar.addClassName(this.editorOptions.toolbarStyle);
        }
		
		attachMobileScroll(this.actionBar, "horizontal");
		var obs = this.resize.bind(this);
		modal.observe("modal:resize", obs);
		this.element.observe("editor:close", function(){
			modal.stopObserving("modal:resize", obs);
		});
		
	},
	
	/**
	 * Experimental : detach toolbar
	 */
	makeToolbarFloatable : function(){
        this.element.up("div.dialogContent").setStyle({position:'relative'});
		this.actionBar.absolutize();
        var crtIndex = parseInt(this.element.getStyle("zIndex"));
        if(!crtIndex) crtIndex = 1000;
		this.actionBar.setStyle({
			zIndex:(crtIndex + 1000),
			width : '',
			top: ''
		});
        this.actionBar.addClassName("floatingBar");
		this.actionBar.down("div.separator").remove();
		this.actionBarPlacer = function(){
            var anchor = (this.floatingToolbarAnchor?this.floatingToolbarAnchor:this.contentMainContainer);
            if(!anchor) return;
            var w = this.actionBar.getWidth();
            var elW = anchor.getWidth();
            this.actionBar.setStyle({left:(Math.max(0,(elW-w)/2))+(anchor.positionedOffset().left)+'px'});
            this.actionBar.setStyle({top:(anchor.getHeight()-this.actionBar.getHeight() - 30 )+'px'});
		}.bind(this);
		this.element.observe("editor:resize", this.actionBarPlacer);
		this.element.observe("editor:close", function(){
			this.element.stopObserving("editor:resize", this.actionBarPlacer);
		}.bind(this));
		window.setTimeout(this.actionBarPlacer, 100);
		new Draggable(this.actionBar);
	},
	
	/**
	 * Creates the title label depending on the "modified" status
	 */
	createTitleSpans : function(){
		var crtTitle = $(modal.dialogTitle).select('span.titleString')[0];
		this.filenameSpan = new Element("span", {className:"filenameSpan"});
		crtTitle.insert({bottom:this.filenameSpan});
		
		this.modifSpan = new Element("span", {className:"modifiedSpan"});
		crtTitle.insert({bottom:this.modifSpan});		
		
	},
	
	/**
	 * Opens the editor with the current model
	 * @param userSelection AjxpDataModel the data model
	 */
	open : function(userSelection){
		this.userSelection = userSelection;
	},
	/**
	 * Updates the editor title
	 * @param title String
	 */
	updateTitle : function(title){
		if(title != ""){
			title = " - " + title;
		}
		this.filenameSpan.update(title);
		if(this.fullScreenMode){
			this.refreshFullScreenTitle();
		}
	},
	/**
	 * Change editor status
	 * @param isModified Boolean
	 */
	setModified : function(isModified){
		this.isModified = isModified;
		this.modifSpan.update((isModified?"*":""));
		if(this.actions.get("saveButton")){
			if(isModified){
				this.actions.get("saveButton").removeClassName("disabled");
			}else{
				this.actions.get("saveButton").addClassName("disabled");
			}
		}
		if(this.fullScreenMode){
			this.refreshFullScreenTitle();
		}
		this.element.fire("editor:modified", isModified);
	},
	/**
	 * Switch to fullscreen mode
	 */
	setFullScreen : function(){
		if(!this.contentMainContainer){
			this.contentMainContainer = this.element;
		}
		this.originalHeight = this.contentMainContainer.getHeight();	
		this.originalWindowTitle = document.title;
        this.element.fire("editor:enterFS");

		this.element.absolutize();
		this.actionBar.setStyle({marginTop: 0});
		$(document.body).insert(this.element);
		this.element.setStyle({
			top:0,
			left:0,
			marginBottom:0,
			backgroundColor:'#fff',
			width:parseInt(document.viewport.getWidth())+'px',
			height:parseInt(document.viewport.getHeight())+"px",
			zIndex:3000});
		this.actions.get("fsButton").hide();
		this.actions.get("nofsButton").show();
		this.fullScreenListener = function(){
			this.element.setStyle({height:parseInt(document.viewport.getHeight())+"px"});
			this.resize();		
		}.bind(this);
		Event.observe(window, "resize", this.fullScreenListener);
		this.refreshFullScreenTitle();
		this.resize();
		this.fullScreenMode = true;
		this.element.fire("editor:enterFSend");
	},
	/**
	 * Exits fullscreen mode
	 */
	exitFullScreen : function(){
		if(!this.fullScreenMode) return;
		this.element.fire("editor:exitFS");
		Event.stopObserving(window, "resize", this.fullScreenListener);
        var dContent = $$('.dialogContent')[0];
        dContent.setStyle({position:"relative"});
		dContent.insert(this.element);
        this.element.relativize();
        this.element.setStyle({position:"relative"});
		this.element.setStyle({top:0,left:0,
            width:parseInt(dContent.getWidth())+'px',
            height:parseInt(dContent.getHeight())+"px",
            zIndex:100});
		this.resize(this.originalHeight);
		this.actions.get("fsButton").show();
		this.actions.get("nofsButton").hide();		
		document.title = this.originalWindowTitle;
		this.fullScreenMode = false;
		this.element.fire("editor:exitFSend");
	},
	/**
	 * Resizes the main container
	 * @param size int|null
	 */
	resize : function(size){
		if(size){
			this.contentMainContainer.setStyle({height:size+"px"});
		}else{
			fitHeightToBottom(this.contentMainContainer, this.element);
		}
		this.element.fire("editor:resize", size);
	},
	/**
	 * Closes the editor
	 * @returns Boolean
	 */
	close : function(){		
		if(this.fullScreenMode){
			this.exitFullScreen();
		}
		this.element.fire("editor:close");
		modal.setCloseAction(null);
		return false;
	},
	
	/**
	 * Refreshes the title
	 */
	refreshFullScreenTitle : function(){
		document.title = "AjaXplorer - "+$(modal.dialogTitle).innerHTML.stripTags().replace("&nbsp;","");
	},
	/**
	 * Add a loading image to the given element
	 * @param element Element dom node
	 */
	setOnLoad : function(element){	
		addLightboxMarkupToElement(element);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+"/images/loadingImage.gif";
		$(element).select("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	/**
	 * Removes the image from the element 
	 * @param element Element dom node
	 */
	removeOnLoad : function(element){
		removeLightboxFromElement(element);
		this.loading = false;	
	},

	/**
	 * Called by the other components to create a preview (thumbnail) of a given node
	 * @param ajxpNode AjxpNode The node to display
	 * @param rich Boolean whether to display a rich content (flash, video, etc...) or not (image)
	 * @returns Element
	 */
	getPreview : function(ajxpNode, rich){
		// Return icon if not overriden by derived classes
		src = AbstractEditor.prototype.getThumbnailSource(ajxpNode);
		imgObject = new Element("img", {src:src, width:64, height:64, align:'absmiddle', border:0});
		imgObject.resizePreviewElement = function(dimensionObject){
			dimensionObject.maxWidth = dimensionObject.maxHeight = 64;
			var styleObject = fitRectangleToDimension({width:64,height:64},dimensionObject);
			if(dimensionObject.width >= 64){
				var newHeight = parseInt(styleObject.height);
				var mT = parseInt((dimensionObject.width - 64)/2) + dimensionObject.margin;
				var mB = dimensionObject.width+(dimensionObject.margin*2)-newHeight-mT-1;
				styleObject.marginTop = mT + "px"; 
				styleObject.marginBottom = mB + "px"; 
			}
			this.setStyle(styleObject);
		}.bind(imgObject);
		return imgObject;
	},
	
	/**
	 * Gets the standard thumbnail source for previewing the node
	 * @param ajxpNode AjxpNode
	 * @returns String
	 */
	getThumbnailSource : function(ajxpNode){
		return resolveImageSource(ajxpNode.getIcon(), "/images/mimes/ICON_SIZE", 64);
	}
	
});