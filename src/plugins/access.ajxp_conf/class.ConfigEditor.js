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
 * Description : A fully functionnal manager for the whole "Admin" driver
 */
ConfigEditor = Class.create({

	initialize: function(oForm){
		this.form = oForm;
		this.userId = 0;
	},
	
	
	
	/*************************************/
	/*       USERS FUNCTIONS             */
	/*************************************/			
	loadUser: function(userId){
		this.userId = userId;
		var params = new Hash();
		params.set("get_action", "edit_user");
		params.set("user_id", userId);
		var connexion = new Connexion();
		connexion.setParameters(params);
		connexion.onComplete = function(transport){
			this.feedUserForm(transport.responseXML);			
			modal.refreshDialogPosition();
			modal.refreshDialogAppearance();
		}.bind(this);
		connexion.sendAsync();		
	},	
	
	feedUserForm : function(xmlData){
		
		//window.XMLDATA = xmlData;
		
		
		var editPass = XPathGetSingleNodeText(xmlData, "admin_data/edit_options/@edit_pass")=="1";
		var editAdminRight = XPathGetSingleNodeText(xmlData, "admin_data/edit_options/@edit_admin_right")=="1";
		var editDelete = XPathGetSingleNodeText(xmlData, "admin_data/edit_options/@edit_delete")=="1";
		var adminStatus = (XPathGetSingleNodeText(xmlData, "admin_data/user/special_rights/@is_admin") == "1");		
						
		var rightsPane = this.form.select('[id="rights_pane"]')[0];
		var rightsTable = rightsPane.select('table')[0];		
		var repositories = XPathSelectNodes(xmlData, "//repo");				
		for(var i=0;i<repositories.length;i++){
			var repoNode = repositories[i];
			var repoLabel = XPathGetSingleNodeText(repoNode, "label");
			var repoId = XPathGetSingleNodeText(repoNode, "@id");
			var accessType = XPathGetSingleNodeText(repoNode, "@access_type");
			
			var readBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_read'}).setStyle({width:'25px'});
			var writeBox = new Element('input', {type:'checkbox', id:'chck_'+repoId+'_write'}).setStyle({width:'25px'});			
			readBox.checked = (XPathGetSingleNodeText(repoNode, "@r")=='1');
			writeBox.checked = (XPathGetSingleNodeText(repoNode, "@w")=='1');			
			readBox.observe('click', this.changeUserRight.bind(this));
			writeBox.observe('click', this.changeUserRight.bind(this));
			
			var rightsCell = new Element('td', {width:'55%'});
			rightsCell.insert("Read ");
			rightsCell.insert(readBox);
			rightsCell.insert("Write ");
			rightsCell.insert(writeBox);
			var tr = new Element('tr');
			var titleCell = new Element('td', {width:'45%'}).update(repoLabel);
			tr.insert(titleCell);
			tr.insert(rightsCell);
			rightsTable.insert({"bottom":tr});
			
			var walletParams = XPathSelectNodes(xmlData, "admin_data/drivers/ajxpdriver[@name='"+accessType+"']/user_param");				
			var walletValues = XPathSelectNodes(xmlData, "admin_data/user_wallet/wallet_data[@repo_id='"+repoId+"']");			
			if(walletParams.length){
				var walletCell = new Element("td", {colspan:"2"});
				var newRow = new Element("tr");
				newRow.insert(walletCell);
				rightsTable.insert({"bottom":newRow});				
				var walletPane = new Element('div', {style:"border:1px solid #ccc;", id:"wallet_pane_"+repoId});
				walletCell.insert({bottom:walletPane});				
				this.addRepositoryUserParams(walletPane, repoId, walletParams, walletValues);
				walletPane.hide();
				var image = new Element("img", {src:ajxpResourcesFolder+"/images/0.gif"}).setStyle({marginRight:3});
				image.setStyle({cursor:'pointer'});
				image.setAttribute("pane_id", repoId);
				image.observe("click", function(event){
					var img = Event.element(event);
					var pane = $('wallet_pane_'+img.getAttribute("pane_id"));
					pane.toggle();
					img.src = (pane.visible()?ajxpResourcesFolder+"/images/1.gif":ajxpResourcesFolder+"/images/0.gif");
				});
				titleCell.insert({top:image});
			}else{
				titleCell.setStyle({paddingLeft:12});
			}
			
		}
				
		rightsTable.select('[id="loading_row"]')[0].remove();		
				
		var passwordPane = this.form.select('[id="password_pane"]')[0];
		if(!editPass){
			passwordPane.hide();
		}else{
			var passButton = passwordPane.select('input[type="submit"]')[0];
			passButton.observe('click', this.changePassword.bind(this));
		}
		
		if(!editAdminRight){
			this.form.select('[id="admin_right_pane"]')[0].hide();
		}else{
			var adminButton = this.form.select('[id="admin_rights"]')[0];
			adminButton.checked = adminStatus;
			adminButton.observe('click', function(){
				this.changeAdminRight(adminButton);
			}.bind(this));			
		}
		
		var deletePane = this.form.select('[id="delete_user_pane"]')[0];
		if(!editDelete){
			deletePane.hide();
		}else{
			var deleteButton = deletePane.select('input[type="submit"]')[0];
			deleteButton.observe('click', this.deleteUser.bind(this));
		}				
	},
	
	
	addRepositoryUserParams : function(walletPane, repoId, walletParams, walletValues){
		var repoParams = $A([]);
		walletParams.each(function(walletParam){
			repoParams.push(this.driverParamNodeToHash(walletParam));
		}.bind(this) );
		
		var userId = this.userId;		
		var newTd = new Element('div', {className:'driver_form', id:'repo_user_params_'+userId+'_'+repoId});
		walletPane.insert(newTd);
		var repoValues = $H({});
		walletValues.each(function(tag){
			repoValues.set(tag.getAttribute('option_name'), tag.getAttribute('option_value'));
		});
		this.createParametersInputs(newTd, repoParams, false, repoValues);
		var submitButton = new Element('input', {type:'submit', value:'SAVE', className:'submit', onClick:'return false;'}).setStyle({padding:0,width:30});
		submitButton.observe("click", function(){
			this.submitUserParamsForm(userId, repoId);
		}.bind(this));
		newTd.insert({bottom:submitButton});
	},
	
	submitUserParamsForm : function(userId, repositoryId){
		var parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('repository_id', repositoryId);
		if(this.submitParametersInputs($('repo_user_params_'+userId+'_'+repositoryId), parameters, "DRIVER_OPTION_")){
			this.displayMessage("ERROR", "Mandatory fields are missing!");
			return false;
		}
		this.submitForm("edit_user", 'save_repository_user_params', parameters, null);
	},
	
		
	changeUserRight: function(event){	
		var oChckBox = Event.element(event);
		var parts = oChckBox.id.split('_');		
		var repositoryId = parts[1];
		var rightName = parts[2];
		var userId = this.userId;
		
		var newState = oChckBox.checked;
		oChckBox.checked = !oChckBox.checked;
		oChckBox.disabled = true;		
		var rightString;
		
		if(rightName == 'read') 
		{
			$('chck_'+repositoryId+'_write').disabled = true;
			rightString = (newState?'r':'');
		}
		else 
		{
			$('chck_'+repositoryId+'_read').disabled = true;
			rightString = (newState?'rw':($('chck_'+repositoryId+'_read').checked?'r':''));
		}
				
		var parameters = new Hash();
		parameters.set('user_id', userId);
		parameters.set('repository_id', repositoryId);
		parameters.set('right', rightString);
		this.submitForm("edit_user", 'update_user_right', parameters, null);
	},
	
	changeAdminRight: function(oChckBox){
		var boxValue = oChckBox.checked;
		var parameters = new Hash();
		parameters.set('user_id', this.userId);
		parameters.set('right_value', (boxValue?'1':'0'));
		this.submitForm("edit_user", 'change_admin_right', parameters, null);
	},
	
	changePassword: function(){
		var newPass = $('new_pass');
		var newPassConf = $('new_pass_confirm');
		if(newPass.value == '') return;
		if(newPass.value != newPassConf.value){
			 this.displayMessage('ERROR', 'Warning, password and confirmation differ!');
			 return;
		}
		// First get a seed to check whether the pass should be encoded or not.
		var sync = new Connexion();
		var seed;
		sync.addParameter('get_action', 'get_seed');
		sync.onComplete = function(transport){
			seed = transport.responseText;			
		}		
		sync.sendSync();
		parameters = new Hash();
		parameters.set('user_id', this.userId);
		if(seed != '-1'){
			parameters.set('user_pwd', hex_md5(newPass.value));
		}else{
			parameters.set('user_pwd', newPass.value);
		}
		this.submitForm("edit_user", 'update_user_pwd', parameters, null);
		newPass.value = '';
		newPassConf.value = '';
	},

	deleteUser: function(){
		var chck = this.form.select('[id="delete_confirm"]')[0];
		if(!chck.checked){
			this.displayMessage("ERROR", "Please check the box to confirm!");
			return;
		}
		parameters = new Hash();
		parameters.set('user_id', this.userId);
		this.submitForm("edit_user", 'delete_user', parameters, null);
		chck.checked = false;
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
		this.submitForm("edit_user", 'create_user', parameters, null);
		login.value = pass.value = passConf.value = '';
		return;
		
	},
		

	/*************************************/
	/*       REPOSITORIES FUNCTIONS      */
	/*************************************/
	initCreateRepoWizard : function(){
		this.newRepoLabelInput = this.form.select('input[type="text"]')[0];
		this.driverSelector = this.form.select('select')[0];
		this.driverForm = this.form.select('div[id="driver_form"]')[0];
		this.repoSubmitButton = this.form.select('input[id="submit_create_repo"]')[0];
		this.repoSubmitButton.observe("click", function(){
			this.repoButtonClick(true);
		}.bind(this));
		this.drivers = new Hash();
		this.submitForm('create_repository', 'get_drivers_definition', new Hash(), null, function(xmlData){
			console.log(xmlData);
			var driverNodes = XPathSelectNodes(xmlData, "ajxpdriver");
			driversNodes.each(function(driver){
				var driverDef = new Hash();
				var driverLabel = XPathGetSingleNodeText(driver, "@label");
				var driverParams = XPathSelectNodes(driver, "param");
				driverDef.set('label', driverLabel);
				var driverParamsArray = new Array();
				driverParams.each(function(paramNode){
					driverParamsArray.push(this.driverParamNodeToHash(paramNode));
				});
				driverDef.set('params', driverParamsArray);
				this.drivers.set(driverLabel, driverDef);
			}); 
			this.updateDriverSelector();
		});
	},
	
	updateDriverSelector : function(){		
		if(!this.drivers || !this.driverSelector) return;
		this.driverSelector.update('<option value="0"></option>');
		this.drivers.each(function(pair){
			var option = new Element('option');
			option.setAttribute('value', pair.key);
			option.update(pair.value.get('label'));
			this.driverSelector.insert({'bottom':option});
		}.bind(this) );
		this.driverSelector.onchange = this.driverSelectorChange.bind(this);
	},
	
	driverSelectorChange : function(){
		var height = (Prototype.Browser.IE?62:32);
		var dName = this.driverSelector.getValue();
		this.createDriverForm(dName);
		if(dName != "0"){
			var height = 100 + this.driverForm.getHeight() + (Prototype.Browser.IE?15:0);
			if(height > 400) height=400;
		}
		new Effect.Morph(this.driverForm.up('div'),{
			style:'height:'+height + 'px',
			duration:0.3, 
			afterFinish : function(){
				modal.refreshDialogPosition();
				modal.refreshDialogAppearance();			
			}
		});		
	},
	
	createDriverForm : function(driverName){
		if(driverName == "0"){
			this.driverForm.update('');
			return;
		}
		var dOpt = this.drivers.get(driverName);
		this.driverForm.update('<div style="padding-top:4px;color:#79f;"><b style="color:#79f;">'+dOpt.get('label') + '</b> : ' + dOpt.get('description')+'<br></div>');
		this.createParametersInputs(this.driverForm, dOpt.get('params'), false);
		//var buttons = '';
		//this.driverForm.insert({'bottom':buttons});
	},
	
	repoButtonClick  : function(validate){
		if(!validate) {
			this.newRepoLabelInput.value = '';
			this.driverSelector.selectedIndex = 0;
			this.driverSelectorChange();
			return false;		
		}
		var toSubmit = new Hash();
		var missingMandatory = false;
		if(this.newRepoLabelInput.value == ''){
			missingMandatory = true;
		}else{
			toSubmit.set('DISPLAY', this.newRepoLabelInput.value);
		}
		toSubmit.set('DRIVER', this.driverSelector.options[this.driverSelector.selectedIndex].value);
		
		if(missingMandatory || this.submitParametersInputs(this.driverForm, toSubmit, 'DRIVER_OPTION_')){
			this.displayMessage("ERROR", "Mandatory fields are missing!");
			return false;
		}		
		this.submitForm('create_repository', '', toSubmit, null, function(){
			//this.repoButtonClick(false);
			//this.loadRepList();
			//this.loadUsers();
			hideLightBox();			
		}.bind(this));
		return false;		
	},
	
	loadRepository : function(repId){
		var params = new Hash();
		params.set("get_action", "edit_repository");
		params.set("repository_id", repId);
		var connexion = new Connexion();
		connexion.setParameters(params);
		connexion.onComplete = function(transport){
			this.feedRepositoryForm(transport.responseXML);			
			modal.refreshDialogPosition();
			modal.refreshDialogAppearance();
		}.bind(this);
		connexion.sendAsync();		
	},

	feedRepositoryForm: function(xmlData){
		
		var repo = XPathSelectSingleNode(xmlData, "admin_data/repository");
		var driverParams = XPathSelectNodes(xmlData, "admin_data/ajxpdriver/param");
		var optionsPane = this.form.select('[id="options_pane"]')[0];		
			
		var driverParamsHash = $A([]);
		driverParams.each(function(param){
			driverParamsHash.push(this.driverParamNodeToHash(param));
		}.bind(this));		
				
		var form = new Element('div', {className:'driver_form'});
		optionsPane.update(new Element('legend').update(XPathGetSingleNodeText(xmlData, "admin_data/ajxpdriver/@name").toUpperCase()+' Driver Options'));
		optionsPane.insert({bottom:form});
		
		var paramsValues = new Hash();
		$A(repo.childNodes).each(function(child){
			if(child.nodeName != 'param') return;
			paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
		});
		var writeable = repo.getAttribute("writeable");			
		this.createParametersInputs(form, driverParamsHash, false, paramsValues, !writeable);

		if(writeable){
			var submitButton = new Element("input", {type:"button",value:"SAVE CHANGES"});
			submitButton.observe("click", function(e){
				var toSubmit = new Hash();
				toSubmit.set("repository_id", repo.getAttribute("index"));
				this.submitParametersInputs(form, toSubmit, 'DRIVER_OPTION_');
				this.submitForm('edit_repository', 'edit_repository_data', toSubmit, null, function(){
					this.loadRepList();
					this.loadUsers();
				}.bind(this));			
			}.bind(this));
			optionsPane.insert({bottom:new Element('div', {align:'right'}).update(submitButton)});
		}
		
		
				
		var labelPane = this.form.select('[id="label_pane"]')[0];
		var deleteRepoPane = this.form.select('[id="delete_repo_pane"]')[0];
		if(!writeable || writeable != "1"){
			labelPane.hide();
			deleteRepoPane.hide();
		}else{
			var repoId = XPathGetSingleNodeText(repo, "@index");
			var repoLabel = XPathGetSingleNodeText(repo, "@display");
			
			labelInput = labelPane.select('input[type="text"]')[0]; 
			labelInput.value = repoLabel;
			labelSave = labelPane.select('input[type="button"]')[0];
			labelSave.observe("click", function(){
				this.submitForm('edit_repository', 'edit_repository_label', new Hash({repository_id:repoId,newLabel:labelInput.getValue()}), null, function(){
					//this.loadRepList();
					//this.loadUsers();
				}.bind(this) );
			}.bind(this));
			
			var deleteBox = deleteRepoPane.select('input[type="checkbox"]')[0]; 
			var deleteButton = deleteRepoPane.select('input[type="button"]')[0]; 		
			console.log(deleteBox, deleteButton);
			deleteButton.observe('click', function(){
				if(!deleteBox.checked) {
					alert("Please check the box to confirm!");
					return;
				}
				this.deleteRepository(repoId);
			}.bind(this));
		}
	},
	
	deleteRepository : function(repId){
		var params = new Hash();
		params.set('repository_id', repId);
		this.submitForm('edit_repository', 'delete_repository', params, null, function(){
			
		}.bind(this));
	},
		
	/*************************************/
	/*       COMMON FUNCTIONS            */
	/*************************************/	
	driverParamNodeToHash : function(driverNode){
		var driversAtts = $A(['name', 'type', 'label', 'description', 'default', 'mandatory']);
		var driverHash = new Hash();
		driversAtts.each(function(attName){
			driverHash.set(attName, (XPathGetSingleNodeText(driverNode, '@'+attName) || ''));
		});
		return driverHash;
	},	
	
	createParametersInputs : function(form, parametersDefinitions, showTip, values, disabled){
		parametersDefinitions.each(function(param){		
			var label = param.get('label');
			var name = param.get('name');
			var type = param.get('type');
			var desc = param.get('description');
			var mandatory = false;
			if(param.get('mandatory') && param.get('mandatory')=='true') mandatory = true;
			var defaultValue = (values?'':(param.get('default') || ""));
			if(values && values.get(name)){
				defaultValue = values.get(name);
			}
			var element;
			var disabledString = (disabled?' disabled="true" ':'');
			if(type == 'string'){
				element = '<input type="text" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" class="text" value="'+defaultValue+'"'+disabledString+'>';
		    }else if(type == 'password'){
				element = '<input type="password" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'##" class="text" value="'+defaultValue+'"'+disabledString+'>';
			}else if(type == 'boolean'){
				var selectTrue, selectFalse;
				if(defaultValue){
					if(defaultValue == "true" || defaultValue == "1") selectTrue = true;
					if(defaultValue == "false" || defaultValue == "0") selectFalse = true;
				}
				element = '<input type="radio" class="radio" name="'+name+'" value="true" '+(selectTrue?'checked':'')+''+disabledString+'> Yes';
				element = element + '<input type="radio" class="radio" name="'+name+'" '+(selectFalse?'checked':'')+' value="false"'+disabledString+'> No';
			}
			var div = new Element('div', {style:"padding:2px; clear:left"}).update('<div style="float:left; width:30%;text-align:right;"><b>'+label+(mandatory?'*':'')+'</b>&nbsp;:&nbsp;</div><div style="float:left;width:70%">'+element+(showTip?' &nbsp;<small style="color:#AAA;">'+desc+'</small>':' <img src="'+ajxpResourcesFolder+'/images/crystal/actions/16/help-about.png" alt="'+desc+'"  title="'+desc+'" width="16" height="16" align="absmiddle" class="helpImage"/>')+'</div>');
			form.insert({'bottom':div});
		});
	},
	
	submitParametersInputs : function(form, parametersHash, prefix){
		prefix = prefix || '';
		var missingMandatory = false;
		form.select('input').each(function(el){			
			if(el.type == "text" || el.type == "password"){
				if(el.getAttribute('ajxp_mandatory') == 'true' && el.value == ''){
					missingMandatory = true;
				}
				parametersHash.set(prefix+el.name, el.value);				
			}
			else if(el.type=="radio" && el.checked){
				parametersHash.set(prefix+el.name, el.value)
			};			
		});		
		return missingMandatory;
	},	
	
	submitForm: function(mainAction, action, parameters, formName, callback){
		//var connexion = new Connexion('admin.php');
		var connexion = new Connexion();
		if(formName)
		{
			$(formName).getElements().each(function(fElement){
				connexion.addParameter(fElement.name, fElement.getValue());
			});	
		}
		if(parameters)
		{
			parameters.set('get_action', mainAction);
			parameters.set('sub_action', action);
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
		var connexion = new Connexion();
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
				if(read != 'old') $('chck_'+repositoryId+'_read').checked = (read=='1'?true:false);
				$('chck_'+repositoryId+'_read').disabled = false;
				if(write != 'old') $('chck_'+repositoryId+'_write').checked = (write=='1'?true:false);
				$('chck_'+repositoryId+'_write').disabled = false;
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
				var userParams = $A([]);
				var dChilds = childs[i].childNodes;
				for(var j=0;j<dChilds.length;j++){
					var childNodeName = dChilds[j].nodeName;
					if(childNodeName == 'param' || childNodeName == 'user_param'){
						var paramProp = new Hash();
						driversAtts.each(function(attName){
							paramProp.set(attName, (dChilds[j].getAttribute(attName) || ''));
						});
						if(childNodeName == 'param') params.push(paramProp);
						else userParams.push(paramProp);
					}
				}
				dOption.set('params', params);
				dOption.set('user_params', userParams);
				this.drivers.set(dName, dOption);
			}
			else if(childs[i].nodeName == "repository")
			{
				if(!this.repositories || !repList) this.repositories = new Hash();
				repList = true;
				this.repositories.set(childs[i].getAttribute('index'), childs[i]);
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
