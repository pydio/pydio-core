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
 * A selector for displaying repository list. Will hook to ajaxplorer:repository_list_refreshed.
 */
Class.create("RepositorySelect", {
	__implements : "IAjxpWidget",
	_defaultString:'No Repository',
	_defaultIcon : 'network-wired.png',
	/**
	 * Constructor
	 * @param oElement HTMLElement Anchor
	 */
	initialize : function(oElement){
		this.element = oElement;
		this.element.ajxpPaneObject = this;
		this.show = true;
		this.createGui();
		document.observe("ajaxplorer:repository_list_refreshed", function(e){
			this.refreshRepositoriesMenu(e.memo.list,e.memo.active);
		}.bind(this) );
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
	},
	
	/**
	 * Creates the HTML
	 */
	createGui : function(){
		if(MessageHash){
			this._defaultString = MessageHash[391];
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
		this.button.addClassName('disabled');
		var actions = $A([]);
		var lastActions = $A([]);
        var sharedActions = $A([]);
		if(repositoryList && repositoryList.size()){
			repositoryList.each(function(pair){
				var repoObject = pair.value;
				var key = pair.key;
				var selected = (key == repositoryId ? true:false);
				
				var actionData = {
					name:repoObject.getLabel(),
					alt:repoObject.getLabel() + (repoObject.getOwner() ? " ("+MessageHash[413]+" " + repoObject.getOwner()+ ")":""),
					image:repoObject.getIcon(),
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
				if(repoObject.getAccessType() == "ajxp_shared" || repoObject.getAccessType() == "ajxp_conf"){
					lastActions.push(actionData);
                }else if(repoObject.getOwner()){
                    sharedActions.push(actionData);
				}else{
					actions.push(actionData);
				}				
				if(key == repositoryId){
					this.label.setValue(repoObject.getLabel());
					this.icon.src = repoObject.getIcon();
				}
			}.bind(this));
		}else{
			this.label.setValue(this._defaultString);
			this.icon.src = resolveImageSource(this._defaultIcon,'/images/actions/ICON_SIZE', 16);
		}
		
		var fonc = function(a,b){
		    var x = a.name.toLowerCase();
		    var y = b.name.toLowerCase();
		    return ((x < y) ? -1 : ((x > y) ? 1 : 0));
		};
        actions.sort(fonc);
        if(sharedActions.length){
	        sharedActions.sort(fonc);
	        actions.push({separator:true});	        
	        actions = actions.concat(sharedActions);
        }
        if(lastActions.length){
	        lastActions.sort(fonc);
	        actions.push({separator:true});
	        actions = actions.concat(lastActions);
        }

        var menuItems = $A();
        var otherActions = ajaxplorer.actionBar.getActionsForAjxpWidget("RepositorySelect", this.element.id).each(function(otherAction){
            menuItems.push({
                name:otherAction.getKeyedText(),
                alt:otherAction.options.title,
                action_id:otherAction.options.name,
                className:"edit",
                image:resolveImageSource(otherAction.options.src, '/images/actions/ICON_SIZE', 16),
                callback:function(e){this.apply();}.bind(otherAction)
            });
        });
        if(menuItems.length){
            actions.push({separator:true});
            actions = actions.concat(menuItems);
        }

		if(this.repoMenu){
			this.repoMenu.options.menuItems = actions;
			this.repoMenu.refreshList();
		}else{
			this.repoMenu = new Proto.Menu({			
				className: 'menu rootDirChooser',
				mouseClick:'left',
				anchor:this.button,
				createAnchor:false,
				anchorContainer:$('dir_chooser'),
				anchorSrc:ajxpResourcesFolder+'/images/arrow_down.png',
				anchorTitle:MessageHash[200],
				topOffset:2,
				leftOffset:-127,
				menuTitle:MessageHash[200],
				menuItems: actions,
				fade:true,
				zIndex:1500
			});		
			this.notify("createMenu");
		}
		if(actions.length) this.button.removeClassName('disabled');
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
		var parent = this.element.getOffsetParent();
		if(parent.getWidth() < this.currentRepositoryLabel.getWidth()*3.5){
			this.showElement(false);
		}else{
			this.showElement(true);
		}
	},

	/**
	 * Gets the bookmark actions for a bookmark
	 * @param bmPath String
	 * @param bmTitle String
	 */
	getContextActions: function(repositoryId){

		var removeAction = {
				name:MessageHash[423],
				alt:MessageHash[423],
				image:ajxpResourcesFolder+'/images/actions/16/delete_bookmark.png',
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
		if(this.currentRepositoryLabel.visible()) return this.element.getWidth();
		else return this.button.getWidth() + 10;
	}
	
});