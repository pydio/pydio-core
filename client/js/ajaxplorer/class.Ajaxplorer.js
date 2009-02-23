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
 * Description : The main JavaScript class instantiated at startup.
 */
if(dynamicLibLoading)
{
	document.write('<script language="javascript" type="text/javascript" src="'+ajxpResourcesFolder+'/js/lib/scriptaculous/src/scriptaculous.js?load=builder,effects,dragdrop"></script><script language="javascript" type="text/javascript" src="'+ajxpResourcesFolder+'/js/lib/leightbox/lightbox.js"></script><script language="javascript" type="text/javascript" src="'+ajxpResourcesFolder+'/js/ajaxplorer/class.Connexion.js"></script><script language="javascript" type="text/javascript" src="'+ajxpResourcesFolder+'/js/ajaxplorer/class.Modal.js"></script>');
}

Ajaxplorer = Class.create({

	initialize: function(loadRep, usersEnabled, loggedUser, rootDirId, rootDirsList, rootDirsSettings, defaultDisplay)
	{	
		this._initLoadRep = loadRep;
		this._initObj = true ;
		this.usersEnabled = usersEnabled;
		this._initLoggedUser = loggedUser;
		this._initRootDirsList = rootDirsList;
		this._initRootDirsSettings = rootDirsSettings;
		this._initRootDirId = rootDirId;
		this._initDefaultDisp = ((defaultDisplay && defaultDisplay!='')?defaultDisplay:'list');
		this.histCount=0;
		if(!this.usersEnabled) this.rootDirId = rootDirId;
		modal.setLoadingStepCounts(this.usersEnabled?7:6);
		this.initTemplates();
		modal.initForms();
		this.initObjects();
	},
	
	initTemplates:function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_template');
		connexion.onComplete = function(transport){
			$(document.body).insert({top:transport.responseText});
		};
		connexion.addParameter('template_name', 'gui_tpl.html');
		connexion.sendSync();
		modal.updateLoadingProgress('Main template loaded');	
	},
	
	loadI18NMessages: function(newLanguage){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_i18n_messages');
		connexion.onComplete = function(transport){
			if(transport.responseText){
				var result = transport.responseText.evalScripts();
				MessageHash = result[0];
				this.updateI18nTags();
				if(this.infoPanel) this.infoPanel.update();
				if(this.actionBar) this.actionBar.loadActions();
				if(this.filesList) this.filesList.reload();
				this.currentLanguage = newLanguage;
			}
		}.bind(this);
		connexion.sendSync();
	},
	
	updateI18nTags: function(){
		$$('[ajxp_message_id]').each(function(tag){			
			tag.update(MessageHash[tag.readAttribute("ajxp_message_id")]);
		});
		$$('[ajxp_message_title_id]').each(function(tag){			
			tag.writeAttribute('title', MessageHash[tag.readAttribute("ajxp_message_title_id")]);
		});
	},
	
	initObjects: function(){
		loadRep = this._initLoadRep;
		crtUser = this._initCrtUser;
		rootDirName = this._initRootDir;
		this.infoPanel = new InfoPanel("info_panel");
		//modal.updateLoadingProgress('Libraries loaded');
		if(!this.usersEnabled)
		{
			var fakeUser = new User("shared");
			fakeUser.setActiveRepository(this._initRootDirId, 1, 1);
			fakeUser.setRepositoriesList(this._initRootDirsList);
			this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, fakeUser, this);
			this.foldersTree = new FoldersTree('tree_container', this._initRootDirsList.get(this._initRootDirId), ajxpServerAccessPath+'?action=ls', this);
			this.refreshRootDirMenu(this._initRootDirsList, this._initRootDirId);
			this.actionBar.loadActions();
			this.infoPanel.load();
		}
		else
		{
			this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, null, this);
			this.foldersTree = new FoldersTree('tree_container', 'No Repository', ajxpServerAccessPath+'?action=ls', this);
			if(this._initLoggedUser)
			{
				this.getLoggedUserFromServer();
			}else{
				this.tryLogUserFromCookie();
			}
		}
		
		this.actionBar.init();
		modal.updateLoadingProgress('ActionBar Initialized');
		
	
		this.contextMenu = new Proto.Menu({
		  selector: '', // context menu will be shown when element with class name of "contextmenu" is clicked
		  className: 'menu desktop', // this is a class which will be attached to menu container (used for css styling)
		  menuItems: [],
		  fade:true,
		  zIndex:2000
		});
		var protoMenu = this.contextMenu;
		protoMenu.options.beforeShow = function(e){setTimeout(function(){
		  	this.options.menuItems = ajaxplorer.actionBar.getContextActions(Event.element(e));
		  	this.refreshList();
		  }.bind(protoMenu),0);};
	
		this.foldersTree.setContextualMenu(this.contextMenu);
		this.actionBar.setContextualMenu(this.contextMenu);
		  
		this.sEngine = new SearchEngine("search_container");
		this.messageBox = $('message_div');
		this.initGUI();	
		this.filesList = new FilesList($("selectable_div"), 
										true, 
										["StringDirFile", "NumberKo", "String", "MyDate"], 
										null, 
										this, 
										this._initDefaultDisp) ;
		this.filesList.setContextualMenu(this.contextMenu);
		modal.updateLoadingProgress('GUI Initialized');
		this.initFocusBehaviours();
		this.initTabNavigation();
		modal.updateLoadingProgress('Navigation loaded');
		this.focusOn(this.foldersTree);
		this.blockShortcuts = false;
		this.blockNavigation = false;
		
		new AjxpAutocompleter("current_path", "autocomplete_choices");
		if(Prototype.Browser.Gecko){
			this.history = new Proto.History(function(hash){
				this.goTo(this.historyHashToPath(hash));
			}.bind(this));
		}
		if(!this.usersEnabled){
			this.goTo(loadRep);	
		}
	},

	
	tryLogUserFromCookie : function(){
		var connexion = new Connexion();
		var rememberData = retrieveRememberData();
		if(rememberData!=null){
			connexion.addParameter('get_action', 'login');
			connexion.addParameter('userid', rememberData.user);
			connexion.addParameter('password', rememberData.pass);
			connexion.addParameter('cookie_login', 'true');
			connexion.onComplete = function(transport){this.actionBar.parseXmlMessage(transport.responseXML);}.bind(this);
		}else{
			connexion.addParameter('get_action', 'logged_user');
			connexion.onComplete = function(transport){this.logXmlUser(transport.responseXML);}.bind(this);
		}
		connexion.sendAsync();	
	},
	
	getLoggedUserFromServer: function(){
		var connexion = new Connexion();
		var rememberData = retrieveRememberData();
		connexion.addParameter('get_action', 'logged_user');
		connexion.onComplete = function(transport){this.logXmlUser(transport.responseXML);}.bind(this);
		connexion.sendAsync();	
	},
	
	logXmlUser: function(xmlResponse){
		this.user = null;
		try{			
			var childs = xmlResponse.documentElement.childNodes;		
			for(var i=0; i<childs.length;i++){
				if(childs[i].tagName == "user"){
					var userId = childs[i].getAttribute('id');
					childs = childs[i].childNodes;
				}
			}	
			if(userId){ 
				this.user = new User(userId, childs);
			}
		}catch(e){alert('Error parsing XML for user : '+e);}
		
		var repLabel = 'No Repository';
		var repList = null;
		var repId = null;
		if(this.user != null)
		{
			repId = this.user.getActiveRepository();
			repList = this.user.getRepositoriesList();			
			repLabel = repList.get(repId);
		}
		this.actionBar.setUser(this.user);
		this.refreshRootDirMenu(repList, repId);
		this.loadRepository(repId, repLabel);
	},
		
	loadRepository: function(repositoryId, repositoryLabel){
		this.actionBar.loadActions();
		this.foldersTree.reloadFullTree(repositoryLabel);
		if(!this._initObj) { 
			this.filesList.loadXmlList('/') ;
			this.rootDirId = repositoryId;
			this.actionBar.loadBookmarks();
		} else { this._initObj = null ;}
		if(this._initLoadRep){
			this.goTo(this._initLoadRep);
			this._initLoadRep = null;
		}
		$('repo_path').value = repositoryLabel;
		var sEngineName = 'SearchEngine';
		if(this.usersEnabled && this.user){
			$('repo_icon').src = this.user.getRepositoryIcon(repositoryId) || ajxpResourcesFolder+'/images/crystal/actions/16/network-wired.png';
			sEngineName = this.user.getRepoSearchEngine(repositoryId) || 'SearchEngine';		
		}else{
			if(this._initRootDirsSettings){
				$('repo_icon').src = this._initRootDirsSettings.get(repositoryId).get('icon') || ajxpResourcesFolder+'/images/crystal/actions/16/network-wired.png';
				sEngineName = this._initRootDirsSettings.get(repositoryId).get('search_engine') || 'SearchEngine';
				this.refreshRootDirMenu(this._initRootDirsList, repositoryId);
			}
		}
				
		this.sEngine = eval('new '+sEngineName+'("search_container");');
	},

	goTo: function(rep, selectFile){
		this.actionBar.updateLocationBar(rep);
		//this.actionBar.update(true);
		this.foldersTree.goToDeepPath(rep);	
		this.filesList.loadXmlList(rep, selectFile);	
	},
	
	refreshRootDirMenu: function(rootDirsList, rootDirId){
		$('goto_repo_button').addClassName('disabled');
		//if(!rootDirsList || rootDirsList.size() <= 1) return;
		var actions = new Array();
		if(rootDirsList && rootDirsList.size() > 1){
			rootDirsList.each(function(pair){
				var value = pair.value;
				var key = pair.key;
				var selected = (key == rootDirId ? true:false);
				var repoImage = null;
				if(this.usersEnabled){
					repoImage = (this.user?this.user.getRepositoryIcon(key):null);				
				}else{
					repoImage = this._initRootDirsSettings.get(key).get('icon') || null;
				}
				actions[actions.length] = {
					name:value,
					alt:value,				
					image:repoImage || ajxpResourcesFolder+'/images/foldericon.png',
					className:"edit",
					disabled:selected,
					callback:function(e){
						ajaxplorer.triggerRootDirChange(''+key);
					}
				}
			}.bind(this));		
		}
		if(this.rootMenu){
			this.rootMenu.options.menuItems = actions;
			this.rootMenu.refreshList();
		}else{
			this.rootMenu = new Proto.Menu({			
				className: 'menu rootDirChooser',
				mouseClick:'left',
				//anchor:'root_dir_button',
				anchor:'goto_repo_button',
				createAnchor:false,
				anchorContainer:$('dir_chooser'),
				anchorSrc:ajxpResourcesFolder+'/images/crystal/lower.png',
				anchorTitle:MessageHash[200],
				topOffset:6,
				leftOffset:-107,
				menuTitle:MessageHash[200],
				menuItems: actions,
				fade:true,
				zIndex:1500
			});		
		}
		if(actions.length) $('goto_repo_button').removeClassName('disabled');
	},
	

	triggerRootDirChange: function(rootDirId){
		this.actionBar.updateLocationBar('/');
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'switch_root_dir');
		connexion.addParameter('root_dir_index', rootDirId);
		oThis = this;
		connexion.onComplete = function(transport){
			if(this.usersEnabled)
			{
				this.getLoggedUserFromServer();
			}
			else
			{
				this.actionBar.parseXmlMessage(transport.responseXML);
				this.loadRepository(rootDirId, this._initRootDirsList.get(rootDirId));
			}
		}.bind(this);
		connexion.sendAsync();
	},
	
	updateHistory: function(path){
		if(this.history) this.history.historyLoad(this.pathToHistoryHash(path));
	},
	
	pathToHistoryHash: function(path){
		document.title = 'AjaXplorer - '+(getBaseName(path)?getBaseName(path):'/');
		if(!this.pathesHash){
			this.pathesHash = new Hash();
			this.histCount = -1;
		}
		var foundKey;
		this.pathesHash.each(function(pair){
			if(pair.value == path) foundKey = pair.key;
		});
		if(foundKey != undefined) return foundKey;
	
		this.histCount++;
		this.pathesHash.set(this.histCount, path);
		return this.histCount;
	},
	
	historyHashToPath: function(hash){
		if(!this.pathesHash) return "/";
		var path = this.pathesHash.get(hash);
		if(path == undefined) return "/";
		return path;
	},
	
	
	cancelCopyOrMove: function(){
		this.foldersTree.setTreeInNormalMode();
		this.foldersTree.selectCurrentNodeName();
		this.actionBar.treeCopyActive = false;
		hideLightBox();
		return false;
	},
	
	disableShortcuts: function(){
		this.blockShortcuts = true;
	},
	
	enableShortcuts: function(){
		this.blockShortcuts = false;
	},
	
	disableNavigation: function(){
		this.blockNavigation = true;
	},
	
	enableNavigation: function(){
		this.blockNavigation = false;
	},
	
	getActionBar: function(){
		return this.actionBar;
	},
	
	getFilesList: function(){
		return this.filesList;
	},
	
	getFoldersTree: function(){
		return this.foldersTree;
	},
	
	closeMessageDiv: function(){
		if(this.messageDivOpen)
		{
			new Effect.Fade(this.messageBox);
			this.messageDivOpen = false;
		}
	},
	
	tempoMessageDivClosing: function(){
		this.messageDivOpen = true;
		setTimeout('ajaxplorer.closeMessageDiv()', 3000);
	},
	
	displayMessage: function(messageType, message){
		message = message.replace(new RegExp("(\\n)", "g"), "<br>");
		if(messageType == "ERROR"){ this.messageBox.removeClassName('logMessage');  this.messageBox.addClassName('errorMessage');}
		else { this.messageBox.removeClassName('errorMessage');  this.messageBox.addClassName('logMessage');}
		$('message_content').innerHTML = message;
		// appear at bottom of content panel
		var containerOffset = Position.cumulativeOffset($('content_pane'));
		var containerDimensions = $('content_pane').getDimensions();
		var boxHeight = $(this.messageBox).getHeight();
		var topPosition = containerOffset[1] + containerDimensions.height - boxHeight - 20;
		var boxWidth = parseInt(containerDimensions.width * 90/100);
		var leftPosition = containerOffset[0] + parseInt(containerDimensions.width*5/100);
		this.messageBox.style.top = topPosition+'px';
		this.messageBox.style.left = leftPosition+'px';
		this.messageBox.style.width = boxWidth+'px';
		new Effect.Corner(this.messageBox,"round");
		new Effect.Appear(this.messageBox);
		this.tempoMessageDivClosing();
	},
	
	initFocusBehaviours: function(){
		$('topPane').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.foldersTree);
		});
		$('content_pane').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.filesList);
		});	
		$('action_bar').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.actionBar);
		});
		$('search_div').observe("click", function(){
			ajaxplorer.focusOn(ajaxplorer.sEngine);
		});
		
	},
	
	focusOn : function(object){
		var objects = [this.foldersTree, this.sEngine, this.filesList, this.actionBar];
		objects.each(function(obj){
			if(obj != object) obj.blur();
		});
		object.focus();
	},
	
	
	initTabNavigation: function(){
		var objects = [this.foldersTree, this.filesList, this.actionBar];		
		// ASSIGN OBSERVER
		Event.observe(document, "keydown", function(e)
		{			
			if(e.keyCode == Event.KEY_TAB)
			{
				if(this.blockNavigation) return;
				var shiftKey = e['shiftKey'];
				for(i=0; i<objects.length;i++)
				{
					if(objects[i].hasFocus)
					{
						objects[i].blur();
						var nextIndex;
						if(shiftKey)
						{
							if(i>0) nextIndex=i-1;
							else nextIndex = (objects.length) - 1;
						}
						else
						{
							if(i<objects.length-1)nextIndex=i+1;
							else nextIndex = 0;
						}
						objects[nextIndex].focus();
						break;
					}
				}
				Event.stop(e);
			}
			if(this.blockShortcuts || e['ctrlKey']) return;
			if(e.keyCode > 90 || e.keyCode < 65) return;
			else return this.actionBar.fireActionByKey(e, String.fromCharCode(e.keyCode).toLowerCase());
		}.bind(this));
	},
	
	initGUI: function(){
		try{
		var marginBottom = (Prototype.Browser.IE?18:15);
		if($('optional_bottom_div') && $('optional_bottom_div').getHeight()>15 ){
			marginBottom = $('optional_bottom_div').getHeight();
		}
		fitHeightToBottom($("browser"), window, marginBottom);
		fitHeightToBottom($("verticalSplitter"), $('browser'), 8);	
		if(Prototype.Browser.IE){
			fitHeightToBottom($("content_pane"), $('verticalSplitter'));
		}
		var s1 = new Splitter('sidebarSplitter',{
				direction: "horizontal",
				initB: 150,
				minB: 24,
				maxB: 500,
				onDrag:function(){
						fitHeightToBottom($('tree_container'), null, (Prototype.Browser.IE?0:4), true);
						fitHeightToBottom($('bottomSplitPane'), null, (Prototype.Browser.IE?-1:1), true);
						this.sEngine.resize();
					}.bind(this)
				});
		this.splitter = new Splitter('verticalSplitter', {
				direction: "vertical",
				initA: 200, maxA:400, minA:50, 
				onDrag: function(){
					s1.resizeGroup(null, null, true);
					if(this.filesList)this.filesList.applyHeadersWidth();					
				}.bind(this)
		});
		
		
		this.currentSideToggle = 'search';
		this.toggleSidePanel('info');	
		
		new Effect.Fade(this.messageBox);
		$(this.actionBar._htmlElement).getElementsBySelector('a', 'input[type="image"]').each(function(element){
			disableTextSelection(element);
		});
		disableTextSelection($('tree_container'));
		disableTextSelection($('bookmarks_bar'));
		disableTextSelection($('panelsToggle'));
		disableTextSelection($('info_panel'));
		disableTextSelection($('dir_chooser'));
		}catch(e){alert(e);}
		
	},
		
	toggleSidePanel: function(srcName){	
		if(srcName == 'info' && this.currentSideToggle != 'info'){
			$(this.sEngine.htmlElement).hide();
			$('search_header').addClassName("toggleInactive");
			$('search_header').getElementsBySelector("img")[0].hide();
			$(this.infoPanel.htmlElement).show();
			$('info_panel_header').removeClassName("toggleInactive");
			$('info_panel_header').getElementsBySelector("img")[0].show();
		}
		else if(srcName == 'search' && this.currentSideToggle != 'search'){
			$(this.sEngine.htmlElement).show();
			$('search_header').removeClassName("toggleInactive");
			$('search_header').getElementsBySelector("img")[0].show();
			$(this.infoPanel.htmlElement).hide();
			$('info_panel_header').addClassName("toggleInactive");
			$('info_panel_header').getElementsBySelector("img")[0].hide();			
			this.sEngine.resize();
		}
		this.currentSideToggle = srcName;
	},

	loadLibraries: function(){
		if(!dynamicLibLoading) {this.init(); return;}
		var connexion = new Connexion();
		var toLoad = $A([]);			
		modal.incrementStepCounts(toLoad.size());
		toLoad.each(function(fileName){
			var onLoad = function(){modal.updateLoadingProgress(fileName);};
			if(fileName == toLoad.last()) onLoad = function(){modal.updateLoadingProgress(fileName);this.init();}.bind(this);
			connexion.loadLibrary(fileName, onLoad);
		});
	},
	
	libLoaded: function(fileName){	
		modal.updateLoadingProgress('Loaded : ' + fileName);
	}

});