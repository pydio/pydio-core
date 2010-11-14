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
		this._registry = null;
		this._resourcesRegistry = {};
		this._initDefaultDisp = 'list';
		this.histCount=0;
		this._guiComponentsConfigs = new Hash();
	},
	
	init:function(){
		document.observe("ajaxplorer:registry_loaded", function(){
			this.refreshExtensionsRegistry();
			this.logXmlUser(this._registry);	
			this.loadActiveRepository();	
			if(this.guiLoaded) {
				this.refreshTemplateParts();
				this.refreshGuiComponentConfigs();
			} else {
				document.observe("ajaxplorer:gui_loaded", function(){
					this.refreshTemplateParts();
					this.refreshGuiComponentConfigs();
				}.bind(this));
			}
		}.bind(this));

		modal.setLoadingStepCounts(5);
		this.loadXmlRegistry(true);		
		this.initTemplates();
		modal.initForms();
		this.initObjects();
		window.setTimeout(function(){document.fire('ajaxplorer:loaded');}, 500);		
	},
	
	loadXmlRegistry : function(sync, xPath){
		var connexion = new Connexion();
		connexion.onComplete = function(transport){
			if(transport.responseXML == null || transport.responseXML.documentElement == null) return;
			if(transport.responseXML.documentElement.nodeName == "ajxp_registry"){
				this._registry = transport.responseXML.documentElement;
				modal.updateLoadingProgress('XML Registry loaded');
				document.fire("ajaxplorer:registry_loaded", this._registry);
			}else if(transport.responseXML.documentElement.nodeName == "ajxp_registry_part"){
				this.refreshXmlRegistryPart(transport.responseXML.documentElement);
			}
		}.bind(this);
		connexion.addParameter('get_action', 'get_xml_registry');
		if(xPath){
			connexion.addParameter('xPath', xPath);
		}
		if(sync){
			connexion.sendSync();		
		}else{
			connexion.sendAsync();
		}
	},

	refreshXmlRegistryPart : function(documentElement){
		var xPath = documentElement.getAttribute("xPath");
		var existingNode = XPathSelectSingleNode(this._registry, xPath);
		if(existingNode && existingNode.parentNode){
			var parentNode = existingNode.parentNode;
			parentNode.removeChild(existingNode);
			if(documentElement.firstChild){
				parentNode.appendChild(documentElement.firstChild.cloneNode(true));
			}
		}else if(xPath.indexOf("/") > -1){
			// try selecting parentNode
			var parentPath = xPath.substring(0, xPath.lastIndexOf("/"));
			var parentNode = XPathSelectSingleNode(this._registry, parentPath);
			if(parentNode && documentElement.firstChild){
				//parentNode.ownerDocument.importNode(documentElement.firstChild);
				parentNode.appendChild(documentElement.firstChild.cloneNode(true));
			}			
		}else{
			if(documentElement.firstChild) this._registry.appendChild(documentElement.firstChild.cloneNode(true));
		}
		document.fire("ajaxplorer:registry_part_loaded", xPath);		
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
		protoMenu.options.beforeShow = function(e){
			this.options.lastElement = Event.element(e);
			this.options.menuItems = ajaxplorer.actionBar.getContextActions(Event.element(e));
			this.refreshList();
		}.bind(protoMenu);
		protoMenu.options.beforeHide = function(e){
			this.options.lastElement = null;
		}.bind(protoMenu);
		document.observe("ajaxplorer:actions_refreshed", function(){
			if(this.options.lastElement){
				this.options.menuItems = ajaxplorer.actionBar.getContextActions(this.options.lastElement);
				this.refreshList();
			}			
		}.bind(protoMenu));
		
		this.actionBar = new ActionsManager(this.usersEnabled);
		if(this._registry){
			this.actionBar.loadActionsFromRegistry(this._registry);
		}
		document.observe("ajaxplorer:registry_loaded", function(event){
			this.actionBar.loadActionsFromRegistry(event.memo);
		}.bind(this) );
				
		if(!Prototype.Browser.WebKit && !Prototype.Browser.IE){
			this.history = new Proto.History(function(hash){
				this.goTo(this.historyHashToPath(hash));
			}.bind(this));
			document.observe("ajaxplorer:context_changed", function(event){
				this.updateHistory(this.getContextNode().getPath());
			}.bind(this));
		}else{
			document.observe("ajaxplorer:context_changed", function(event){
				var path = this.getContextNode().getPath();
				document.title = 'AjaXplorer - '+(getBaseName(path)?getBaseName(path):'/');
			}.bind(this));
		}
		document.observe("ajaxplorer:context_changed", function(event){
			if(this.skipLsHistory || !this.user || !this.user.getActiveRepository()) return;			
			window.setTimeout(function(){
				var data = this.user.getPreference("ls_history", true) || {};
				data = new Hash(data);
				data.set(this.user.getActiveRepository(), this.getContextNode().getPath());
				this.user.setPreference("ls_history", data, true);
				this.user.savePreference("ls_history");
			}.bind(this), 100 );
		}.bind(this) );
		modal.updateLoadingProgress('Actions Initialized');
		
		this.activityMonitor = new ActivityMonitor(
			window.ajxpBootstrap.parameters.get('session_timeout'), 
			window.ajxpBootstrap.parameters.get('client_timeout'), 
			window.ajxpBootstrap.parameters.get('client_timeout_warning'));
		  
		/*********************
		/* USER GUI
		/*********************/
		this.guiLoaded = false;
		this.buildGUI($(ajxpBootstrap.parameters.get('MAIN_ELEMENT')));
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
		this.guiLoaded = true;
		document.fire("ajaxplorer:gui_loaded");
		modal.updateLoadingProgress('GUI Initialized');
		this.initTabNavigation();
		this.blockShortcuts = false;
		this.blockNavigation = false;
		modal.updateLoadingProgress('Navigation loaded');
		

		this.tryLogUserFromCookie();
		document.fire("ajaxplorer:registry_loaded", this._registry);		
	},

	buildGUI : function(domNode){
		if(domNode.nodeType != 1) return;
		if(!this.guiCompRegistry) this.guiCompRegistry = $A([]);
		domNode = $(domNode);
		var ajxpClassName = domNode.readAttribute("ajxpClass") || "";
		var ajxpClass = Class.getByName(ajxpClassName);
		var ajxpId = domNode.readAttribute("id") || "";
		var ajxpOptions = {};
		if(domNode.readAttribute("ajxpOptions")){
			ajxpOptions = domNode.readAttribute("ajxpOptions").evalJSON();
		}		
		if(ajxpClass && ajxpId && Class.objectImplements(ajxpClass, "IAjxpWidget")){
			this.guiCompRegistry.push({ajxpId:ajxpId, ajxpNode:domNode, ajxpClass:ajxpClass, ajxpOptions:ajxpOptions});
		}		
		$A(domNode.childNodes).each(function(node){
			this.buildGUI(node);
		}.bind(this) );
	},
	
	refreshTemplateParts : function(){
		var parts = XPathSelectNodes(this._registry, "client_configs/template_part");
		for(var i=0;i<parts.length;i++){
			var ajxpId = parts[i].getAttribute("ajxpId");
			var ajxpClass = Class.getByName(parts[i].getAttribute("ajxpClass"));
			var ajxpOptions = parts[i].getAttribute("ajxpOptions").evalJSON();
			if(ajxpClass && ajxpId && Class.objectImplements(ajxpClass, "IAjxpWidget")){
				this.refreshGuiComponent(ajxpId, ajxpClass, ajxpOptions);
			}
		}
	},
	
	refreshGuiComponent:function(ajxpId, ajxpClass, ajxpOptions){
		if(!window[ajxpId]) return;
		// First destroy current component, unregister actions, etc.			
		var oldObj = window[ajxpId];
		if(Class.objectImplements(oldObj, "IFocusable")){
			this._focusables = this._focusables.without(oldObj);
		}
		if(Class.objectImplements(oldObj, "IActionProvider")){
			oldObj.getActions().each(function(act){
				this.guiActions = this.guiActions.without(act);
			}.bind(this) );
		}

		var obj = new ajxpClass($(ajxpId), ajxpOptions);			
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

		window[ajxpId] = obj;
		obj.resize();
		delete(oldObj);
	},
	
	refreshGuiComponentConfigs : function(){
		var nodes = XPathSelectNodes(this._registry, "client_configs/component_config");
		if(!nodes.length) return;
		for(var i=0;i<nodes.length;i++){
			this.setGuiComponentConfig(nodes[i]);
		}
	},
	
	setGuiComponentConfig : function(domNode){
		var className = domNode.getAttribute("className");
		var classId = domNode.getAttribute("classId") || null;
		var classConfig = this._guiComponentsConfigs.get(className) || new Hash();		
		if(classId){
			classConfig.set(classId, domNode);
		}else{
			classConfig.set('all', domNode);
		}
		this._guiComponentsConfigs.set(className,classConfig);
		document.fire("ajaxplorer:component_config_changed", {className:className, classConfig:classConfig});
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
			connexion.sendAsync();	
		}
	},
			
	logXmlUser: function(documentElement, skipEvent){
		this.user = null;
		var userNode = XPathSelectSingleNode(documentElement, "user");
		if(userNode){
			var userId = userNode.getAttribute('id');
			var children = userNode.childNodes;
			if(userId){ 
				this.user = new User(userId, children);
			}
		}
		if(!skipEvent){
			document.fire("ajaxplorer:user_logged", this.user);
		}
	},
		
	loadActiveRepository : function(){
		var repositoryObject = new Repository(null);
		if(this.user != null)
		{
			var repId = this.user.getActiveRepository();
			var repList = this.user.getRepositoriesList();			
			repositoryObject = repList.get(repId);
			if(!repositoryObject){
				alert("No active repository found for user!");
			}
			if(this.user.getPreference("pending_folder") && this.user.getPreference("pending_folder") != "-1"){
				this._initLoadRep = this.user.getPreference("pending_folder");
				this.user.setPreference("pending_folder", "-1");
				this.user.savePreference("pending_folder");
			}else if(this.user.getPreference("ls_history", true)){
				var data = new Hash(this.user.getPreference("ls_history", true));
				this._initLoadRep = data.get(repId);
			}
		}
		this.loadRepository(repositoryObject);		
		if(repList && repId){
			document.fire("ajaxplorer:repository_list_refreshed", {list:repList,active:repId});
		}else{
			document.fire("ajaxplorer:repository_list_refreshed", {list:false,active:false});
		}		
	},
	
	reloadRepositoriesList : function(){
		if(!this.user) return;
		document.observeOnce("ajaxplorer:registry_part_loaded", function(event){
			if(event.memo != "user/repositories") return;
			this.logXmlUser(this._registry, true);
			repId = this.user.getActiveRepository();
			repList = this.user.getRepositoriesList();
			document.fire("ajaxplorer:repository_list_refreshed", {list:repList,active:repId});			
		}.bind(this));
		this.loadXmlRegistry(false, "user/repositories");
	},
	
	loadRepository: function(repository){		
		repository.loadResources();
		var repositoryId = repository.getId();		
		var	newIcon = repository.getIcon(); 
				
		this.skipLsHistory = true;
		
		var rootNode = new AjxpNode("/", false, repository.getLabel(), newIcon);
		this._contextHolder.setRootNode(rootNode);
				
		if(!this._initObj) { 			
			this.repositoryId = repositoryId;
		} else { 
			this._initObj = null ;
			if(!ajxpBootstrap.parameters.get('usersEnabled')) return;
		}
		
		if(this._initLoadRep){
			if(this._initLoadRep != "" && this._initLoadRep != "/"){
				var copy = this._initLoadRep.valueOf();
				this._initLoadRep = null;
				rootNode.observeOnce("loaded", function(){
						setTimeout(function(){
							if(this.pathExists(copy)){
								this.goTo(new AjxpNode(copy));
							}
							this.skipLsHistory = false;
						}.bind(this), 1000);						
				}.bind(this));
			}else{
				this.skipLsHistory = false;
			}
		}else{
			this.skipLsHistory = false;
		}
	},

	pathExists : function(dirName){
		var connexion = new Connexion();
		connexion.addParameter("get_action", "stat");
		connexion.addParameter("file", dirName);
		this.tmpResTest = false;
		connexion.onComplete = function(transport){
			if(transport.responseJSON && transport.responseJSON.mode) this.tmpResTest = true;
		}.bind(this);
		connexion.sendSync();		
		return this.tmpResTest;
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
		document.fire("ajaxplorer:trigger_repository_switch");
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'switch_repository');
		connexion.addParameter('repository_id', repositoryId);
		oThis = this;
		connexion.onComplete = function(transport){
			this.loadXmlRegistry();
		}.bind(this);
		var root = this._contextHolder.getRootNode();
		if(root){
			this.skipLsHistory = true;
			this._contextHolder.setContextNode(root);
			root.clear();			
		}
		connexion.sendAsync();
	},

	initExtension : function(xmlNode, extensionDefinition){
		var activeCondition = XPathSelectSingleNode(xmlNode, 'processing/activeCondition');
		if(activeCondition && activeCondition.firstChild){
			try{
				var func = new Function(activeCondition.firstChild.nodeValue.strip());
				if(func() === false) return false;
			}catch(e){}
		}
		if(xmlNode.nodeName == 'editor'){
			Object.extend(extensionDefinition, {
				openable : (xmlNode.getAttribute("openable") == "true"?true:false),
				formId : xmlNode.getAttribute("formId") || null,				
				text : MessageHash[xmlNode.getAttribute("text")],
				title : MessageHash[xmlNode.getAttribute("title")],
				icon : xmlNode.getAttribute("icon"),
				editorClass : xmlNode.getAttribute("className"),
				mimes : $A(xmlNode.getAttribute("mimes").split(",")),
				write : (xmlNode.getAttribute("write") && xmlNode.getAttribute("write")=="true"?true:false)
			});
		}else if(xmlNode.nodeName == 'uploader'){
			var clientForm = XPathSelectSingleNode(xmlNode, 'processing/clientForm');
			if(clientForm && clientForm.firstChild && clientForm.getAttribute('id'))
			{
				extensionDefinition.formId = clientForm.getAttribute('id');
				if(!$('all_forms').select('[id="'+clientForm.getAttribute('id')+'"]').length){
					$('all_forms').insert(clientForm.firstChild.nodeValue);
				}
			}
			var extensionOnInit = XPathSelectSingleNode(xmlNode, 'processing/extensionOnInit');
			if(extensionOnInit && extensionOnInit.firstChild){
				try{eval(extensionOnInit.firstChild.nodeValue);}catch(e){}
			}
			var dialogOnOpen = XPathSelectSingleNode(xmlNode, 'processing/dialogOnOpen');
			if(dialogOnOpen && dialogOnOpen.firstChild){
				extensionDefinition.dialogOnOpen = dialogOnOpen.firstChild.nodeValue;
			}
			var dialogOnComplete = XPathSelectSingleNode(xmlNode, 'processing/dialogOnComplete');
			if(dialogOnComplete && dialogOnComplete.firstChild){
				extensionDefinition.dialogOnComplete = dialogOnComplete.firstChild.nodeValue;
			}
		}
		return true;
	},
	
	refreshExtensionsRegistry : function(){
		this._extensionsRegistry = {"editor":$A([]), "uploader":$A([])};
		var extensions = XPathSelectNodes(this._registry, "plugins/editor|plugins/uploader");
		for(var i=0;i<extensions.length;i++){
			var extensionDefinition = {
				id : extensions[i].getAttribute("id"),
				xmlNode : extensions[i],
				resourcesManager : new ResourcesManager()				
			};
			this._resourcesRegistry[extensionDefinition.id] = extensionDefinition.resourcesManager;
			for(var j=0;j<extensions[i].childNodes.length;j++){
				var child = extensions[i].childNodes[j];
				extensionDefinition.resourcesManager.loadFromXmlNode(child);
			}
			if(this.initExtension(extensions[i], extensionDefinition)){
				this._extensionsRegistry[extensions[i].nodeName].push(extensionDefinition);
			}
		}
		ResourcesManager.prototype.loadAutoLoadResources(this._registry);
	},
	
	getActiveExtensionByType : function(extensionType){
		var exts = $A();
		return this._extensionsRegistry[extensionType];
	},
	
	findEditorById : function(editorId){
		return this._extensionsRegistry.editor.detect(function(el){return(el.id == editorId);});
	},
	
	findEditorsForMime : function(mime){
		var editors = $A([]);
		var checkWrite = false;
		if(this.user != null && !this.user.canWrite()){
			checkWrite = true;
		}
		this._extensionsRegistry.editor.each(function(el){
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
		if(!this._registry) return;
		var tNodes = XPathSelectNodes(this._registry, "client_configs/template");
		for(var i=0;i<tNodes.length;i++){
			var target = tNodes[i].getAttribute("element");
			if($(target)){
				var position = tNodes[i].getAttribute("position");
				var obj = {}; obj[position] = tNodes[i].firstChild.nodeValue;
				$(target).insert(obj);
			}
		}		
		modal.updateLoadingProgress('Html templates loaded');	
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
				if(this.guiActions){
					this.guiActions.each(function(pair){
						pair.value.refreshFromI18NHash();
					});
				}
				this.loadXmlRegistry();
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
				tag.update(MessageHash[messageId]);
			}catch(e){}
		});
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
	
	getXmlRegistry : function(){
		return this._registry;
	},	
	
	cancelCopyOrMove: function(){
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
			
	displayMessage: function(messageType, message){
		var urls = parseUrl(message);
		if(urls.length && this.user && this.user.repositories){
			urls.each(function(match){
				var repo = this.user.repositories.get(match.host);
				if(!repo) return;
				message = message.replace(match.url, repo.label+":" + match.path + match.file);
			}.bind(this));
		}
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
				var foundFocus = false;
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
						foundFocus = true;
						break;
					}
				}
				if(!foundFocus && objects[0]){
					this.focusOn(objects[0]);
				}
				Event.stop(e);
			}
			if(this.blockShortcuts || e['ctrlKey']) return;
			if(e.keyCode > 90 || e.keyCode < 65) return;
			else return this.actionBar.fireActionByKey(e, String.fromCharCode(e.keyCode).toLowerCase());
		}.bind(this));
	}
		
});
