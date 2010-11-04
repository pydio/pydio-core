/**
 * @package info.ajaxplorer.plugins
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
 * Description : Container for parent/location/bookmark components.
 */
Class.create("LocationBar", {
	__implements : ["IAjxpWidget", "IFocusable"],
	_defaultGotoIcon : 'media-playback-start.png',
	_reloadGotoIcon : 'reload.png',
	_modified : false,
	_beforeModified : '',
	
	initialize : function(oElement, options){
		this.element = oElement;
		this.element.ajxpPaneObject = this;
		this.realPath = '/';
		this.createGui();		
		this.options = options || {};
		document.observe("ajaxplorer:user_logged", this.resize.bind(this));
	},
	createGui : function(){
		this.parentButton = simpleButton(
			'goto_parent', 
			'inlineBarButtonRight', 
			24, 24, 
			'goto_parent.png', 16, 
			'inline_hover', 
			function(){ajaxplorer.actionBar.fireAction('up_dir');}
			);
		this.element.insert(this.parentButton);
		var locDiv = new Element('div', {id:'location_form'});
		this.initCurrentPath();
		this.element.insert(locDiv);
		locDiv.insert(this.currentPath);
		var inputDims = this.currentPath.getDimensions();
		this.currentPath.hide();
		this.label = new Element('div').update("/test");
		this.label.setStyle({
			marginTop: 1,
			fontSize:'11px',
			height: (Prototype.Browser.IE?'18px':'15px'),
			fontFamily : 'Trebuchet MS,sans-serif,Nimbus Sans L',
			zIndex : 10000,
			backgroundColor: 'white',
			whiteSpace:'nowrap',
			overflow:'hidden'
		});
		locDiv.insert(this.label);
		this.label.observe("click", function(){
			this.label.hide();
			this.currentPath.show();
			this.currentPath.focus();
		}.bind(this) );
		
		this.gotoButton = simpleButton(
			'location_goto', 
			'inlineBarButton', 
			104, 
			104, 
			this._reloadGotoIcon, 
			16, 
			'inline_hover', 
			this.submitPath.bind(this)
			);
		this.element.insert(this.gotoButton);
		
		this.bmButton = simpleButton(
			'bookmarks_goto', 
			'inlineBarButtonLeft', 
			145, 145, 
			'bookmark_menu.png', 
			16, 
			'inline_hover');
		this.element.insert(this.bmButton);
		this.initBookmarksBar();
	},
	initCurrentPath : function(){
		this.currentPath = new Element('input', {
			id:'current_path',
			type:'text',
			value:'/'
		});		
		this.autoComp = new AjxpAutocompleter(this.currentPath, "autocomplete_choices");
		this.currentPath.observe("keydown", function(event){
			if(event.keyCode == 9) return false;
			if(!this._modified && (this._beforeModified != this.currentPath.getValue())){
				this.setModified(true);
			}
			if(event.keyCode == 13){
				if(this.autoComp.active) return;
				this.submitPath();
				Event.stop(event);
			}
		}.bind(this));		
		this.currentPath.observe("focus", function(e)	{
			ajaxplorer.disableShortcuts();
			this.hasFocus = true;
			this.currentPath.select();
			return false;
		}.bind(this) );		
		this.currentPath.observe("blur",function(e)	{
			this.currentPath.hide();
			this.label.show();
			if(!currentLightBox){
				ajaxplorer.enableShortcuts();
				this.hasFocus = false;
			}
		}.bind(this));
		document.observe("ajaxplorer:context_changed", function(event){
			window.setTimeout(function(){
				this.updateLocationBar(event.memo);
			}.bind(this), 0);			
		}.bind(this) );

	},
	initBookmarksBar : function(){
		this.bookmarkBar = new BookmarksBar(this.bmButton);
	},
	submitPath : function(){
		if(!this._modified){
			ajaxplorer.actionBar.fireAction("refresh");
		}else{
			var url = this.currentPath.value;
			if(url == '') return false;	
			var node = new AjxpNode(url, false);
			var parts = url.split("##");
			if(parts.length == 2){
				var data = new Hash();
				data.set("new_page", parts[1]);
				url = parts[0];
				node = new AjxpNode(url);
				node.getMetadata().set("paginationData", data);
			}
			// Manually entered, stat path before calling
			if(!ajaxplorer.pathExists(url)){
				modal.displayMessage('ERROR','Cannot find : ' + url);
				this.currentPath.setValue(this._beforeModified);
			}else{
				ajaxplorer.actionBar.fireDefaultAction("dir", node);
			}
		}
		return false;
	},
	updateLocationBar: function (newNode)
	{
		if(Object.isString(newNode)){
			newNode = new AjxpNode(newNode);
		}
		var newPath = newNode.getPath();
		if(newNode.getMetadata().get('paginationData')){
			newPath += "##" + newNode.getMetadata().get('paginationData').get('current');
		}
		this.realPath = newPath;
		this.currentLabel = this.realPath;
		if(getBaseName(newPath) != newNode.getLabel()){
			this.currentLabel = getRepName(newPath) + '/' + newNode.getLabel();
		}
		this.label.update(this.currentLabel);
		this.currentPath.value = this.realPath;
		this.setModified(false);
	},	
	setModified:function(bool){
		this._modified = bool;
		this.gotoButton.setSrc(resolveImageSource((bool?this._defaultGotoIcon:this._reloadGotoIcon), '/images/actions/ICON_SIZE', 16));
		this._beforeModified = this.currentPath.getValue();
	},
	resize : function(){
		if(this.options.flexTo){
			var parentWidth = $(this.options.flexTo).getWidth();
			var siblingWidth = 0;
			this.element.siblings().each(function(s){
				if(s.ajxpPaneObject && s.ajxpPaneObject.getActualWidth){
					siblingWidth+=s.ajxpPaneObject.getActualWidth();
				}else{
					siblingWidth+=s.getWidth();
				}
			});
			if(!this.buttonsWidth){
				this.buttonsWidth = this.gotoButton.getWidth() + this.parentButton.getWidth() + this.bmButton.getWidth() + 20;
			}
			var newWidth = Math.min((parentWidth-siblingWidth-this.buttonsWidth),320);
			if(newWidth < 5){
				this.element.hide();
			}else{
				this.element.show();
				this.currentPath.setStyle({width:newWidth + 'px'});
				this.label.setStyle({width : newWidth + 'px'});
			}
		}
	},
	showElement : function(show){},
	setFocusBehaviour : function(){},
	focus : function(){
		this.label.hide();
		this.currentPath.show();
		this.currentPath.focus();
		this.hasFocus = true;
	},
	blur : function(){
		this.currentPath.blur();
		this.currentPath.hide();
		this.label.show();
		this.hasFocus = false;
	}	
});