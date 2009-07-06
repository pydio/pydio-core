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
Ajaxplorer = Class.create({

	initialize: function(loadRep, usersEnabled, loggedUser, repositoryId, repoListXML, defaultDisplay)
	{	
		this._initLoadRep = loadRep;
		this._initObj = true ;
		this.usersEnabled = usersEnabled;
		this._initLoggedUser = loggedUser;
		this._initRepositoriesList = $H({});
		if(repoListXML && repoListXML.childNodes.length){
			for(j=0;j<repoListXML.documentElement.childNodes.length;j++)
			{
				var repoChild = repoListXML.documentElement.childNodes[j];
				if(repoChild.nodeName != "repo") continue;				
				var repository = new Repository(repoChild.getAttribute("id"), repoChild);
				this._initRepositoriesList.set(repoChild.getAttribute("id"), repository);
			}
		}
		this._initRepositoryId = repositoryId;
		this._initDefaultDisp = ((defaultDisplay && defaultDisplay!='')?defaultDisplay:'list');
		this.histCount=0;
		if(!this.usersEnabled) this.repositoryId = repositoryId;
		modal.setLoadingStepCounts(this.usersEnabled?6:5);
		this.initTemplates();
		modal.initForms();
		this.initObjects();
		window.setTimeout(function(){document.fire('ajaxplorer:loaded');}, 500);
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
		var messageTags = $$('[ajxp_message_id]');		
		messageTags.each(function(tag){	
			var messageId = tag.getAttribute("ajxp_message_id");
			try{
				//console.log(MessageHash[messageId]);
				tag.innerHTML = MessageHash[messageId];
			}catch(e){}
		});
		/*
		$$('[ajxp_message_title_id]').each(function(tag){
			tag.setAttribute('title', MessageHash[tag.getAttribute("ajxp_message_title_id")]);
		});
		*/
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
			fakeUser.setActiveRepository(this._initRepositoryId, 1, 1);
			fakeUser.setRepositoriesList(this._initRepositoriesList);
			this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, fakeUser, this);
			var repoObject = this._initRepositoriesList.get(this._initRepositoryId);
			this.foldersTree = new FoldersTree('tree_container', repoObject.getLabel(), ajxpServerAccessPath+'?action=ls', this);
			this.refreshRepositoriesMenu(this._initRepositoriesList, this._initRepositoryId);
			this.actionBar.loadActions();
			this.infoPanel.load();
			this.foldersTree.changeRootLabel(repoObject.getLabel(), repoObject.getIcon());
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
		if(!Prototype.Browser.WebKit && !Prototype.Browser.IE){
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
		//var rememberData = retrieveRememberData();
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
		
		var repList = null;
		var repId = null;
		var repositoryObject = new Repository(null);
		if(this.user != null)
		{
			repId = this.user.getActiveRepository();
			repList = this.user.getRepositoriesList();			
			repositoryObject = repList.get(repId);
			if(!repositoryObject){
				alert("Empty repository object!");
			}
		}
		this.actionBar.setUser(this.user);
		this.refreshRepositoriesMenu(repList, repId);
		this.loadRepository(repositoryObject);
	},
		
	reloadRepositoriesList : function(){
		if(!this.user) return;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'logged_user');
		connexion.onComplete = function(transport){			
			try{			
				var childs = transport.responseXML.documentElement.childNodes;		
				for(var i=0; i<childs.length;i++){
					if(childs[i].tagName == "user"){
						var userId = childs[i].getAttribute('id');
						childs = childs[i].childNodes;
					}
				}	
				if(userId != this.user.id){ 
					return;
				}
				this.user.loadFromXml(childs);
			}catch(e){alert('Error parsing XML for user : '+e);}
			
			repId = this.user.getActiveRepository();
			repList = this.user.getRepositoriesList();
			this.refreshRepositoriesMenu(repList, repId);
			
		}.bind(this);
		connexion.sendAsync();			
	},
	
	loadRepository: function(repository){		
		repository.loadResources();
		var repositoryId = repository.getId();
		this.actionBar.loadActions();
		
		var	newIcon = repository.getIcon(); 
		var sEngineName = repository.getSearchEngine();
		
		this.foldersTree.reloadFullTree(repository.getLabel(), newIcon);
		if(!this._initObj) { 
			this.filesList.loadXmlList('/') ;
			this.repositoryId = repositoryId;
			this.actionBar.loadBookmarks();
		} else { this._initObj = null ;}
		if(this._initLoadRep){
			this.goTo(this._initLoadRep);
			this._initLoadRep = null;
		}
		$('repo_path').value = repository.getLabel();
		$('repo_icon').src = newIcon;
		if(!(this.usersEnabled && this.user) && this._initRepositoriesList){
			this.refreshRepositoriesMenu(this._initRepositoriesList, repositoryId);			
		}
		this.sEngine = eval('new '+sEngineName+'("search_container");');
	},

	goTo: function(rep, selectFile){
		this.actionBar.updateLocationBar(rep);
		//this.actionBar.update(true);
		this.foldersTree.goToDeepPath(rep);	
		this.filesList.loadXmlList(rep, selectFile);	
	},
	
	refreshRepositoriesMenu: function(rootDirsList, repositoryId){
		$('goto_repo_button').addClassName('disabled');
		//if(!rootDirsList || rootDirsList.size() <= 1) return;
		var actions = new Array();
		if(rootDirsList && rootDirsList.size() > 1){
			rootDirsList.each(function(pair){
				var repoObject = pair.value;
				var key = pair.key;
				var selected = (key == repositoryId ? true:false);
				actions[actions.length] = {
					name:repoObject.getLabel(),
					alt:repoObject.getLabel(),				
					image:repoObject.getIcon(),
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
	

	triggerRootDirChange: function(repositoryId){
		this.actionBar.updateLocationBar('/');
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'switch_root_dir');
		connexion.addParameter('root_dir_index', repositoryId);
		oThis = this;
		connexion.onComplete = function(transport){
			if(this.usersEnabled)
			{
				this.getLoggedUserFromServer();
			}
			else
			{
				this.actionBar.parseXmlMessage(transport.responseXML);
				this.loadRepository(this._initRepositoriesList.get(repositoryId));
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
		setTimeout('ajaxplorer.closeMessageDiv()', 6000);
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
			
	toggleSidePanel: function(srcName){			
		if(srcName == 'info' && this.currentSideToggle != 'info'){
			this.sEngine.showElement(false);
			if($('search_header')){
				$('search_header').addClassName("toggleInactive");
				$('search_header').getElementsBySelector("img")[0].hide();
			}
			this.infoPanel.showElement(true);
			if($('info_panel_header')){
				$('info_panel_header').removeClassName("toggleInactive");
				$('info_panel_header').getElementsBySelector("img")[0].show();
			}
		}
		else if(srcName == 'search' && this.currentSideToggle != 'search'){
			this.sEngine.showElement(true);
			if($('search_header')){
				$('search_header').removeClassName("toggleInactive");
				$('search_header').getElementsBySelector("img")[0].show();
			}
			this.infoPanel.showElement(false);
			if($('info_panel_header')){
				$('info_panel_header').addClassName("toggleInactive");
				$('info_panel_header').getElementsBySelector("img")[0].hide();			
			}
			this.sEngine.resize();
		}
		this.currentSideToggle = srcName;
	}
});