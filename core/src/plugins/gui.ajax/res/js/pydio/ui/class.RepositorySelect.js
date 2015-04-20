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
 * A selector for displaying repository list. Will hook to ajaxplorer:repository_list_refreshed.
 */
Class.create("RepositorySelect", {
	__implements : "IAjxpWidget",
	_defaultString:'No Repository',
	_defaultIcon : 'network-wired.png',
    options : {},
	/**
	 * Constructor
	 * @param oElement HTMLElement Anchor
	 */
	initialize : function(oElement, options){
		this.element = oElement;
		this.element.ajxpPaneObject = this;
		this.show = true;
        if(options){
            this.options = options;
        }
		this.createGui();
        this.observer = function(e){
            this.refreshRepositoriesMenu(e.memo.list,e.memo.active);
        }.bind(this);
		document.observe("ajaxplorer:repository_list_refreshed",  this.observer);

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
		this.element = null;
        document.stopObserving("ajaxplorer:repository_list_refreshed", this.observer);
        if(this.repoMenu){
            this.repoMenu.destroy();
        }
	},
	
	/**
	 * Creates the HTML
	 */
	createGui : function(){
		if(MessageHash){
			this._defaultString = MessageHash[391];
		}		

        if(this.options.simpleLabel){
            this.element.insert(this.options.simpleLabel);
            return;
        }

		this.icon = new Element('img', {
			id:'repo_icon',
			src:resolveImageSource(this._defaultIcon,'/images/actions/ICON_SIZE', 16),
			width:16,
			height:16,
			align:'absmiddle'
		});
		this.label = new Element('input', {
			 type:"text", 
			 name:"repo_path", 
			 value:this._defaultString, 
			 id:"repo_path"
		});
		this.currentRepositoryLabel = new Element('div', {id:'repository_form'});
		this.currentRepositoryLabel.insert(this.icon);
		this.currentRepositoryLabel.insert(this.label);
		this.element.insert(this.currentRepositoryLabel);
		this.button = simpleButton(
			'repository_goto', 
			'inlineBarButton', 
			200, 
			200, 
			ajxpResourcesFolder + '/images/arrow_down.png', 
			16,
			'inline_hover', null, true);
		this.button.setStyle({marginRight:'7px'});		
		this.button.select('img')[0].setStyle({height:'6px', width:'10px', marginLeft:'1px', marginRight:'1px', marginTop:'8px'});
		this.element.insert(this.button);
	},
	/**
	 * Refresh the whole drop-down list
	 * @param repositoryList $A
	 * @param repositoryId String
	 */
	refreshRepositoriesMenu: function(repositoryList, repositoryId){

        var button;
        if(this.options.simpleLabel){
            button = this.element;
        }else{
            button = this.button;
        }

		button.addClassName('disabled');
		var actions = $A([]);
		var lastActions = $A([]);
        var sharedActions = $A([]);
		if(repositoryList && repositoryList.size()){
			repositoryList.each(function(pair){
				var repoObject = pair.value;
				var key = pair.key;
				var selected = (key == repositoryId);

                if(repoObject.getAccessType().startsWith('ajxp_')){
                    return;
                }

                var label =  repoObject.getHtmlBadge() + '<span class="menu_label">' + repoObject.getLabel() + '</span>';
                var alt = repoObject.getLabel();
                if(repoObject.getDescription()){
                    label += '<span class="menu_description">' + repoObject.getDescription() + '</span>';
                    alt += '-' + repoObject.getDescription();
                }else{
                    alt += (repoObject.getOwner() ? " ("+MessageHash[413]+" " + repoObject.getOwner()+ ")":"");
                }
                var actionData = {
					name:label,
					alt:alt,
					image:repoObject.getIcon(),
                    icon_class:"icon-hdd",
                    overlay:repoObject.getOverlay(),
					className:"edit",
					disabled:selected,
					callback:function(e){
						this.onRepoSelect(''+key);
					}.bind(this)
				};
                if(repoObject.userEditable){
                    actionData.moreActions = this.getContextActions(key);
                }
				if(repoObject.getAccessType() == "ajxp_shared"){
					lastActions.push(actionData);
                }else if(repoObject.getOwner()){
                    sharedActions.push(actionData);
				}else{
					actions.push(actionData);
				}				
				if(key == repositoryId){
					if(this.label) this.label.setValue(repoObject.getLabel());
                    if(this.icon) this.icon.src = repoObject.getIcon();
				}
			}.bind(this));
		}else{
            if(this.label) this.label.setValue(this._defaultString);
            if(this.icon) this.icon.src = resolveImageSource(this._defaultIcon,'/images/actions/ICON_SIZE', 16);
		}
		
		var fonc = function(a,b){
		    var x = a.name.toLowerCase();
		    var y = b.name.toLowerCase();
		    return ((x < y) ? -1 : ((x > y) ? 1 : 0));
		};
        actions.sort(fonc);
        if(sharedActions.length){
	        sharedActions.sort(fonc);
	        actions.push({
                separator:true,
                menuTitle:MessageHash[469]
            });
	        actions = actions.concat(sharedActions);
        }
        if(lastActions.length){
	        lastActions.sort(fonc);
	        actions.push({
                separator:true,
                menuTitle:'Other Actions'
            });
	        actions = actions.concat(lastActions);
        }

        if(!actions.length){
            if(this.repoMenu){
                this.repoMenu.options.menuItems = actions;
                this.repoMenu.refreshList();
            }
            return;
        }

        var menuActionsLoader = function(){
            var menuItems = $A();
            ajaxplorer.actionBar.getActionsForAjxpWidget("RepositorySelect", this.element.id).each(function(otherAction){
                menuItems.push({
                    name:otherAction.getKeyedText(),
                    alt:otherAction.options.title,
                    action_id:otherAction.options.name,
                    icon_class:otherAction.options.icon_class,
                    className:"edit",
                    image:resolveImageSource(otherAction.options.src, '/images/actions/ICON_SIZE', 16),
                    callback:function(e){this.apply();}.bind(otherAction)
                });
            });
            if(menuItems.length){
                actions.push({separator:true});
                actions = actions.concat(menuItems);
            }
            this.repoMenu.options.menuItems = actions;
            this.repoMenu.refreshList();
        }.bind(this);

		if(this.repoMenu){
			this.repoMenu.options.menuItems = actions;
			this.repoMenu.refreshList();
		}else{
			this.repoMenu = new Proto.Menu({			
				className: 'menu rootDirChooser menuDetails workspacesMenu',
				mouseClick:(this.options.menuEvent? this.options.menuEvent : 'left'),
				anchor:button,
                position: (this.options.menuPosition? this.options.menuPosition : 'bottom'),
				createAnchor:false,
				anchorContainer:$('dir_chooser'),
				anchorSrc:ajxpResourcesFolder+'/images/arrow_down.png',
				anchorTitle:MessageHash[200],
				topOffset:(this.options.menuOffsetTop !== undefined ? this.options.menuOffsetTop: 2),
				leftOffset:(this.options.menuOffsetLeft !== undefined ? this.options.menuOffsetLeft: -127),
				menuTitle:MessageHash[468],
				menuItems: actions,
                menuMaxHeight:this.options.menuMaxHeight,
                menuFitHeight:this.options.menuFitHeight,
				fade:true,
                beforeShow:menuActionsLoader,
				zIndex:1500
			});
			this.notify("createMenu");
		}
		if(actions.length) button.removeClassName('disabled');
	},
	/**
	 * Listener for repository selection 
	 * @param key String
	 */
	onRepoSelect : function(key){
		ajaxplorer.triggerRepositoryChange(key);
	},
	/**
	 * Resize widget
	 */
	resize : function(){
        if(!this.currentRepositoryLabel) return;
		var parent = this.element.getOffsetParent();
		if(parent.getWidth() < this.currentRepositoryLabel.getWidth()*3.5){
			this.showElement(false);
		}else{
			this.showElement(true);
		}
	},

    /**
     * Gets the bookmark actions for a bookmark
     * @param repositoryId
     */
	getContextActions: function(repositoryId){

		var removeAction = {
				name:MessageHash[423],
				alt:MessageHash[423],
				image:ajxpResourcesFolder+'/images/actions/16/delete_bookmark.png',
                icon_class:'icon-remove',
				disabled:false,
				className:"edit",
				callback:function(e){
					if(window.confirm(MessageHash[424])){
                        var conn = new Connexion();
                        conn.setParameters({get_action:'user_delete_repository', repository_id:repositoryId});
                        conn.onComplete = function(transport){
                            ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                        };
                        conn.sendAsync();
                    }
				}.bind(this)
			};

		return new Array(removeAction);
	},
	/**
	 * Show/hide element
	 * @param show Boolean
	 */
	showElement : function(show){
		this.show = show;
		if(show){
			this.currentRepositoryLabel.show();
			if(this.repoMenu) this.repoMenu.options.leftOffset = -127;
		}
		else{
			this.currentRepositoryLabel.hide();
			if(this.repoMenu) this.repoMenu.options.leftOffset = 0;
		}
		if(!this.repoMenu){
			this.observeOnce("createMenu", function(){this.showElement(this.show);}.bind(this));
		}
	},
	/**
	 * Utilitary
	 * @returns Integer
	 */
	getActualWidth : function(){
        if(this.options.simpleLabel) return this.element.getWidth();
		if(this.currentRepositoryLabel.visible()) return this.element.getWidth();
		else return this.button.getWidth() + 10;
	}
	
});