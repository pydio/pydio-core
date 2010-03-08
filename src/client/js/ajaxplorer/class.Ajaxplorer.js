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
Class.create("Ajaxplorer", {

	initialize: function(loadRep, usersEnabled, loggedUser)
	{	
		this._initLoadRep = loadRep;
		this._initObj = true ;
		this.usersEnabled = usersEnabled;
		this._initLoggedUser = loggedUser;
		this._contextHolder = new AjxpDataModel();
		this._contextHolder.setAjxpNodeProvider(new RemoteNodeProvider());
		this._focusables = [];
		this._resourcesRegistry = {};
		this._initDefaultDisp = 'list';
		this.histCount=0;
		modal.setLoadingStepCounts(5);
		this.initTemplates();
		this.initEditorsRegistry();		
		modal.initForms();
		this.initObjects();
		window.setTimeout(function(){document.fire('ajaxplorer:loaded');}, 500);
	},
	
	updateContextData : function(ajxpContextNode, ajxpSelectedNodes, selectionSource){
		if(ajxpContextNode){
			this._contextHolder.requireContextChange(ajxpContextNode);
		}
		if(ajxpSelectedNodes){
			this._contextHolder.setSelectedNodes(ajxpSelectedNodes, selectionSource);
		}
	},
	
	getContextHolder : function(){
		return this._contextHolder;
	},
	
	getContextNode : function(){
		return this._contextHolder.getContextNode() || new AjxpNode("");
	},
	
	getUserSelection : function(){
		return this._contextHolder;
	},		
	
	fireContextRefresh : function(){
		this.getContextHolder().requireContextChange(this.getContextNode(), true);
	},
	
	fireNodeRefresh : function(nodePathOrNode){
		this.getContextHolder().requireNodeReload(nodePathOrNode);
	},
	
	fireContextUp : function(){
		if(this.getContextNode().isRoot()) return;
		this.updateContextData(this.getContextNode().getParent());
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
		this.loadTemplate("usertemplate_top.html", $(document.body), 'top');
		this.loadTemplate("gui_tpl.html", $('ajxp_desktop'), 'top');
		this.loadTemplate("usertemplate_bottom.html", $(document.body), 'bottom');
		modal.updateLoadingProgress('Html templates loaded');	
	},
	
	loadTemplate : function(templateName, target, targetPosition){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_template');
		connexion.onComplete = function(transport){
			transport.responseText.evalScripts();
			var obj = {}; obj[targetPosition] = transport.responseText;
			target.insert(obj);
		};
		connexion.addParameter('template_name', templateName);
		connexion.sendSync();		
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
				if(this.actionBar) this.actionBar.loadActions();
				this.fireContextRefresh();
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

		this.actionBar = new ActionsManager(this.usersEnabled);		
		
		if(!Prototype.Browser.WebKit && !Prototype.Browser.IE){
			this.history = new Proto.History(function(hash){
				this.goTo(this.historyHashToPath(hash));
			}.bind(this));
			document.observe("ajaxplorer:context_changed", function(event){
				this.updateHistory(this.getContextNode().getPath());
			}.bind(this));
		}
		modal.updateLoadingProgress('Actions Initialized');
		  
		  
		/*********************
		/* USER GUI
		/*********************/
		this.buildGUI($('ajxp_desktop'));
		document.fire("ajaxplorer:before_gui_load");
		// Rewind components creation!
		var lastInst;
		if(this.guiCompRegistry && this.guiCompRegistry.length){
			for(var i=this.guiCompRegistry.length;i>0;i--){
				var el = this.guiCompRegistry[i-1];
				var ajxpId = el.ajxpId;
				this.guiCompRegistry[i-1] = new el['ajxpClass'](el.ajxpNode, el.ajxpOptions);
				window[ajxpId] = this.guiCompRegistry[i-1];
				lastInst = this.guiCompRegistry[i-1];
			}
			if(lastInst){
				lastInst.resize();
			}
			for(var j=0;j<this.guiCompRegistry.length;j++){
				var obj = this.guiCompRegistry[j];
				if(Class.objectImplements(obj, "IFocusable")){
					obj.setFocusBehaviour();
					this._focusables.push(obj);
				}
				if(Class.objectImplements(obj, "IContextMenuable")){
					obj.setContextualMenu(this.contextMenu);
				}
				if(Class.objectImplements(obj, "IActionProvider")){
					if(!this.guiActions) this.guiActions = new Hash();
					this.guiActions.update(obj.getActions());
				}
			}
		}
		document.fire("ajaxplorer:gui_loaded");
		modal.updateLoadingProgress('GUI Initialized');
		this.initTabNavigation();
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
	},

	buildGUI : function(domNode){
		if(domNode.nodeType != 1) return;
		if(!this.guiCompRegistry) this.guiCompRegistry = $A([]);
		domNode = $(domNode);
		var ajxpClassName = domNode.readAttribute("ajxpClass") || "";
		var ajxpClass = Class.getByName(ajxpClassName);
		var ajxpId = domNode.readAttribute("id") || "";
		if(domNode.readAttribute("ajxpOptions")){
			var ajxpOptions = domNode.readAttribute("ajxpOptions").evalJSON();
		}		
		if(ajxpClass && ajxpOptions && ajxpId && Class.objectImplements(ajxpClass, "IAjxpWidget")){
			this.guiCompRegistry.push({ajxpId:ajxpId, ajxpNode:domNode, ajxpClass:ajxpClass, ajxpOptions:ajxpOptions});
		}		
		$A(domNode.childNodes).each(function(node){
			this.buildGUI(node);
		}.bind(this) );
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
		
		var repositoryObject = new Repository(null);
		if(this.user != null)
		{
			var repId = this.user.getActiveRepository();
			var repList = this.user.getRepositoriesList();			
			repositoryObject = repList.get(repId);
			if(!repositoryObject){
				alert("Empty repository object!");
			}
			if(this.user.getPreference("history_last_listing")){
				this._initLoadRep = this.user.getPreference("history_last_listing");
			}
		}
		this.actionBar.setUser(this.user);
		this.loadRepository(repositoryObject);
		if(repList && repId){
			document.fire("ajaxplorer:repository_list_refreshed", {list:repList,active:repId});
		}else{
			document.fire("ajaxplorer:repository_list_refreshed", {list:false,active:false});
		}
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
			document.fire("ajaxplorer:repository_list_refreshed", {list:repList,active:repId});			
		}.bind(this);
		connexion.sendAsync();			
	},
	
	loadRepository: function(repository){		
		repository.loadResources();
		var repositoryId = repository.getId();
		this.actionBar.loadActions();
		
		var	newIcon = repository.getIcon(); 
		var sEngineName = repository.getSearchEngine();
				
		var rootNode = new AjxpNode("/", false, repository.getLabel(), newIcon);
		this._contextHolder.setRootNode(rootNode);
				
		if(!this._initObj) { 			
			this.repositoryId = repositoryId;
		} else { this._initObj = null ;}
		
		if(this._initLoadRep){
			rootNode.observeOnce("loaded", function(){
				if(this._initLoadRep != "" && this._initLoadRep != "/"){
					this.goTo(this._initLoadRep);
				}
				this._initLoadRep = null;
			}.bind(this));
		}
		this.sEngine = eval('new '+sEngineName+'("search_container");');
	},

	goTo: function(nodeOrPath){		
		if(Object.isString(nodeOrPath)){
			node = new AjxpNode(nodeOrPath);
		}else{
			node = nodeOrPath;
		}
		this._contextHolder.requireContextChange(node);
	},
	
	triggerRepositoryChange: function(repositoryId){		
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'switch_root_dir');
		connexion.addParameter('root_dir_index', repositoryId);
		oThis = this;
		connexion.onComplete = function(transport){
			this.getLoggedUserFromServer();
		}.bind(this);
		var root = this._contextHolder.getRootNode();
		if(root){
			this._contextHolder.setContextNode(root);
			root.clear();
		}
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
		this.actionBar.treeCopyActive = false;
		hideLightBox();
		return false;
	},
	
	changeDataColumnsDefinition : function(columnsDef){
		document.fire("ajaxplorer:data_columns_def_changed", columnsDef);
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
			
	displayMessage: function(messageType, message){		
		modal.displayMessage(messageType, message);
	},
	
	focusOn : function(object){
		this._focusables.each(function(obj){
			if(obj != object) obj.blur();
		});
		object.focus();
	},
	
	blurAll : function(){
		this._focusables.each(function(f){
			if(f.hasFocus) this._lastFocused = f;
			f.blur();
		}.bind(this) );
	},	
	
	focusLast : function(){
		if(this._lastFocused) this.focusOn(this._lastFocused);
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
