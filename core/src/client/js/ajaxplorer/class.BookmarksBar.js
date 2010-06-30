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
 * Description : Manages the display of the bookmarks menus.
 */
 Class.create("BookmarksBar", {
	
	initialize: function(oElement){
		this.element = $(oElement);
		this.currentCount = 0;	
		this.bookmarks = $A([]);
		this.createMenu();
		document.observe("ajaxplorer:registry_loaded", function(event){
			this.parseXml(event.memo);
		}.bind(this) );
		document.observeOnce("ajaxplorer:actions_loaded", function(){
			var bmAction = ajaxplorer.actionBar.actions.get('bookmark');
			this.addBookmarkObject = {
				name:bmAction.getKeyedText(),
				alt:bmAction.options.title,
				image:ajxpResourcesFolder+'/images/actions/16/bookmark_add.png',
				callback:function(e){
					var node = ajaxplorer.getContextNode();
					this.addBookmark(node.getPath(), node.getLabel());
				}.bind(this)
			};		
		}.bind(this));
		document.observe("ajaxplorer:add_bookmark", function(){
			var node = ajaxplorer.getContextNode();
			this.addBookmark(node.getPath(), node.getLabel());			
		}.bind(this) );
	},
	
	parseXml: function(registry){
		this.clear();
		var childNodes = XPathSelectNodes(registry, "user/bookmarks/bookmark");
		for (var i=0; i < childNodes.length;i++)
		{
			var bookmark = {
				name:childNodes[i].getAttribute('title'),
				alt:childNodes[i].getAttribute('path'),
				image:ajxpResourcesFolder+'/images/mimes/16/folder.png'
			};
			bookmark.callback = function(e){ajaxplorer.goTo(this.alt)}.bind(bookmark);
			bookmark.moreActions = this.getContextActions(bookmark.alt, bookmark.name);
			this.bookmarks.push(bookmark);
		}
		this.bmMenu.options.menuItems = this.bookmarks;
		this.bmMenu.refreshList();
		if(this.bookmarks.length) this.element.removeClassName('inline_disabled');
		if(modal.pageLoading) modal.updateLoadingProgress('Bookmarks Loaded');
	},
	
	createMenu : function(){
		this.bmMenu = new Proto.Menu({			
			className: 'menu bookmarksMenu',
			mouseClick:'left',
			anchor:this.element,
			createAnchor:false,
			topOffset:2,
			leftOffset:0,
			menuItems: this.bookmarks,
			fade:true,
			zIndex:2000
		});
	},
		
	clear: function(){
		this.currentCount = 0;
		if(this.addBookmarkObject){
			this.bookmarks = $A([this.addBookmarkObject,{separator:true}]);
		}else{
			this.bookmarks = $A();
		}
		this.element.addClassName('inline_disabled');
		this.bmMenu.options.menuItems = this.bookmarks;
		this.bmMenu.refreshList();		
	},
	
	getContextActions: function(bmPath, bmTitle){
		
		var removeAction = {
				name:MessageHash[146],
				alt:MessageHash[146],
				image:ajxpResourcesFolder+'/images/actions/16/delete_bookmark.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					this.removeBookmark(bmPath);
				}.bind(this)
			};
		
		var renameAction = {
				name:MessageHash[6],
				alt:MessageHash[6],
				image:ajxpResourcesFolder+'/images/actions/16/applix.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					this.toggleRenameForm(bmPath, bmTitle);
				}.bind(this)
			};
		
			
			
		return new Array(renameAction, removeAction);
	},
	
	toggleRenameForm:function(bmPath, bmTitle){
		
		modal.prepareHeader(MessageHash[225], ajxpResourcesFolder+'/images/actions/16/bookmark.png');
	 	var onLoad = function(newForm){
	 		$(newForm).bm_path.value = bmPath;
	 		$(newForm).bm_title.value = bmTitle;
	 	};
	 	var onComplete = function(){	 		
	 		this.renameBookmark(modal.getForm().bm_path.value, modal.getForm().bm_title.value);
	 		hideLightBox(true);
	 	}.bind(this);
		modal.showDialogForm('Rename', 'rename_bookmark', onLoad, onComplete);
	},
	
	load: function(actionsParameters){
		var connexion = new Connexion();
		if(!actionsParameters) actionsParameters = new Hash();
		actionsParameters.set('get_action', 'get_bookmarks');
		connexion.setParameters(actionsParameters);
		connexion.onComplete = function(transport){
			document.observeOnce("ajaxplorer:registry_part_loaded", function(event){
				if(event.memo != "user/bookmarks") return;
				this.parseXml(ajaxplorer.getXmlRegistry());
			}.bind(this) );			
			ajaxplorer.loadXmlRegistry(false, "user/bookmarks");
			this.bmMenu.refreshList();
			this.bmMenu.show();
		}.bind(this);
		connexion.sendAsync();
	},
	
	addBookmark: function(path,title){
		var parameters = new Hash();
		parameters.set('bm_action', 'add_bookmark');
		parameters.set('bm_path', path);
		if(title){
			parameters.set('bm_title', title);
		}
		this.load(parameters);
	},
	
	removeBookmark: function(path){
		var parameters = new Hash();
		parameters.set('bm_action', 'delete_bookmark');
		parameters.set('bm_path', path);
		this.load(parameters);		
	},
	
	renameBookmark: function(path, title){
		var parameters = new Hash();
		parameters.set('bm_action', 'rename_bookmark');
		parameters.set('bm_path', path);
		parameters.set('bm_title', title);
		this.load(parameters);		
	}
	
});
