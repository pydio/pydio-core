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
		this._focusables = [];
		this._resourcesRegistry = {};
		this._initDefaultDisp = ((defaultDisplay && defaultDisplay!='')?defaultDisplay:'list');
		this.histCount=0;
		if(!this.usersEnabled) this.repositoryId = repositoryId;
		modal.setLoadingStepCounts(this.usersEnabled?7:6);
		this.initTemplates();
		this.initEditorsRegistry();		
		modal.initForms();
		this.initObjects();
		window.setTimeout(function(){document.fire('ajaxplorer:loaded');}, 500);
	},
	
	initEditorsRegistry : function(){
		this.editorsRegistry = $A([]);
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_editors_registry');
		connexion.onComplete = function(transport){
			var xmlResponse = transport.responseXML;
			if(xmlResponse == null || xmlResponse.documentElement == null) return;
			var editors = xmlResponse.documentElement.childNodes;		
			for(var i=0;i<editors.length;i++){
				if(editors[i].nodeName == "editor"){					
					var editorDefinition = {
						id : editors[i].getAttribute("id"),
						text : MessageHash[editors[i].getAttribute("text")],
						title : MessageHash[editors[i].getAttribute("title")],
						icon : editors[i].getAttribute("icon"),
						editorClass : editors[i].getAttribute("className"),
						mimes : $A(editors[i].getAttribute("mimes").split(",")),
						formId : editors[i].getAttribute("formId") || null,
						write : (editors[i].getAttribute("write") && editors[i].getAttribute("write")=="true"?true:false),
						resourcesManager : new ResourcesManager()
					};
					this._resourcesRegistry[editorDefinition.id] = editorDefinition.resourcesManager;
					this.editorsRegistry.push(editorDefinition);					
					for(var j=0;j<editors[i].childNodes.length;j++){
						var child = editors[i].childNodes[j];
						editorDefinition.resourcesManager.loadFromXmlNode(child);
					}
				}
				
			}
		}.bind(this);
		connexion.sendSync();
		modal.updateLoadingProgress('Editors Registry loaded');			
	},
	
	findEditorsForMime : function(mime){
		var editors = $A([]);
		var checkWrite = false;
		if(this.user != null && !this.user.canWrite()){
			checkWrite = true;
		}
		this.editorsRegistry.each(function(el){
			if(el.mimes.include(mime)) {
				if(!checkWrite || !el.write) editors.push(el);
			}
		});
		return editors;
	},
	
	loadEditorResources : function(resourcesManager){
		var registry = this._resourcesRegistry;
		resourcesManager.load(registry);
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
	
    triggerDownload: function(url){
        document.location.href = url;
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
				tag.innerHTML = MessageHash[messageId];
			}catch(e){}
		});
	},
		
	initObjects: function(){

		/*********************
		/* STANDARD MECHANISMS
		/*********************/
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

		this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, null, this);
		this.actionBar.init();
		this.actionBar.setContextualMenu(this.contextMenu);		  
		this.actionBar.setFocusBehaviour();
		this._focusables.push(this.actionBar);
		new AjxpAutocompleter("current_path", "autocomplete_choices");
		if(!Prototype.Browser.WebKit && !Prototype.Browser.IE){
			this.history = new Proto.History(function(hash){
				this.goTo(this.historyHashToPath(hash));
			}.bind(this));
		}
		modal.updateLoadingProgress('ActionBar Initialized');
		  
		  
		/*********************
		/* USER GUI
		/*********************/
		this.infoPanel = new InfoPanel("info_panel");		
		this.foldersTree = new FoldersTree($('topPane'), 'No Repository', ajxpServerAccessPath+'?get_action=ls&options=dz', this);
		this.sEngine = new SearchEngine("search_container");
		this.filesList = new FilesList($("content_pane"),this._initDefaultDisp);
		
		this.foldersTree.setContextualMenu(this.contextMenu);
		this.filesList.setContextualMenu(this.contextMenu);				
		
		this.foldersTree.setFocusBehaviour();
		this.filesList.setFocusBehaviour();
		this._focusables.push(this.foldersTree);
		this._focusables.push(this.filesList);

		modal.updateLoadingProgress('GUI Initialized');				
		this.initTabNavigation();
		this.focusOn(this.foldersTree);
		this.blockShortcuts = false;
		this.blockNavigation = false;
		modal.updateLoadingProgress('Navigation loaded');
		

		/*******************************
		/* NOW LAUNCH USER MANAGEMENT
		/*****************************/
		if(this._initLoggedUser)
		{
			this.getLoggedUserFromServer();
		}else{
			this.tryLogUserFromCookie();
		}
		
		if(!this.usersEnabled){
			Event.observe(document, "ajaxplorer:loaded", function(){
				var fakeUser = new User("shared");			
				fakeUser.setActiveRepository(this._initRepositoryId, 1, 1);
				fakeUser.setRepositoriesList(this._initRepositoriesList);
				var repoObject = this._initRepositoriesList.get(this._initRepositoryId);
				this.refreshRepositoriesMenu(this._initRepositoriesList, this._initRepositoryId);
				this.actionBar.setUser(fakeUser);
				this.loadRepository(repoObject);						
			}.bind(this) );
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
			if(this.user.getPreference("history_last_listing")){
				this._initLoadRep = this.user.getPreference("history_last_listing");
			}
		}
		this.actionBar.setUser(this.user);
		this.refreshRepositoriesMenu(repList, repId);
		this.loadRepository(repositoryObject);
		document.fire("ajaxplorer:user_logged");
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
		this.foldersTree.goToDeepPath(rep);	
		this.filesList.loadXmlList(rep, selectFile);	
	},
	
	refreshRepositoriesMenu: function(rootDirsList, repositoryId){
		$('goto_repo_button').addClassName('disabled');
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
				anchor:'goto_repo_button',
				createAnchor:false,
				anchorContainer:$('dir_chooser'),
				anchorSrc:ajxpResourcesFolder+'/images/crystal/lower.png',
				anchorTitle:MessageHash[200],
				topOffset:6,
				leftOffset:-127,
				menuTitle:MessageHash[200],
				menuItems: actions,
				fade:true,
				zIndex:1500
			});		
		}
		if(actions.length) $('goto_repo_button').removeClassName('disabled');
        actions.sort(function(a,b) { return a.name > b.name; });
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
	
	displayMessage: function(messageType, message){		
		modal.displayMessage(messageType, message);
	},
	
	focusOn : function(object){
		this._focusables.each(function(obj){
			if(obj != object) obj.blur();
		});
		object.focus();
	},
	
	
	initTabNavigation: function(){
		var objects = this._focusables;
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
	}
		
});
