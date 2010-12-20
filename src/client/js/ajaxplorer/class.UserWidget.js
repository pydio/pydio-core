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
Class.create("UserWidget", {
	__implements : ["IAjxpWidget"],
	initialize: function(element){
		this.element = element;
		this.element.ajxpPaneObject = this;
		this.element.observe("mouseover", function(){
			this.element.select('div').invoke('setStyle', {borderColor:'#bbbbbb'});			
		}.bind(this));
		this.element.observe("mouseout", function(){
			var divs = this.element.select('div');
			if(!divs.length) return;
			if(divs[0].hasClassName('inline_hover_light')) return
			divs.invoke('setStyle', {borderColor:'#dddddd'});	
		}.bind(this));		
		document.observe("ajaxplorer:user_logged", this.updateGui.bind(this));
		document.observe("ajaxplorer:actions_loaded", this.updateActions.bind(this));		
		if(Prototype.Browser.IE) document.observe("ajaxplorer:actions_refreshed", this.updateActions.bind(this));
	},
	
	updateGui : function(){
		var logging_string = "";
		var oUser = ajaxplorer.user;		
		if(oUser != null) 
		{
			if(oUser.id != 'guest') 
			{
				logging_string = '<div class="user_widget_label"><ajxp:message ajxp_message_id="142">'+MessageHash[142]+'</ajxp:message><i ajxp_message_title_id="189" title="'+MessageHash[189]+'">'+ oUser.id+' </i></div><div class="inlineBarButtonLeft" style="-moz-border-radius: 0pt 5px 5px 0pt;border-radius: 0pt 5px 5px 0pt;border-left-style:none; border-width:1px; border-color:#ddd;"><img width="16" height="16" style="height: 6px; width: 10px; margin-top: 9px; margin-left: 3px; margin-right: 3px;" ajxp_message_title="189" title="'+MessageHash[189]+'" src="'+ajxpResourcesFolder+'/images/arrow_down.png"></div>';
				this.element.removeClassName('disabled');
				if(oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				{
					ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				}
			}
			else 
			{
				logging_string = '<div style="padding:3px 0 3px 7px;"><ajxp:message ajxp_message_id="143">'+MessageHash[143]+'</ajxp:message></div>';
				this.element.addClassName('disabled');
			}
		}
		else 
		{
			logging_string = '<div style="padding:3px 0 3px 7px;"><ajxp:message ajxp_message_id="142">'+MessageHash[144]+'</ajxp:message></div>';
			this.element.addClassName('disabled');
		}
		this.element.update(logging_string);
	},
	
	updateActions : function(){
		var menuItems = $A();
		var actions = ajaxplorer.actionBar.getActionsForAjxpWidget("UserWidget", this.element.id).each(function(action){
			menuItems.push({
				name:action.getKeyedText(),
				alt:action.options.title,
				image:resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16),						
				callback:function(e){this.apply();}.bind(action)
			});			
		});
		
		if(this.menu){
			this.menu.options.menuItems = menuItems;
			this.menu.refreshList();
		}else{			
			this.menu = new Proto.Menu({			
				className: 'menu rootDirChooser rightAlignMenu',
				mouseClick:'left',
				position: 'bottom right',
				anchor:this.element,
				createAnchor:false,
				topOffset:2,
				leftOffset:-5,
				menuTitle:MessageHash[200],
				menuItems: menuItems,
				fade:true,
				zIndex:1500,
				beforeShow : function(e){
					this.element.select('div').invoke('addClassName', 'inline_hover_light');		
					this.element.select('div').invoke('setStyle', {borderColor:'#bbbbbb'});											
				}.bind(this),
				beforeHide : function(e){
					this.element.select('div').invoke('removeClassName', 'inline_hover_light');
					this.element.select('div').invoke('setStyle', {borderColor:'#dddddd'});	
				}.bind(this),
				beforeSelect : function(e){
					this.element.select('div').invoke('removeClassName', 'inline_hover_light');
					this.element.select('div').invoke('setStyle', {borderColor:'#dddddd'});	
				}.bind(this)
			});		
			this.notify("createMenu");
		}
		
	},
	
	resize : function(){
	},
	showElement : function(show){
		this.element.select(".user_widget_label").invoke((show?'show':'hide'));
	}	
});