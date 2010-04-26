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
 * Description : Container for parent/location/bookmark components.
 */
Class.create("UserWidget", {
	__implements : ["IAjxpWidget"],
	initialize: function(element){
		this.element = element;
		this.element.ajxpPaneObject = this;
		this.element.observe("click", this.displayUserPrefs.bind(this));
		document.observe("ajaxplorer:user_logged", this.updateGui.bind(this));
	},
	updateGui : function(){
		var logging_string = "";
		var oUser = ajaxplorer.user;
		var observer = this.displayUserPrefs.bind(this);
		if(oUser != null) 
		{
			if(oUser.id != 'guest') 
			{
				logging_string = '<span style="cursor:pointer;"><span class="user_widget_label"><ajxp:message ajxp_message_id="142">'+MessageHash[142]+'</ajxp:message><i ajxp_message_title_id="189" title="'+MessageHash[189]+'">'+ oUser.id+' </i></span><img src="'+ajxpResourcesFolder+'/images/actions/16/configure.png" height="16" width="16" border="0" align="absmiddle"></span>';
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
		this.element.update(logging_string);
		
	},
	
	displayUserPrefs: function()
	{
		if(ajaxplorer.user == null) return;
		if(ajaxplorer.user.id == 'guest') return;
		var userLang = ajaxplorer.user.getPreference("lang");
		var userDisp = ajaxplorer.user.getPreference("display");	
		var onLoad = function(oForm){
			var selector = $(oForm).select('select[id="language_selector"]')[0];
			var languages = $H(window.ajxpBootstrap.parameters.get("availableLanguages"));
			languages.each(function(pair){
				var option = new Element('option', {value:pair.key,id:'lang_'+pair.key});
				option.update(pair.value);
				selector.insert(option);
			});
			selector.setValue(userLang);
			if(window.ajxpBootstrap.parameters.get("userChangePassword")){
				$('user_pref_change_password').show();
				$('user_change_ownpass_old').value = $('user_change_ownpass1').value = $('user_change_ownpass2').value = '';
				// Update pass_seed
				var connexion = new Connexion();
				connexion.addParameter("get_action", "get_seed");
				connexion.onComplete = function(transport){
					$('pass_seed').value = transport.responseText;
				};
				connexion.sendSync();			
			}else{
				$('user_pref_change_password').hide();
			}
			if($('display_'+userDisp))$('display_'+userDisp).checked = true;
		};
		
		var onComplete = function(oForm){
			ajaxplorer.user.setPreference("lang", $('user_pref_form').select('select[id="language_selector"]')[0].getValue());
			var userOldPass = null;
			var userPass = null;
			var passSeed = null;
			if($('user_pref_change_password').visible() && $('user_change_ownpass1') && $('user_change_ownpass1').value && $('user_change_ownpass2').value)
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
		
		modal.prepareHeader(MessageHash[195], ajxpResourcesFolder+'/images/actions/16/configure.png');
		modal.showDialogForm('Preferences', 'user_pref_form', onLoad, onComplete);
	},
	
	resize : function(){
	},
	showElement : function(show){
		this.element.select(".user_widget_label").invoke((show?'show':'hide'));
	}	
});