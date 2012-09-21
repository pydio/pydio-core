/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

/**
 * @package info.ajaxplorer.plugins
 * @class ConfigEditor
 * Configurations editor
 */
ConfigEditor = Class.create({

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
			
		this.form.down('#rights_pane').remove();
		this.form.down('#rights_legend').remove();
		this.form.down('#roles_pane').select('.dialogLegend')[0].update(MessageHash['ajxp_conf.83']);
		this.form.down('#roles_pane').select('span')[1].update(MessageHash['ajxp_conf.84']);
		var url = window.ajxpServerAccessPath + '&get_action=batch_users_roles';
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
			var action = "edit";
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
		if(pass.value.length < window.ajxpBootstrap.parameters.get("password_min_length")){
			ajaxplorer.displayMessage("ERROR", MessageHash[378]);
			return false;
		}
		if(pass.value != passConf.value){
			ajaxplorer.displayMessage("ERROR", MessageHash['ajxp_conf.37']);
			return false;
		}
		parameters = new Hash();
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
            var loadFunc = function(oForm){
                this.setForm(oForm);
                this.loadUser(newUserName);
            }.bind(this);
            modal.showDialogForm('', 'edit_config_box', loadFunc, function(){hideLightBox();}, null, true);
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
		return;
		
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
				var driverParamsArray = new Array();
				for(j=0;j<driverParams.length;j++){
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
			var height = 130 + this.driverForm.getHeight() + (Prototype.Browser.IE?15:0);
            var addscroll = false;
			if(height > 425) {
                height=425;
                addscroll = true;
            };
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
                var loadFunc = function(oForm){
                    ajaxplorer.getUserSelection().updateFormOrUrl(oForm);
                    if(!ajaxplorer.actionBar.configEditor){
                        ajaxplorer.actionBar.configEditor = new ConfigEditor(oForm);
                    }
                    ajaxplorer.actionBar.configEditor.setForm(oForm);
                    ajaxplorer.actionBar.configEditor.loadRepository(newRepoId, true);
                };
                var closeFunc = function(){
                    var toSubmit = new Hash();
                    var configEditor = ajaxplorer.actionBar.configEditor;
                    if(!configEditor.currentRepoWriteable){
                        hideLightBox();
                        return;
                    }
                    toSubmit.set("repository_id", configEditor.currentRepoId);
                    var missing = configEditor.formManager.serializeParametersInputs(configEditor.currentForm, toSubmit, 'DRIVER_OPTION_', configEditor.currentRepoIsTemplate);
                    if(missing && !configEditor.currentRepoIsTemplate){
                        configEditor.displayMessage("ERROR", MessageHash['ajxp_conf.36']);
                    }else{
                        configEditor.submitForm('edit_repository', 'edit_repository_data', toSubmit, null, function(){
                            this.loadRepList();
                            this.loadUsers();
                        }.bind(configEditor));
                        hideLightBox();
                    }
                };
                modal.showDialogForm('Edit Online', 'edit_repo_box', loadFunc, closeFunc);
            }
		}.bind(this), function(){});
		return false;		
	},
	
	loadRepository : function(repId, metaTab){
		var params = new Hash();		
		params.set("get_action", "edit");
		params.set("sub_action", "edit_repository");
		params.set("repository_id", repId);
		var connexion = new Connexion();
		connexion.setParameters(params);
		connexion.onComplete = function(transport){
			this.feedRepositoryForm(transport.responseXML, metaTab);			
			modal.refreshDialogPosition();
			modal.refreshDialogAppearance();
			ajaxplorer.blurAll();
		}.bind(this);
		connexion.sendAsync();		
	},
	
	loadPluginConfig : function(pluginId){
		var params = new Hash();		
		params.set("get_action", "get_plugin_manifest");
		params.set("plugin_id", pluginId);
		var connexion = new Connexion();
		connexion.setParameters(params);
		connexion.onComplete = function(transport){
			var xmlData = transport.responseXML;
			var params = XPathSelectNodes(xmlData, "//global_param");
			var values = XPathSelectNodes(xmlData, "//plugin_settings_values/param");
            var documentation = XPathSelectSingleNode(xmlData, "//plugin_doc");
			var optionsPane = this.form.select('[id="options_pane"]')[0];
			
			var paramsValues = new Hash();
			$A(values).each(function(child){
				if(child.nodeName != 'param') return;
				paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
			});		
			
			
			var driverParamsHash = $A([]);
            if(pluginId.split("\.")[0] != "core"){
                driverParamsHash.push($H({
                    name:'AJXP_PLUGIN_ENABLED',
                    type:'boolean',
                    label:MessageHash['ajxp_conf.104'],
                    description:""
                }));
            }
			for(var i=0;i<params.length;i++){
				var hashedParams = this.formManager.parameterNodeToHash(params[i]);
				driverParamsHash.push(hashedParams);
			}
            var form = new Element('div', {className:'driver_form'});
            if(documentation){
                var docDiv = new Element('div', {style:'display:none;overflow:auto;max-height:'+parseInt(document.viewport.getHeight()*50/100)+'px'}).insert(documentation.firstChild.nodeValue);
                docDiv.select('img').each(function(img){
                    img.setStyle({width:'220px'});
                    img.setAttribute('src', 'plugins/'+pluginId+'/'+img.getAttribute('src'));
                });  
                var link1 = MessageHash['ajxp_conf.107'];
                var link2 = MessageHash['ajxp_conf.108'];
                var legend = this.createTabbedFieldset(link1, form, link2, docDiv);
                optionsPane.update(legend);
                optionsPane.insert({bottom:form});
                optionsPane.insert({bottom:docDiv});
            }else{
                optionsPane.update("<legend>"+MessageHash['ajxp_conf.107']+"</legend>");
                optionsPane.insert({bottom:form});
            }

			if(driverParamsHash.size()){
				this.formManager.createParametersInputs(form, driverParamsHash, true, (paramsValues.size()?paramsValues:null));
			}else{
				form.update(MessageHash['ajxp_conf.105']);
			}
			
			modal.refreshDialogPosition();
			modal.refreshDialogAppearance();
			ajaxplorer.blurAll();
		}.bind(this);
		connexion.sendAsync();		
	},

	feedRepositoryForm: function(xmlData, metaTab){
		
		var repo = XPathSelectSingleNode(xmlData, "admin_data/repository");
		var driverParams = XPathSelectNodes(xmlData, "admin_data/ajxpdriver/param");
		var optionsPane = this.form.select('[id="options_pane"]')[0];		
		var tplParams = XPathSelectNodes(xmlData, "admin_data/template/option");
        this.currentRepoIsTemplate = (repo.getAttribute("isTemplate") === "true");

		if(tplParams.length){
			var tplParamNames = $A();
			for(var k=0;k<tplParams.length;k++) {
				if(tplParams[k].getAttribute("name")){
					tplParamNames.push(tplParams[k].getAttribute("name"));					
				}
			}
		}
		
		var driverParamsHash = $A([]);
		for(var i=0;i<driverParams.length;i++){
			var hashedParams = this.formManager.parameterNodeToHash(driverParams[i]);
			if(tplParamNames && tplParamNames.include(hashedParams.get('name'))) continue;
            if(this.currentRepoIsTemplate && driverParams[i].getAttribute('no_templates') == 'true'){
                continue;
            }else if(!this.currentRepoIsTemplate && driverParams[i].getAttribute('templates_only') == 'true'){
                continue;
            }
			driverParamsHash.push(hashedParams);
		}
				
		var form = new Element('div', {className:'driver_form'});
		
		if(!tplParams.length){
			var metaForm = new Element('div', {className:'driver_form', style:'display:none;'});		
			var link1 = XPathGetSingleNodeText(xmlData, "admin_data/ajxpdriver/@name").toUpperCase()+' '+ MessageHash['ajxp_conf.41'];
			var link2 = MessageHash['ajxp_conf.10'];		
			var legend = this.createTabbedFieldset(link1, form, link2, metaForm);
			optionsPane.update(legend);
			optionsPane.insert({bottom:form});
			optionsPane.insert({bottom:metaForm});			
		}else{
			optionsPane.update("<legend>Repository Options</legend>");
			optionsPane.insert({bottom:form});			
		}
				
		var paramsValues = new Hash();
		$A(repo.childNodes).each(function(child){
			if(child.nodeName != 'param') return;
			paramsValues.set(child.getAttribute('name'), child.getAttribute('value'));
		});		
		var writeable = (repo.getAttribute("writeable")?(repo.getAttribute("writeable")=="true"):false);			
		this.currentForm = form;
		this.currentRepoId = repo.getAttribute("index");
		this.currentRepoWriteable = writeable;
		this.formManager.createParametersInputs(form, driverParamsHash, false, paramsValues, !writeable, false, this.currentRepoIsTemplate);
		
		if(!tplParams.length){
			if(writeable){
				this.feedMetaSourceForm(xmlData, metaForm);		
				if(metaTab){
					form.hide();metaForm.show();
					metaLegend.addClassName('active');
					optLegend.removeClassName('active');
					modal.refreshDialogAppearance();
                    modal.refreshDialogPosition();
				}
			}else{
				metaForm.update(MessageHash['ajxp_conf.88']);
			}			
		}
		
	},
	
	feedMetaSourceForm : function(xmlData, metaPane){
		var data = XPathSelectSingleNode(xmlData, 'admin_data/repository/param[@name="META_SOURCES"]');
		if(data && data.firstChild && data.firstChild.nodeValue){
			var metaSourcesData = data.firstChild.nodeValue.evalJSON();
			for(var plugId in metaSourcesData){
                var metaLabel = XPathSelectSingleNode(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/@label').nodeValue;
				//var form = new Element("div", {className:"metaPane"}).update("<img name=\"delete_meta_source\" src=\""+ajxpResourcesFolder+"/images/actions/16/editdelete.png\"><img name=\"edit_meta_source\" src=\""+ajxpResourcesFolder+"/images/actions/16/filesave.png\"><span class=\"title\">"+metaLabel+"</span>");
                var metaDefNodes = XPathSelectNodes(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/param');

				var titleString = "<img name=\"delete_meta_source\" src=\""+ajxpResourcesFolder+"/images/actions/16/editdelete.png\" style='float:right;' class='metaPaneTitle'>"+(metaDefNodes.length?"<img name=\"edit_meta_source\" src=\""+ajxpResourcesFolder+"/images/actions/16/filesave.png\" style='float:right;' class='metaPaneTitle'>":"")+"<span class=\"title\">"+metaLabel+"</span>";
                var title = new Element('div',{className:'accordion_toggle', tabIndex:0}).update(titleString);
                var form = new Element("div", {className:"accordion_content"});
				title._plugId = plugId;
				form._plugId = plugId;
                if(metaDefNodes.length){
                    var driverParamsHash = $A([]);
                    for(var i=0;i<metaDefNodes.length;i++){
                        driverParamsHash.push(this.formManager.parameterNodeToHash(metaDefNodes[i]));
                    }
                    var paramsValues = new Hash(metaSourcesData[plugId]);
                    this.formManager.createParametersInputs(form, driverParamsHash, true, paramsValues, false, true);
                }else{
                    form.update('No parameters');
                }
                metaPane.insert(title);
                metaPane.insert(form);
                title.observe('focus', function(event){
                    if(metaPane.SF_accordion && metaPane.SF_accordion.showAccordion!=event.target.next(0)) {
                        metaPane.SF_accordion.activate(event.target);
                    }
                });
			}
            metaPane.SF_accordion = new accordion(metaPane, {
                classNames : {
                    toggle : 'accordion_toggle',
                    toggleActive : 'accordion_toggle_active',
                    content : 'accordion_content'
                },
                defaultSize : {
                    width : '360px',
                    height: null
                },
                direction : 'vertical'
            });
		}

		var addForm = new Element("div", {className:"metaPane"}).update("<div style='clear:both;'><img name=\"add_meta_source\" src=\""+ajxpResourcesFolder+"/images/actions/16/filesave.png\"><span class=\"title\">"+MessageHash['ajxp_conf.11']+"</span></div>");
		var formEl = new Element("div", {className:"SF_element"}).update("<div class='SF_label'>"+MessageHash['ajxp_conf.12']+" :</div>");
		this.metaSelector = new Element("select", {name:'new_meta_source', className:'SF_input'});
		var choices = XPathSelectNodes(xmlData, 'admin_data/metasources/meta');
		this.metaSelector.insert(new Element("option", {value:"", selected:"true"}));
		for(var i=0;i<choices.length;i++){
			var id = choices[i].getAttribute("id");
			var label = choices[i].getAttribute("label");
			this.metaSelector.insert(new Element("option",{value:id}).update(label));
		}		
		addForm.insert(formEl);
		formEl.insert(this.metaSelector);
		metaPane.insert({top:addForm});
		var addFormDetail = new Element("div");
		addForm.insert(addFormDetail);
		addForm.select('img')[0]._form = addForm;
		
		this.metaSelector.observe("change", function(){
			var plugId = this.metaSelector.getValue();
			addFormDetail.update("");
			if(plugId){
				var metaDefNodes = XPathSelectNodes(xmlData, 'admin_data/metasources/meta[@id="'+plugId+'"]/param');
				var driverParamsHash = $A([]);
				for(var i=0;i<metaDefNodes.length;i++){
					driverParamsHash.push(this.formManager.parameterNodeToHash(metaDefNodes[i]));
				}				
				this.formManager.createParametersInputs(addFormDetail, driverParamsHash, true, null, null, true);
			}
			modal.refreshDialogAppearance();
            modal.refreshDialogPosition();
		}.bind(this));

		metaPane.select('img').each(function(img){
			img.observe("click", this.metaActionClick.bind(this));
		}.bind(this));
		
	},
	
	metaActionClick : function(event){
		var img = Event.findElement(event, 'img');
        Event.stop(event);
		if(img._form){
			var form = img._form;
		}else{
			var form = Event.findElement(event, 'div').next(0);
		}
		//var params = target._parameters;
		var params = new Hash();
		if(form._plugId){
			params.set('plugId', form._plugId);
		}
		if(img.getAttribute('name')){
			params.set('get_action', img.getAttribute('name'));
		}
		params.set('repository_id', this.currentRepoId);
		this.formManager.serializeParametersInputs(form, params, "DRIVER_OPTION_");
		if(params.get('get_action') == 'add_meta_source' && params.get('DRIVER_OPTION_new_meta_source') == ''){
			alert(MessageHash['ajxp_conf.42']);
			return;
		}
		if(params.get('DRIVER_OPTION_new_meta_source')){
			params.set('new_meta_source', params.get('DRIVER_OPTION_new_meta_source'));
			params.unset('DRIVER_OPTION_new_meta_source');
		}
		if(params.get('get_action') == 'delete_meta_source'){
			var res = confirm(MessageHash['ajxp_conf.13']);
			if(!res) return;
		}
		
		var conn = new Connexion();
		conn.setParameters(params);
		conn.onComplete = function(transport){
			this.parseXmlMessage(transport.responseXML);
			this.loadRepository(this.currentRepoId, true);
		}.bind(this);
		conn.sendAsync();
		
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
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		var repList = false;

		for(var i=0; i<childs.length;i++)
		{
            if(childs[i].nodeName == "update_checkboxes")
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
