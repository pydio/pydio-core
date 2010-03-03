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
 * Description : Singleton that manages all actions, but also the action bar display.
 */
Class.create("ActionsManager", AjxpPane, {

	__implements : "IFocusable",
	
	initialize: function($super, oElement, bUsersEnabled, oUser, oAjaxplorer)
	{
		$super(oElement);
		this._registeredKeys = new Hash();
		this._actions = new Hash();
		this._ajaxplorer = oAjaxplorer;
		this.usersEnabled = bUsersEnabled;
		//this._currentUser = sCrtUserName;
		if(oUser != null){
			this._currentUser = oUser.id;
		}
		else {
			this._currentUser = 'shared';
		}
		this.oUser = oUser;
		this.bookmarksBar = new BookmarksBar();
		this.bgManager = new BackgroundManager(this);
		
		this.subMenus = [];				
		this.actions = new Hash();
		this.defaultActions = new Hash();
		this.toolbars = new Hash();		
		this.loadActions('ajxp');	
		document.observe("ajaxplorer:context_changed", function(event){
			this.fireContextChange();
			this.updateLocationBar(event.memo.getContextNode());
		}.bind(this) );
		
		document.observe("ajaxplorer:selection_changed", function(event){
			this.fireSelectionChange();
		}.bind(this) );
		
	},	
	
	init: function()
	{		
		this._items = this.htmlElement.select('[action]');
		$('current_path').onfocus = function(e)	{
			ajaxplorer.disableShortcuts();
			this.hasFocus = true;
			$('current_path').select();
			return false;
		}.bind(this);
		var buttons = this.htmlElement.getElementsBySelector("input");
		buttons.each(function(object){
			$(object).onkeydown = function(e){
				if(e == null) e = window.event;		
				if(e.keyCode == 9) return false;
				return true;
			};
			if($(object) == $('goto_button'))
			{
				$(object).onfocus = function(){
					$('current_path').focus();
				};
			}
		});
		
		$('current_path').onblur = function(e)	{
			if(!currentLightBox){
				ajaxplorer.enableShortcuts();
				this.hasFocus = false;
			}
		}.bind(this);
		
	},
	
	setContextualMenu: function(contextualMenu)
	{
		this.bookmarksBar.setContextualMenu(contextualMenu);
	},
	
	setUser: function(oUser)
	{	
		this.oUser = oUser;
		var logging_string = "";
		if(oUser != null) 
		{
			if(oUser.id != 'guest') 
			{
				logging_string = '<ajxp:message ajxp_message_id="142">'+MessageHash[142]+'</ajxp:message><i ajxp_message_title_id="189" title="'+MessageHash[189]+'" onclick="ajaxplorer.actionBar.displayUserPrefs();">'+ oUser.id+' <img src="'+ajxpResourcesFolder+'/images/crystal/actions/16/configure.png" height="16" width="16" border="0" align="absmiddle"></i>';
				if(oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				{
					ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				}
			}
			else 
			{
				logging_string = '<ajxp:message ajxp_message_id="143">'+MessageHash[143]+'</ajxp:message>';
			}
		}
		else 
		{
			logging_string = '<ajxp:message ajxp_message_id="142">'+MessageHash[144]+'</ajxp:message>';
		}
		$('logging_string').innerHTML = logging_string;
		this.loadBookmarks();
	},
	
	displayUserPrefs: function()
	{
		if(ajaxplorer.user == null) return;
		var userLang = ajaxplorer.user.getPreference("lang");
		var userDisp = ajaxplorer.user.getPreference("display");	
		var onLoad = function(){		
			var elements = $('user_pref_form').getElementsBySelector('input[type="radio"]');		
			elements.each(function(elem){
				elem.checked = false;			
				if(elem.id == 'display_'+userDisp || elem.id == 'lang_'+userLang) {
					elem.checked = true;
				}
			});
			if($('user_change_ownpass_old')){
				$('user_change_ownpass_old').value = $('user_change_ownpass1').value = $('user_change_ownpass2').value = '';
				// Update pass_seed
				var connexion = new Connexion();
				connexion.addParameter("get_action", "get_seed");
				connexion.onComplete = function(transport){
					$('pass_seed').value = transport.responseText;
				};
				connexion.sendSync();			
			}
		};
		
		var onComplete = function(){
			var elements = $('user_pref_form').getElementsBySelector('input[type="radio"]');
			elements.each(function(elem){			
				if(elem.checked){
					 ajaxplorer.user.setPreference(elem.name, elem.value);
				}
			});
			var userOldPass = null;
			var userPass = null;
			var passSeed = null;
			if($('user_change_ownpass1') && $('user_change_ownpass1').value != "" && $('user_change_ownpass2').value != "")
			{
				if($('user_change_ownpass1').value != $('user_change_ownpass2').value){
					alert(MessageHash[238]);
					return false;
				}
				if($('user_change_ownpass_old').value == ''){
					alert(MessageHash[239]);
					return false;					
				}
				passSeed = $('pass_seed').value;
				if(passSeed == '-1'){
					userPass = $('user_change_ownpass1').value;
					userOldPass = $('user_change_ownpass_old').value;
				}else{
					userPass = hex_md5($('user_change_ownpass1').value);
					userOldPass = hex_md5( hex_md5($('user_change_ownpass_old').value)+$('pass_seed').value);
				}				
			}
			var onComplete = function(transport){
				var oUser = ajaxplorer.user;
				if(oUser.getPreference('lang') != null 
					&& oUser.getPreference('lang') != "" 
					&& oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				{
					ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				}
					
				if(userPass != null){
					if(transport.responseText == 'PASS_ERROR'){
						alert(MessageHash[240]);
					}else if(transport.responseText == 'SUCCESS'){
						ajaxplorer.displayMessage('SUCCESS', MessageHash[197]);
						hideLightBox(true);
					}
				}else{
					ajaxplorer.displayMessage('SUCCESS', MessageHash[241]);
					hideLightBox(true);
				}
			};
			ajaxplorer.user.savePreferences(userOldPass, userPass, passSeed, onComplete);
			return false;		
		};
		
		modal.prepareHeader(MessageHash[195], ajxpResourcesFolder+'/images/crystal/actions/16/configure.png');
		modal.showDialogForm('Preferences', 'user_pref_form', onLoad, onComplete);
	},
		
	getContextActions: function(srcElement)
	{		
		var actionsSelectorAtt = 'selectionContext';
		if(srcElement.id && (srcElement.id == 'table_rows_container' ||  srcElement.id == 'selectable_div'))
		{
			actionsSelectorAtt = 'genericContext';
		}
		else if(srcElement.id.substring(0,5)=='webfx')
		{
			actionsSelectorAtt = 'directoryContext';
		}
		else
		{
			// find the bookmark origin
			var bm = this.bookmarksBar.findBookmarkEventSource(srcElement);
			if(bm != null){
				return this.bookmarksBar.getContextActions(bm);
			}
		};
		var contextActions = new Array();
		var crtGroup;
		this.actions.each(function(pair){
			var action = pair.value;
			if(!action.context.contextMenu) return;
			if(actionsSelectorAtt == 'selectionContext' && !action.context.selection) return;
			if(actionsSelectorAtt == 'directoryContext' && !action.context.dir) return;
			if(actionsSelectorAtt == 'genericContext' && action.context.selection) return;
			if(action.contextHidden || action.deny) return;
			if(crtGroup && crtGroup != action.context.actionBarGroup){
				contextActions.push({separator:true});
			}
			var isDefault = false;
			if(actionsSelectorAtt == 'selectionContext'){
				// set default in bold
				var userSelection = ajaxplorer.getUserSelection();
				if(!userSelection.isEmpty()){
					var defaultAction = 'file';
					if(userSelection.isUnique() && userSelection.hasDir()){
						defaultAction = 'dir';
					}
					if(this.defaultActions.get(defaultAction) && action.options.name == this.defaultActions.get(defaultAction)){
						isDefault = true;
					}
				}
			}
			var menuItem = {
				name:action.getKeyedText(),
				alt:action.options.title,
				image:resolveImageSource(action.options.src, '/images/crystal/actions/ICON_SIZE', 16),
				isDefault:isDefault,
				callback:function(e){this.apply()}.bind(action)
			};
			if(action.options.subMenu){
				menuItem.subMenu = [];
				if(action.subMenuItems.staticOptions){
					menuItem.subMenu = action.subMenuItems.staticOptions;
				}
				if(action.subMenuItems.dynamicBuilder){
					menuItem.subMenuBeforeShow = action.subMenuItems.dynamicBuilder;
				}
			}
			contextActions.push(menuItem);
			crtGroup = action.context.actionBarGroup;
		}.bind(this));
		
		return contextActions;
	},
	
	getInfoPanelActions:function(){
		var actions = $A([]);
		this.actions.each(function(pair){
			var action = pair.value;
			if(action.context.infoPanel && !action.deny) actions.push(action);
		});
		return actions;
	},
	
	fireDefaultAction: function(defaultName){
		var actionName = this.defaultActions.get(defaultName); 
		if(actionName != null){
			arguments[0] = actionName;
			if(actionName == "ls"){
				var action = this.actions.get(actionName);
				if(action) action.enable(); // Force enable on default action
			}
			this.fireAction.apply(this, arguments);
		}
	},
	
	fireAction: function (buttonAction)	{		
		var action = this.actions.get(buttonAction);
		if(action != null) {
			var args = $A(arguments);
			args.shift();
			action.apply(args);
			return;
		}
	},
	
	registerKey: function(key, actionName, optionnalCommand){		
		if(optionnalCommand){
			actionName = actionName + "::" + optionnalCommand;
		}
		this._registeredKeys.set(key.toLowerCase(), actionName);
	},
	
	clearRegisteredKeys: function(){
		this._registeredKeys = new Hash();
	},
	
	fireActionByKey: function(event, keyName)
	{	
		if(this._registeredKeys.get(keyName) && !ajaxplorer.blockShortcuts)
		{
			if(this._registeredKeys.get(keyName).indexOf("::")!==false){
				var parts = this._registeredKeys.get(keyName).split("::");
				this.fireAction(parts[0], parts[1]);
			}else{
				this.fireAction(this._registeredKeys.get(keyName));
			}
			Event.stop(event);
		}
		return;
	},
	
	
	applyDragMove: function(fileName, destDir, destNodeName, copy)
	{
		if((!copy && !this.defaultActions.get('dragndrop')) || 
			(copy && (!this.defaultActions.get('ctrldragndrop')||this.getDefaultAction('ctrldragndrop').deny))){
			return;
		}
		if(fileName == null) fileNames = ajaxplorer.getUserSelection().getFileNames();
		else fileNames = [fileName];
		if(destNodeName != null)
		{
			// Check that dest is not a child of the source
			if(this.checkDestIsChildOfSource(fileNames, destNodeName)){
				ajaxplorer.displayMessage('ERROR', MessageHash[202]);
				return;
			}
			// Check that dest is not the source it self
			for(var i=0; i<fileNames.length;i++)
			{			
				if(fileNames[i] == destDir){
					ajaxplorer.displayMessage('ERROR', MessageHash[202]);
					 return;
				}
			}
			// Check that dest is not the direct parent of source, ie current rep!
			if(destDir == ajaxplorer.getContextNode().getPath()){
				ajaxplorer.displayMessage('ERROR', MessageHash[203]);
				 return;
			}
		}
		var connexion = new Connexion();
		if(copy){
			connexion.addParameter('get_action', this.defaultActions.get('ctrldragndrop'));
		}else{
			connexion.addParameter('get_action', this.defaultActions.get('dragndrop'));
		}
		if(fileName != null){
			connexion.addParameter('file', fileName);
		}else{
			for(var i=0; i<fileNames.length;i++){
				connexion.addParameter('file_'+i, fileNames[i]);
			}
		}
		connexion.addParameter('dest', destDir);
		if(destNodeName) connexion.addParameter('dest_node', destNodeName);
		connexion.addParameter('dir', ajaxplorer.getContextNode().getPath());
		oThis = this;
		connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
		connexion.sendAsync();
	},
	
	getDefaultAction : function(defaultName){
		if(this.defaultActions.get(defaultName)){
			return this.actions.get(this.defaultActions.get(defaultName));
		}
		return null;
	},
	
	checkDestIsChildOfSource: function(srcNames, destNodeName)
	{
		if(typeof srcNames == "string"){
			srcNames = [srcNames];
		}
		var destNode = webFXTreeHandler.all[destNodeName];
		while(destNode.parentNode){
			for(var i=0; i<srcNames.length;i++){
				if(destNode.filename == srcNames[i]){				
					return true;
				}
			}
			destNode = destNode.parentNode;
		}
		return false;
	},
		
	submitForm: function(formName, post)
	{
		var connexion = new Connexion();
		if(post){
			connexion.setMethod('POST');
		}
		$(formName).getElements().each(function(fElement){
			// OPERA : ADDS 'http://www.yourdomain.com/ajaxplorer/' to the action attribute value
			var fValue = fElement.getValue();
			if(fElement.name == 'get_action' && fValue.substr(0,4) == 'http'){			
				fValue = getBaseName(fValue);
			}
			if(fElement.type == 'radio' && !fElement.checked) return;
			connexion.addParameter(fElement.name, fValue);
		});
		if(ajaxplorer.getContextNode()){
			connexion.addParameter('dir', ajaxplorer.getContextNode().getPath());
		}
		oThis = this;
		connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
		connexion.sendAsync();
	},
	
	parseXmlMessage: function(xmlResponse)
	{
		var messageBox = ajaxplorer.messageBox;
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		
		var reloadNodes = [];
		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].tagName == "message")
			{
				var messageTxt = "No message";
				if(childs[i].firstChild) messageTxt = childs[i].firstChild.nodeValue;
				ajaxplorer.displayMessage(childs[i].getAttribute('type'), messageTxt);
			}
			else if(childs[i].tagName == "reload_instruction")
			{
				var obName = childs[i].getAttribute('object');
				if(obName == 'data')
				{
					var node = childs[i].getAttribute('node');				
					if(node){
						reloadNodes.push(node);
					}else{
						var file = childs[i].getAttribute('file');
						if(file){
							ajaxplorer.getContextHolder().setPendingSelection(file);
						}
						reloadNodes.push(ajaxplorer.getContextNode());
					}
				}
				else if(obName == 'repository_list')
				{
					ajaxplorer.reloadRepositoriesList();
				}
			}
			else if(childs[i].tagName == "logging_result")
			{
				var result = childs[i].getAttribute('value');
				if(result == '1')
				{
					hideLightBox(true);
					if(childs[i].getAttribute('remember_login') && childs[i].getAttribute('remember_pass')){
						var login = childs[i].getAttribute('remember_login');
						var pass = childs[i].getAttribute('remember_pass');
						storeRememberData(login, pass);
					}
					ajaxplorer.getLoggedUserFromServer();
				}
				else if(result == '0' || result == '-1')
				{
					// Update Form!					
					alert(MessageHash[285]);
				}
				else if(result == '2')
				{					
					ajaxplorer.getLoggedUserFromServer();
				}
				else if(result == '-2')
				{
					alert(MessageHash[286]);
				}
			}else if(childs[i].tagName == "trigger_bg_action"){
				var name = childs[i].getAttribute("name");
				var messageId = childs[i].getAttribute("messageId");
				var parameters = new Hash();
				for(var j=0;j<childs[i].childNodes.length;j++){
					var paramChild = childs[i].childNodes[j];
					if(paramChild.tagName == 'param'){
						parameters.set(paramChild.getAttribute("name"), paramChild.getAttribute("value"));
					}
				}
				this.bgManager.queueAction(name, parameters, messageId);
				this.bgManager.next();
			}

		}
		if(reloadNodes.length){
			ajaxplorer.getContextHolder().multipleNodesReload(reloadNodes);
		}
	},
		
	removeBookmark: function (path)
	{
		this.bookmarksBar.removeBookmark(path);
	},
	
	loadBookmarks: function ()
	{
		this.bookmarksBar.load();
	},
	
	fireSelectionChange: function(){
		var userSelection = null;
		if (ajaxplorer && ajaxplorer.getUserSelection()){
			userSelection = ajaxplorer.getUserSelection();
			if(userSelection.isEmpty()) userSelection = null;
		} 
		this.actions.each(function(pair){
			pair.value.fireSelectionChange(userSelection);
		});
		this.refreshToolbarsSeparator();
	},
	
	fireContextChange: function(){
		var crtRecycle = false;
		var crtInZip = false;
		var crtIsRoot = false;
		var crtMime;
		
		if(ajaxplorer && ajaxplorer.getContextNode()){ 
			var crtNode = ajaxplorer.getContextNode();
			crtRecycle = (crtNode.getAjxpMime() == "ajxp_recycle");
			crtInZip = crtNode.hasAjxpMimeInBranch("ajxp_browsable_archive");
			crtIsRoot = crtNode.isRoot();
			crtMime = crtNode.getAjxpMime();			
		}	
		this.actions.each(function(pair){			
			pair.value.fireContextChange(this.usersEnabled, 
									 this.oUser, 									 
									 crtRecycle, 
									 crtInZip, 
									 crtIsRoot,
									 crtMime);
		}.bind(this));
		this.refreshToolbarsSeparator();
	},
	
	initToolbars: function () {
		var crtCount = 0;
		var toolbarsList = $A(['default', 'put', 'get', 'change', 'user', 'remote']);
		toolbarsList.each(function(toolbar){			
			var tBar = this.initToolbar(toolbar);			
			if(tBar && tBar.actionsCount){				
				if(crtCount < toolbarsList.size()-1) {
					var separator = new Element('div');
					separator.addClassName('separator');
					tBar.insert({top:separator});
				}
				$('buttons_bar').insert(tBar);
				crtCount ++;
			}
		}.bind(this));
		$('buttons_bar').select('a').each(disableTextSelection);
	},
	
	refreshToolbarsSeparator: function(){
		this.toolbars.each(function(pair){
			var toolbar = $('buttons_bar').select('[id="'+pair.key+'_toolbar"]')[0];
			if(!toolbar) return;
			var sep = toolbar.select('div.separator')[0];
			if(!sep) return;
			var hasVisibleActions = false;
			toolbar.select('a').each(function(action){
				if(action.visible()) hasVisibleActions = true;
			});
			if(hasVisibleActions) sep.show();
			else sep.hide();
		});
	},
	
	initToolbar: function(toolbar){
		if(!this.toolbars.get(toolbar)) {
			return;
		}
		var toolEl = $(toolbar+'_toolbar');		
		if(!toolEl){ 
			var bgColor = $('action_bar').getStyle('backgroundColor');
			var toolEl = new Element('div', {
				id: toolbar+'_toolbar',
				style: 'display:inline;background-color:'+bgColor
			});
		}
		toolEl.actionsCount = 0;
		this.toolbars.get(toolbar).each(function(actionName){
			var action = this.actions.get(actionName);		
			if(!action) return;	
			toolEl.insert(action.toActionBar());
			toolEl.actionsCount ++;			
		}.bind(this));
		return toolEl;
	},
	
	emptyToolbars: function(){
		$('buttons_bar').select('div').each(function(divElement){			
			divElement.remove();
		}.bind(this));
		this.toolbars = new Hash();
	},
		
	removeActions: function(){
		this.actions.each(function(pair){
			pair.value.remove();
		});
		this.actions = new Hash();
		this.emptyToolbars();
		this.clearRegisteredKeys();
	},
	
	loadActions: function(type){
		this.removeActions();		
		var connexion = new Connexion();
		connexion.onComplete = function(transport){
			this.parseActions(transport.responseXML);
		}.bind(this);
		connexion.addParameter('get_action', 'get_ajxp_actions');
		connexion.sendSync();
		if(!type){
			connexion.addParameter('get_action', 'get_driver_actions');
			connexion.sendSync();
		}
		this.initToolbars();
		document.fire("ajaxplorer:actions_loaded");
		this.fireContextChange();
		this.fireSelectionChange();			
	},
	
	parseActions: function(xmlResponse){		
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var actions = xmlResponse.documentElement.childNodes;		
		for(var i=0;i<actions.length;i++){
			if(actions[i].nodeName != 'action') continue;
            if(actions[i].getAttribute('enabled') == 'false') continue;
			var newAction = new Action(this);			
			newAction.createFromXML(actions[i]);
			this.actions.set(actions[i].getAttribute('name'), newAction);
			if(actions[i].getAttribute('dirDefault') && actions[i].getAttribute('dirDefault') == "true"){
				this.defaultActions.set('dir', actions[i].getAttribute('name'));
			} 
			else if(actions[i].getAttribute('fileDefault') && actions[i].getAttribute('fileDefault') == "true"){
				this.defaultActions.set('file', actions[i].getAttribute('name'));
			} 
			else if(actions[i].getAttribute('dragndropDefault') && actions[i].getAttribute('dragndropDefault') == "true"){
				this.defaultActions.set('dragndrop', actions[i].getAttribute('name'));
			}			
			else if(actions[i].getAttribute('ctrlDragndropDefault') && actions[i].getAttribute('ctrlDragndropDefault') == "true"){
				this.defaultActions.set('ctrldragndrop', actions[i].getAttribute('name'));
			}			
			if(newAction.context.actionBar){				
				if(this.toolbars.get(newAction.context.actionBarGroup) == null){
					this.toolbars.set(newAction.context.actionBarGroup, new Array());
				}
				this.toolbars.get(newAction.context.actionBarGroup).push(newAction.options.name);
			}
			if(newAction.options.hasAccessKey){
				this.registerKey(newAction.options.accessKey, newAction.options.name);
			}
			if(ajaxplorer && newAction.options.name == "ls"){
				for(var j=0;j<actions[i].childNodes.length;j++){
					if(actions[i].childNodes[j].nodeName == 'displayDefinitions'){
						var displayDef = actions[i].childNodes[j];
						break;
					}					
				}
				if(!displayDef) continue;
				for(var j=0; j<displayDef.childNodes.length;j++){
					if(displayDef.childNodes[j].nodeName == 'display' && displayDef.childNodes[j].getAttribute('mode') == 'list'){
						var columnsDef = displayDef.childNodes[j];
					}
				}
				if(!columnsDef) continue;
				var columns = $A([]);
				$A(columnsDef.childNodes).each(function(column){
					if(column.nodeName == "column"){
						columns.push({
							messageId:column.getAttribute("messageId"),
							attributeName:column.getAttribute("attributeName")
						});
					}
				});
				ajaxplorer.changeDataColumnsDefinition(columns);
			}
			if(newAction.options.listeners['init']){				
				try{
					window.listenerContext = newAction;
					newAction.options.listeners['init'].evalScripts();
				}catch(e){
					alert(e);
				}
			}
		}
		if(this.defaultActions.get("select")){
			this.defaultActions.set("file", this.defaultActions.get("select"));
		}else{
			if(this.actions.get("ext_select")){
				this.actions.unset("ext_select");
			}
		}
	},
	
	getActionByName : function(actionName){
		return this.actions.get(actionName);		
	},
	
	locationBarSubmit: function (url)
	{
		if(url == '') return false;	
		var node = new AjxpNode(url, false);
		var parts = url.split("#");
		if(parts.length == 2){
			var data = new Hash();
			data.set("new_page", parts[1]);
			url = parts[0];
			node = new AjxpNode(url);
			node.getMetadata().set("paginationData", data);
		}
		this.fireDefaultAction("dir", node);
		return false;
	},
	
	locationBarFocus: function()
	{
		$('current_path').activate();
	},
	
	updateLocationBar: function (newNode)
	{
		if(Object.isString(newNode)){
			newNode = new AjxpNode(newNode);
		}
		var newPath = newNode.getPath();
		if(newPath == ""){
			newPath = "/";
		}
		if(newNode.getMetadata().get('paginationData')){
			newPath += "#" + newNode.getMetadata().get('paginationData').get('current');
		}
		$('current_path').value = newPath;
	},
	
	getLocationBarValue: function ()
	{
		return $('current_path').getValue();
	},
	
	focus: function()
	{
		$('current_path').focus();
		this.hasFocus = true;
	},
	
	blur: function()
	{
		$('current_path').blur();
		this.hasFocus = false;
	},
	
	getFlashVersion: function()
	{
		if (!this.pluginVersion) {
			var x;
			if(navigator.plugins && navigator.mimeTypes.length){
				x = navigator.plugins["Shockwave Flash"];
				if(x && x.description) x = x.description;
			} else if (Prototype.Browser.IE){
				try {
					x = new ActiveXObject("ShockwaveFlash.ShockwaveFlash");
					x = x.GetVariable("$version");
				} catch(e){}
			}
			this.pluginVersion = (typeof(x) == 'string') ? parseInt(x.match(/\d+/)[0]) : 0;
		}
		return this.pluginVersion;
	}
});
