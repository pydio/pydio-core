/*
 * @package info.ajaxplorer
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
 */
/**
 * Abstract container any type of pane that can resize
 */
Class.create("AjxpPane", {	
	
	__implements : "IAjxpWidget",
	
	/**
	 * Constructor
	 * @param htmlElement HTMLElement The Node anchor
	 * @param options Object The pane parameters
	 */
	initialize : function(htmlElement, options){
		this.htmlElement = $(htmlElement);
		if(!this.htmlElement){
			throw new Error('Cannot find element for AjxpPane : ' + this.__className);
		}
		this.options = options || {};
		this.htmlElement.ajxpPaneObject = this;
		if(this.htmlElement.getAttribute('ajxpPaneHeader')){
			this.addPaneHeader(
				this.htmlElement.getAttribute('ajxpPaneHeader'), 
				this.htmlElement.getAttribute('ajxpPaneIcon'));
		}
        if(this.htmlElement && this.options.elementStyle){
            this.htmlElement.setStyle(this.options.elementStyle);
        }
		this.childrenPanes = $A([]);
		this.scanChildrenPanes(this.htmlElement);
	},
	
	/**
	 * Called when the pane is resized
	 */
	resize : function(){		
		// Default behaviour : resize children
    	if(this.options.fit && this.options.fit == 'height'){
    		var marginBottom = 0;
    		if(this.options.fitMarginBottom){
    			var expr = this.options.fitMarginBottom;
    			try{marginBottom = parseInt(eval(expr));}catch(e){}
    		}
    		fitHeightToBottom(this.htmlElement, (this.options.fitParent?$(this.options.fitParent):null), expr);
    	}
    	this.childrenPanes.invoke('resize');
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	getDomNode : function(){
		return this.htmlElement;
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	destroy : function(){
        this.htmlElement.update("");
		this.htmlElement = null;
	},
	
	/**
	 * Find and reference direct children IAjxpWidget
	 * @param element HTMLElement
	 */
	scanChildrenPanes : function(element){
		if(!element.childNodes) return;
		$A(element.childNodes).each(function(c){
			if(c.ajxpPaneObject) {
				this.childrenPanes.push(c.ajxpPaneObject);
			}else{
				this.scanChildrenPanes(c);
			}
		}.bind(this));
	},
	
	/**
	 * Show the main html element
	 * @param show Boolean
	 */
	showElement : function(show){
		if(show){
			this.htmlElement.show();
		}else{
			this.htmlElement.hide();
		}
	},
	
	/**
	 * Adds a simple haeder with a title and icon
	 * @param headerLabel String The title
	 * @param headerIcon String Path for the icon image
	 */
	addPaneHeader : function(headerLabel, headerIcon){
        var header = new Element('div', {className:'panelHeader',ajxp_message_id:headerLabel}).update(MessageHash[headerLabel]);
        if(headerIcon){
            var ic = resolveImageSource(headerIcon, '/images/actions/ICON_SIZE', 16);
            header.insert({top: new Element("img", {src:ic, className:'panelHeaderIcon'})});
            header.addClassName('panelHeaderWithIcon');
        }
        if(this.options.headerClose){
            var ic = resolveImageSource(this.options.headerClose.icon, '/images/actions/ICON_SIZE', 16);
            var img = new Element("img", {src:ic, className:'panelHeaderCloseIcon', title:MessageHash[this.options.headerClose.title]});
            header.insert({top: img});
            var sp = this.options.headerClose.splitter;
            img.observe("click", function(){
                window[sp]["fold"]();
            });
        }
		this.htmlElement.insert({top : header});
		disableTextSelection(header);
	},
	
	/**
	 * Sets a listener when the htmlElement is focused to notify ajaxplorer object
	 */
	setFocusBehaviour : function(){
		this.htmlElement.observe("click", function(){
			if(ajaxplorer) ajaxplorer.focusOn(this);
		}.bind(this));
	},


    getUserPreference : function(prefName){
        if(!ajaxplorer || !ajaxplorer.user) return;
        var gui_pref = ajaxplorer.user.getPreference("gui_preferences", true);
        if(!gui_pref || !gui_pref[this.htmlElement.id+"_"+this.__className]) return;
        return gui_pref[this.htmlElement.id+"_"+this.__className][prefName];
    },

    setUserPreference : function(prefName, prefValue){
        if(!ajaxplorer || !ajaxplorer.user) return;
        var guiPref = ajaxplorer.user.getPreference("gui_preferences", true);
        if(!guiPref) guiPref = {};
        if(!guiPref[this.htmlElement.id+"_"+this.__className]) guiPref[this.htmlElement.id+"_"+this.__className] = {};
        guiPref[this.htmlElement.id+"_"+this.__className][prefName] = prefValue;
        ajaxplorer.user.setPreference("gui_preferences", guiPref, true);
        ajaxplorer.user.savePreference("gui_preferences");
    }

});