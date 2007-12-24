var BookmarksBar = Class.create(ResizeableBar, {
	
	initialize: function($super){
		this.oElement = $('bmbar_content');		
		$super("bmbar_content", "bookmarks_bar", "bm", "bmbar_title", "bmbar_extension");
		this.load();
		this.currentCount = 0;
	},
	
	parseXml: function(transport){
		var oXmlDoc = transport.responseXML;
		if(oXmlDoc == null || oXmlDoc.documentElement == null) return;
		this.clearElement();
		var root = oXmlDoc.documentElement;
		for (var i=0; i < root.childNodes.length;i++)
		{
			if(root.childNodes[i].tagName != 'bookmark') continue;			
			this.displayBookmark(root.childNodes[i].getAttribute('path'), root.childNodes[i].getAttribute('title'));
		}
		this.updateUI();
		if(this.contextMenu) this.contextMenu.addElements('div.bm');
		if(modal.pageLoading) modal.updateLoadingProgress('Bookmarks Loaded');
	},
	
	displayBookmark: function(path, title){
		this.oElement.innerHTML += '<div id="bookmark_'+this.currentCount+'" bm_path="'+path+'" class="bm" onmouseover="this.className=\'bm_hover\';" onmouseout="this.className=\'bm\';" title="'+path+'"><img width="16" height="16" src="images/crystal/mimes/16/folder.png" border="0" align="ABSMIDDLE"  hspace="5" style="float:left;"><!--<a href="#" class="disabled" title="'+MessageHash[146]+'" onclick="ajaxplorer.actionBar.removeBookmark(\''+path+'\'); return false;" onmouseover="$(this).addClassName(\'enabled\');" onmouseout="$(this).removeClassName(\'enabled\');"><img width="16" height="16" src="images/crystal/actions/16/delete_bookmark.png" border="0" align="ABSMIDDLE" alt="'+MessageHash[146]+'"></a>--> <a href="#" onclick="ajaxplorer.goTo(\''+path+'\'); return false;" class="bookmark_button">'+title+'</a></div>';			
		this.currentCount++;
	},
	
	clearElement: function(){
		this.oElement.update();
		this.currentCount = 0;
		if(this.contextMenu) this.contextMenu.removeElements('div.bm');
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
	
	getContextActions: function(bmElement){
		
		var removeAction = {
				name:MessageHash[146],
				alt:MessageHash[146],
				image:'images/crystal/actions/16/delete_bookmark.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					this.removeBookmark(bmElement.getAttribute('bm_path'));
				}.bind(this)
			};
		
		var goToAction = {
				name:MessageHash[224],
				alt:MessageHash[104],
				image:'images/crystal/actions/16/forward.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					ajaxplorer.goTo(bmElement.getAttribute('bm_path'));
				}
			};
		
		var renameAction = {
				name:MessageHash[6],
				alt:MessageHash[6],
				image:'images/crystal/actions/16/applix.png',
				disabled:false,
				className:"edit",
				callback:function(e){
					this.toggleRenameForm(bmElement);
				}.bind(this)
			};
		
			
			
		return new Array(goToAction, renameAction, removeAction);
	},
	
	toggleRenameForm:function(bmElement){
		
		modal.prepareHeader(MessageHash[225], 'images/crystal/actions/16/bookmark.png');
	 	var onLoad = function(newForm){
	 		$(newForm).bm_path.value = bmElement.getAttribute('bm_path');
	 		$(newForm).bm_title.value = bmElement.select('a.bookmark_button')[0].innerHTML;
	 	}
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
