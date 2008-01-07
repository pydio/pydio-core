ActionsManager = Class.create({

	initialize: function(oElement, bUsersEnabled, oUser, oAjaxplorer)
	{
		this._htmlElement = oElement;
		this._registeredKeys = new Hash();
		this._actions = new Hash();
		this._ajaxplorer = oAjaxplorer;
		this.usersEnabled = bUsersEnabled;
		//this._currentUser = sCrtUserName;
		if(oUser != null){
			this._currentUser = oUser.id;
		}
		else this._currentUser = 'shared';
		this.oUser = oUser;
		this.bookmarksBar = new BookmarksBar();
		
		this.actions = new Hash();
		this.defaultActions = new Hash();
		this.toolbars = new Hash();		
		this.loadActions('ajxp');
	},	
	
	init: function()
	{		
		this._items = this._htmlElement.select('[action]');
		var oThis = this;
		for(i=0; i<this._items.length;i++)
		{	
			if(!this._items[i].getAttribute('action')) continue;
			// Set action link
			var item = this._items[i];
			var action = item.getAttribute('action');
			// OPERA : OPERA ADDS "http://yourdomain.com/ajaxplorer/" to the action attribute value...
			if(action.substr(0,4)=='http')
			{
				action = getBaseName(action);
			}
			this._actions.set(action, i);
			item.href = "javascript:ajaxplorer.getActionBar().fireAction('"+action+"')";
			// Set image
			if(item.getAttribute('icon_src'))
			{
				var displayString = item.innerHTML;
				item.innerHTML = '';
				
				var image = new Element('img');
				item.appendChild(image);
				image.width='22';
				image.height='22';
				image.src = ajxpResourcesFolder+'/images/crystal/actions/22/'+item.getAttribute('icon_src');
				image.setAttribute('border', '0');
				image.setAttribute('align', 'ABSMIDDLE');
				image.setAttribute('alt', item.getAttribute('title'));
				image.setAttribute('title', item.getAttribute('title'));			
	
				if(item.getAttribute('key'))
				{
					if(item.getAttribute('key') == 'none') continue;
					firstKey = item.getAttribute('key');
				}
				else
				{
					firstKey = displayString.charAt(0);
				}
				displayString = displayString.substring(0,displayString.indexOf(firstKey)) + '<u>'+firstKey+'</u>' + displayString.substring(displayString.indexOf(firstKey)+1, displayString.length);
				this._registeredKeys.set(firstKey.toLowerCase(), action);			
				
				spanTitle = new Element('span');
				spanTitle.innerHTML = displayString;
				
				item.appendChild(new Element('br'));
				item.appendChild(spanTitle);
			}else{
			// Set keyboard shortcut
			var textNode = item.lastChild;
			var textNodeString = textNode.innerHTML;
			if(item.getAttribute('key'))
			{
				if(item.getAttribute('key') == 'none') continue;
				firstKey = item.getAttribute('key');
			}
			else
			{
				firstKey = textNodeString.charAt(0);
			}
			replaceHtml = textNodeString.substring(0,textNodeString.indexOf(firstKey)) + '<u>'+firstKey+'</u>' + textNodeString.substring(textNodeString.indexOf(firstKey)+1, textNodeString.length);
			this._registeredKeys.set(firstKey.toLowerCase(), action);
			textNode.innerHTML = replaceHtml;
			}
		}		
		//alert(this._registeredKeys['u']);
		var oThis = this;
		$('current_path').onfocus = function(e)	{
			ajaxplorer.disableShortcuts();
			this.hasFocus = true;
			$('current_path').select();
			return false;
		}.bind(this);
		var buttons = this._htmlElement.getElementsBySelector("input");
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
				logging_string = '<ajxp_message ajxp_message_id="142">'+MessageHash[142]+'</ajxp_message><i style="cursor:pointer;text-decoration:underline;" title="'+MessageHash[189]+'" onclick="ajaxplorer.actionBar.displayUserPrefs();">'+ oUser.id+'</i>.';
				if(oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				{
					ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				}
			}
			else 
			{
				logging_string = '<ajxp_message ajxp_message_id="143">'+MessageHash[143]+'</ajxp_message>';
			}
		}
		else 
		{
			logging_string = '<ajxp_message ajxp_message_id="142">'+MessageHash[144]+'</ajxp_message>';
		}
		$('logging_string').innerHTML = logging_string;
		if(oUser != null)
		{
			disp = oUser.getPreference("display");
			if(disp && (disp == 'thumb' || disp == 'list'))
			{
				if(disp != ajaxplorer.filesList._displayMode) ajaxplorer.filesList.switchDisplayMode(disp);
			}
		}		
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
			$('user_change_ownpass1').value = $('user_change_ownpass2').value = '';
		};
		
		var onComplete = function(){
			var elements = $('user_pref_form').getElementsBySelector('input[type="radio"]');
			elements.each(function(elem){			
				if(elem.checked){
					 ajaxplorer.user.setPreference(elem.name, elem.value);
				}
			});
			var userPass = null;
			if($('user_change_ownpass1') && $('user_change_ownpass1').value != "" && $('user_change_ownpass2').value != "")
			{
				if($('user_change_ownpass1').value != $('user_change_ownpass2').value)
				{
					alert('Passwords differ!');
					return false;
				}
				userPass = $('user_change_ownpass1').value;
			}
			var onComplete = function(transport){
				if(userPass != null) alert(MessageHash[197]);
				var oUser = ajaxplorer.user;
				if(oUser.getPreference('lang') != null 
					&& oUser.getPreference('lang') != "" 
					&& oUser.getPreference('lang') != ajaxplorer.currentLanguage)
				ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
				hideLightBox(true);
			}
			ajaxplorer.user.savePreferences(userPass, onComplete);
			return false;		
		}
		
		modal.prepareHeader(MessageHash[195], '');
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
			contextActions.push({
				name:action.options.text,
				alt:action.options.title,
				image:ajxpResourcesFolder+'/images/crystal/actions/16/'+action.options.src,				
				callback:function(e){this.apply()}.bind(action)
			});
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
	
	registerKey: function(key, actionName){		
		this._registeredKeys.set(key.toLowerCase(), actionName);
	},
	
	clearRegisteredKeys: function(){
		this._registeredKeys = new Hash();
	},
	
	fireActionByKey: function(event, keyName)
	{	
		if(this._registeredKeys.get(keyName) && !ajaxplorer.blockShortcuts)
		{
			 this.fireAction(this._registeredKeys.get(keyName));
			 Event.stop(event);
		}
		return;
	},
	
	
	applyDragMove: function(fileName, destDir, destNodeName, copy)
	{
		if(fileName == null) fileNames = ajaxplorer.filesList.getUserSelection().getFileNames();
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
			if(destDir == ajaxplorer.filesList.getCurrentRep()){
				ajaxplorer.displayMessage('ERROR', MessageHash[203]);
				 return;
			}
		}
		var connexion = new Connexion();
		if(copy){
			connexion.addParameter('get_action', 'copy');
		}else{
			connexion.addParameter('get_action', 'move');
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
		connexion.addParameter('dir', ajaxplorer.getFilesList().getCurrentRep());
		oThis = this;
		connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
		connexion.sendAsync();
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
		
	submitForm: function(formName)
	{
		var connexion = new Connexion();
		$(formName).getElements().each(function(fElement){
			// OPERA : ADDS 'http://www.yourdomain.com/ajaxplorer/' to the action attribute value
			var fValue = fElement.getValue();
			if(fElement.name == 'get_action' && fValue.substr(0,4) == 'http'){			
				fValue = getBaseName(fValue);
			}
			connexion.addParameter(fElement.name, fValue);
		});
		connexion.addParameter('dir', ajaxplorer.getFilesList().getCurrentRep());
		oThis = this;
		connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
		connexion.sendAsync();
	},
	
	parseXmlMessage: function(xmlResponse)
	{
		var messageBox = ajaxplorer.messageBox;
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].tagName == "message")
			{
				ajaxplorer.displayMessage(childs[i].getAttribute('type'), childs[i].firstChild.nodeValue);
			}
			else if(childs[i].tagName == "reload_instruction")
			{
				var obName = childs[i].getAttribute('object');
				if(obName == 'tree')
				{
					var node = childs[i].getAttribute('node');				
					if(node == null) ajaxplorer.foldersTree.reloadCurrentNode();
					else ajaxplorer.foldersTree.reloadNode(node);
				}
				else if(obName == 'list')
				{
					var file = childs[i].getAttribute('file');
					ajaxplorer.filesList.reload(file);				
				}
			}
			else if(childs[i].tagName == "logging_result")
			{
				var result = childs[i].getAttribute('value');
				if(result == '1')
				{
					hideLightBox(true);
					ajaxplorer.getLoggedUserFromServer();
				}
				else if(result == '0' || result == '-1')
				{
					// Update Form!
					alert('User does not exists, please try again');
				}
				else if(result == '2')
				{
					ajaxplorer.getLoggedUserFromServer();
				}
			}
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
		if (ajaxplorer && ajaxplorer.getFilesList() && ajaxplorer.getFilesList().getUserSelection()){
			userSelection = ajaxplorer.getFilesList().getUserSelection();
		} 
		this.actions.each(function(pair){
			pair.value.fireSelectionChange(userSelection);
		});
	},
	
	fireContextChange: function(){
		var crtRecycle = false;
		if(ajaxplorer && ajaxplorer.foldersTree){ 
			crtRecycle = ajaxplorer.foldersTree.currentIsRecycle();
		}	
		var displayMode = '';
		if(ajaxplorer && ajaxplorer.filesList) displayMode = ajaxplorer.filesList.getDisplayMode();
		this.actions.each(function(pair){			
			pair.value.fireContextChange(this.usersEnabled, 
									 this.oUser, 
									 crtRecycle, 
									 displayMode);
		}.bind(this));
		this.refreshToolbarsSeparator();
	},
	
	initToolbars: function () {
		var crtCount = 0;
		var toolbarsList = $A(['default', 'put', 'get', 'change', 'user']);
		toolbarsList.each(function(toolbar){			
			var tBar = this.initToolbar(toolbar);			
			if(tBar && tBar.actionsCount){				
				if(crtCount < toolbarsList.size()-1) {
					var separator = new Element('div');
					separator.addClassName('separator');
					tBar.insert(separator);
				}
				$('buttons_bar').insert(tBar);
				crtCount ++;
			}
		}.bind(this));
		bgCorners("#action_bar a", "round 8px");
	},
	
	refreshToolbarsSeparator: function(){
		this.toolbars.each(function(pair){
			var toolbar = $('buttons_bar').select('[id="'+pair.key+'_toolbar"]')[0];
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
			var toolEl = new Element('div', {
				id: toolbar+'_toolbar',
				style: 'display:inline;'
			});
		}
		toolEl.actionsCount = 0;
		this.toolbars.get(toolbar).each(function(actionName){
			var action = this.actions.get(actionName);			
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
		if(ajaxplorer && ajaxplorer.infoPanel) ajaxplorer.infoPanel.load();
		this.fireContextChange();
		this.fireSelectionChange();	
	},
	
	parseActions: function(xmlResponse){		
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var actions = xmlResponse.documentElement.childNodes;		
		for(var i=0;i<actions.length;i++){
			if(actions[i].nodeName != 'action') continue;
			var newAction = new Action();
			newAction.createFromXML(actions[i]);
			this.actions.set(actions[i].getAttribute('name'), newAction);
			if(actions[i].getAttribute('dirDefault') && actions[i].getAttribute('dirDefault') == "true"){
				this.defaultActions.set('dir', actions[i].getAttribute('name'));
			} 
			if(actions[i].getAttribute('fileDefault') && actions[i].getAttribute('fileDefault') == "true"){
				this.defaultActions.set('file', actions[i].getAttribute('name'));
			} 
			if(actions[i].getAttribute('dragndropDefault') && actions[i].getAttribute('dragndropDefault') == "true"){
				this.defaultActions.set('dragndrop', actions[i].getAttribute('name'));
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
		}
	},
	
	locationBarSubmit: function (url)
	{
		if(url == '') return false;	
		this._ajaxplorer.goTo(url);
		return false;
	},
	
	locationBarFocus: function()
	{
		$('current_path').activate();
	},
	
	updateLocationBar: function (newPath)
	{
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