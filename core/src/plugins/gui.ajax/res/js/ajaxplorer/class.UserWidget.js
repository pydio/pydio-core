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
 * Widget for users action, displayed on the right of the toolbar
 */
Class.create("UserWidget", {
	__implements : ["IAjxpWidget"],
    options : {},
    /**
     * Constructor
     * @param element HTMLElement
     * @param options Object
     */
	initialize: function(element, options){
		this.element = element;
		this.element.ajxpPaneObject = this;
        if(options){
            this.options = options;
        }
		
		this.mObs1 = function(){
            this.element.select('div').invoke('addClassName', 'user_widget_hover');
		}.bind(this);
		this.mObs2 = function(){
			var divs = this.element.select('div');
			if(!divs.length) return;
			if(divs[0].hasClassName('inline_hover_light')) return;
            this.element.select('div').invoke('removeClassName', 'user_widget_hover');
		}.bind(this);
		this.uLoggedObs = this.updateGui.bind(this);
		this.actLoaded = this.updateActions.bind(this);
		
		this.element.observe("mouseover", this.mObs1 );
		this.element.observe("mouseout", this.mObs2 );		
		document.observe("ajaxplorer:user_logged", this.uLoggedObs );
		document.observe("ajaxplorer:actions_loaded", this.actLoaded );
		if(Prototype.Browser.IE) {
			document.observe("ajaxplorer:actions_refreshed", this.actLoaded );
		}
	},
	/**
	 * Updates on user status change
	 */
	updateGui : function(){
		var logging_string = "";
		var oUser = ajaxplorer.user;		
		if(oUser != null) 
		{
			if(oUser.id != 'guest') 
			{
                var displayName = oUser.id;
                if(oUser.getPreference('USER_DISPLAY_NAME')){
                    displayName = oUser.getPreference('USER_DISPLAY_NAME');
                }
                //var label = '<ajxp:message ajxp_message_id="142">'+MessageHash[142]+'</ajxp:message><i>'+ oUser.id +' </i>';
                var label = '<i>'+ displayName +' </i> <span class="icon-caret-down ajxp_icon_arrow"></span>';
                var img = '';
                var imgSrc = '';
                var conn = new Connexion();
                if(oUser.getPreference("avatar")){
                    imgSrc = conn._baseUrl + "&get_action=get_binary_param&binary_id=" + oUser.getPreference("avatar") + "&user_id=" + oUser.id;
                } else if(ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.avatar']").get("AVATAR_PROVIDER")) {
                    // Get avatar from avatar plugins
                    conn.addParameter('get_action', 'get_avatar_url');
                    conn.addParameter('userid', oUser.id);
                    conn.onComplete = function(transport){
                        imgSrc = transport.responseText;
                    };
                    conn.sendSync();
                }

                if (imgSrc != '') {
                    img = '<img src="'+imgSrc+'" alt="avatar" class="user_widget_mini">';
                    label = '<i>' + img + displayName + '</i>';
                }
				logging_string = '<div class="user_widget_label '+(img?'withImage':'')+'"><span class="icon-reorder"></span> '+label+' </div><div class="inlineBarButtonLeft" style="-moz-border-radius: 0 5px 5px 0;border-radius: 0 5px 5px 0;border-left-style:none; border-width:1px;"><img width="16" height="16" style="height: 6px; width: 10px; margin-top: 9px; margin-left: 3px; margin-right: 3px;" ajxp_message_title="189" title="'+MessageHash[189]+'" src="'+ajxpResourcesFolder+'/images/arrow_down.png"></div>';
				this.element.removeClassName('disabled');
				if(!oUser.lock && oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != ajaxplorer.currentLanguage)
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
	
	/**
	 * Updates the menu with dedicated actions
	 */
	updateActions : function(){
		var menuItems = $A();
		var actions = ajaxplorer.actionBar.getActionsForAjxpWidget("UserWidget", this.element.id);
        var groups = {};

        actions.each(function(action){
            var bGroup;
            try{
                bGroup = action.context.actionBarGroup;
            }catch (e){}
            if(!bGroup) bGroup = "default";
            if(!groups[bGroup]){
                groups[bGroup] = $A();
            }
            groups[bGroup].push({
				name:action.getKeyedText(),
                action_id:action.options.name,
				alt:action.options.title,
				image:resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16),
                icon_class:action.options.icon_class,
				callback:function(e){this.apply();}.bind(action)
			});
		});
        for(var key in groups){
            if(groups.hasOwnProperty(key)){
                menuItems = menuItems.concat(groups[key], {separator:true});
            }
        }
        menuItems.pop();
		
		if(this.menu){
			this.menu.options.menuItems = menuItems;
			this.menu.refreshList();
		}else{			
			this.menu = new Proto.Menu({			
				className: 'menu rootDirChooser menuDetails',
				mouseClick:(this.options.menuEvent?this.options.menuEvent:"left"),
				position: 'bottom right',
				anchor:this.element,
				createAnchor:false,
				topOffset: (this.options.menuOffsetTop ? this.options.menuOffsetTop  : 2),
				leftOffset:(this.options.menuOffsetLeft? this.options.menuOffsetLeft :-3),
				menuItems: menuItems,
                menuTitle: MessageHash[511].replace('%s', ajaxplorer.getPluginConfigs("ajaxplorer").get("APPLICATION_TITLE")),
                detailedItems: true,
				fade:true,
				zIndex:1500,
				beforeShow : function(e){
					this.element.select('div').invoke('addClassName', 'inline_hover_light');
                    this.element.select('div').invoke('addClassName', 'user_widget_hover');
				}.bind(this),
				beforeHide : function(e){
					this.element.select('div').invoke('removeClassName', 'inline_hover_light');
					this.element.select('div').invoke('removeClassName', 'user_widget_hover');
				}.bind(this),
				beforeSelect : function(e){
					this.element.select('div').invoke('removeClassName', 'inline_hover_light');
                    this.element.select('div').invoke('removeClassName', 'user_widget_hover');
				}.bind(this)
			});		
			this.notify("createMenu");
		}
		
	},
	/**
	 * Resize widget
	 */
	resize : function(){
	},
	/**
	 * Show/hide widget
	 * @param show Boolean
	 */
	showElement : function(show){
		this.element.select(".user_widget_label").invoke((show?'show':'hide'));
	},
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	getDomNode : function(){
		return this.element;
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	destroy : function(){
		this.element.stopObserving("mouseover", this.mObs1 );
		this.element.stopObserving("mouseout", this.mObs2 );		
		document.stopObserving("ajaxplorer:user_logged", this.uLoggedObs );
		document.stopObserving("ajaxplorer:actions_loaded", this.actLoaded );		
		if(Prototype.Browser.IE) {
			document.stopObserving("ajaxplorer:actions_refreshed", this.actLoaded );
		}
        if(this.menu){
            this.menu.destroy();
        }
		this.element = null;
	}
	
});