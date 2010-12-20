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
 * Description : A selector for displaying repository list. Will hook to ajaxplorer:repository_list_refreshed.
 */
Class.create("RepositorySelect", {
	__implements : "IAjxpWidget",
	_defaultString:'No Repository',
	_defaultIcon : 'network-wired.png',
	
	initialize : function(oElement){
		this.element = oElement;
		this.element.ajxpPaneObject = this;
		this.show = true;
		this.createGui();
		document.observe("ajaxplorer:repository_list_refreshed", function(e){
			this.refreshRepositoriesMenu(e.memo.list,e.memo.active);
		}.bind(this) );
	},
	
	createGui : function(){
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
		this.button.select('img')[0].setStyle({height:6, width:10, marginLeft:1, marginRight:1, marginTop:8});
		this.element.insert(this.button);
	},
	
	refreshRepositoriesMenu: function(repositoryList, repositoryId){
		this.button.addClassName('disabled');
		var actions = $A([]);
		var lastActions = $A([]);
		if(repositoryList && repositoryList.size()){
			repositoryList.each(function(pair){
				var repoObject = pair.value;
				var key = pair.key;
				var selected = (key == repositoryId ? true:false);
				
				var actionData = {
					name:repoObject.getLabel(),
					alt:repoObject.getLabel(),				
					image:repoObject.getIcon(),
					className:"edit",
					disabled:selected,
					callback:function(e){
						this.onRepoSelect(''+key);
					}.bind(this)
				};
				if(repoObject.getAccessType() == "ajxp_shared" || repoObject.getAccessType() == "ajxp_conf"){
					lastActions.push(actionData);
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
        if(lastActions.length){
	        lastActions.sort(fonc);
	        actions.push({separator:true});	        
	        actions = actions.concat(lastActions);
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
	
	onRepoSelect : function(key){
		ajaxplorer.triggerRepositoryChange(key);
	},
	
	resize : function(){
		var parent = this.element.getOffsetParent();
		if(parent.getWidth() < this.currentRepositoryLabel.getWidth()*3.5){
			this.showElement(false);
		}else{
			this.showElement(true);
		}
	},
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
	getActualWidth : function(){
		if(this.currentRepositoryLabel.visible()) return this.element.getWidth();
		else return this.button.getWidth() + 10;
	}
	
});