AdminPageManager = Class.create({

	initialize: function(){
		this.loadUsers();
		this.loadDrivers();
		this.loadRepList();
		this.loadLogList();
		this.usersPanel = $('users_management');
		this.repoPanel = $('repositories_management');		
		this.logsPanel = $('logs_management');		
		this.toggleSidePanel('users');
		if(Prototype.Browser.IE) $('repo_create_form').setStyle({height:'62px'});
	},
	
	toggleSidePanel: function(srcName){	
		if(srcName == 'users'){
			this.repoPanel.hide();
			$('repositories_header').addClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].hide();
			this.logsPanel.hide();
			$('logs_header').addClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].hide();
			this.usersPanel.show();
			$('users_header').removeClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].show();
		}
		else if(srcName == 'repositories'){
			this.repoPanel.show();
			$('repositories_header').removeClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].show();
			this.usersPanel.hide();
			$('users_header').addClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].hide();			
			this.logsPanel.hide();
			$('logs_header').addClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].hide();
		}
		else if(srcName == 'logs'){
			this.repoPanel.hide();
			$('repositories_header').addClassName("toggleInactive");
			$('repositories_header').getElementsBySelector("img")[0].hide();
			this.usersPanel.hide();
			$('users_header').addClassName("toggleInactive");
			$('users_header').getElementsBySelector("img")[0].hide();			
			this.logsPanel.show();
			$('logs_header').removeClassName("toggleInactive");
			$('logs_header').getElementsBySelector("img")[0].show();
		}
		this.currentSideToggle = srcName;
	},
	
	
	loadUsers: function(){
		var p = new Hash();
		p.set('get_action','users_list');
		this.loadHtmlToDiv($('users_list'), p);	
	},
	
	loadDrivers : function(){
		this.submitForm('drivers_list', new Hash());
	},
	
	updateDriverSelector : function(){
		if(!this.drivers || !$('drivers_selector')) return;
		this.drivers.each(function(pair){
			var option = new Element('option');
			option.setAttribute('value', pair.key);
			option.update(pair.value.get('label'));
			$('drivers_selector').insert({'bottom':option});
		});
		$('drivers_selector').onchange = this.driverSelectorChange.bind(this);
	},
	
	driverSelectorChange : function(){
		var height = (Prototype.Browser.IE?62:32);
		var dName = $('drivers_selector').getValue();
		this.createDriverForm(dName);
		if(dName != "0"){
			var height = 32 + $('driver_form').getHeight() + (Prototype.Browser.IE?15:0);
		}
		
		new Effect.Morph('repo_create_form',{
			style:'height:'+height + 'px',
			duration:0.5
		});		
	},
	
	createDriverForm : function(driverName){
		if(driverName == "0"){
			$('driver_form').update('');
			return;
		}
		var dOpt = this.drivers.get(driverName);
		$('driver_form').update('<div style="padding-top:4px;color:#79f;"><b style="color:#79f;">'+dOpt.get('label') + '</b> : ' + dOpt.get('description')+'<br></div>');
		dOpt.get('params').each(function(param){
			var label = param.get('label');
			var name = param.get('name');
			var type = param.get('type');
			var desc = param.get('description');
			var mandatory = false;
			if(param.get('mandatory') && param.get('mandatory')=='true') mandatory = true;
			var defaultValue = param.get('default') || "";
			var element;
			if(type == 'string'){
				element = '<input type="text" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" class="text" value="'+defaultValue+'">';
			}else if(type == 'boolean'){
				var selectTrue, selectFalse;
				if(defaultValue){
					if(defaultValue == "true") selectTrue = true;
					if(defaultValue == "false") selectFalse = true;
				}
				element = '<input type="radio" class="radio" name="'+name+'" value="true" '+(selectTrue?'checked':'')+'> Yes';
				element = element + '<input type="radio" class="radio" name="'+name+'" '+(selectFalse?'checked':'')+' value="false"> No';
			}
			var div = new Element('div', {style:"padding:2px; clear:left"}).update('<div style="float:left; width:20%;text-align:right;"><b>'+label+(mandatory?'*':'')+'</b>&nbsp;:&nbsp;</div><div style="float:left;width:80%">'+element+' &nbsp;<small style="color:#AAA;">'+desc+'</small></div>');
			$('driver_form').insert({'bottom':div});
		});
		var buttons = '<div align="center" style="clear:left;padding-top:5px;"><input type="button" value="Save" class="button" onclick="return manager.repoButtonClick(true);"> <input type="button" value="Cancel" class="button" onclick="return manager.repoButtonClick(false);"></div>';
		$('driver_form').insert({'bottom':buttons});
	},
	
	repoButtonClick  : function(validate){
		if(!validate) {
			$('driver_label').value = '';
			$('drivers_selector').selectedIndex = 0;
			this.driverSelectorChange();
			return false;		
		}
		var toSubmit = new Hash();
		var missingMandatory = false;
		if($('driver_label').value == ''){
			missingMandatory = true;
		}else{
			toSubmit.set('DISPLAY', $('driver_label').value);
		}
		toSubmit.set('DRIVER', $('drivers_selector').options[$('drivers_selector').selectedIndex].value);
		
		$('driver_form').select('input').each(function(el){			
			if(el.type == "text"){
				if(el.getAttribute('ajxp_mandatory') == 'true' && el.value == ''){
					missingMandatory = true;
				}
				toSubmit.set('DRIVER_OPTION_'+el.name, el.value);				
			}
			else if(el.type=="radio" && el.checked){
				toSubmit.set('DRIVER_OPTION_'+el.name, el.value)
			};
		});
		if(missingMandatory){
			this.displayMessage("ERROR", "Mandatory fields are missing!");
			return false;
		}		
		this.submitForm('create_repository', toSubmit, null, function(){
			this.repoButtonClick(false);
			this.loadRepList();
			this.loadUsers();
		}.bind(this));
		return false;		
	},
	
	loadRepList : function(){
		this.submitForm('repository_list', new Hash());
	},
	
	updateRepList : function(){
		if(!this.repositories) return;
		$('repo_list').update('');
		this.repositories.each(function(pair){
			var deleteButton = '';
			if(pair.value){
				deleteButton = '<img src="'+ajxpResourcesFolder+'/images/crystal/actions/16/button_cancel.png" width="16" height="16" onclick="manager.deleteRepository(\''+pair.key+'\');return false;" style="cursor:pointer;margin-left: 20px;">';
			}
			$('repo_list').insert({"bottom":'<div class="user user_id" style="cursor:default; height:25px; padding-top:0px;"><img align="absmiddle" src="'+ajxpResourcesFolder+'/images/crystal/actions/32/folder_red.png" width="32" height="32" style="padding:5px;">Repository <b>'+pair.key+'</b>'+deleteButton+'</div>'});
		});
	},
	
	deleteRepository : function(repLabel){
		var params = new Hash();
		params.set('repo_label', repLabel);
		this.submitForm('delete_repository', params, null, function(){
			this.loadRepList();
			this.loadUsers();
		}.bind(this));
	},
	
	loadLogList : function(){
		this.submitForm('list_logs', new Hash());
	},
	
	updateLogsSelector : function(){
		var selector = $('log_selector');
		if(!this.logFiles || !selector) return;
		this.logFiles.each(function(pair){
			var option = new Element('option');
			option.setAttribute('value', pair.key);
			option.update(pair.value);
			selector.insert({'top':option});
		});
		selector.onchange = this.logSelectorChange.bind(this);
		// Select first
		selector.selectedIndex = 0;
		this.logSelectorChange();
		
	},
	
	logSelectorChange : function(){
		if($('log_selector').getValue()) this.loadLogs($('log_selector').getValue());
	},
	
	loadLogs : function(date){
		var param = new Hash();
		param.set('date', date);
		this.submitForm('read_log', param, null, this.updateLogBrowser.bind(this));
	},
	
	updateLogBrowser : function(xmlResponse){
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		browser = $('log_browser');
		var childs = xmlResponse.documentElement.childNodes;
		this.even = true;
		var table = new Element('table', {width:'100%', className:'logs_table',cellPadding:'0',cellSpacing:'1'});
		browser.update(table);
		this.insertRow(table, ["Date","IP","Level","User", "Action", "Parameters"], true);
		for(var i=0;i<childs.length;i++){
			var child = childs[i];
			this.insertRow(table, [
				child.getAttribute("date"),
				child.getAttribute("ip"),
				child.getAttribute("level"),
				child.getAttribute("user"),
				child.getAttribute("action"),
				child.getAttribute("params"),
			], false);			
			if(i>1 && i%8==0){
				this.insertRow(table, ["Date","IP","Level","User", "Action", "Parameters"], true);
			}
		}
		var transp = new Element('div');
		browser.insert({'bottom':transp});
		browser.scrollTop = transp.offsetTop;
	},
	
	insertRow : function(table, values, isHeader){		
		var tdSt = '<tr>';
		var className="";
		if(!isHeader && !this.even){className="odd"};
		this.even = !this.even;
		values.each(function(cell){
			if(cell){
			while(cell.indexOf(';')>-1) cell = cell.replace(';', '<br>');
			while(cell.indexOf(',')>-1) cell = cell.replace(',', '<br>');
			tdSt = tdSt + '<td class="'+(isHeader?'header':className)+'">'+cell+'</td>';
			}
		});
		tsSt = tdSt+'</tr>';
		table.insert({'bottom':tdSt});
	},
	
	changeUserRight: function(oChckBox, userId, repositoryId, rightName){	
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
				
		var parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('repository_id', repositoryId);
		parameters.set('right', rightString);
		this.submitForm('update_user_right', parameters, null);
	},
	
	changePassword: function(userId){
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
	},
	
	createUser: function (){
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
		
	},
	
	deleteUser: function(userId){
		var chck = $('delete_confirm_'+userId);
		if(!chck.checked){
			this.displayMessage("ERROR", "Please check the box to confirm!");
			return;
		}
		parameters = new Hash();
		parameters.set('user_id', userId);
		this.submitForm('delete_user', parameters, null);
		chck.checked = false;
	},
	
	toggleUser: function (userId){
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
	},
	
	submitForm: function(action, parameters, formName, callback){
		var connexion = new Connexion('admin.php');
		if(formName)
		{
			$(formName).getElements().each(function(fElement){
				connexion.addParameter(fElement.name, fElement.getValue());
			});	
		}
		if(parameters)
		{
			parameters.set('get_action', action);
			connexion.setParameters(parameters);
		}
		if(!callback){
			connexion.onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this);
		}else{
			connexion.onComplete = function(transport){
				this.parseXmlMessage(transport.responseXML);
				callback(transport.responseXML);
			}.bind(this);
		}
		connexion.sendAsync();
	},
	
	loadHtmlToDiv: function(div, parameters, completeFunc){
		var connexion = new Connexion('admin.php');
		parameters.each(function(pair){
			connexion.addParameter(pair.key, pair.value);
		});
		connexion.onComplete = function(transport){		
			$(div).update(transport.responseText);
			if(completeFunc) completeFunc();
		};
		connexion.sendAsync();	
	},
	
	
	parseXmlMessage: function(xmlResponse){
		//var messageBox = ajaxplorer.messageBox;
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		var driversList = false;
		var driversAtts = $A(['name', 'type', 'label', 'description', 'default', 'mandatory']);
		var repList = false;
		var logFilesList = false;
		var logsList = false;
		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].nodeName == "message")
			{
				this.displayMessage(childs[i].getAttribute('type'), childs[i].firstChild.nodeValue);
				//alert(childs[i].firstChild.nodeValue);
			}
			else if(childs[i].nodeName == "update_checkboxes")
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
			else if(childs[i].nodeName == "refresh_user_list")
			{
				this.loadUsers();
			}
			else if(childs[i].nodeName == "ajxpdriver")
			{
				driversList = true;
				if(!this.drivers) this.drivers = new Hash();
				var dOption = new Hash();
				var dName = childs[i].getAttribute('name');
				dOption.set('label', childs[i].getAttribute('label'));
				dOption.set('description', childs[i].getAttribute('description'));
				var params = $A([]);
				var dChilds = childs[i].childNodes;
				for(var j=0;j<dChilds.length;j++){
					var paramProp = new Hash();
					driversAtts.each(function(attName){
						paramProp.set(attName, (dChilds[j].getAttribute(attName) || ''));
					});
					params.push(paramProp);
				}
				dOption.set('params', params);
				this.drivers.set(dName, dOption);
			}
			else if(childs[i].nodeName == "repository")
			{
				if(!this.repositories || !repList) this.repositories = new Hash();
				repList = true;
				this.repositories.set(childs[i].getAttribute('display'), childs[i].getAttribute('writeable'));
			}
			else if(childs[i].nodeName == "file"){
				if(!this.logFiles) this.logFiles = new Hash();
				logFilesList = true;
				this.logFiles.set(childs[i].getAttribute('date'), childs[i].getAttribute('display'));
			}
		}
		if(driversList){
			this.updateDriverSelector();
		}
		if(repList){
			this.updateRepList();
		}
		if(logFilesList){
			this.updateLogsSelector();
		}
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
		setTimeout(function(){this.closeMessageDiv();}.bind(this), 3000);
	},
	
	displayMessage: function(messageType, message){
		this.messageBox = $('message_div');
		message = message.replace(new RegExp("(\\n)", "g"), "<br>");
		if(messageType == "ERROR"){ this.messageBox.removeClassName('logMessage');  this.messageBox.addClassName('errorMessage');}
		else { this.messageBox.removeClassName('errorMessage');  this.messageBox.addClassName('logMessage');}
		$('message_content').innerHTML = message;
		this.messageBox.style.top = '80%';
		this.messageBox.style.left = '60%';
		this.messageBox.style.width = '30%';
		new Effect.Corner(this.messageBox,"round");
		new Effect.Appear(this.messageBox);
		this.tempoMessageDivClosing();
	}
});