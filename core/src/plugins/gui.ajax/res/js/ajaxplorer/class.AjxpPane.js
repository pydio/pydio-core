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
 * Abstract container any type of pane that can resize
 */
Class.create("AjxpPane", {	
	
	__implements : "IAjxpWidget",
    childrenPanes : null,
	
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
		this.scanChildrenPanes(this.htmlElement, true);
        if(this.options.bindSizeTo){
            this.boundSizeEvents = $H();
            if(this.options.bindSizeTo.width){
                this.options.bindSizeTo.width.events.each(function(eventName){
                    var binder = this.resizeBound.bind(this);
                    this.boundSizeEvents.set("ajaxplorer:" + eventName, binder);
                    document.observe("ajaxplorer:" + eventName, binder);
                }.bind(this) );

            }
        }
        if(this.options.resize_events){
            this.resizeEvents = $H();
            this.options.resize_events.each(function(eventName){
                var binder = this.resize.bind(this);
                this.resizeEvents.set("ajaxplorer:" + eventName, binder);
                document.observe("ajaxplorer:" + eventName, binder);
            }.bind(this) );
        }

        if(this.options.messageBoxReference && ajaxplorer){
            ajaxplorer.registerAsMessageBoxReference(this.htmlElement);
        }

        if(this.options.imageBackgroundFromConfigs){
            this.buildImageBackgroundFromConfigs(this.options.imageBackgroundFromConfigs);
        }

        this.configObserver = function(event){
            if(event.memo.className == "AjxpPane::"+htmlElement.id){
                this.parseComponentConfig(event.memo.classConfig.get("all"));
            }
        }.bind(this);
        document.observe("ajaxplorer:component_config_changed", this.configObserver);

        if(this.options.replaceScroller){
            this.scroller = new Element('div', {
                id:'scroller_'+this.htmlElement.id,
                className:'scroller_track'
            });
            this.scroller.insert(new Element('div', {
                id:'scrollbar_handle_'+this.htmlElement.id,
                className:'scroller_handle'
            }));
            this.htmlElement.insert(this.scroller);
            this.htmlElement.setStyle({overflow:"hidden"});
            this.scroller.setStyle({
                height:this.htmlElement.getHeight() + 'px'
            });
            this.scrollbar = new Control.ScrollBar(this.htmlElement,this.scroller, {fixed_scroll_distance:50});
        }

        var cPref = this.getUserPreference('rootElementClassPreference');
        if(cPref){
            if(Object.isString(cPref))cPref= {className:cPref};
            var cName = cPref['className'];
            if(cName){
                if(cName[0] == '!'){
                    cName = cName.substring(1);
                    this.htmlElement.removeClassName(cName);
                }else{
                    this.htmlElement.addClassName(cName);
                }
                if(cPref['externalButtonId'] && $(cPref['externalButtonId'])){
                    $(cPref['externalButtonId']).toggleClassName(cPref['externalButtonClassName'])
                }
            }
        }

    },

    parseComponentConfig: function(domNode){
        var change = false;
        XPathSelectNodes(domNode, "additional_content").each(function(addNode){
            var cdataContent = addNode.firstChild.nodeValue;
            var anchor = this.htmlElement;
            if(cdataContent && anchor){
                if(!anchor.down('#'+addNode.getAttribute("id"))){
                    anchor.insert(cdataContent);
                    var compReg = $A();
                    ajaxplorer.buildGUI(anchor.down('#'+addNode.getAttribute("id")), compReg);
                    if(compReg.length) ajaxplorer.initAjxpWidgets(compReg);
                    change = true;
                }
            }
        }.bind(this));
        if(change){
            this.scanChildrenPanes(this.htmlElement, true);
            this.resize();
            this.reorderContents();
        }
    },

    reorderContents: function(){
        var pos = {};
        this.htmlElement.select('> div[ajxp_position]').each(function(d){
            pos[parseInt(d.readAttribute('ajxp_position'))] = d;
        });
        var keys = $H(pos).keys();
        if(keys.length){
            keys.sort();
            keys.each(function(k){
                this.htmlElement.insert(pos[k]);
            }.bind(this));
        }
    },


    resizeBound : function(event){
        "use strict";
        if(!$(this.options.bindSizeTo.width.id) || !this.htmlElement) return;
        var min = this.options.bindSizeTo.width.min;
        if(Object.isString(min) && min.indexOf("%") != false) min = this.htmlElement.parentNode.getWidth() * min / 100;
        var w = Math.max($(this.options.bindSizeTo.width.id).getWidth() + this.options.bindSizeTo.width.offset, min);
        if(this.options.bindSizeTo.width.max) {
            var max = this.options.bindSizeTo.width.max;
            if(Object.isString(max) && max.indexOf("%") != false) max = this.htmlElement.parentNode.getWidth() * max / 100;
            w = Math.min(max, w);
        }
        if(this.options.bindSizeTo.width.checkSiblings){
            w = this.filterWidthFromSiblings(w);
        }
        this.htmlElement.setStyle({width: w + "px"});
        this.resize();

        if(this.options.bindSizeTo.width.checkSiblings){
            this.htmlElement.siblings().each(function(s){
                if(s.ajxpPaneObject){
                    s.ajxpPaneObject.resize();
                }
            });
        }
    },

    filterWidthFromSiblings : function(original){
        "use strict";
        if(!this.htmlElement || !this.htmlElement.parentNode) return null;
        var parentWidth = this.htmlElement.parentNode.getWidth();
        var siblingWidth = 0;
        this.htmlElement.siblings().each(function(s){
            if(s.hasClassName('skipSibling')) return;
            if(s.ajxpPaneObject && s.ajxpPaneObject.getActualWidth){
                siblingWidth+=s.ajxpPaneObject.getActualWidth();
            }else{
                siblingWidth+=s.getWidth();
            }
        });
        original = Math.min(original, parentWidth - siblingWidth - 20);
        return original;
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
            var minOffsetTop = 0;
            if(this.options.fitMinOffsetTop){
                var expr2 = this.options.fitMinOffsetTop;
                try{minOffsetTop = parseInt(eval(expr2));}catch(e){}
            }
    		fitHeightToBottom(this.htmlElement, this.options.fitParent, marginBottom, false, minOffsetTop);
            if(this.scrollbar){
                this.scroller.setStyle({
                    height:this.htmlElement.getHeight() + 'px'
                });
                this.scrollbar.recalculateLayout();
            }
    	}
        if(this.options.flexTo){
            var parentWidth = $(this.options.flexTo).getWidth();
            var siblingWidth = 0;
            this.htmlElement.siblings().each(function(s){
                if(s.hasClassName('skipSibling')) return;
                if(s.ajxpPaneObject && s.ajxpPaneObject.getActualWidth){
                    siblingWidth+=s.ajxpPaneObject.getActualWidth();
                }else{
                    siblingWidth+=s.getWidth();
                }
            });
            var buttonsWidth = 0;
            this.htmlElement.select("div.inlineBarButton,div.inlineBarButtonLeft,div.inlineBarButtonRight").each(function(el){
                buttonsWidth += el.getWidth();
            });
            var newWidth = (parentWidth-siblingWidth);
            if(this.options.flexToMargin) newWidth = newWidth - this.options.flexToMargin;
            if(newWidth < 5){
                this.htmlElement.hide();
            }else{
                this.htmlElement.show();
                this.htmlElement.setStyle({width:newWidth + 'px'});
            }
        }

        if(this.options.imageBackgroundFromConfigs && this.htmlElement.getHeight()){
            var ratio = this.htmlElement.getWidth() / this.htmlElement.getHeight();
            if(ratio < 1){
                this.htmlElement.addClassName('fit_background_height');
            }else{
                this.htmlElement.removeClassName('fit_background_height');
            }
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
        this.childrenPanes.each(function(child){
            child.destroy();
        });
        if(!this.htmlElement) return;
        this.htmlElement.update("");
        if(window[this.htmlElement.id]){
            try{delete window[this.htmlElement.id];}catch(e){}
        }
		this.htmlElement = null;
        if(this.boundSizeEvents){
            this.boundSizeEvents.each(function(pair){
                document.stopObserving(pair.key, pair.value);
            });
        }
        if(this.resizeEvents){
            this.resizeEvents.each(function(pair){
                document.stopObserving(pair.key, pair.value);
            });
        }
        if(Class.objectImplements(this, 'IFocusable')){
            ajaxplorer.unregisterFocusable(this);
        }
        if(Class.objectImplements(this, "IActionProvider")){
            this.getActions().each(function(act){
                ajaxplorer.guiActions.unset(act.key);
            }.bind(this));
        }
        if(this.configObserver){
            document.stopObserving("ajaxplorer:component_config_changed", this.configObserver);
        }
	},

    /**
     * Find and reference direct children IAjxpWidget
     * @param element HTMLElement
     * @param reset Clear existing children
     */
	scanChildrenPanes : function(element, reset){
        if(!element.childNodes) return;
        if(reset) this.childrenPanes = $A();
		$A(element.childNodes).each(function(c){
			if(c.ajxpPaneObject) {
				if(!this.childrenPanes){
                    this.childrenPanes = $A();
                }
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
            if(this.childrenPanes) this.childrenPanes.invoke('showElement', show);
		}else{
            if(this.childrenPanes) this.childrenPanes.invoke('showElement', show);
			this.htmlElement.hide();
		}
	},
	
	/**
	 * Adds a simple haeder with a title and icon
	 * @param headerLabel String The title
	 * @param headerIcon String Path for the icon image
	 */
	addPaneHeader : function(headerLabel, headerIcon){
        var label = new Element('span', {ajxp_message_id:headerLabel}).update(MessageHash[headerLabel]);
        var header = new Element('div', {className:'panelHeader'}).update(label);
        var ic;
        if(headerIcon){
            ic = resolveImageSource(headerIcon, '/images/actions/ICON_SIZE', 16);
            header.insert({top: new Element("img", {src:ic, className:'panelHeaderIcon'})});
            header.addClassName('panelHeaderWithIcon');
        }
        if(this.options.headerClose){
            ic = resolveImageSource(this.options.headerClose.icon, '/images/actions/ICON_SIZE', 16);
            var img = new Element("img", {src:ic, className:'panelHeaderCloseIcon', title:MessageHash[this.options.headerClose.title]});
            header.insert({top: img});
            var sp = this.options.headerClose.splitter;
            img.observe("click", function(){
                window[sp]["fold"]();
            });
        }
		this.htmlElement.insert({top : header});
		disableTextSelection(header);

        if(this.options.headerToolbarOptions){
            var tbD = new Element('div', {id:"display_toolbar"});
            header.insert({top:tbD});
            new ActionsToolbar(tbD, this.options.headerToolbarOptions);
        }


    },
	
	/**
	 * Sets a listener when the htmlElement is focused to notify ajaxplorer object
	 */
	setFocusBehaviour : function(){
		this.htmlElement.observe("click", function(){
			if(ajaxplorer) ajaxplorer.focusOn(this);
		}.bind(this));
	},

    toggleClassNameSavingPref:function(className, externalButtonId, externalButtonClassName){
        var invert = false;
        if(className[0] == '!') {
            className = className.substring(1);
            invert = true;
        }
        this.htmlElement.toggleClassName(className);
        if(externalButtonId){
            $(externalButtonId).toggleClassName(externalButtonClassName);
        }
        var pref = {
            externalButtonId:externalButtonId,
            externalButtonClassName: externalButtonClassName
        };
        if(invert){
            pref['className'] = this.htmlElement.hasClassName(className)?'':'!'+className;
        }else{
            pref['className'] = this.htmlElement.hasClassName(className)?className:'';

        }
        this.setUserPreference('rootElementClassPreference', pref)
    },

    getUserPreference : function(prefName){
        if(!ajaxplorer || !ajaxplorer.user || !this.htmlElement) return null;
        var gui_pref = ajaxplorer.user.getPreference("gui_preferences", true);
        var classkey = this.htmlElement.id+"_"+this.__className;
        if(!gui_pref || !gui_pref[classkey]) return null;
        if(ajaxplorer.user.activeRepository && gui_pref[classkey]['repo-'+ajaxplorer.user.activeRepository]){
            return gui_pref[classkey]['repo-'+ajaxplorer.user.activeRepository][prefName];
        }
        return gui_pref[classkey][prefName];
    },

    setUserPreference : function(prefName, prefValue){
        if(!ajaxplorer || !ajaxplorer.user || !this.htmlElement) return;
        var guiPref = ajaxplorer.user.getPreference("gui_preferences", true);
        if(!guiPref) guiPref = {};
        var classkey = this.htmlElement.id+"_"+this.__className;
        if(!guiPref[classkey]) guiPref[classkey] = {};
        if(ajaxplorer.user.activeRepository ){
            var repokey = 'repo-'+ajaxplorer.user.activeRepository;
            if(!guiPref[classkey][repokey]) guiPref[classkey][repokey] = {};
            if(guiPref[classkey][repokey][prefName] && guiPref[classkey][repokey][prefName] == prefValue){
                return;
            }
            guiPref[classkey][repokey][prefName] = prefValue;
        }else{
            if(guiPref[classkey][prefName] && guiPref[classkey][prefName] == prefValue){
                return;
            }
            guiPref[classkey][prefName] = prefValue;
        }
        ajaxplorer.user.setPreference("gui_preferences", guiPref, true);
        ajaxplorer.user.savePreference("gui_preferences");
    },


    buildImageBackgroundFromConfigs:function(configName, forceConfigs){
        var bgrounds,paramPrefix,bStyles,index, i;
        if(forceConfigs){
            bgrounds = forceConfigs;
            paramPrefix = configName;
            bStyles = [];
            index = 1;
            while(bgrounds[paramPrefix+index]){
                bStyles.push("background-image:url('"+bgrounds[paramPrefix+index]+"');" + (bgrounds[paramPrefix + 'ATTRIBUTES_'+index]?bgrounds[paramPrefix + 'ATTRIBUTES_'+index]:''));
                index++;
            }
            if (bStyles.length) {
                i = Math.floor( Math.random() * bStyles.length);
                this.htmlElement.setAttribute("style", bStyles[i]);
            }
            return;
        }

        var exp = configName.split("/");
        var plugin = exp[0];
        paramPrefix = exp[1];
        var registry = ajaxplorer.getXmlRegistry();
        var configs = XPathSelectNodes(registry, "plugins/*[@id='"+plugin+"']/plugin_configs/property[contains(@name, '"+paramPrefix+"')]");
        var defaults = XPathSelectNodes(registry, "plugins/*[@id='"+plugin+"']/server_settings/global_param[contains(@name, '"+paramPrefix+"')]");


        bgrounds = {};
        configs.each(function(c){
            bgrounds[c.getAttribute("name")] = c.firstChild.nodeValue.replace(/"/g, '');
        });
        defaults.each(function(d){
            if(!d.getAttribute('defaultImage')) return;
            var n = d.getAttribute("name");
            if(!bgrounds[n]){
                bgrounds[n] = d.getAttribute("defaultImage");
            }else{
                if(getBaseName(bgrounds[n]) == bgrounds[n]){
                    bgrounds[n] = window.ajxpServerAccessPath+"&get_action=get_global_binary_param&binary_id="+bgrounds[n];
                }
            }
        });
        bStyles = [];
        index = 1;
        while(bgrounds[paramPrefix+index]){
            bStyles.push("background-image:url('"+bgrounds[paramPrefix+index]+"');" + (bgrounds[paramPrefix + 'ATTRIBUTES_'+index]?bgrounds[paramPrefix + 'ATTRIBUTES_'+index]:''));
            index++;
        }
        if (bStyles.length) {
            i = Math.floor( Math.random() * bStyles.length);
            var bg = bStyles[i];
            if(Modernizr.backgroundsize) bg = bg.replace('background-size:100%','background-size:cover').replace('background-size:140%','background-size:cover');
            this.htmlElement.setAttribute("style", bg);
        }

    }

});