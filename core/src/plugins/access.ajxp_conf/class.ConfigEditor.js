/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

/**
 * @package info.ajaxplorer.plugins
 * @class ConfigEditor
 * Configurations editor
 */
Class.create("ConfigEditor",{

	formManager:null,
	
	initialize: function(oForm){
		if(oForm) this.form = oForm;
		this.formManager = new FormManager();
	},
	
	setForm : function(oForm){
		this.form = oForm;
	},	

	
	loadUsers : function(selection){	
		this.roleId = null;
		this.userId = null;
			
//		this.form.down('#rights_pane').remove();
//		this.form.down('#rights_legend').remove();
		this.form.down('#roles_pane').select('.dialogLegend')[0].update(MessageHash['ajxp_conf.83']);
//		this.form.down('#roles_pane').select('span')[1].update(MessageHash['ajxp_conf.84']);
		var url = window.ajxpServerAccessPath + '&get_action=user_update_role';
		this.selectionUrl = selection.updateFormOrUrl(null, url);
		var connexion = new Connexion(this.selectionUrl);
		connexion.onComplete = function(transport){			
			this.populateRoles(transport.responseXML);
		}.bind(this);
		connexion.sendAsync();
	},


	populateRoles : function(xmlData){
		var rolesPane = this.form.down('[id="roles_pane"]');
		var availableRoles = XPathSelectNodes(xmlData, "admin_data/ajxp_roles/role");
		var userRoles = XPathSelectNodes(xmlData, "admin_data/user/ajxp_roles/role");
		var availSelect = rolesPane.down('div#available_roles');
		var userSelect = rolesPane.down('div#user_roles');
		var rolesId = $A();
		userRoles.each(function(xmlElement){
			var id = xmlElement.getAttribute('id');
			var option = new Element('div', {id:id, className:'ajxp_role user_role'}).update(id);
			userSelect.insert(option);
			rolesId.push(id);
		});
		availableRoles.each(function(xmlElement){
			var id = xmlElement.getAttribute('id');
			if(!rolesId.include(id)){
				var option = new Element('div', {id:id, className:'ajxp_role available_role'}).update(id);
				availSelect.insert(option);
			}
		});
		this.draggables = $A();
		rolesPane.select("div.ajxp_role").each(function(item){
			var container = item.parentNode;
			this.draggables.push(new Draggable(item, {
				revert:true,
				ghosting:false,
				onStart:function(){
					container.parentNode.insert(item);
				},
				onEnd : function(){
					if(item.ajxp_dropped) return;
					container.insert(item);
				},
				reverteffect:function(element){element.setStyle({top:0,left:0});}
			}));
		}.bind(this));
		var dropFunc = function(dragged, dropped, event){
			dragged.ajxp_dropped = true;
			dropped.insert(dragged);
			dragged.setStyle({top:0,left:0});
			var sub_action;
			if(dragged.hasClassName('user_role')){
				dragged.removeClassName('user_role');
				dragged.addClassName('available_role');
				sub_action = "user_delete_role";
			}else{
				dragged.removeClassName('available_role');
				dragged.addClassName('user_role');
				sub_action = "user_add_role";
			}
			if(this.userId){
				var conn = new Connexion();
				conn.setParameters($H({get_action:"edit", sub_action:sub_action, user_id:this.userId, role_id:dragged.id}));
				conn.onComplete = function(transport){
					this.parseXmlMessage(transport.responseXML);
					this.loadUser(this.userId, true);
					ajaxplorer.fireContextRefresh();
				}.bind(this);
				conn.sendAsync();
			}else if(this.selectionUrl){
				var connexion = new Connexion(this.selectionUrl);
				connexion.addParameter("update_role_action", (sub_action=="user_delete_role"?"remove":"add"));
				connexion.addParameter("role_id", dragged.id);
				connexion.onComplete = function(transport){
					this.clearRolesForm();
					this.populateRoles(transport.responseXML);
					ajaxplorer.fireContextRefresh();
				}.bind(this);
				connexion.sendAsync();
			}
		}.bind(this);
		Droppables.add(availSelect, {accept:'user_role', onDrop:dropFunc, hoverclass:'roles_hover'});
		Droppables.add(userSelect, {accept:'available_role', onDrop:dropFunc, hoverclass:'roles_hover'});
		modal.setCloseAction(function(){
			Droppables.remove(availSelect);
			Droppables.remove(userSelect);
			this.draggables.invoke('destroy');
		}.bind(this));

	},


	loadCreateUserForm : function(){
		var params = new Hash();
		params.set("get_action", "edit");
		params.set("sub_action", "get_custom_params");
		var connexion = new Connexion();
		connexion.setParameters(params);
		connexion.onComplete = function(transport){
			modal.refreshDialogPosition();
			modal.refreshDialogAppearance();
			ajaxplorer.blurAll();
		}.bind(this);
		connexion.sendAsync();
	},
		
	submitCreateUser : function(){
		var login = this.form.select('[name="new_user_login"]')[0];
		var pass = this.form.select('[name="new_user_pwd"]')[0];
		var passConf = this.form.select('[name="new_user_pwd_conf"]')[0];
		var extraParams = this.form.select('div#custom_pane input');
		
		if(login.value == ''){
			ajaxplorer.displayMessage("ERROR", MessageHash['ajxp_conf.38']);
			return false;
		}
		if(pass.value == '' || passConf.value == '' ){
			ajaxplorer.displayMessage("ERROR", MessageHash['ajxp_conf.39']);
			return false;
		}
		if(pass.value.length < parseInt(window.ajaxplorer.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"))){
			ajaxplorer.displayMessage("ERROR", MessageHash[378]);
			return false;
		}
		if(pass.value != passConf.value){
			ajaxplorer.displayMessage("ERROR", MessageHash['ajxp_conf.37']);
			return false;
		}
		var parameters = new Hash();
		parameters.set('new_user_login', login.value);
		parameters.set('new_user_pwd', this.encodePassword(pass.value));
        var currentPath = ajaxplorer.getContextNode().getPath();
        if(currentPath.startsWith("/data/users")){
            var groupPath = currentPath.substr("/data/users".length);
            parameters.set('group_path', groupPath);
        }
		extraParams.each( function(input){
			parameters.set('DRIVER_OPTION_'+input.name, input.value);
		});
        var newUserName = login.value;
		this.submitForm("create_user", 'create_user', parameters, null, function(responseXML){
            // success callback
            hideLightBox();
            var editorData = ajaxplorer.findEditorById("editor.ajxp_role");
            var node = new AjxpNode(currentPath + "/"+newUserName, true);
            node.getMetadata().set("ajxp_mime", "user");
            ajaxplorer.openCurrentSelectionInEditor(editorData, node);
        }.bind(this), function(responseXML){
            // error callback;
        });
		return false;
	},

	deleteUser: function(){
		var chck = this.form.select('[id="delete_confirm"]')[0];
		if(!chck.checked){
			this.displayMessage("ERROR", MessageHash['ajxp_conf.40']);
			return;
		}
		var parameters = new Hash();
		parameters.set('user_id', this.userId);
		this.submitForm("edit_user", 'delete_user', parameters, null);
		chck.checked = false;
	},
		
	
	createUser: function (){
		var login = $('new_user_login');
		var pass = $('new_user_pwd');
		var passConf = $('new_user_pwd_conf');
		if(login.value == ''){
			this.displayMessage("ERROR", MessageHash['ajxp_conf.38']);
			return;
		}
		if(pass.value == '' || passConf.value == ''){
			this.displayMessage("ERROR", MessageHash['ajxp_conf.39']);
			return;
		}
		if(pass.value != passConf.value){
			this.displayMessage("ERROR", MessageHash['ajxp_conf.37']);
			return;
		}
		
		var parameters = new Hash();
		parameters.set('new_login', login.value);
		parameters.set('new_pwd', pass.value);
		this.submitForm("edit_user", 'create_user', parameters, null);
		login.value = pass.value = passConf.value = '';

	},

    encodePassword : function(password){
        // First get a seed to check whether the pass should be encoded or not.
        var sync = new Connexion();
        var seed;
        sync.addParameter('get_action', 'get_seed');
        sync.onComplete = function(transport){
            seed = transport.responseText;
        };
        sync.sendSync();
        var encoded;
        if(seed != '-1'){
            encoded = hex_md5(password);
        }else{
            encoded = password;
        }
        return encoded;

    },


    /*************************************/
	/*       REPOSITORIES FUNCTIONS      */
	/*************************************/
	initCreateRepoWizard : function(repositoryOrTemplate){
        this.currentCreateRepoType = repositoryOrTemplate;
        if(this.currentCreateRepoType == "template"){
            this.form.select('.repoCreationString').invoke("hide");
            this.form.select('.tplCreationString').invoke("show");
        }else{
            this.form.select('.repoCreationString').invoke("show");
            this.form.select('.tplCreationString').invoke("hide");
        }
		this.newRepoLabelInput = this.form.select('input[type="text"]')[0];
		this.driverSelector = this.form.select('select')[0];
		this.driverForm = this.form.select('div[id="driver_form"]')[0];
		this.repoSubmitButton = this.form.select('input[id="submit_create_repo"]')[0];
		this.repoSubmitButton.observe("click", function(){
			this.repoButtonClick(true);
		}.bind(this));
		this.drivers = new Hash();
		this.templates = new Hash();
		this.submitForm('create_repository', 'get_drivers_definition', new Hash(), null, function(xmlData){
            var root = XPathSelectSingleNode(xmlData, "drivers");
            if(root.getAttribute("allowed") == "false"){
                this.drivers.NOT_ALLOWED = true;
            }
			var driverNodes = XPathSelectNodes(xmlData, "drivers/ajxpdriver");
			for(var i=0;i<driverNodes.length;i++){				
				var driver = driverNodes[i];
				var driverDef = new Hash();
				var driverLabel = XPathGetSingleNodeText(driver, "@label");
				var driverName = XPathGetSingleNodeText(driver, "@name");
				var driverParams = XPathSelectNodes(driver, "param");
				driverDef.set('label', driverLabel);
				driverDef.set('description', XPathGetSingleNodeText(driver, "@description"));
				driverDef.set('name', driverName);
				var driverParamsArray = $A();
				for(var j=0;j<driverParams.length;j++){
					var paramNode = driverParams[j];
					if(this.currentCreateRepoType == "template" && paramNode.getAttribute('no_templates') == 'true'){
						continue;
					}else if(this.currentCreateRepoType == "repository" && paramNode.getAttribute('templates_only') == 'true'){
                        continue;
                    }
					driverParamsArray.push(this.formManager.parameterNodeToHash(paramNode));
				}
				driverDef.set('params', driverParamsArray);
				this.drivers.set(driverName, driverDef);
			}
			if(this.currentCreateRepoType == "template"){
				this.updateDriverSelector();
			}else{
				this.submitForm('create_repository', 'get_templates_definition', new Hash(), null, function(xmlData){			
					var driverNodes = XPathSelectNodes(xmlData, "repository_templates/template");			
					for(var i=0;i<driverNodes.length;i++){				
						var driver = driverNodes[i];
						var driverDef = new Hash();
						var driverName = XPathGetSingleNodeText(driver, "@repository_id");
						driverDef.set('label', XPathGetSingleNodeText(driver, "@repository_label"));
						driverDef.set('type', XPathGetSingleNodeText(driver, "@repository_type"));
						driverDef.set('name', driverName);
						var driverParams = XPathSelectNodes(driver, "option");
						var optionsList = $A();
						for(var k=0;k<driverParams.length;k++){
							optionsList.push(driverParams[k].getAttribute("name"));
						}
						driverDef.set('options', optionsList);
						this.templates.set(driverName, driverDef);
					}
					this.updateDriverSelector();
				}.bind(this) );				
			}
		}.bind(this) );
	},
	
	updateDriverSelector : function(){
		if(!this.drivers || !this.driverSelector) return;
		if(Prototype.Browser.IE){this.driverSelector.hide();}
		this.driverSelector.update('<option value="0" selected></option>');
		if(this.templates.size()){
			this.driverSelector.insert(new Element('optgroup', {label:"Repository Templates"}));
			this.templates.each(function(pair){
				var option = new Element('option');
				option.setAttribute('value', 'ajxp_template_'+pair.key);
				option.update(pair.value.get('label'));
				this.driverSelector.insert({'bottom':option});			
			}.bind(this));			
		}
        if(!this.drivers.NOT_ALLOWED){
            this.driverSelector.insert(new Element('optgroup', {label:"Access Drivers"}));
            this.drivers.each(function(pair){
                var option = new Element('option');
                option.setAttribute('value', pair.key);
                option.update(pair.value.get('label'));
                this.driverSelector.insert({'bottom':option});
            }.bind(this) );
        }
		if(Prototype.Browser.IE){this.driverSelector.show();}
		this.driverSelector.onchange = this.driverSelectorChange.bind(this);
	},
	
	driverSelectorChange : function(){
		var height = (Prototype.Browser.IE?62:32);
		var dName = this.driverSelector.getValue();
		if(dName.indexOf("ajxp_template_") === 0){
			var templateName = dName.substring(14);
			this.createDriverFormFromTemplate(templateName);
		}else{			
			this.createDriverForm(dName, (this.currentCreateRepoType == "template"?true:false) );
		}
		if(dName != "0"){
			height = 130 + this.driverForm.getHeight() + (Prototype.Browser.IE?15:0);
            var addscroll = false;
			if(height > 425) {
                height=425;
                addscroll = true;
            }
		}
		new Effect.Morph(this.driverForm.up('div'),{
			style:'height:'+height + 'px' + (addscroll?'overflow-x:scroll':'overflow-x:auto;'),
			duration:0.3, 
			afterFinish : function(){
				modal.refreshDialogPosition();
				modal.refreshDialogAppearance();			
			}
		});		
	},

	createDriverFormFromTemplate : function(templateName){
        this.driverForm.update('');
		var templateData = this.templates.get(templateName);
		var templateOptions = templateData.get("options");
		var driver = this.drivers.get(templateData.get("type"));
		var driverParams = driver.get("params");
		var prunedParams = driverParams.findAll(function(param){
			return !(templateOptions.include(param.get('name')));
		});
		this.formManager.createParametersInputs(this.driverForm, prunedParams);
        var firstAcc = this.driverForm.down(".accordion_content");
        if(!firstAcc) firstAcc = this.driverForm;
		firstAcc.insert({top:'<div class="dialogLegend">' + driver.get('description')+'</div>'});		
	},
	
	createDriverForm : function(driverName, addCheckBox){
        this.driverForm.update('');
		if(driverName == "0"){
			return;
		}
		var dOpt = this.drivers.get(driverName);
		var options = dOpt.get('params');
		this.formManager.createParametersInputs(this.driverForm, options, false, null, false, false, addCheckBox);
        var firstAcc = this.driverForm.down(".accordion_content");
        if(!firstAcc) firstAcc = this.driverForm;
		firstAcc.insert({top:'<div class="dialogLegend">' + dOpt.get('description')+'</div>'});

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
			$(this.newRepoLabelInput).addClassName("SF_failed");
			missingMandatory = true;
		}else{
			toSubmit.set('DISPLAY', this.newRepoLabelInput.value);
		}
		toSubmit.set('DRIVER', this.driverSelector.options[this.driverSelector.selectedIndex].value);
		var missingMandFields = this.formManager.serializeParametersInputs(this.driverForm, toSubmit, 'DRIVER_OPTION_');
		if(missingMandatory || missingMandFields){
			this.displayMessage("ERROR", MessageHash['ajxp_conf.36']);
			return false;
		}		
		this.submitForm('edit_repository', 'create_repository', toSubmit, null, function(responseXML){
			//hideLightBox();
            var reloadNode = XPathSelectSingleNode(responseXML.documentElement, "//reload_instruction/@file");
            if(reloadNode && reloadNode.nodeValue){
                var newRepoId = reloadNode.nodeValue;
                var editors = ajaxplorer.findEditorsForMime("repository");
                if(editors.length && editors[0].openable){
                    var editorData = editors[0];
                    var currentPath = ajaxplorer.getContextNode().getPath();
                    var node = new AjxpNode(currentPath+"/"+newRepoId, true);
                    node.getMetadata().set("text", this.newRepoLabelInput.getValue());
                    ajaxplorer.openCurrentSelectionInEditor(editorData, node);
                    hideLightBox();
                }
            }
		}.bind(this), function(){});
		return false;		
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
	createTabbedFieldset: function(link1, pane1, link2, pane2){
		var legend1 = new Element('a', {className:"active"}).update(link1);
		var legend2 = new Element('a').update(link2);
		var legend = new Element('legend');
		legend.insert(legend1);
		legend.insert(" | ");
		legend.insert(legend2);		
		
		legend1.observe("click", function(){
			pane2.hide();pane1.show();
			legend1.addClassName('active');
			legend2.removeClassName('active');
			modal.refreshDialogAppearance();
			modal.refreshDialogPosition();
		});
		legend2.observe("click", function(){
			pane1.hide();pane2.show();
			legend2.addClassName('active');
			legend1.removeClassName('active');
			modal.refreshDialogAppearance();
            modal.refreshDialogPosition();
		});				
		return legend;
	},
	
	submitForm: function(mainAction, action, parameters, formName, callback, errorCallback){
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
			parameters.set('get_action', "edit");			
			parameters.set('sub_action', action);
			connexion.setParameters(parameters);
		}
		if(!callback){
			connexion.onComplete = function(transport){
                var res = this.parseXmlMessage(transport.responseXML);
                if(!res && errorCallback) errorCallback(transport.responseXML);
            }.bind(this);
		}else{
			connexion.onComplete = function(transport){
				var res = this.parseXmlMessage(transport.responseXML);
                if(!res && errorCallback) errorCallback(transport.responseXML);
                else callback(transport.responseXML);
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
		if(xmlResponse == null || xmlResponse.documentElement == null) return false;
		var childs = xmlResponse.documentElement.childNodes;	
		var repList = false;

		for(var i=0; i<childs.length;i++)
		{
            if(childs[i].nodeName == "update_checkboxes")
			{
				var repositoryId = childs[i].getAttribute('repository_id');
				var read = childs[i].getAttribute('read');
				var write = childs[i].getAttribute('write');
				if(read != 'old') $('chck_'+repositoryId+'_read').checked = (read=='1'?true:false);
				$('chck_'+repositoryId+'_read').disabled = false;
				if(write != 'old') $('chck_'+repositoryId+'_write').checked = (write=='1'?true:false);
				$('chck_'+repositoryId+'_write').disabled = false;
			}
			else if(childs[i].nodeName == "repository")
			{
				if(!this.repositories || !repList) this.repositories = new Hash();
				repList = true;
				this.repositories.set(childs[i].getAttribute('index'), childs[i]);
			}
		}
        ajaxplorer.actionBar.parseXmlMessage(xmlResponse);
        if(xmlResponse.documentElement){
            if(XPathSelectSingleNode(xmlResponse.documentElement, 'message[@type="ERROR"]') != null){
                return false;
            }
        }
        return true;
	},

	
	displayMessage: function(messageType, message){
        ajaxplorer.displayMessage(messageType, message);
	}
});
