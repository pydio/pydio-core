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
 * Description : Encapsulation of the lightbox script for modal windows.
 */
Modal = Class.create({

	pageLoading: true,
	initialize: function(){
	},
	
	initForms: function(){
		this.elementName = 'generic_dialog_box';
		this.htmlElement = $(this.elementName);
		this.dialogTitle = this.htmlElement.getElementsBySelector(".dialogTitle")[0];
		this.dialogContent = this.htmlElement.getElementsBySelector(".dialogContent")[0];
		this.currentForm;
		this.cachedForms = new Hash();
		this.iframeIndex = 0;	
	},
	
	prepareHeader: function(sTitle, sIconSrc){
		var hString = "<span class=\"titleString\">";
		if(sIconSrc != "") hString = "<span class=\"titleString\"><img src=\""+sIconSrc.replace('22', '16')+"\" width=\"16\" height=\"16\" align=\"top\"/>&nbsp;";
		hString += sTitle + '</span>';
		this.dialogTitle.innerHTML = hString;
	},
	
	showDialogForm: function(sTitle, sFormId, fOnLoad, fOnComplete, fOnCancel, bOkButtonOnly, skipButtons){
		this.clearContent(this.dialogContent);
		//this.dialogTitle.innerHTML = sTitle;
		var newForm;
		if($(sFormId).tagName == 'FORM') // WE PASSED A PREFETCHED HIDDEN FORM
		{
			newForm = $(sFormId);
			newForm.show();
		}
		else
		{
			var formDiv = $(sFormId);
			//var formDiv = $('all_forms').select('[id="'+sFormId+'"]')[0];	
			var newForm = document.createElement('form');
			newForm.id = 'modal_action_form';
			newForm.setAttribute('name','modal_action_form');
			newForm.setAttribute('action', 'cont.php');
			newForm.appendChild(formDiv.cloneNode(true));
			var reloadIFrame = null;
			if($(newForm).getElementsByTagName("iframe")[0])
			{
				reloadIFrame = $(newForm).getElementsByTagName("iframe")[0];
				reloadIFrameSrc = $(newForm).getElementsByTagName("iframe")[0].getAttribute("src");
			}
			if(formDiv.getAttribute('action'))
			{
				var actionField = document.createElement('input');
				actionField.setAttribute('type', 'hidden'); 
				actionField.setAttribute('name', 'get_action'); 
				actionField.setAttribute('value', formDiv.getAttribute('action'));
				newForm.appendChild(actionField);
			}		
		}
		if(!this.cachedForms.get(sFormId) && !skipButtons){
			this.addSubmitCancel(newForm, fOnCancel, bOkButtonOnly);
		}
		this.dialogContent.appendChild(newForm);
		
		if(fOnComplete)	{
			newForm.onsubmit = function(){
				try{
					fOnComplete();
				}catch(e){
					alert('Unexpected Error : please report!\n'+e);				
				}
				return false;
			}
		}
		else {
			newForm.onsubmit = function(){
				ajaxplorer.actionBar.submitForm(modal.getForm());
				hideLightBox();
				return false;
			};
		}
		this.showContent(this.elementName, $(sFormId).getAttribute("box_width"), $(sFormId).getAttribute("box_height"));
		if($(newForm).getElementsBySelector(".dialogFocus").length)
		{
			objToFocus = $(newForm).getElementsBySelector(".dialogFocus")[0];
			setTimeout('objToFocus.focus()', 500);
		}
		if($(newForm).getElementsBySelector(".replace_rep").length)
		{
			repDisplay = $(newForm).getElementsBySelector(".replace_rep")[0];
			repDisplay.innerHTML = ajaxplorer.filesList.getCurrentRep();
		}
		if($(newForm).getElementsBySelector(".replace_file").length)
		{
			repDisplay = $(newForm).getElementsBySelector(".replace_file")[0];
			repDisplay.innerHTML = getBaseName(ajaxplorer.filesList.getUserSelection().getUniqueFileName());
		}
		this.currentForm = newForm;
		if(fOnLoad != null)
		{
			fOnLoad(this.currentForm);
			// Reload shadow if the content has changed after the fOnLoad call
			this.refreshDialogAppearance();
		}
		// SAFARI => FORCE IFRAME RELOADING
		if(Prototype.Browser.WebKit && reloadIFrame && reloadIFrameSrc) reloadIFrame.src = reloadIFrameSrc;
	},
	
	showContent: function(elementName, boxWidth, boxHeight, skipShadow){
		ajaxplorer.disableShortcuts();
		ajaxplorer.disableNavigation();
		ajaxplorer.filesList.blur();
		var winWidth = $(document.body).getWidth();
		var winHeight = $(document.body).getHeight();
	
		// WIDTH / HEIGHT
		if(boxWidth != null){
			if(boxWidth.indexOf('%') > -1){
				percentWidth = parseInt(boxWidth);
				boxWidth = parseInt((winWidth * percentWidth) / 100);
			}
			$(elementName).setStyle({width:boxWidth+'px'});
		}
		if(boxHeight != null){
			if(boxHeight.indexOf('%') > -1){
				percentHeight = parseInt(boxHeight);
				boxHeight = parseInt((winHeight * percentHeight) / 100);
			}
			$(elementName).setStyle({height:boxHeight+'px'});
		}else{
			if (Prototype.Browser.IE){	
				$(elementName).setStyle({height:'1%'});
			}else{
				$(elementName).setStyle({height:'auto'});
			}
		}
		
		this.refreshDialogPosition();
			
		displayLightBoxById(elementName);
		
		// FORCE ABSOLUTE FOR SAFARI
		$(elementName).style.position = 'absolute';
		// FORCE FIXED FOR FIREFOX
		if (Prototype.Browser.Gecko){					
			$(elementName).style.position = 'fixed';
		}
		else if(Prototype.Browser.IE){
			$$('select').invoke('show');
			// REFRESH PNG IMAGES FOR IE!
			refreshPNGImages(this.dialogContent);			
		}
		
		if(skipShadow) return;
		Shadower.shadow($(elementName), 
			{
				distance: 4,
				angle: 130,
				opacity: 0.5,
				nestedShadows: 3,
				color: '#000000',
				shadowStyle:{display:'block'}
			}, true);
		
	},
	
	getForm: function()	{
		return this.currentForm;
	},
	
	refreshDialogPosition: function(checkHeight, elementToScroll){
		var winWidth = $(document.body).getWidth();
		var winHeight = $(document.body).getHeight();
		boxWidth = $(this.elementName).getWidth();	
		var boxHeight = $(this.elementName).getHeight();
		
		if(checkHeight && boxHeight > parseInt(winHeight*90/100)){
			var maxHeight = parseInt(winHeight*90/100);
			var crtScrollHeight = elementToScroll.getHeight();
			var crtOffset = boxHeight - crtScrollHeight;
			if(maxHeight > crtOffset){ 
				elementToScroll.setStyle({
					overflow:'auto',
					height:(maxHeight-crtOffset)+'px'
				});		
				boxHeight = $(this.elementName).getHeight();
			}
		}
		// POSITION HORIZONTAL
		var offsetLeft = parseInt((winWidth - parseInt(boxWidth)) / 2);
		$(this.elementName).setStyle({left:offsetLeft+'px'});
		// POSITION VERTICAL
		var offsetTop = parseInt(((winHeight - boxHeight)/3));
		$(this.elementName).setStyle({top:offsetTop+'px'});		
	},
	
	refreshDialogAppearance:function(){
		Shadower.shadow($(this.elementName), 
			{
				distance: 4,
				angle: 130,
				opacity: 0.5,
				nestedShadows: 3,
				color: '#000000',
				shadowStyle:{display:'block'}
			}, true);		
	},
	
	clearContent: function(object){
		// REMOVE CURRENT FORM, IF ANY
		if(object.getElementsBySelector("form").length)
		{
			var oThis = this;
			object.getElementsBySelector("form").each(function(currentForm){
				if(currentForm.target == 'hidden_iframe' || currentForm.id=='login_form' || currentForm.id=='user_pref_form'){
					currentForm.hide();
					oThis.cachedForms.set(currentForm.id,true);
				}
				else{
					object.removeChild(currentForm);
				}
			});		
		}	
	},
	
	addSubmitCancel: function(oForm, fOnCancel, bOkButtonOnly){
		var contDiv = document.createElement('div');
		contDiv.className = 'dialogButtons';
		var okButton = document.createElement('input');
		okButton.setAttribute('type', 'submit');
		okButton.setAttribute('name', 'sub');
		okButton.setAttribute('value', MessageHash[48]);	
		$(okButton).addClassName('dialogButton');
		$(okButton).addClassName('dialogFocus');
		contDiv.appendChild(okButton);
		if(!bOkButtonOnly)
		{
			var caButton = document.createElement('input');
			caButton.setAttribute('type', 'button');
			caButton.setAttribute('name', 'can');
			caButton.setAttribute('value', MessageHash[49]);
			$(caButton).addClassName('dialogButton');
			if(fOnCancel){
				caButton.onclick = function(){fOnCancel();hideLightBox();return false;};
			}
			else{
				caButton.onclick = function(){hideLightBox();return false;};
			}
			contDiv.appendChild(caButton);
		}	
		oForm.appendChild(contDiv);
		oForm.hasButtons = true;
	},
	
	setLoadingStepCounts: function(count){
		this.loadingStepsCount = count;
		this.loadingStep = count;
	},
	
	incrementStepCounts: function(add){
		this.loadingStepsCount += add;
		this.loadingStep += add;
	},
	
	updateLoadingProgress: function(state){	
		this.loadingStep --;
		var percent = (1 - (this.loadingStep / this.loadingStepsCount));
		var width = parseInt(parseInt($('progressBarBorder').getWidth()) * percent);
		/*
		var command = "if($('progressBar')) $('progressBar').style.width = '"+width+"px';";
		setTimeout(command, 0);
		*/
		if(state){
			$('progressState').update(state);
		}
		if($('progressBar')){
			 /*
			 $('progressBar').style.width = width+'px';
			 */
			 var afterFinishFunc;
			if(parseInt(percent)==1){
				afterFinishFunc = function(effect){
						new Effect.Opacity('loading_overlay', {
							from:1.0,
							to:0,
							duration:0.3,
							afterFinish:function(effect){
								$('loading_overlay').remove();
								//if(ajaxplorer) ajaxplorer.actionBar.update();
							}
						});
				}
			}
			 
			new Effect.Morph('progressBar',{
				style:'width:'+width + 'px',
				duration:0.8,
				afterFinish:afterFinishFunc
			});
		}
		if(this.loadingStep == 0){
			//$('loading_overlay').remove();
			this.pageLoading = false;
		}
	},
	
	setCloseAction: function(func){
		this.closeFunction = func;
	},
	
	close: function(){	
		Shadower.deshadow($(this.elementName));
		if(this.closeFunction){
			 this.closeFunction();
			 //this.closeFunction = null;
		}
	}
});
	
var modal = new Modal();
