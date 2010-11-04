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
Class.create("ActionsManager", {
	
	initialize: function(bUsersEnabled)
	{
		this._registeredKeys = new Hash();
		this._actions = new Hash();
		this.usersEnabled = bUsersEnabled;
		
		this.bgManager = new BackgroundManager(this);		
		this.subMenus = [];				
		this.actions = new Hash();
		this.defaultActions = new Hash();
		this.toolbars = new Hash();		
		document.observe("ajaxplorer:context_changed", function(event){
			window.setTimeout(function(){
				this.fireContextChange();
			}.bind(this), 0);			
		}.bind(this) );
		
		document.observe("ajaxplorer:selection_changed", function(event){
			window.setTimeout(function(){
				this.fireSelectionChange();
			}.bind(this), 0);
		}.bind(this) );
		
		document.observe("ajaxplorer:user_logged", function(event){
			if(event.memo && event.memo.getPreference){
				this.setUser(event.memo);
			}else{
				this.setUser(null);
			}
		}.bind(this));
		
	},	
	
	setUser: function(oUser)
	{	
		this.oUser = oUser;
		if(oUser != null && ajaxplorer  && oUser.id != 'guest' && oUser.getPreference('lang') != null 
			&& oUser.getPreference('lang') != "" 
			&& oUser.getPreference('lang') != ajaxplorer.currentLanguage) 
		{
			ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
		}
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
				image:resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16),
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
	
	getActionsForAjxpWidget:function(ajxpClassName, widgetId){
		var actions = $A([]);
		this.actions.each(function(pair){
			var action = pair.value;
			if(action.context.ajxpWidgets && (action.context.ajxpWidgets.include(ajxpClassName+'::'+widgetId)||action.context.ajxpWidgets.include(ajxpClassName)) && !action.deny) actions.push(action);
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
		connexion.addParameter('dir', ajaxplorer.getContextNode().getPath());		
		connexion.onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this);
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
		
	submitForm: function(formName, post, completeCallback)
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
		if(completeCallback){
			connexion.onComplete = completeCallback;
		}else{
			connexion.onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this) ;
		}
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
					ajaxplorer.loadXmlRegistry();
				}
				else if(result == '0' || result == '-1')
				{
					// Update Form!					
					alert(MessageHash[285]);
				}
				else if(result == '2')
				{					
					ajaxplorer.loadXmlRegistry();
				}
				else if(result == '-2')
				{
					alert(MessageHash[286]);
				}
				else if(result == '-3')
				{
					alert(MessageHash[366]);
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
			
	fireSelectionChange: function(){
		var userSelection = null;
		if (ajaxplorer && ajaxplorer.getUserSelection()){
			userSelection = ajaxplorer.getUserSelection();
			if(userSelection.isEmpty()) userSelection = null;
		} 
		this.actions.each(function(pair){
			pair.value.fireSelectionChange(userSelection);
		});		
		document.fire("ajaxplorer:actions_refreshed");
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
		document.fire("ajaxplorer:actions_refreshed");
	},
			
	removeActions: function(){
		this.actions.each(function(pair){
			pair.value.remove();
		});
		this.actions = new Hash();
		this.clearRegisteredKeys();
	},
	
	loadActionsFromRegistry : function(registry){
		this.removeActions();		
		this.parseActions(registry);
		if(ajaxplorer && ajaxplorer.guiActions){
			ajaxplorer.guiActions.each(function(pair){
				var act = pair.value;
				this.registerAction(act);
			}.bind(this));
		}
		document.fire("ajaxplorer:actions_loaded", this.actions);
		this.fireContextChange();
		this.fireSelectionChange();		
	},
	
	registerAction : function(action){
		var actionName = action.options.name;
		this.actions.set(actionName, action);
		if(action.defaults){
			for(var key in action.defaults) this.defaultActions.set(key, actionName);
		}
		if(action.options.hasAccessKey){
			this.registerKey(action.options.accessKey, actionName);
		}
		action.setManager(this);
	},
	
	parseActions: function(documentElement){		
		actions = XPathSelectNodes(documentElement, "actions/action");
		for(var i=0;i<actions.length;i++){
			if(actions[i].nodeName != 'action') continue;
            if(actions[i].getAttribute('enabled') == 'false') continue;
			var newAction = new Action();
			newAction.createFromXML(actions[i]);
			this.registerAction(newAction);
		}
	},
	
	getActionByName : function(actionName){
		return this.actions.get(actionName);		
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