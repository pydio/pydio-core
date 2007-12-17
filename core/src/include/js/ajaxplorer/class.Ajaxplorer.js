if(dynamicLibLoading)
{
	jQuery.noConflict();
	document.write('<script language="javascript" type="text/javascript" src="include/js/lib/jquery/jquery.corner.js"></script><script language="javascript" type="text/javascript" src="include/js/lib/scriptaculous/src/scriptaculous.js?load=builder,effects,dragdrop"></script><script language="javascript" type="text/javascript" src="include/js/lib/leightbox/lightbox.js"></script><script language="javascript" type="text/javascript" src="include/js/ajaxplorer/class.Connexion.js"></script><script language="javascript" type="text/javascript" src="include/js/ajaxplorer/class.Modal.js"></script>');
}

function Ajaxplorer(loadRep, usersEnabled, loggedUser, rootDirId, rootDirsList, defaultDisplay)
{	
	this._initLoadRep = loadRep;
	this.usersEnabled = usersEnabled;
	this._initLoggedUser = loggedUser;
	this._initRootDirsList = rootDirsList;
	this._initRootDirId = rootDirId;
	this._initDefaultDisp = defaultDisplay;
	if(!this.usersEnabled) this.rootDirId = rootDirId;
	modal.setLoadingStepCounts(8);

	var connexion = new Connexion();
	connexion.addParameter('get_action', 'get_template');
	connexion.onComplete = function(transport){
		$('all_forms').innerHTML += transport.responseText;
	}
	connexion.addParameter('template_name', 'forms_tpl.html');
	connexion.sendSync();
	modal.updateLoadingProgress('Dialogs loaded');
	$('originalUploadForm').hide();

	connexion.onComplete = function(transport){
		document.body.innerHTML += transport.responseText;
	}
	connexion.addParameter('template_name', 'gui_tpl.html');
	connexion.sendSync();
	modal.updateLoadingProgress('Main template loaded');
	//connexion.loadLibraries();
	modal.init();
	this.init();
}

Ajaxplorer.prototype.init = function()
{
	loadRep = this._initLoadRep;
	crtUser = this._initCrtUser;
	rootDirName = this._initRootDir;
	//modal.updateLoadingProgress('Libraries loaded');
	if(!this.usersEnabled)
	{
		var fakeUser = new User("shared");
		fakeUser.setActiveRepository(this._initRootDirId, 1, 1);
		fakeUser.setRepositoriesList(this._initRootDirsList);
		this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, fakeUser, this);
		this.foldersTree = new FoldersTree('tree_container', this._initRootDirsList[this._initRootDirId], 'content.php?action=xml_listing', this);
		this.refreshRootDirMenu(this._initRootDirsList, this._initRootDirId);
	}
	else
	{
		this.actionBar = new ActionsManager($("action_bar"), this.usersEnabled, null, this);
		this.foldersTree = new FoldersTree('tree_container', 'No Repository', 'content.php?action=xml_listing', this);
		if(this._initLoggedUser)
		{
			this.getLoggedUserFromServer();
		}
	}
	//this.actionBar = new ActionsManager($("action_bar"), crtUser, this);
	this.actionBar.init();
	modal.updateLoadingProgress('ActionBar Initialized');
	
	//this.foldersTree = new FoldersTree('tree_container', rootDirName, 'content.php?action=xml_listing', this);
	
	this.filesList = new FilesList($("selectable_div"), 
									true, 
									["StringDirFile", "NumberKo", "String", "MyDate"], 
									null, 
									this, 
									this._initDefaultDisp);	

	this.contextMenu = new Proto.Menu({
	  selector: '', // context menu will be shown when element with class name of "contextmenu" is clicked
	  className: 'menu desktop', // this is a class which will be attached to menu container (used for css styling)
	  menuItems: [],
	  fade:true
	});
	var protoMenu = this.contextMenu;
	protoMenu.options.beforeShow = function(e){setTimeout(function(){
	  	this.options.menuItems = ajaxplorer.actionBar.getContextActions(Event.element(e));
	  	this.refreshList();
	  }.bind(protoMenu),0);};

	  this.filesList.setContextualMenu(this.contextMenu);
	  this.foldersTree.setContextualMenu(this.contextMenu);
	  
	this.sEngine = new SearchEngine("search_container", "search_txt","search_results", "search_button", this);
	this.infoPanel = new InfoPanel("info_panel");
	this.messageBox = $('message_div');
	this.initGUI();	
	modal.updateLoadingProgress('GUI Initialized');
	this.initFocusBehaviours();
	this.initTabNavigation();
	modal.updateLoadingProgress('Navigation loaded');
	this.focusOn(this.foldersTree);
	this.goTo(loadRep);
	document.onkeydown = function(event){		
		if(event == null)
		{
			event = window.event;				
			if(event.keyCode == 9){return false;}
		}		
	};
	this.blockShortcuts = false;
	this.blockNavigation = false;
	
	new AjxpAutocompleter("current_path", "autocomplete_choices");
	
}

Ajaxplorer.prototype.getLoggedUserFromServer = function()
{
	var connexion = new Connexion();
	connexion.addParameter('get_action', 'logged_user');
	oThis = this;
	connexion.onComplete = function(transport){oThis.logXmlUser(transport.responseXML);};
	connexion.sendAsync();	
}

Ajaxplorer.prototype.logXmlUser = function(xmlResponse)
{
	try{
		
		var childs = xmlResponse.documentElement.childNodes;		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].tagName == "user")
			{
				var userId = childs[i].getAttribute('id');
				childs = childs[i].childNodes;
				break;
			}		
		}	
		if(userId)
		{
			this.user = new User(userId);	
			var repositories = new Hash();
			for(var i=0; i<childs.length;i++)
			{
				if(childs[i].tagName == "active_repo")
				{
					this.user.setActiveRepository(childs[i].getAttribute('id'), 
													childs[i].getAttribute('read'), 
													childs[i].getAttribute('write'));
				}
				else if(childs[i].tagName == "repositories")
				{
					for(j=0;j<childs[i].childNodes.length;j++)
					{
						var repoChild = childs[i].childNodes[j];
						if(repoChild.tagName == "repo") repositories.set(repoChild.getAttribute("id"), repoChild.firstChild.nodeValue);
					}
					this.user.setRepositoriesList(repositories);
				}
				else if(childs[i].tagName == "preferences")
				{
					for(j=0;j<childs[i].childNodes.length;j++)
					{
						var prefChild = childs[i].childNodes[j];
						if(prefChild.tagName == "pref") this.user.setPreference(prefChild.getAttribute("name"), 
																				prefChild.getAttribute("value"));
					}					
				}
			}
		}
		else
		{
			this.user = null;
		}
	}catch(e){alert('Error parsing XML for user : '+e);}
	
	if(this.user != null)
	{
		this.rootDirId = this.user.getActiveRepository();
		var repList = this.user.getRepositoriesList();
		this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), repList.get(this.user.getActiveRepository()));
		this.refreshRootDirMenu(this.user.getRepositoriesList(), this.user.getActiveRepository());
	}
	else
	{
		this.refreshRootDirMenu(null, null);
		this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), 'No Repository');
	}
	this.actionBar.setUser(this.user);
	this.foldersTree.setCurrentNodeName(this.foldersTree.getRootNodeId());
	this.foldersTree.reloadCurrentNode();
	this.filesList.loadXmlList('/');
	//this.rootDirId = rootDirId;
	this.actionBar.loadBookmarks();
}

Ajaxplorer.prototype.loadLibraries = function()
{
	if(!dynamicLibLoading) {this.init(); return;}
	var connexion = new Connexion();
	var toLoad = $A([
		"lib/webfx/slider/js/timer.js", 
		"lib/webfx/slider/js/range.js", 
		"lib/webfx/slider/js/slider.js", 
		"lib/leightbox/lightbox.js", 
		"lib/jquery/dimensions.js", 
		"lib/jquery/splitter.js", 
		"lib/ufo/ufo.js",
		"lib/prototype/proto.menu.js",
		"lib/codepress/codepress.js",
		"lib/webfx/selectableelements.js", 
		"lib/webfx/selectabletablerows.js", 
		"lib/webfx/sortabletable.js", 
		"lib/webfx/numberksorttype.js", 
		"lib/webfx/slider/js/timer.js", 
		"lib/webfx/slider/js/range.js", 
		"lib/webfx/slider/js/slider.js", 
		"lib/xloadtree/xtree.js", 
		"lib/xloadtree/xloadtree.js", 
		"lib/xloadtree/xmlextras.js",
		"ajaxplorer/ajxp_multifile.js", 
		"ajaxplorer/ajxp_utils.js", 
		"ajaxplorer/class.User.js", 
		"ajaxplorer/class.AjxpDraggable.js",
		"ajaxplorer/class.AjxpAutoCompleter.js",
		"ajaxplorer/class.Diaporama.js",
		"ajaxplorer/class.Editor.js",
		"ajaxplorer/class.ActionsManager.js", 
		"ajaxplorer/class.FilesList.js", 
		"ajaxplorer/class.FoldersTree.js", 
		"ajaxplorer/class.SearchEngine.js", 
		"ajaxplorer/class.InfoPanel.js", 
		"ajaxplorer/class.ResizeableBar.js", 
		"ajaxplorer/class.UserSelection.js"]);
		
		
	modal.incrementStepCounts(toLoad.size());
	toLoad.each(function(fileName){
		var onLoad = function(){modal.updateLoadingProgress(fileName);};
		if(fileName == toLoad.last()) onLoad = function(){modal.updateLoadingProgress(fileName);this.init();}.bind(this);
		connexion.loadLibrary(fileName, onLoad);
	});
}

Ajaxplorer.prototype.libLoaded = function(fileName)
{	
	modal.updateLoadingProgress('Loaded : ' + fileName);
}

Ajaxplorer.prototype.goTo = function(rep, selectFile)
{
	this.actionBar.updateLocationBar(rep);
	this.actionBar.update(true);
	this.foldersTree.goToDeepPath(rep);	
	this.filesList.loadXmlList(rep, selectFile);
}

Ajaxplorer.prototype.clickDir = function(url, parent_url, objectName)
{
	if(this.actionBar.treeCopyActive)
	{
		if(this.actionBar.treeCopyActionDest) this.actionBar.treeCopyActionDest.each(function(element){element.value = url});
		if(this.actionBar.treeCopyActionDestNode) this.actionBar.treeCopyActionDestNode.each(function(element){element.value = objectName});
	}
	else
	{
		this.getFoldersTree().clickDir(url, parent_url, objectName);
		this.getFilesList().loadXmlList(url);
		this.getActionBar().updateLocationBar(url);
		this.getActionBar().update(true);
	}
}

Ajaxplorer.prototype.cancelCopyOrMove = function()
{
	this.foldersTree.setTreeInNormalMode();
	this.foldersTree.selectCurrentNodeName();
	this.actionBar.treeCopyActive = false;
	hideLightBox();
	return false;
}

Ajaxplorer.prototype.disableShortcuts = function(){
	this.blockShortcuts = true;
}

Ajaxplorer.prototype.enableShortcuts = function(){
	this.blockShortcuts = false;
}

Ajaxplorer.prototype.disableNavigation = function(){
	this.blockNavigation = true;
}

Ajaxplorer.prototype.enableNavigation = function(){
	this.blockNavigation = false;
}

Ajaxplorer.prototype.getActionBar = function()
{
	return this.actionBar;
}

Ajaxplorer.prototype.getFilesList = function()
{
	return this.filesList;
}

Ajaxplorer.prototype.getFoldersTree = function()
{
	return this.foldersTree;
}

Ajaxplorer.prototype.closeMessageDiv = function()
{
	if(this.messageDivOpen)
	{
		new Effect.Fade(this.messageBox);
		this.messageDivOpen = false;
	}
}

Ajaxplorer.prototype.tempoMessageDivClosing = function()
{
	this.messageDivOpen = true;
	setTimeout('ajaxplorer.closeMessageDiv()', 3000);
}

Ajaxplorer.prototype.displayMessage = function(messageType, message)
{
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
	jQuery(this.messageBox).corner("round");
	new Effect.Appear(this.messageBox);
	this.tempoMessageDivClosing();
}

Ajaxplorer.prototype.initFocusBehaviours = function()
{
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
	
}

Ajaxplorer.prototype.focusOn  = function(object)
{
	var objects = [this.foldersTree, this.sEngine, this.filesList, this.actionBar];
	objects.each(function(obj){
		if(obj != object) obj.blur();
	});
	object.focus();
}


Ajaxplorer.prototype.initTabNavigation = function()
{
	var objects = [this.foldersTree, this.filesList, this.actionBar];
	var oThis = this;
	// FIRST TOTALLY DISABLE TAB KEY
	/*
	document.onkeydown = function(e){
		if (e == null) e = document.parentWindow.event;		
		if (e.keyCode == 9 && !ajaxplorer.blockNavigation){return false;}
		return true;
	}
	*/	
	// NOW ASSIGN OBSERVER
	Event.observe(document, "keydown", function(e)
	{
		if (e == null) e = document.parentWindow.event;
		if(e.keyCode == Event.KEY_TAB)
		{			
			if(ajaxplorer.blockNavigation) return;			
			var shiftKey = e['shiftKey'];
			for(i=0; i<objects.length;i++)
			{
				if(objects[i].hasFocus)
				{
					objects[i].blur();
					var nextIndex;
					if(shiftKey)
					{
						if(i>0)
						{
							nextIndex=i-1;
						}
						else 
						{
							nextIndex = (objects.length) - 1;
						}
					}else
					{
						if(i<objects.length-1)nextIndex=i+1;
						else nextIndex = 0;
					}
					objects[nextIndex].focus();
					break;
				}
			}
			return false;
		}
		if(ajaxplorer.blockShortcuts || e['ctrlKey']) return true;
		if(e.keyCode > 90 || e.keyCode < 65) return true;
		else return oThis.actionBar.fireActionByKey(String.fromCharCode(e.keyCode).toLowerCase());
	});
}

Ajaxplorer.prototype.initGUI = function()
{
	jQuery("#toolbars").corner("round bottom 10px");
	jQuery("#action_bar a").corner("round 8px");
	jQuery(".action_bar a").corner("round 8px");
	//jQuery(".panelHeader").corner("round tl 5px");
	//jQuery("#last_header").corner("round tr 5px");
	//jQuery("#last_header").css("border-bottom", "1px solid #aaa");		
	jQuery("#location_form").corner("round 8px");
	
	jQuery("#verticalSplitter").splitter({
			type: "v",
			initA: 200, maxA:400, minA:50
			});
	jQuery("#sidebarSplitter").splitter({
			type: "h",
			initB: 150,
			minB: 23,
			maxB: 500
			});
			
	jQuery("#browser_round").corner("round 8px");
	fitHeightToBottom($("browser"), window, 15);
	fitHeightToBottom($("verticalSplitter"), $('browser'), (Prototype.Browser.IE?8:0));
	fitHeightToBottom($('tree_container'), null, (Prototype.Browser.IE?0:3));
	//jQuery("#search_div").corner("round bl 10px");
	//jQuery("#content_pane").corner("round br 10px");
	fitHeightToBottom(this.sEngine._resultsBox, null, 10);
	this.currentSideToggle = 'search';
	this.toggleSidePanel('info');	
	jQuery("#sidebarSplitter").trigger("resize");
	
	new Effect.Fade(this.messageBox);
	$(this.actionBar._htmlElement).getElementsBySelector('a', 'input[type="image"]').each(function(element){
		disableTextSelection(element);
	});
	$('search_container').getElementsBySelector('a', 'div[id="search_results"]').each(function(element){
		disableTextSelection(element);
	});
	disableTextSelection($('tree_container'));
	disableTextSelection($('bookmarks_bar'));
	disableTextSelection($('panelsToggle'));
	disableTextSelection($('info_panel'));
	disableTextSelection($('dir_chooser'));
	
}

Ajaxplorer.prototype.refreshRootDirMenu = function(rootDirsList, rootDirId)
{
	if(this.usersEnabled)
	{	
		if(this.rootDirsButton) {this.rootDirsButton.remove();this.rootDirsButton = null;}
		if(this.rootDirsMenu && this.rootDirsMenu.parentNode) {this.rootDirsMenu.remove();};
		if(this.rootDirsMenuVisible) this.rootDirsMenuVisible = false;
	}
	if(rootDirsList && rootDirsList.size() > 1)
	{
		var oThis = this;
		// CREATE BUTTON
		var img = $(document.createElement("img"));
		img.src = 'images/crystal/lower.png';
		img.setAttribute("align", "absmiddle");
		img.setStyle({cursor:"pointer"});		
		$('dir_chooser').appendChild(img);
		Event.observe(img, 'click', function(){oThis.toggleRootDirMenu();});
		this.rootDirsButton = img;
		
		// CREATE MENU
		// build menu and show next to button.
		this.rootDirsMenu = $(document.createElement("div"));
		this.rootDirsMenu.addClassName("rootDirChooser");
		var titleDiv = $(document.createElement('span'));
		titleDiv.addClassName('rootDirTitle');
		titleDiv.appendChild(document.createTextNode(MessageHash[200]));
		this.rootDirsMenu.appendChild(titleDiv);
		rootDirsList.each(function(pair){
			var element = document.createElement('a');
			var img = document.createElement('img');
			img.src = "images/foldericon.png";
			img.setAttribute("align", "absmiddle");
			img.setAttribute("hspace", "3");
			element.appendChild(img);
			element.appendChild(document.createTextNode(pair.value));
			element.rootDirId = pair.key;
			element.href = "javascript:ajaxplorer.triggerRootDirChange("+pair.key+");";
			oThis.rootDirsMenu.appendChild(element);
		});		
	}
}

Ajaxplorer.prototype.toggleRootDirMenu = function(hideOnly)
{
	if(!this.rootDirsMenuVisible && !hideOnly)
	{
		// Refresh list with current rootdir active
		var oThis = this;
		this.rootDirsMenu.getElementsBySelector("a").each(function(element){
			if(element.rootDirId == oThis.rootDirId) {
				element.setStyle({fontWeight:"bold", cursor:"default"});
				element.onclick = function(){return false;};
				element.getElementsBySelector("img")[0].src = "images/openfoldericon.png";
			}
			else{
				element.setStyle({fontWeight:"normal", cursor:"pointer"});
				element.onclick = function(){return true;};
				element.getElementsBySelector("img")[0].src = "images/foldericon.png";
			}
		});
		
		// Position list
		var buttonPosition = Position.cumulativeOffset($(this.rootDirsButton));
		var topPos = buttonPosition[1] + $(this.rootDirsButton).getHeight();
		var leftPos = buttonPosition[0];
		this.rootDirsMenu.style.top = topPos + "px";
		this.rootDirsMenu.style.left = leftPos + "px";
		
		
		// Show and set click handlers
		document.body.appendChild(this.rootDirsMenu);
		this.rootDirsMenuVisible = true;
		var oThis = this;
		var closeHandler = function(){
			window.setTimeout(function(){oThis.toggleRootDirMenu(true)}, 100); 
			Event.stopObserving(document.body,"click", closeHandler); 
			return true;};
		window.setTimeout(function(){ Event.observe(document.body,"click", closeHandler);}, 1);
	}
	else if(this.rootDirsMenuVisible)
	{
		document.body.removeChild(this.rootDirsMenu);
		this.rootDirsMenuVisible = false;
	}
}

Ajaxplorer.prototype.triggerRootDirChange = function(rootDirId)
{
	// CALL CONTENT TO SWITCH ROOT DIR
	// THEN RELOAD EVERYTHING
	// alert(rootDirId);
	//this.foldersTree.setCurrentNodeName(this.foldersTree.getRootNodeId());
	//this.foldersTree.changeNodeLabel(this.foldersTree.getRootNodeId(), this._initRootDirsList[rootDirId]);
	this.actionBar.updateLocationBar('/');
	var connexion = new Connexion();
	connexion.addParameter('get_action', 'switch_root_dir');
	connexion.addParameter('root_dir_index', rootDirId);
	oThis = this;
	connexion.onComplete = function(transport){
		if(oThis.usersEnabled)
		{
			oThis.getLoggedUserFromServer();
		}
		else
		{
			oThis.foldersTree.setCurrentNodeName(oThis.foldersTree.getRootNodeId());
			oThis.foldersTree.changeNodeLabel(oThis.foldersTree.getRootNodeId(), oThis._initRootDirsList[rootDirId]);
			oThis.actionBar.parseXmlMessage(transport.responseXML);
			oThis.foldersTree.reloadCurrentNode();
			oThis.filesList.loadXmlList('/');
			oThis.rootDirId = rootDirId;
			oThis.actionBar.loadBookmarks();
		}
	};
	connexion.sendAsync();
}

Ajaxplorer.prototype.toggleSidePanel = function(srcName)
{	
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
		fitHeightToBottom(this.sEngine._resultsBox, null, 5, true);
	}
	this.currentSideToggle = srcName;
}