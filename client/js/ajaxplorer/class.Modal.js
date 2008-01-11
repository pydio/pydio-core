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
			newForm.id = 'action_form';
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
		}
		// SAFARI => FORCE IFRAME RELOADING
		if(reloadIFrame) reloadIFrame.src = reloadIFrameSrc;
	},
	
	showContent: function(elementName, boxWidth, boxHeight){
		ajaxplorer.disableShortcuts();
		ajaxplorer.disableNavigation();
		ajaxplorer.filesList.blur();
		if(Prototype.Browser.IE){
			new Effect.Corner($$('#'+elementName + ' .dialogTitle')[0],"round top 7px");
		}else{
			new Effect.Corner(elementName,"round top 7px");
		}
		if(!this.isRounded)
		{
			ajxpCorners($(elementName), 'bottom');
			this.isRounded = true;		
		}
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
		
		// POSITION HORIZONTAL
		boxWidth = $(elementName).getWidth();	
		var offsetLeft = (winWidth - parseInt(boxWidth)) / 2;
		$(elementName).setStyle({left:offsetLeft+'px'});
		// POSITION VERTICAL
		var boxHeight = $(elementName).getHeight();
		var offsetTop = parseInt(((winHeight - boxHeight)/3));
		$(elementName).setStyle({top:offsetTop+'px'});
			
		displayLightBoxById(elementName);
		
		// FORCE ABSOLUTE FOR SAFARI
		$(elementName).style.position = 'absolute';
		// FORCE FIXED FOR FIREFOX
		if (Prototype.Browser.Gecko){					
			$(elementName).style.position = 'fixed';
		}
		
		// REFRESH PNG IMAGES FOR IE!
		refreshPNGImages(this.dialogContent);
	},
	
	getForm: function()	{
		return this.currentForm;
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
			$('progressState').value = state;
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
		if(this.closeFunction){
			 this.closeFunction();
			 this.closeFunction = null;
		}
	}
});
	
var modal = new Modal();
