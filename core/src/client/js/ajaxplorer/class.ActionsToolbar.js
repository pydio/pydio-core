/**
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
 * 
 * Description : Toolbar to display actions buttons
 */
Class.create("ActionsToolbar", {
	__implements : "IAjxpWidget",
	initialize : function(oElement, options){
		this.element = oElement;
		this.options = Object.extend({
			buttonRenderer : 'this',
			toolbarsList : $A(['default', 'put', 'get', 'change', 'user', 'remote'])
		}, options || {});
		var renderer = this.options.buttonRenderer;
		if(renderer == 'this'){
			this.options.buttonRenderer = this;
		}else{
			this.options.buttonRenderer = new renderer();
		}
		this.toolbars = $H();
		document.observe("ajaxplorer:actions_loaded", this.actionsLoaded.bind(this));
		document.observe("ajaxplorer:actions_refreshed", this.refreshToolbarsSeparator.bind(this));
	},
	
	actionsLoaded : function() {
		this.actions = ajaxplorer.actionBar.actions;
		this.emptyToolbars();
		this.initToolbars();
	},
	
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
	},
	
	initToolbar: function(toolbar){
		if(!this.toolbars.get(toolbar)) {
			return;
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
			//toolEl.insert(action.toActionBar());
			toolEl.actionsCount ++;			
		}.bind(this));
		return toolEl;
	},
	
	emptyToolbars: function(){
		this.element.select('div').each(function(divElement){			
			divElement.remove();
		}.bind(this));
		this.toolbars = new Hash();
	},
	
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
		var imgPath = resolveImageSource(action.options.src,action.__DEFAULT_ICON_PATH, 22);
		var img = new Element('img', {
			id:action.options.name +'_button_icon',
			src:imgPath,
			width:18,
			height:18,
			border:0,
			align:'absmiddle',
			alt:action.options.title,
			title:action.options.title
		});
		var titleSpan = new Element('span', {id:action.options.name+'_button_label'}).setStyle({paddingLeft:6,paddingRight:6, cursor:'pointer'});
		button.insert(img).insert(new Element('br')).insert(titleSpan.update(action.getKeyedText()));
		//this.elements.push(this.button);
		if(action.options.subMenu){
			this.buildActionBarSubMenu(button, action);// TODO
			button.arrowDiv = new Element('div');
			button.arrowDiv.insert(new Element('img',{src:ajxpResourcesFolder+'/images/crystal/arrow_down.png',height:6,width:10,border:0}));
			button.arrowDiv.imgRef = img;
			button.insert(button.arrowDiv);
		}else{
			button.observe("mouseover", function(){
				this.buttonStateHover(button, action);
			}.bind(this) );
			button.observe("mouseout", function(){
				this.buttonStateOut(button, action);
			}.bind(this) );
		}
		button.hide();
		this.attachListeners(button, action);
		return button;
		
	},
	
	attachListeners : function(button, action){
		action.observe("hide", function(){
			button.hide();
		}.bind(this));
		action.observe("show", function(){
			button.show();
			this.placeArrowDiv(button);
		}.bind(this));
		action.observe("disable", function(){
			button.addClassName("disabled");
		}.bind(this));
		action.observe("enable", function(){
			button.removeClassName("disabled");
			this.placeArrowDiv(button);
		}.bind(this));
		action.observe("submenu_active", function(submenuItem){
			if(!submenuItem.src || !action.options.subMenuUpdateImage) return;
			var images = button.select('img[id="'+action.options.name +'_button_icon"]');
			if(!images.length) return;
			images[0].src = resolveImageSource(submenuItem.src, action.__DEFAULT_ICON_PATH,22);
			action.options.src = submenuItem.src;
		}.bind(this));
	},
	
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
			this.buttonStateHover(button, action);
		  	if(action.subMenuItems.dynamicBuilder){
		  		action.subMenuItems.dynamicBuilder(subMenu);
		  	}
		}.bind(this);		
		subMenu.options.beforeHide = function(e){
			button.removeClassName("menuAnchorSelected");
			this.buttonStateOut(button, action);
		}.bind(this);
		if(!this.element.subMenus) this.element.subMenus = $A([]);
		this.element.subMenus.push(subMenu);
	},
	
	placeArrowDiv : function(button){
		if(button.arrowDiv){
			var imgPos = Position.cumulativeOffset(button.arrowDiv.imgRef)[0] + 11;
			button.arrowDiv.setStyle({position:'absolute',top:18,left:imgPos});		
		}
	},
	
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
	
	updateTitleSpan : function(span, state){		
		if(!span.orig_width && state == 'big'){
			var origWidth = span.getWidth();
			span.setStyle({display:'block',width:origWidth, overflow:'visible', padding:0});
			span.orig_width = origWidth;
		}
		span.setStyle({fontSize:(state=='big'?'11px':'9px')});
	},	
	
	resize : function(){},
	showElement : function(show){}	
	
});