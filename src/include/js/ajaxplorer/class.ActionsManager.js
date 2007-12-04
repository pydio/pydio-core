function ActionsManager(oElement, bUsersEnabled, oUser, oAjaxplorer)
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
	// LOAD BOOKMARKS
	this.bookmarksBar = new ResizeableBar("bmbar_content", "bookmarks_bar", "bm", "bmbar_title", "bmbar_extension");
	this.loadBookmarks();
}

ActionsManager.prototype.init = function()
{
	this._items = this._htmlElement.getElementsByTagName('a');
	var oThis = this;
	for(i=0; i<this._items.length;i++)
	{
		// Set action link
		var item = this._items[i];
		var action = item.getAttribute('action');
		// OPERA : OPERA ADDS "http://yourdomain.com/ajaxplorer/" to the action attribute value...
		if(action.substr(0,4)=='http')
		{
			action = getBaseName(action);
		}
		this._actions[action] = i;
		item.href = "javascript:ajaxplorer.getActionBar().fireAction('"+action+"')";
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
		this._registeredKeys[firstKey.toLowerCase()] = action;
		textNode.innerHTML = replaceHtml;
	}
	this.downloader = new MultiDownloader($('multiple_download_container'), 'content.php?action=telecharger&fic='); 
	this.downloader.triggerEnd = function() {hideLightBox();};

	//this.multi_selector = new MultiSelector( $( 'upload_files_list' ), '6' );
	//this.multi_selector.addElement( $( 'upload_focus' ) );
	
	//alert(this._registeredKeys['u']);
	var oThis = this;
	$('current_path').onfocus = function(e)	{
		ajaxplorer.disableShortcuts();
		oThis.hasFocus = true;
		$('current_path').select();
		return false;
	};
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
			oThis.hasFocus = false;
		}
	};
	if(!this.usersEnabled){
		 $('login_button').hide();
		 $('logging_string').hide();
		 $('admin_button').hide();
	}

}

ActionsManager.prototype.setUser = function(oUser)
{	
	this.oUser = oUser;
	var logging_string = "";
	if(oUser != null) 
	{
		if(oUser.id != 'guest') 
		{
			this.switchLoginButton('loggedin');
			logging_string = MessageHash[142]+'<i style="cursor:pointer;text-decoration:underline;" title="'+MessageHash[189]+'" onclick="ajaxplorer.actionBar.displayUserPrefs();">'+ oUser.id+'</i>.';
			if(oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != ajaxplorer.currentLanguage)
			{
				res = confirm(MessageHash[196]);
				if(res) window.location.href = window.location.href;
			}
		}
		else 
		{
			this.switchLoginButton('loggedout');
			logging_string = MessageHash[143];
		}
	}
	else 
	{
		this.switchLoginButton('loggedout');
		$('admin_button').hide();
		logging_string = MessageHash[144];
	}
	$('logging_string').innerHTML = logging_string;	
	for(var i=0; i<this._items.length; i++)
	{
		if(this._items[i].getAttribute('write_access') 
			&& this._items[i].getAttribute('write_access')=='true' 
			&& (this.oUser == null || !this.oUser.canWrite()))
			{
				this._items[i].hide();
			}
		else this._items[i].show();
	}
	if(this.oUser == null || !this.oUser.canWrite()) $('write_access_separator').hide();
	else $('write_access_separator').show();
	if(oUser != null && oUser.id == 'admin') $('admin_button').show();
	else  $('admin_button').hide();
	$('view_button').hide();
	$('edit_button').hide();

	if(oUser != null)
	{
		disp = oUser.getPreference("display");
		if(disp && (disp == 'thumb' || disp == 'list'))
		{
			if(disp != ajaxplorer.filesList._displayMode) ajaxplorer.filesList.switchDisplayMode(disp);
		}
	}
	
	this.loadBookmarks();
}

ActionsManager.prototype.displayUserPrefs = function()
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
			alert(MessageHash[197]);
			hideLightBox(true);
		}
		ajaxplorer.user.savePreferences(userPass, onComplete);
		return false;		
	}
	
	modal.prepareHeader(MessageHash[195], '');
	modal.showDialogForm('Preferences', 'user_pref_form', onLoad, onComplete);
}

ActionsManager.prototype.update = function(bClear)
{
	var bSelection;
	if(!this._ajaxplorer.getFilesList() || bClear) 
	{
		bSelection = false;
	}
	else
	{
		var userSelection = this._ajaxplorer.getFilesList().getUserSelection();
		if(!userSelection)
		{
			bSelection = false;
		}
		else
		{
			bSelection = !userSelection.isEmpty();
			var bUnique = userSelection.isUnique();
			var bFile = userSelection.hasFile();
			var bDir = userSelection.hasDir();
			var bEditable = userSelection.isEditable()||userSelection.isImage();			
		}
	}
	
	for(i=0; i<this._items.length;i++)
	{
		var attSelection = ((this._items[i].getAttribute('selection') && this._items[i].getAttribute('selection') == 'true')?true:false);
		var attUnique = ((this._items[i].getAttribute('unique') && this._items[i].getAttribute('unique') == 'true')?true:false);
		var attFile = ((this._items[i].getAttribute('file') && this._items[i].getAttribute('file') == 'true')?true:false);
		var attDir = ((this._items[i].getAttribute('folder') && this._items[i].getAttribute('folder') == 'true')?true:false);
		var attEditable = ((this._items[i].getAttribute('editable') && this._items[i].getAttribute('editable') == 'true')?true:false);
				
		this._items[i].className = 'disabled';
		if(!attSelection)
		{
			this._items[i].className = 'enabled';		
			continue;
		}
		if(attSelection && !bSelection) continue;
		if((attFile || attDir) && !bFile && !bDir)continue;
		if(attFile && !attDir && !bFile)continue;
		if(attDir && !attFile && !bDir)continue;
		if(attEditable && !bEditable) continue;
		if(attUnique && !bUnique) continue;
		if(!attFile && bFile) continue;
		if(!attDir && bDir) continue;
		this._items[i].className = 'enabled';		
	}
	// EDIT - VIEW BUTTON
	$('view_button').hide();
	$('edit_button').hide();
	if(bUnique && userSelection.isImage()) $('view_button').show();
	else if(bUnique && userSelection.isEditable() && (this.oUser==null || this.oUser.canWrite()))$('edit_button').show();
}

ActionsManager.prototype.actionIsAllowed = function(buttonAction)
{
	var button = this._items[this._actions[buttonAction]];
	var attSelection = ((button.getAttribute('selection') && button.getAttribute('selection') == 'true')?true:false);
	var attUnique = ((button.getAttribute('unique') && button.getAttribute('unique') == 'true')?true:false);
	var attFile = ((button.getAttribute('file') && button.getAttribute('file') == 'true')?true:false);
	var attDir = ((button.getAttribute('folder') && button.getAttribute('folder') == 'true')?true:false);
	var attEditable = ((button.getAttribute('editable') && button.getAttribute('editable') == 'true')?true:false);
	var writeAccess = ((button.getAttribute('write_access') && button.getAttribute('write_access') == 'true')?true:false);
	
	var userSelection = this._ajaxplorer.getFilesList().getUserSelection();
	
	if(writeAccess && this.oUser != null && !this.oUser.canWrite()) return false;	
	if(attSelection && userSelection.isEmpty()) return false;
	if((attFile || attDir) && !userSelection.hasFile() && !userSelection.hasDir()) return false;
	if(attFile && !attDir && !userSelection.hasFile())return false;
	if(attDir && !attFile && !userSelection.hasDir())return false;
	if(attEditable && !(userSelection.isEditable()||userSelection.isImage()))return false;	
	
	return true;
}

ActionsManager.prototype.fireAction = function (buttonAction)
{
	var button = this._items[this._actions[buttonAction]];
	var dialogTitle = "";
	var iconSrc = "";
	if(button.getAttribute("title")) dialogTitle = button.getAttribute("title");
	var oImage = $(button).getElementsBySelector("img")[0];
	if(oImage.original_src){
		iconSrc = oImage.original_src; // IF IE PNG HACK, THE SRC IS "transparent.gif", THE REAL ONE IS IN original_src
	}else{
		iconSrc = oImage.src;
	}
	if(!this.actionIsAllowed(buttonAction)) return;
	var oThis = this;
	if($A(["create_file", "rename", "upload", "create_dir", "copy", "move", "delete", "download", "splash", "login", "admin", "view", "edit"]).indexOf(buttonAction) != -1)
	{
		modal.prepareHeader(dialogTitle, iconSrc);
	}
	switch(buttonAction)
	{	
		case "up_dir":
			url = this.getLocationBarValue();
			currentParentUrl = url.substr(0, url.lastIndexOf('/'));
			if(currentParentUrl == "") currentParentUrl = "/";
			ajaxplorer.getFilesList().loadXmlList(currentParentUrl);
			ajaxplorer.getFoldersTree().goToParentNode();
			this.updateLocationBar(currentParentUrl);			
		break;
		
		case "refresh":
			ajaxplorer.getFilesList().reload();
			ajaxplorer.getFoldersTree().reloadCurrentNode();
			this.update(true);
		break;
		
		case "bookmark":
			var params = new Hash();
			params['get_action'] = 'display_bookmark_bar';
			params['bm_action'] = 'add_bookmark';
			params['user'] = this._currentUser;
			params['bm_path'] = this.getLocationBarValue();
			var bmBar = this.bookmarksBar;
			this.loadHtmlToDiv($('bmbar_content'), params, function(){bmBar.updateUI();});
						
		break;		
		
		case "upload":		
			$('hidden_frames').innerHTML = '<iframe name="hidden_iframe" id="hidden_iframe"></iframe>';			
			var onLoadFunction = function(oForm){
				oThis.multi_selector = new MultiSelector(oForm, oForm.getElementsBySelector('div.uploadFilesList')[0], '6' );
				oThis.multi_selector.addElement(oForm.getElementsBySelector('.dialogFocus')[0]);
				var rep = document.createElement('input');
				rep.setAttribute('type', 'hidden');
				rep.setAttribute('name', 'rep');
				rep.setAttribute('value', ajaxplorer.getFilesList().getCurrentRep());
				oForm.appendChild(rep);
			}
			modal.showDialogForm('Upload', 'originalUploadForm', onLoadFunction, function(){
				ajaxplorer.actionBar.multi_selector.submitMainForm();
				return false;
			});		
		break;
		
		case "create_file":
		case "create_dir":
			var divId = buttonAction.replace('_', '')+'_form';
			modal.showDialogForm('Create', divId, null, function(){
				var oForm = $(modal.getForm());	
				var elementToCheck=(oForm['nomfic']?oForm['nomfic']:oForm['nomdir']);
				if(ajaxplorer.getFilesList().fileNameExists($(elementToCheck).getValue()))
				{
					alert(MessageHash[125]);
					return false;
				}				
				oThis.submitForm(oForm);				
				hideLightBox(true);
				return false;
			});

		break;
		
		case "rename":
		 	var onLoad = function(newForm){		 		
				var userSelection = ajaxplorer.getFilesList().getUserSelection();
				userSelection.updateFormOrUrl(newForm, '');
				var crtFileName = userSelection.getUniqueFileName();
				newForm.fic_new.value = getBaseName(crtFileName);
		 	}
			modal.showDialogForm('Rename', 'rename_form', onLoad);

		break;
		
		case "copy":
		case "move":
			var onLoad = function(oForm){
				var getAction = oForm.getElementsBySelector('input[name="get_action"]')[0];
				if(buttonAction == 'copy') getAction.value = 'copier_suite';
				else getAction.value = 'deplacer_suite';
				var container = oForm.getElementsBySelector(".treeCopyContainer")[0];
				var eDestLabel = oForm.getElementsBySelector('input[name="dest"]')[0];
				var eDestNodeHidden = oForm.getElementsBySelector('input[name="dest_node"]')[0];
				if(!oThis.treeCopy){
					oThis.treeCopy = new WebFXLoadTree('SELECT A DIR', 
														'content.php?action=xml_listing', 
														"javascript:ajaxplorer.clickDir(\'/\',\'/\',CURRENT_ID)", 
														'explorer');
				}
				else{
					window.setTimeout('ajaxplorer.actionBar.treeCopy.reload()', 100);
				}				
				oThis.treeCopyActive = true;
				oThis.treeCopyActionDest = $A([eDestLabel]);
				oThis.treeCopyActionDestNode = $A([eDestNodeHidden]);
				container.innerHTML = oThis.treeCopy.toString();
				oThis.treeCopy.focus();
			};
			var onCancel = function(){				
				ajaxplorer.cancelCopyOrMove();
			};
			var onSubmit = function(){
				var oForm = modal.getForm();
				var eDestLabel = oForm.getElementsBySelector('input[name="dest"]')[0];
				if(eDestLabel.value == ajaxplorer.filesList.getCurrentRep())
				{
					alert(MessageHash[183]);
					return false;
				}
				ajaxplorer.filesList.getUserSelection().updateFormOrUrl(oForm);				
				oThis.treeCopyActive = false;
				oThis.submitForm(oForm);
				hideLightBox(true);
				return false;
			};
			modal.showDialogForm('Move/Copy', 'copymove_form', onLoad, onSubmit, onCancel);
		break;
		
		case "delete":
			var onLoad = function(oForm){
		    	var message = MessageHash[177];
		    	if(ajaxplorer.foldersTree.recycleEnabled() && !ajaxplorer.foldersTree.currentIsRecycle()){
		    		message = MessageHash[176];
		    	}
   		    	$(oForm).getElementsBySelector('span#delete_message')[0].innerHTML = message;
			}
			modal.showDialogForm('Delete', 'delete_form', onLoad, function(){
				var oForm = modal.getForm();
				ajaxplorer.filesList.getUserSelection().updateFormOrUrl(oForm);
				oThis.submitForm(oForm);
				hideLightBox(true);
				return false;				
			});
		break;
		
		case "download":
			var userSelection = this._ajaxplorer.getFilesList().getUserSelection();
			if(userSelection.isUnique())
			{
				var downloadUrl = 'content.php?action=telecharger';
				downloadUrl = userSelection.updateFormOrUrl(null,downloadUrl);
				document.location.href = downloadUrl;
				break;
			}
			var loadFunc = function(oForm){
				var dObject = oForm.getElementsBySelector('div[id="multiple_download_container"]')[0];
				var downloader = new MultiDownloader(dObject, 'content.php?action=telecharger&fic=');
				downloader.triggerEnd = function(){hideLightBox()};
				fileNames = userSelection.getFileNames();
				for(var i=0; i<fileNames.length;i++)
				{
					downloader.addListRow(fileNames[i]);
				}				
			};
			var closeFunc = function(){
				hideLightBox();
				return false;
			};
			modal.showDialogForm('Download Multiple', 'multi_download_form', loadFunc, closeFunc, null, true);
		break;
		
		case "splash":
			modal.showDialogForm(
				'Ajaxplorer', 
				'splash_form', 
				null, 
				function(){hideLightBox();return false;}, 
				null, 
				true);		
		break;
		
		case "admin":
			modal.showDialogForm(
				'Ajaxplorer Settings', 
				'admin_form', 
				null, 
				function(){				
					hideLightBox();return false;
				}, 
				null, 
				true);		
		break;
		
		case "edit":	
			var userSelection =  this._ajaxplorer.getFilesList().getUserSelection();
			if(!userSelection.isEditable()) break;
			var sTitle = MessageHash[187]+userSelection.getUniqueFileName();
			modal.prepareHeader(sTitle, iconSrc);
			var loadFunc = function(oForm){
				ajaxplorer.filesList.getUserSelection().updateFormOrUrl(oForm);
				oThis.editor = new Editor(oForm);
				oThis.editor.createEditor(userSelection.getUniqueFileName());
				oThis.editor.loadFile(userSelection.getUniqueFileName());
			}
			modal.showDialogForm('Edit Online', 'edit_box', loadFunc, null, null, true, true);
		break;

		case "view":			
			var userSelection =  this._ajaxplorer.getFilesList().getUserSelection();
			if(!userSelection.isImage()) break;
			var loadFunc = function(oForm){
				oThis.diaporama = new Diaporama($(oForm));
				oThis.diaporama.open(ajaxplorer.getFilesList().getItems(), userSelection.getUniqueFileName());
			}
			var closeFunc = function(){
				oThis.diaporama.close();
				hideLightBox();
				return false;
			}
			modal.showDialogForm('Diaporama', 'diaporama_box', loadFunc, closeFunc, null, true, true);
		break;
		
		case "switch_display":
			var newDisplay = ajaxplorer.filesList.switchDisplayMode();
			this.updateDisplayButton(newDisplay);
		
		break;
		
		case "login":			
			if($('login_button').getAttribute('action') == 'logout' 
			|| getBaseName($('login_button').getAttribute('action')) == 'logout') // opera...
			{
				var connexion = new Connexion();
				connexion.addParameter('get_action', 'logout');
				oThis = this;
				connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
				connexion.sendAsync();
			}
			else
			{
				modal.showDialogForm('Log In', 'login_form', null, function(){
					var oForm = modal.getForm();
					//ajaxplorer.filesList.getUserSelection().updateFormOrUrl(oForm);
					oThis.submitForm(oForm);
					//hideLightBox(true);
					return false;				
				});
			}
		
		break;
		
		default:
			alert("Cannot find action '"+buttonAction+"'");
		break;
	}
	
}

ActionsManager.prototype.fireActionByKey = function(keyName)
{	
	//alert(ajaxplorer.blockShortcuts);
	if(this._registeredKeys[keyName] && !ajaxplorer.blockShortcuts)
	{
		 this.fireAction(this._registeredKeys[keyName]);
		 return false;
	}
	return true;
}


ActionsManager.prototype.applyCreate = function()
{
}

ActionsManager.prototype.updateDisplayButton = function (newDisplay)
{
	$('sd_button').getElementsBySelector('img')[0].src = 'images/crystal/'+(newDisplay=='list'?'view_icon.png':'view_text.png');
}

ActionsManager.prototype.submitForm = function(formName)
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
	connexion.addParameter('rep', ajaxplorer.getFilesList().getCurrentRep());
	oThis = this;
	connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
	connexion.sendAsync();
}

ActionsManager.prototype.parseXmlMessage = function(xmlResponse)
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
}

ActionsManager.prototype.switchLoginButton = function(action)
{
	if(action == "loggedin")
	{
		$('login_logout_image').src = 'images/crystal/cancel.png';
		$('login_button').setAttribute('action', 'logout');
		$('login_button').setAttribute('title', MessageHash[169]);
		$('login_logout_span').innerHTML = MessageHash[164];	
	}
	else
	{
		$('login_logout_image').src = 'images/crystal/yast_security.png';
		$('login_button').setAttribute('action', 'login');
		$('login_button').setAttribute('title', MessageHash[168]);
		$('login_logout_span').innerHTML = MessageHash[163];
	}
}

ActionsManager.prototype.removeBookmark = function (path)
{
	var params = new Hash();
	params['get_action'] = 'display_bookmark_bar';
	params['bm_action'] = 'delete_bookmark';
	params['bm_path'] = path;
	params['user'] = this._currentUser;	
	var bmBar = this.bookmarksBar;
	this.loadHtmlToDiv($('bmbar_content'), params, function(){bmBar.updateUI();});	
}

ActionsManager.prototype.loadBookmarks = function ()
{
	// LOAD BOOKMARKS
	var params = new Hash();
	params['get_action'] = 'display_bookmark_bar';
	params['user'] = this._currentUser;
	var oThis = this;
	this.loadHtmlToDiv($('bmbar_content'), params, function(){
		oThis.bookmarksBar.updateUI();
	});	
}

ActionsManager.prototype.loadHtmlToDiv = function(div, parameters, completeFunc)
{
	var connexion = new Connexion();
	parameters.each(function(pair){
		connexion.addParameter(pair.key, pair.value);
	});
	connexion.onComplete = function(transport){		
		div.innerHTML = transport.responseText;
		if(modal.pageLoading) modal.updateLoadingProgress('Bookmarks Loaded');
		if(completeFunc) completeFunc();
	};
	connexion.sendAsync();	
}

ActionsManager.prototype.locationBarSubmit = function (url)
{
	if(url == '') return false;	
	this._ajaxplorer.goTo(url);
	return false;
}

ActionsManager.prototype.locationBarFocus = function()
{
	$('current_path').activate();
}

ActionsManager.prototype.updateLocationBar = function (newPath)
{
	$('current_path').value = newPath;
}

ActionsManager.prototype.getLocationBarValue = function ()
{
	return $('current_path').getValue();
}

ActionsManager.prototype.focus = function()
{
	$('current_path').focus();
	this.hasFocus = true;
}

ActionsManager.prototype.blur = function()
{
	$('current_path').blur();
	this.hasFocus = false;
}