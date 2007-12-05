function AdminPageManager()
{
	this.loadUsers();
}

AdminPageManager.prototype.loadUsers = function()
{
	var p = new Hash();
	p.set('get_action','users_list');
	this.loadHtmlToDiv($('users_list'), p);	
}

AdminPageManager.prototype.changeUserRight = function(oChckBox, userId, repositoryId, rightName)
{	
	var changedBox = rightName;
	var newState = oChckBox.checked;
	oChckBox.checked = !oChckBox.checked;
	oChckBox.disabled = true;
	
	var rightString;
	
	if(rightName == 'read') 
	{
		$('chck_'+userId+'_'+repositoryId+'_write').disabled = true;
		rightString = (newState?'r':'');
	}
	else 
	{
		$('chck_'+userId+'_'+repositoryId+'_read').disabled = true;
		rightString = (newState?'rw':($('chck_'+userId+'_'+repositoryId+'_read').checked?'r':''));
	}
	
	var oThis = this;
	var parameters = new Hash();
	parameters.set('user_id', userId);
	parameters.set('repository_id', repositoryId);
	parameters.set('right', rightString);
	this.submitForm('update_user_right', parameters, null);
}

AdminPageManager.prototype.changePassword = function(userId)
{
	var newPass = $('new_pass_'+userId);
	var newPassConf = $('new_pass_confirm_'+userId);
	if(newPass.value == '') return;
	if(newPass.value != newPassConf.value){
		 this.displayMessage('ERROR', 'Warning, password and confirmation differ!');
		 return;
	}
	parameters = new Hash();
	parameters.set('user_id', userId);
	parameters.set('user_pwd', newPass.value);
	this.submitForm('update_user_pwd', parameters, null);
	newPass.value = '';
	newPassConf.value = '';
}

AdminPageManager.prototype.createUser = function ()
{
	var login = $('new_user_login');
	var pass = $('new_user_pwd');
	var passConf = $('new_user_pwd_conf');
	if(login.value == ''){
		this.displayMessage("ERROR", "Please fill the login field!");
		return;
	}
	if(pass.value == '' || passConf.value == ''){
		this.displayMessage("ERROR", "Please fill both password fields!");
		return;
	}
	if(pass.value != passConf.value){
		this.displayMessage("ERROR", "Password and confirmation differ!");
		return;
	}
	
	var parameters = new Hash();
	parameters.set('new_login', login.value);
	parameters.set('new_pwd', pass.value);
	this.submitForm('create_user', parameters, null);
	login.value = pass.value = passConf.value = '';
	return;
	
}

AdminPageManager.prototype.deleteUser = function(userId)
{
	var chck = $('delete_confirm_'+userId);
	if(!chck.checked){
		this.displayMessage("ERROR", "Please check the box to confirm!");
		return;
	}
	parameters = new Hash();
	parameters.set('user_id', userId);
	this.submitForm('delete_user', parameters, null);
	chck.checked = false;
}

AdminPageManager.prototype.toggleUser = function (userId)
{
	var color;
	if($('user_data_'+userId).visible())
	{
		// closing
		color = "#ddd";
	}
	else
	{
		// opening
		$$('div.user').each(function(element){
			element.setStyle({backgroundColor:"#ddd"});
		});
		$$('div.user_data').each(function(element){
			element.hide();
		});
		color = "#fff";
	}
	$('user_block_'+userId).setStyle({backgroundColor:color});
	$('user_data_'+userId).toggle();	
}

AdminPageManager.prototype.submitForm = function(action, parameters, formName)
{
	var connexion = new Connexion('admin.php');
	if(formName)
	{
		$(formName).getElements().each(function(fElement){
			connexion.addParameter(fElement.name, fElement.getValue());
		});	
	}
	if(parameters)
	{
		parameters['get_action'] = action;
		connexion.setParameters(parameters);
	}
	oThis = this;
	connexion.onComplete = function(transport){oThis.parseXmlMessage(transport.responseXML);};
	connexion.sendAsync();
}

AdminPageManager.prototype.loadHtmlToDiv = function(div, parameters, completeFunc)
{
	var connexion = new Connexion('admin.php');
	parameters.each(function(pair){
		connexion.addParameter(pair.key, pair.value);
	});
	connexion.onComplete = function(transport){		
		div.innerHTML = transport.responseText;
		if(completeFunc) completeFunc();
	};
	connexion.sendAsync();	
}


AdminPageManager.prototype.parseXmlMessage = function(xmlResponse)
{
	//var messageBox = ajaxplorer.messageBox;
	if(xmlResponse == null || xmlResponse.documentElement == null) return;
	var childs = xmlResponse.documentElement.childNodes;	
	
	for(var i=0; i<childs.length;i++)
	{
		if(childs[i].tagName == "message")
		{
			this.displayMessage(childs[i].getAttribute('type'), childs[i].firstChild.nodeValue);
			//alert(childs[i].firstChild.nodeValue);
		}
		else if(childs[i].tagName == "update_checkboxes")
		{
			var userId = childs[i].getAttribute('user_id');
			var repositoryId = childs[i].getAttribute('repository_id');
			var read = childs[i].getAttribute('read');
			var write = childs[i].getAttribute('write');
			if(read != 'old') $('chck_'+userId+'_'+repositoryId+'_read').checked = (read=='1'?true:false);
			$('chck_'+userId+'_'+repositoryId+'_read').disabled = false;
			if(write != 'old') $('chck_'+userId+'_'+repositoryId+'_write').checked = (write=='1'?true:false);
			$('chck_'+userId+'_'+repositoryId+'_write').disabled = false;
		}
		else if(childs[i].tagName == "refresh_user_list")
		{
			this.loadUsers();
		}
	}
}

AdminPageManager.prototype.closeMessageDiv = function()
{
	if(this.messageDivOpen)
	{
		new Effect.Fade(this.messageBox);
		this.messageDivOpen = false;
	}
}

AdminPageManager.prototype.tempoMessageDivClosing = function()
{
	this.messageDivOpen = true;
	setTimeout('manager.closeMessageDiv()', 3000);
}

AdminPageManager.prototype.displayMessage = function(messageType, message)
{
	this.messageBox = $('message_div');
	message = message.replace(new RegExp("(\\n)", "g"), "<br>");
	if(messageType == "ERROR"){ this.messageBox.removeClassName('logMessage');  this.messageBox.addClassName('errorMessage');}
	else { this.messageBox.removeClassName('errorMessage');  this.messageBox.addClassName('logMessage');}
	$('message_content').innerHTML = message;
	this.messageBox.style.top = '80%';
	this.messageBox.style.left = '60%';
	this.messageBox.style.width = '30%';
	jQuery(this.messageBox).corner("round");
	new Effect.Appear(this.messageBox);
	this.tempoMessageDivClosing();
}
