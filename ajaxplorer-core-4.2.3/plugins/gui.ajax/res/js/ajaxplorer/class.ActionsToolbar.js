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
 * Toolbar to display actions buttons
 */
Class.create("ActionsToolbar", {
	__implements : "IAjxpWidget",
	/**
	 * Constructor
	 * @param oElement Element The dom node
	 * @param options Object The toolbar options. Contains a buttonRenderer and a toolbarsList array.
	 */
	initialize : function(oElement, options){
		this.element = oElement;		
		this.element.ajxpPaneObject = this;
		this.options = Object.extend({
			buttonRenderer : 'this',
            skipBubbling: false,
			toolbarsList : $A(['default', 'put', 'get', 'change', 'user', 'remote'])
		}, options || {});
		var renderer = this.options.buttonRenderer;
		if(renderer == 'this'){
			this.options.buttonRenderer = this;
		}else{
			this.options.buttonRenderer = new renderer();
		}
		this.toolbars = $H();
		this.initCarousel();
        if(this.options.styles){
            this.buildActionBarStylingMenu();
            this.style = this.options.defaultStyle;
            document.observe("ajaxplorer:user_logged", function(){
                if(ajaxplorer.user && ajaxplorer.user.getPreference("action_bar_style")){
                    this.style = ajaxplorer.user.getPreference("action_bar_style");
                }else{
                    this.style = this.options.defaultStyle;
                }
                this.switchStyle(false, true);
            }.bind(this));
        }
		attachMobileScroll(oElement.id, "horizontal");
		document.observe("ajaxplorer:actions_loaded", this.actionsLoaded.bind(this));
		document.observe("ajaxplorer:actions_refreshed", this.refreshToolbarsSeparator.bind(this));
        this.componentConfigHandler = function(event){
            if(event.memo.className == "ActionsToolbar"){
                this.parseComponentConfig(event.memo.classConfig.get('all'));
            }
        }.bind(this);
        document.observe("ajaxplorer:component_config_changed", this.componentConfigHandler );

	},
	
	getDomNode : function(){
		return this.element;
	},
	destroy : function(){
		
	},

    /**
     * Apply the config of a component_config node
     * Returns true if the GUI needs refreshing
     * @param domNode XMLNode
     * @returns Boolean
     */
    parseComponentConfig : function(domNode){
        var config = XPathSelectSingleNode(domNode, 'property[@name="style"]');
        if(config){
            var value = config.getAttribute("value");
            if(this.options.styles && this.options.styles[value]){
                this.style = value;
                this.switchStyle();
            }
        }
    },
	/**
	 * Handler for actions_loaded event.
	 * @param event Event ajaxplorer:actions_loaded
	 */
	actionsLoaded : function(event) {
		this.actions = event.memo;
		this.emptyToolbars();
		this.initToolbars();
	},
	
	/**
	 * Initialize all toolbars
	 */
	initToolbars: function () {
		this.actions.each(function(pair){
			var action = pair.value;
			var actionName = pair.key;
			if(action.context.actionBar){
				if(this.toolbars.get(action.context.actionBarGroup) == null){
					this.toolbars.set(action.context.actionBarGroup, new Array());
				}
				this.toolbars.get(action.context.actionBarGroup).push(actionName);
			}			
		}.bind(this));
		var crtCount = 0;
		var toolbarsList = this.options.toolbarsList;
		toolbarsList.each(function(toolbar){			
			var tBar = this.initToolbar(toolbar);			
			if(tBar && tBar.actionsCount){				
				if(crtCount < toolbarsList.size()-1) {
					var separator = new Element('div');
					separator.addClassName('separator');
					tBar.insert({top:separator});
				}
				this.element.insert(tBar);
				crtCount ++;
			}
		}.bind(this));
		this.element.select('a').each(disableTextSelection);		
	},
	/**
	 * Recompute separators if some toolbars are empty due to actions show/hide status.
	 */
	refreshToolbarsSeparator: function(){
		this.toolbars.each(function(pair){
			var toolbar = this.element.select('[id="'+pair.key+'_toolbar"]')[0];
			if(!toolbar) return;
			var sep = toolbar.select('div.separator')[0];
			if(!sep) return;
			var hasVisibleActions = false;
			toolbar.select('a').each(function(action){
				if(action.visible()) hasVisibleActions = true;
			});
			if(hasVisibleActions) sep.show();
			else sep.hide();
		}.bind(this) );
		this.refreshSlides();
	},
	
	/**
	 * Initialize a given toolbar
	 * @param toolbar String The name of the toolbar
	 * @returns HTMLElement
	 */
	initToolbar: function(toolbar){
		if(!this.toolbars.get(toolbar)) {
			return '';
		}
		var toolEl = $(toolbar+'_toolbar');		
		if(!toolEl){ 
			var toolEl = new Element('div', {
				id: toolbar+'_toolbar',
				style: 'display:inline;'
			});
		}
		toolEl.actionsCount = 0;
		this.toolbars.get(toolbar).each(function(actionName){
			var action = this.actions.get(actionName);		
			if(!action) return;
			var button = this.renderToolbarAction(action);	
			toolEl.insert(button);
			toolEl.actionsCount ++;			
		}.bind(this));
		return toolEl;
	},
	
	/**
	 * Remove all toolbars
	 */
	emptyToolbars: function(){
		if(this.element.subMenus){
			this.element.subMenus.invoke("destroy");
		}
		this.element.select('div').each(function(divElement){			
			divElement.remove();
		}.bind(this));
		this.toolbars = new Hash();
	},
	
	/**
	 * Initialize a caroussel to scroll the buttons on small screens
	 */
	initCarousel : function(){
		this.outer = this.element;
		var origHeight = this.outer.getHeight()-1;
		this.prev = new Element("a", {className:'carousel-control', rel:'prev', style:'height:'+origHeight+'px;'}).update(new Element('img', {src:ajxpResourcesFolder+'/images/arrow_left.png'}));
		this.next = new Element("a", {className:'carousel-control', rel:'next', style:'float:right;height:'+origHeight+'px;'}).update(new Element('img', {src:ajxpResourcesFolder+'/images/arrow_right.png'}));
		this.inner = new Element("div", {id:'buttons_inner', style:'width:1000px;'});
		this.outer.insert({before:this.prev});
		this.outer.insert({before:this.next});
		this.outer.insert(this.inner);		
		if(Prototype.Browser.IE) this.outer.setStyle({cssFloat:'left'});
		this.element = this.inner;
		
		this.carousel = new Carousel(this.outer, [], $A([this.prev,this.next]), {
			duration:0.1
		});
	},
	
	/**
	 * Refreshes the caroussel slides 
	 */
	refreshSlides : function(){
		var allSlides = $A();
		this.inner.select('a').each(function(a){
			if(a.visible()) allSlides.push(a);
		});
		this.carousel.refreshSlides(allSlides);
		this.resize();		
	},
	
	/**
	 * Render an Action for the toolbar
	 * @param action Action The action
	 * @returns HTMLElement
	 */
	renderToolbarAction : function(action){
		var button = new Element('a', {
			href:action.options.name,
			id:action.options.name +'_button'
		}).observe('click', function(e){
			Event.stop(e);
			if(this.options.subMenu){
				//this.subMenu.show(e);
			}else{
				this.apply();
			}
		}.bind(action));
        var icSize = (this.options.defaultIconSize?this.options.defaultIconSize:22);
        if(this.options.stylesImgSizes && this.style && this.options.stylesImgSizes[this.style]){
            icSize = this.options.stylesImgSizes[this.style];
        }
		var imgPath = resolveImageSource(action.options.src,action.__DEFAULT_ICON_PATH, icSize);
		var img = new Element('img', {
			id:action.options.name +'_button_icon',
            className:'actionbar_button_icon',
			src:imgPath,
			width:icSize,
			height:icSize,
			border:0,
			alt:action.options.title,
			title:action.options.title,
            'data-action-src':action.options.src
		});
		var titleSpan = new Element('span', {id:action.options.name+'_button_label',className:'actionbar_button_label'});
		button.insert(img).insert(titleSpan.update(action.getKeyedText()));
		//this.elements.push(this.button);
		if(action.options.subMenu){
			this.buildActionBarSubMenu(button, action);// TODO
            button.setStyle({position:'relative'});
			var arrowDiv = new Element('div', {className:'actionbar_arrow_div'});
			arrowDiv.insert(new Element('img',{src:ajxpResourcesFolder+'/images/arrow_down.png',height:6,width:10,border:0}));
			arrowDiv.imgRef = img;
            button.insert(arrowDiv);
		}else if(!this.options.skipBubbling) {
			button.observe("mouseover", function(){
				this.buttonStateHover(button, action);
			}.bind(this) );
			button.observe("mouseout", function(){
				this.buttonStateOut(button, action);
			}.bind(this) );
		}
        if(!this.options.skipBubbling){
            img.setStyle("width:18px;height:18px;margin-top:8px;");
        }
		button.hide();
		this.attachListeners(button, action);
		return button;
		
	},
	
	/**
	 * Attach various listeners to an action to reflect its state on the button
	 * @param button HTMLElement The button
	 * @param action Action The action to observe.
	 */
	attachListeners : function(button, action){
		action.observe("hide", function(){
			button.hide();
		}.bind(this));
		action.observe("show", function(){
			button.show();
		}.bind(this));
		action.observe("disable", function(){
			button.addClassName("disabled");
		}.bind(this));
		action.observe("enable", function(){
			button.removeClassName("disabled");
		}.bind(this));
		action.observe("submenu_active", function(submenuItem){
			if(!submenuItem.src || !action.options.subMenuUpdateImage) return;
			var images = button.select('img[id="'+action.options.name +'_button_icon"]');
			if(!images.length) return;
            icSize = 22;
            if(this.options.stylesImgSizes && this.style && this.options.stylesImgSizes[this.style]){
                icSize = this.options.stylesImgSizes[this.style];
            }
			images[0].src = resolveImageSource(submenuItem.src, action.__DEFAULT_ICON_PATH,icSize);
			action.options.src = submenuItem.src;
		}.bind(this));
	},
	
	/**
	 * Creates a submenu
	 * @param button HTMLElement The anchor of the submenu
	 * @param action Action The action
	 */
	buildActionBarSubMenu : function(button, action){
		var subMenu = new Proto.Menu({
		  mouseClick:"over",
		  anchor: button, // context menu will be shown when element with class name of "contextmenu" is clicked
		  className: 'menu desktop toolbarmenu', // this is a class which will be attached to menu container (used for css styling)
		  topOffset : 0,
		  leftOffset : 0,	
		  parent : this.element,	 
		  menuItems: action.subMenuItems.staticOptions || [],
		  fade:true,
		  zIndex:2000		  
		});	
		var titleSpan = button.select('span')[0];	
		subMenu.options.beforeShow = function(e){
			button.addClassName("menuAnchorSelected");
			if(!this.options.skipBubbling) this.buttonStateHover(button, action);
		  	if(action.subMenuItems.dynamicBuilder){
		  		action.subMenuItems.dynamicBuilder(subMenu);
		  	}
		}.bind(this);		
		subMenu.options.beforeHide = function(e){
			button.removeClassName("menuAnchorSelected");
			if(!this.options.skipBubbling) this.buttonStateOut(button, action);
		}.bind(this);
		if(!this.element.subMenus) this.element.subMenus = $A([]);
		this.element.subMenus.push(subMenu);
	},

    /**
     * Creates a submenu
     * @param button HTMLElement The anchor of the submenu
     * @param action Action The action
     */
    buildActionBarStylingMenu : function(){
        this.stylingMenu = new Proto.Menu({
          mouseClick:"right",
          selector: $(this.element.parentNode),
          anchor: 'mouse',
          className: 'menu desktop textual', // this is a class which will be attached to menu container (used for css styling)
          topOffset : 0,
          leftOffset : 0,
          parent : this.element,
          menuItems: [],
          beforeShow:function(){
              this.stylingMenu.options.menuItems = this.listStyleMenuItems();
          }.bind(this),
          fade:true,
          zIndex:2000
        });
    },

    listStyleMenuItems: function(){
        var items = [];
        var oThis = this;
        for(var k in this.options.styles){
            items.push({
                name: this.options.styles[k],
                alt: k,
                image: resolveImageSource((k == this.style?'button_ok.png':'transp.png'),Action.prototype.__DEFAULT_ICON_PATH, 16),
                isDefault: (k == this.style),
                callback: function(){ oThis.switchStyle(this); }
            });
        }
        return items;
    },

    switchStyle: function(command, start){
        var style = this.style;
        if(command){
            if(command.nodeName.toLowerCase() == 'img'){
                command = command.up('a');
            }
            style = command.getAttribute("title");
            this.style = style;
        }
        var parent = this.element.up("div[@ajxpClass]");
        while(parent.up("div[@ajxpClass]") != undefined){
            parent = parent.up("div[@ajxpClass]");
        }
        var actBar = this.element.up("div.action_bar");

        var applyResize = function(){
            for(var k in this.options.styles){
                if(k!=style) this.element.parentNode.removeClassName(k);
            }
            this.element.parentNode.addClassName(style);
            if(this.options.stylesImgSizes && this.options.stylesImgSizes[style]){
                this.element.select("img.actionbar_button_icon").each(function(img){
                    img.src = resolveImageSource(img.getAttribute("data-action-src"),Action.prototype.__DEFAULT_ICON_PATH, this.options.stylesImgSizes[style]);
                }.bind(this));
            }
            if(parent.ajxpPaneObject) parent.ajxpPaneObject.resize();
            if(ajaxplorer.user && !start){
                ajaxplorer.user.setPreference("action_bar_style", style);
                ajaxplorer.user.savePreference("action_bar_style");
            }
        }.bind(this);

        if(this.options.stylesBarSizes && this.options.stylesBarSizes[style]){
            new Effect.Morph(actBar, {
                style:'height:'+this.options.stylesBarSizes[style]+'px',
                duration:0.5,
                afterFinish:applyResize,
                afterUpdate:function(){
                    actBar.select("div.separator").invoke("setStyle", {height:(actBar.getHeight()-1)+"px"});
                    if(parent.ajxpPaneObject) parent.ajxpPaneObject.resize();
                }
            });
        }else{
            applyResize();
        }


    },

	/**
	 * Listener for mouseover on the button
	 * @param button HTMLElement The button
	 * @param action Action its associated action
	 */
	buttonStateHover : function(button, action){		
		if(button.hasClassName('disabled')) return;
		if(button.hideTimeout) clearTimeout(button.hideTimeout);
		new Effect.Morph(button.select('img[id="'+action.options.name +'_button_icon"]')[0], {
			style:'width:22px; height:22px;margin-top:3px;',
			duration:0.08,
			transition:Effect.Transitions.sinoidal,
			afterFinish: function(){this.updateTitleSpan(button.select('span')[0], 'big');}.bind(this)
		});
	},
	
	/**
	 * Listener for mouseout on the button
	 * @param button HTMLElement The button
	 * @param action Action its associated action
	 */
	buttonStateOut : function(button, action){
		if(button.hasClassName('disabled')) return;
		button.hideTimeout = setTimeout(function(){				
			new Effect.Morph(button.select('img[id="'+action.options.name +'_button_icon"]')[0], {
				style:'width:18px; height:18px;margin-top:8px;',
				duration:0.2,
				transition:Effect.Transitions.sinoidal,
				afterFinish: function(){this.updateTitleSpan(button.select('span')[0], 'small');}.bind(this)
			});	
		}.bind(this), 10);
	},
	
	/**
	 * Updates the button label
	 * @param span HTMLElement <span>
	 * @param state String "big", "small"
	 */
	updateTitleSpan : function(span, state){		
		if(!span.orig_width && state == 'big'){
			var origWidth = span.getWidth();
			span.setStyle({display:'block',width:origWidth, overflow:'visible', padding:0});
			span.orig_width = origWidth;
		}
		span.setStyle({fontSize:(state=='big'?'11px':'9px')});
	},	
	
	/**
	 * Resize the widget. May trigger the apparition/disparition of the Carousel buttons.
	 */
	resize : function(){
		var innerSize = 0;
		var parentWidth = $(this.outer).parentNode.getWidth();
		if(Prototype.Browser.IE){
			parentWidth = $(this.outer).getOffsetParent().getWidth()-4;//document.viewport.getWidth();
		}
		var visibles = [];
		var buttons = this.inner.select('a');
		buttons.each(function(a){
			if(a.visible()) visibles.push(a);
		});
		if(visibles.length){
			var last = visibles[visibles.length-1];
			var innerSize = last.cumulativeOffset()[0] + last.getWidth();
		}
		if(innerSize > parentWidth){
            var h = parseInt(this.element.up("div.action_bar").getHeight()) - 1;
            var m = parseInt( (h - 10) / 2);
            h = h - m;
            this.prev.setStyle({height:h+'px',paddingTop:m+'px'});
            this.next.setStyle({height:h+'px',paddingTop:m+'px'});
			this.prev.show();
			this.next.show();
			this.outer.setStyle({width:(parentWidth-this.prev.getWidth()-this.next.getWidth()) + 'px'});
		}else{
			this.prev.hide();
			this.next.hide();
			this.carousel.first();
			this.outer.setStyle({width:parentWidth + 'px'});
		}
	},
	/**
	 * IAjxpWidget Implementation. Empty.
	 * @param show Boolean
	 */
	showElement : function(show){}	
	
});