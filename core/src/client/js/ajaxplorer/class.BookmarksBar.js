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
var BookmarksBar = Class.create({
	
	initialize: function($super){
		this.oElement = $('bmbar_content');
		this.currentCount = 0;	
		this.bookmarks = $A([]);
		this.createMenu();
	},
	
	parseXml: function(transport){
		this.clear();
		var oXmlDoc = transport.responseXML;
		if(oXmlDoc == null || oXmlDoc.documentElement == null) return;		
		var root = oXmlDoc.documentElement;
		for (var i=0; i < root.childNodes.length;i++)
		{
			if(root.childNodes[i].tagName != 'bookmark') continue;			
			var bookmark = {
				name:root.childNodes[i].getAttribute('title'),
				alt:root.childNodes[i].getAttribute('path'),
				image:ajxpResourcesFolder+'/images/crystal/actions/16/favorite-folder.png'
			};
			bookmark.callback = function(e){ajaxplorer.goTo(this.alt)}.bind(bookmark);
			bookmark.moreActions = this.getContextActions(bookmark.alt, bookmark.name);
			this.bookmarks.push(bookmark);
		}
		this.bmMenu.options.menuItems = this.bookmarks;
		this.bmMenu.refreshList();
		if(this.bookmarks.length) $('bm_goto_button').removeClassName('disabled');
		if(modal.pageLoading) modal.updateLoadingProgress('Bookmarks Loaded');
	},
	
	createMenu : function(){
		this.bmMenu = new Proto.Menu({			
			className: 'menu bookmarksMenu',
			mouseClick:'left',
			anchor:'bm_goto_button',
			createAnchor:false,
			topOffset:4,
			leftOffset:-2,
			menuItems: this.bookmarks,
			fade:true,
			zIndex:2000
		});
	},
	
	displayBookmark: function(path, title){
		this.oElement.innerHTML += '<div id="bookmark_'+this.currentCount+'" bm_path="'+path+'" class="bm" onmouseover="this.className=\'bm_hover\';" onmouseout="this.className=\'bm\';" title="'+path+'"><img width="16" height="16" src="'+ajxpResourcesFolder+'/images/crystal/mimes/16/folder.png" border="0" align="ABSMIDDLE"  hspace="5" style="float:left;"><!--<a href="#" class="disabled" title="'+MessageHash[146]+'" onclick="ajaxplorer.actionBar.removeBookmark(\''+path+'\'); return false;" onmouseover="$(this).addClassName(\'enabled\');" onmouseout="$(this).removeClassName(\'enabled\');"><img width="16" height="16" src="'+ajxpResourcesFolder+'/images/crystal/actions/16/delete_bookmark.png" border="0" align="ABSMIDDLE" alt="'+MessageHash[146]+'"></a>--> <a href="#" onclick="ajaxplorer.goTo(\''+path+'\'); return false;" class="bookmark_button">'+title+'</a></div>';			
		this.currentCount++;
	},
	
	clear: function(){
		this.currentCount = 0;
		this.bookmarks = $A([]);
		$('bm_goto_button').addClassName('disabled');
	},
	
	setContextualMenu:function(oMenu){
		this.contextMenu = oMenu;
	},
	
	findBookmarkEventSource:function(srcElement){
		
		for(var i=0; i<this.currentCount; i++)
		{
			var bookmark = $('bookmark_'+i);
			if(!bookmark) continue;
			if(srcElement == bookmark) return bookmark;
			if(srcElement.descendantOf(bookmark)) return bookmark;
		}
	},
	
	getContextActions: function(bmPath, bmTitle){
		
		var removeAction = {
				name:MessageHash[146],
				alt:MessageHash[146],
				image:ajxpResourcesFolder+'/images/crystal/actions/16/delete_bookmark.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					this.removeBookmark(bmPath);
				}.bind(this)
			};
		
		var goToAction = {
				name:MessageHash[224],
				alt:MessageHash[104],
				image:ajxpResourcesFolder+'/images/crystal/actions/16/forward.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					ajaxplorer.goTo(bmPath);
				}
			};
		
		var renameAction = {
				name:MessageHash[6],
				alt:MessageHash[6],
				image:ajxpResourcesFolder+'/images/crystal/actions/16/applix.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					this.toggleRenameForm(bmPath, bmTitle);
				}.bind(this)
			};
		
			
			
		return new Array(renameAction, removeAction);
	},
	
	toggleRenameForm:function(bmPath, bmTitle){
		
		modal.prepareHeader(MessageHash[225], ajxpResourcesFolder+'/images/crystal/actions/16/bookmark.png');
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
		this.clear();
		var connexion = new Connexion();
		if(!actionsParameters) actionsParameters = new Hash();
		actionsParameters.set('get_action', 'get_bookmarks');
		connexion.setParameters(actionsParameters);
		connexion.onComplete = function(transport){
			this.parseXml(transport);
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
