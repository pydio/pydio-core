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
 * Encapsulation of the lightbox script for modal windows. Used for alerting user, but
 * also for all popup forms (generic_dialog_box)
 * An instance is automatically created at the end of the file, no very clean, ajaxplorer
 * should create it instead.
 */
Class.create("Modal", {

	/**
	 * @var Boolean Current state of the page. If true, calls the updateLoadingProgress
	 */
	pageLoading: true,
	/**
	 * Constructor
	 */
	initialize: function(){
	},

    /**
     * CurrentLightBox
     */
    currentLightBoxElement : null,
    currentLightBoxModal : null,

	/**
	 * Find the forms
	 */
	initForms: function(){
		this.elementName = 'generic_dialog_box';
		this.htmlElement = $(this.elementName);
		this.dialogTitle = this.htmlElement.down(".dialogTitle");
		this.dialogContent = this.htmlElement.down(".dialogContent");
		this.cachedForms = new Hash();
	},
	
	/**
	 * Compute dialogContent html
	 * @param sTitle String Title of the popup
	 * @param sIconSrc String Source icon
	 */
	prepareHeader: function(sTitle, sIconSrc, sIconClass){
		var hString = "<span class=\"titleString\">";
		if(sIconSrc != "") hString = "<span class=\"titleString\"><img src=\""+sIconSrc.replace('22', '16')+"\" width=\"16\" height=\"16\" align=\"top\"/>&nbsp;";
        var closeBtn;
        if(window.ajaxplorer.currentThemeUsesIconFonts){
            if(sIconClass){
                hString = "<span class=\"titleString\"><span class='"+sIconClass+" ajxp_icon_span'></span>";
            }
            closeBtn = '<span id="modalCloseBtn" class="icon-remove" style="cursor:pointer;float:right;"></span>';
        }else{
            closeBtn = '<img id="modalCloseBtn" style="cursor:pointer;float:right;margin-top:2px;" src="'+ajxpResourcesFolder+'/images/actions/16/window_close.png" />';
        }

        hString += '<span class="string-only-title">' + sTitle + '</span></span>';
		this.dialogTitle.update(closeBtn + hString);
	},

    /**
     * Implement IEditorParentContext
     * @param title
     * @param iconClass
     * @param iconImg
     */
    setContextTitle : function(title, iconClass, iconImg){
        if(title){
            this.dialogTitle.down(".string-only-title").update(title);
        }
    },
	
	/**
	 * Shows a dialog box by getting the form from the hidden_forms
	 * @param sTitle String Title of the box
	 * @param sFormId String Id of the form to use as content
	 * @param fOnLoad Function Callback after the popup is shown, passe the form as argument
	 * @param fOnComplete Function Callback for OK button
	 * @param fOnCancel Function Callback for Cancel button
	 * @param bOkButtonOnly Boolean Wether to hide cancel button
	 * @param skipButtons Boolean Wether to hide all buttons
	 */
	showDialogForm: function(sTitle, sFormId, fOnLoad, fOnComplete, fOnCancel, bOkButtonOnly, skipButtons, useNextButtons){
		this.clearContent(this.dialogContent);
		this.htmlElement.className = 'dialogBox form-'+sFormId;
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
			newForm = new Element('form', {
                name:'modal_action_form',
                id:'modal_action_form',
                autocomplete:'off'
            });
			newForm.insert(formDiv.cloneNode(true));
			var reloadIFrame, reloadIFrameSrc;
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
		if((!this.cachedForms.get(sFormId) || sFormId == 'login_form_dynamic')  && !skipButtons){
			this.addSubmitCancel(newForm, fOnCancel, bOkButtonOnly, "bottom", useNextButtons);
		}
		this.dialogContent.appendChild(newForm);
		var boxPadding = $(sFormId).getAttribute("box_padding");
		if(!boxPadding) boxPadding = 10;
		this.dialogContent.setStyle({padding:boxPadding+'px'});

		if(this.dialogTitle.select('#modalCloseBtn')[0]){
            if(fOnCancel){
                this.dialogTitle.select('#modalCloseBtn')[0].observe("click", function(){
                    fOnCancel(this.getForm());
                    hideLightBox();
                }.bind(this));
            }
            else{
                this.dialogTitle.select('#modalCloseBtn')[0].observe("click", function(){
                    hideLightBox();
                });
            }
        }

		if(fOnComplete)	{
			newForm.onsubmit = function(){
				try{
					fOnComplete(this.getForm());
				}catch(e){
					alert('Unexpected Error : please report!\n'+e);				
				}
				return false;
            }.bind(this);
		}
		else {
			newForm.onsubmit = function(){
				ajaxplorer.actionBar.submitForm(this.getForm());
				hideLightBox();
				return false;
            }.bind(this);
		}
        var overlayStyle = undefined;
        if($(sFormId).getAttribute("overlayStyle")){
            overlayStyle = $(sFormId).getAttribute("overlayStyle").evalJSON();
        }
		this.showContent(this.elementName, 
				$(sFormId).getAttribute("box_width"), 
				$(sFormId).getAttribute("box_height"),
				null,
				($(sFormId).getAttribute("box_resize") && $(sFormId).getAttribute("box_resize") == "true"),
                overlayStyle,
                sFormId
        );
		if($(newForm).select(".dialogFocus").length)
		{
			var objToFocus = $(newForm).select(".dialogFocus")[0];
			setTimeout(function(){
                objToFocus.focus();
            }, 500);
		}
        var repDisplay;
		if($(newForm).select(".replace_rep").length)
		{
			repDisplay = $(newForm).select(".replace_rep")[0];
			repDisplay.innerHTML = ajaxplorer.getContextHolder().getContextNode().getPath();
		}
		if($(newForm).select(".replace_file").length)
		{
			repDisplay = $(newForm).select(".replace_file")[0];
			repDisplay.innerHTML = getBaseName(ajaxplorer.getUserSelection().getUniqueFileName());
		}
		if($(newForm).select('.dialogEnterKey').length && Prototype.Browser.IE){
			$(newForm).select('.dialogEnterKey').each(function(el){
				if(el.enterObserver) return;
				el.observe("keypress", function(event){
					if(event.keyCode == Event.KEY_RETURN){
						newForm.onsubmit();						
					}
				});
				el.enterObserver = true;
			});
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
	/**
	 * Utility for effectively showing the modal
	 * @param elementName String
	 * @param boxWidth String Width in pixel or in percent
	 * @param boxHeight String Height in pixel or in percent
	 * @param skipShadow Boolean Do not add a shadow
     * @param boxAutoResize Boolean whether box should be resized on window resize event
     * @param overlayStyle String additional CSS string to be applied to overlay element
     * @param formId String If this id is not null, dialog will have the class form-formId
	 */
	showContent: function(elementName, boxWidth, boxHeight, skipShadow, boxAutoResize, overlayStyle, formId){
		ajaxplorer.disableShortcuts();
		ajaxplorer.disableNavigation();
		ajaxplorer.blurAll();
		var winWidth = document.viewport.getWidth();
		var winHeight = document.viewport.getHeight();
	
		this.currentListensToWidth = false;
		this.currentListensToHeight = false;
		// WIDTH / HEIGHT
        $(elementName).setStyle({
            width:'auto',
            height:'auto'
        });
		if(boxWidth != null){
			if(boxWidth.indexOf("%") ==-1 && parseInt(boxWidth) > winWidth){
				boxWidth = '90%';
			}
			if(boxWidth.indexOf('%') > -1){
				var percentWidth = parseInt(boxWidth);
				boxWidth = parseInt((winWidth * percentWidth) / 100);
				this.currentListensToWidth = percentWidth;
			}
			$(elementName).setStyle({width:boxWidth+'px'});
		}
		if(boxHeight != null){
			if(boxHeight.indexOf('%') > -1){
				var percentHeight = parseInt(boxHeight);
				boxHeight = parseInt((winHeight * percentHeight) / 100);
				this.currentListensToHeight = percentHeight;
			}
			$(elementName).setStyle({height:boxHeight+'px'});
		}else{
            $(elementName).setStyle({height:'auto'});
            $(elementName).down('.dialogContent').setStyle({height:'auto'});
		}
		if(boxAutoResize && (this.currentListensToWidth || this.currentListensToHeight) ){
			this.currentResizeListener = function(){
				if(this.currentListensToWidth){
					var winWidth = document.viewport.getWidth();
					boxWidth = parseInt((winWidth * this.currentListensToWidth) / 100);
					$(elementName).setStyle({width:boxWidth+'px'});					
				}
				if(this.currentListensToHeight){
					var winHeight = document.viewport.getHeight();
					var boxH = parseInt((winHeight * this.currentListensToHeight) / 100);
					$(elementName).setStyle({height:boxH+'px'});
                    fitHeightToBottom($(elementName).down('.dialogContent'));
				}
				this.notify("modal:resize");
			}.bind(this);
			Event.observe(window, "resize", this.currentResizeListener);
		}else{
            this.currentResizeListener = function(){
                this.refreshDialogPosition();
            }.bind(this);
            Event.observe(window, "resize", this.currentResizeListener);
        }
        this._bufferedRefreshDialogPosition();

		displayLightBoxById(elementName, overlayStyle, (formId?'form-'+formId:null));
		
		// FORCE ABSOLUTE FOR SAFARI
		$(elementName).style.position = 'absolute';
		// FORCE FIXED FOR FIREFOX
		if (Prototype.Browser.Gecko){					
			$(elementName).style.position = 'fixed';
		} else if(Prototype.Browser.IE && !Prototype.Browser.IE10plus){
			$(elementName).select('select').invoke('show');
			refreshPNGImages(this.dialogContent);
		}

        if(this.currentResizeListener) this.currentResizeListener();

	},
	/**
	 * Find an editor using the editorData and initialize it
	 * @param editorData Object
     * @param editorArgument Object
	 */
	openEditorDialog : function(editorData, editorArgument){
		if(!editorData.formId){
			ajaxplorer.displayMessage('ERROR', 'Error, you must define a formId attribute in your &lt;editor&gt; manifest (or set it as openable="false")');
			return;
		}
		var editorKlass = editorData.editorClass;
		this.prepareHeader(editorData.text, resolveImageSource(editorData.icon, '/images/actions/ICON_SIZE', 16), editorData.icon_class);
		var loadFunc = function(oForm){			
			if(typeof(editorKlass) == "string"){
				ajaxplorer.actionBar.editor = eval('new '+editorKlass+'(oForm, {editorData:editorData, context:modal})');
			}else{
				ajaxplorer.actionBar.editor = new editorKlass(oForm, {editorData:editorData, context:modal});
			}
            if(editorArgument){
                ajaxplorer.actionBar.editor.open(editorArgument);
            }else{
                ajaxplorer.actionBar.editor.open(ajaxplorer.getUserSelection().getUniqueNode());
            }
            ajaxplorer.actionBar.editor.getDomNode().observe("editor:updateTitle", function(event){
                this.setContextTitle(event.memo);
            }.bind(this));
        }.bind(this);
		this.showDialogForm('', editorData.formId, loadFunc, null, null, true, true);			
	},

    showSimpleModal : function(element, content, okCallback, cancelCallback, position){
        var box = new Element("div", {className:"dialogBox css_boxshadow", style:'display:block;'});
        box.insert(content);
        content.addClassName("dialogContent");
        addLightboxMarkupToElement(element);
        if(Prototype.Browser.IE && !Prototype.Browser.IE10plus){
            $("all_forms").insert(box);
            var outBox = element.up(".dialogBox");
            if(outBox){
                box.setStyle({
                    display:"block",
                    zIndex : outBox.getStyle("zIndex"),
                    top: parseInt(outBox.getStyle('top')),
                    left: parseInt(outBox.getStyle('left'))
                });
            }else{
                box.setStyle({
                    display:"block",
                    zIndex : 2000,
                    top: parseInt(element.getStyle('top')),
                    left: parseInt(element.getStyle('left'))
                });
            }
        }else{
            $(element).down("#element_overlay").insert({after:box});
            $(element).down("#element_overlay").setStyle({opacity:0.9});
            if(element.up('div.dialogBox')){
                //Effect.BlindDown(box, {
                //    duration:0.6,
                //    transition:Effect.Transitions.sinoidal
                //});
            }
        }
        this.currentLightBoxElement = $(element);
        this.currentLightBoxModal = box;
        this.addSubmitCancel(content, cancelCallback, (cancelCallback==null), position);
        content.down(".dialogButtons").select("input").each(function(button){
            if(((cancelCallback==null) && button.getAttribute("name") == "close") || button.getAttribute("name") == "ok"){
                button.observe("click", function(event){
                    Event.stop(event);
                    var res = okCallback();
                    if(res){
                        Effect.BlindUp(box, {
                            duration:0.4,
                            afterFinish:function(){
                                content.down('div.dialogButtons').remove();
                                $(element).down("#element_overlay").setStyle({opacity:0});
                                box.remove();
                                removeLightboxFromElement(element);
                                this.currentLightBoxElement = null;
                                this.currentLightBoxModal = null;
                            }.bind(this)
                        });
                    }
                }.bind(this));
            }else{
                button.stopObserving("click");
                button.observe("click", function(event){
                    Event.stop(event);
                    var res = cancelCallback();
                    if(res){
                        Effect.BlindUp(box, {
                            duration:0.4,
                            afterFinish:function(){
                                content.down('div.dialogButtons').remove();
                                if($(element).down("#element_overlay")) {
                                    $(element).down("#element_overlay").setStyle({opacity:0});
                                }
                                box.remove();
                                removeLightboxFromElement(element);
                                this.currentLightBoxElement = null;
                                this.currentLightBoxModal = null;
                            }.bind(this)
                        });
                    }
                }.bind(this));
            }
        });
    },


    createTopCaret:function(element){
        "use strict";
        element.insert({top:'<span class="icon-caret-up ajxp-caret-up"></span>'});
        var caret = element.down('span.ajxp-caret-up');
        caret.setStyle({
            left: (element.getWidth() -  caret.getWidth()) / 2 + 'px'
        });
    },

	/**
	 * Returns the current form, the real one.
	 * @returns HTMLForm
	 */
	getForm: function()	{
		return this.currentForm;
	},

    _dialogPositionRefreshBuffer: null,
    /**
     * Refresh position after a window change
     * @param checkHeight Boolean
     * @param elementToScroll HTMLElement
     */
    refreshDialogPosition: function(checkHeight, elementToScroll){
        if(this._dialogPositionRefreshBuffer){
            window.clearTimeout(this._dialogPositionRefreshBuffer);
        }
        this._dialogPositionRefreshBuffer = window.setTimeout(function(){
            this._bufferedRefreshDialogPosition(checkHeight, elementToScroll);
        }.bind(this), 200);
    },
    /**
	 * Refresh position after a window change
     * Used internally by the public function to buffer multiple calls
	 * @param checkHeight Boolean
	 * @param elementToScroll HTMLElement
	 */
    _bufferedRefreshDialogPosition: function(checkHeight, elementToScroll){
		var winWidth = document.viewport.getWidth();
		var winHeight = document.viewport.getHeight();
        var element = $(this.elementName);
		var boxWidth = element.getWidth();
		var boxHeight = element.getHeight();
        var dContent = element.down('.dialogContent');
        var dContentScrollHeight = dContent.scrollHeight;
        var dTitle = element.down('.dialogTitle');

		if(checkHeight && boxHeight > parseInt(winHeight*90/100)){
			var maxHeight = parseInt(winHeight*90/100);
			var crtScrollHeight = elementToScroll.getHeight();
			var crtOffset = boxHeight - crtScrollHeight;
			if(maxHeight > crtOffset){ 
				elementToScroll.setStyle({
					overflow:'auto',
					height:(maxHeight-crtOffset)+'px'
				});
                if (window.ajxpMobile){
                    attachMobileScroll(dContent, "vertical");
                }
				boxHeight = element.getHeight();
			}
		}else if(!checkHeight && dContentScrollHeight > winHeight){
            if(dTitle.getStyle('position') == 'absolute'){
                dContent.setStyle({
                    height:(winHeight)+'px',
                    overflow:'auto'
                });
            }else{
                dContent.setStyle({
                    height:(winHeight- parseInt(dTitle.getHeight()) - 20)+'px',
                    overflow:'auto'
                });
            }
            if (window.ajxpMobile){
                attachMobileScroll(dContent, "vertical");
            }
            boxHeight = element.getHeight();
        }else if(dContentScrollHeight >= dContent.getHeight() && !this.currentListensToHeight){
            dContent.setStyle({height: 'auto'});
            boxHeight = element.getHeight();
        }

        if(dTitle.getStyle('position') == 'absolute'){
            var innerH = 0;
            $A(dContent.childNodes).each(function(c){
                if(c.getHeight) innerH += c.getHeight();
            });
            var topPadding = Math.min(winHeight*25/100, Math.max(0, parseInt((winHeight - innerH)/2)));
            dContent.setStyle({paddingTop: topPadding + 'px'});
        }

		var offsetLeft = parseInt((winWidth - parseInt(boxWidth)) / 2);
		var offsetTop = parseInt(((winHeight - parseInt(boxHeight))/3));
        
        if(element.offsetLeft && element.offsetTop){
            new Effect.Morph($(this.elementName), {
                style:'top:'+offsetTop+'px;left:'+offsetLeft+'px',
                duration:0.4,
                afterFinish : this.refreshDialogAppearance.bind(this)
            });
        }else{
            element.setStyle({top:offsetTop+"px",left:offsetLeft+"px"});
            this.refreshDialogAppearance();
        }

	},
	/**
	 * Refresh appearance after the dialog box changed (shadow)
	 */
	refreshDialogAppearance:function(){
	},
	/**
	 * Clear all content
	 * @param object HTMLElement The current form
	 */
	clearContent: function(object){
		// REMOVE CURRENT FORM, IF ANY
		if(object.select("form").length)
		{
			var oThis = this;
			object.select("form").each(function(currentForm){
				if(currentForm.target == 'hidden_iframe' || currentForm.id == 'login_form' || currentForm.id == 'login_form_dynamic' || currentForm.id == 'user_pref_form'){
					currentForm.hide();
					oThis.cachedForms.set(currentForm.id,true);
				}
				else{
					try{object.removeChild(currentForm);}catch(e){}
				}
			});		
		}	
	},
	/**
	 * Adds buttons to the content
	 * @param oForm HTMLElement Current form
	 * @param fOnCancel Function Callback on cancel
	 * @param bOkButtonOnly Boolean Hide cancel
	 * @param position String Position.insert() allowed key.
	 * @returns HTMLElement
	 */
	addSubmitCancel: function(oForm, fOnCancel, bOkButtonOnly, position, useNextButton){
		var contDiv = new Element('div', {className:'dialogButtons'});
        if(useNextButton){
            contDiv.setStyle({direction:'rtl'});
        }
		var okButton = new Element('input', {
			type:'image',
			name:(bOkButtonOnly ? (bOkButtonOnly =='close' ? 'close' :'ok') :'ok'),
			src:ajxpResourcesFolder+'/images/actions/22/'+(bOkButtonOnly?(bOkButtonOnly =='close' ? 'dialog_close' :'dialog_ok_apply'):(useNextButton?'forward':'dialog_ok_apply'))+'.png',
			height:22,
			width:22,
			title:MessageHash[(bOkButtonOnly ? (bOkButtonOnly =='close' ? 49 : 48) : 48)]});
		okButton.addClassName('dialogButton');
		okButton.addClassName('dialogFocus');
        contDiv.insert(okButton);
		if(!bOkButtonOnly)
		{
			var caButton = new Element('input', {
				type:"image",
				name:"can",
				height:22,
				width:22,
				src:ajxpResourcesFolder+'/images/actions/22/dialog_close.png',
				title:MessageHash[49],
				className:"dialogButton"
			});
			if(fOnCancel){
				caButton.observe("click",function(e){
                    fOnCancel(this.getForm());
                    hideLightBox();
                    Event.stop(e);
                    return false;
                }.bind(this));
			}
			else{
				caButton.observe("click",function(e){hideLightBox();Event.stop(e);return false;});
			}
			contDiv.insert(caButton);
		}

        if(!position){
			position = 'bottom';
		}
		var obj = {}; 
		obj[position] = contDiv;
		$(oForm).insert(obj);
		oForm.hasButtons = true;
		return contDiv;
	},
	
	/**
	 * Create a simple tooltip
	 * @param element HTMLElement
	 * @param title String
     * @param position Describe tooltip position
     * @param className Add an arbitrary class to the tooltip
     * @param hookTo either 'element' or 'pointer'
     * @param updateOnShow Load the tooltip content from the "title" element passed.
	 */
	simpleTooltip : function(element, title, position, className, hookTo, updateOnShow){
        if(!position) position = 'bottom right';
        if(!hookTo) hookTo = 'pointer';
		element.observe("mouseover", function(event){
			if(!this.tooltip){
				this.tooltip = new Element("div", {className:"simple_tooltip"});
                if(className) this.tooltip.addClassName(className);
				$$('body')[0].insert(this.tooltip);
			}else{
                this.tooltip.writeAttribute('class', '');
                this.tooltip.addClassName('simple_tooltip');
                if(className) this.tooltip.addClassName(className);
            }
            if(updateOnShow){
                this.tooltip.update(title.cloneNode(true));
            }else if(element.readAttribute('data-simpleTooltipTitle')){
                this.tooltip.update(element.readAttribute('data-simpleTooltipTitle'));
            }else{
                this.tooltip.update(title);
            }
            var baseX = hookTo == "element" ? Element.cumulativeOffset(element).left : Event.pointerX(event);
            var baseY = hookTo == "element" ? Element.cumulativeOffset(element).top : Event.pointerY(event);
            if(hookTo == 'element'){
                baseY -= Element.cumulativeScrollOffset(element).top;
            }
            var y = baseY+10;
            if(position.indexOf('middle') != -1){
                y -= 10 + parseInt(this.tooltip.getHeight())/2 - parseInt(element.getHeight())/2 ;
            }else if(position.indexOf('bottom') != -1){
                y -= 13 + parseInt(element.getHeight());
            }else if(position.indexOf('top') != -1){
                y -= 13 + parseInt(this.tooltip.getHeight());
            }

            var x;
            if(position.indexOf('center') != -1){
                x = baseX - (this.tooltip.getWidth() - element.getWidth())/2;
                if(x < 0){
                    x = (baseX);
                    this.tooltip.addClassName("arrow_tip_arrow_left");
                }
            }else if(position.indexOf('right') != -1){
                x = baseX + 10 + parseInt(element.getWidth());
            }else{
                x = (baseX - 10 - parseInt(this.tooltip.getWidth()));
            }

			this.tooltip.setStyle({top:y+"px",left:x+"px"});
			if(this.tipTimer){
				window.clearTimeout(this.tipTimer);
			}
            element.addClassName("simple_tooltip_observer_active");
			this.tooltip.show();
		}.bind(this) );
		element.observe("mouseout", function(event){
			if(!this.tooltip) return;
			this.tipTimer = window.setTimeout(function(){
				this.tooltip.hide();
			}.bind(this), 200);
            element.removeClassName("simple_tooltip_observer_active");
		}.bind(this) );
        element.addClassName("simple_tooltip_observer");
	},
	/**
	 * Close the Message
	 */
	closeMessageDiv: function(){
		if(this.messageDivOpen)
		{
			new Effect.MessageFade(this.messageBox);
			this.messageDivOpen = false;
		}
	},
	/**
	 * Timer for automatically closing the message
	 */
	tempoMessageDivClosing: function(){
		this.messageDivOpen = true;
        if(this.closeTimer) window.clearTimeout(this.closeTimer);
		this.closeTimer = window.setTimeout(function(){this.closeMessageDiv();}.bind(this), 6000);
	},
	/**
	 * Display a user message (notice or error)
	 * @param messageType String ERROR or SUCCESS
	 * @param message String Content of the message
	 */
	displayMessage: function(messageType, message){
		if(!this.messageBox){
			this.messageBox = new Element("div", {title:MessageHash[98],id:"message_div",className:"messageBox"});
			$(document.body).insert(this.messageBox);
			this.messageContent = new Element("div", {id:"message_content"});
			this.messageBox.update(this.messageContent);
			this.messageBox.observe("click", this.closeMessageDiv.bind(this));
		}
		message = message.stripScripts();
		message = message.replace(new RegExp("(\\n)", "g"), "<br>");
		if(messageType == "ERROR"){ this.messageBox.removeClassName('logMessage');  this.messageBox.addClassName('errorMessage');}
		else { this.messageBox.removeClassName('errorMessage');  this.messageBox.addClassName('logMessage');}
        if(this.messageDivOpen){
            if(this.messageContent.innerHTML.indexOf(message) === -1){
                this.messageContent.insert('<br>' + message);
            }
            this.tempoMessageDivClosing();
            return;
        }else{
    		this.messageContent.update(message);
        }

        var container;
        if(ajaxplorer.getMessageBoxReference()){
            container = ajaxplorer.getMessageBoxReference();
        }else if($('content_pane')) {
            container = $('content_pane');
        }else {
			container = $(ajxpBootstrap.parameters.get("MAIN_ELEMENT"));
		}
		var containerOffset = Position.cumulativeOffset(container);
		var containerDimensions = container.getDimensions();
		var boxWidth = parseInt(containerDimensions.width * 90/100);
		var leftPosition = containerOffset[0] + parseInt(containerDimensions.width*5/100);
		this.messageBox.setStyle({
			bottom:'20px',
			left:leftPosition+'px',
			width:boxWidth+'px'
		});
		new Effect.MessageAppear(this.messageBox);
        if(window.console){
            if(messageType == 'ERROR') window.console.error(message);
            else window.console.info(message);
        }
		this.tempoMessageDivClosing();
	},
	/**
	 * Bootloader helper. Sets total steps
	 * @param count Integer
	 */
	setLoadingStepCounts: function(count){
		this.loadingStepsCount = count;
		this.loadingStep = count;
	},
	
	/**
	 * Bootload helper. Increment total steps 
	 * @param add Integer
	 */
	incrementStepCounts: function(add){
		this.loadingStepsCount += add;
		this.loadingStep += add;
	},
	/**
	 * Bootloader helper
	 * @param state Integer Current loading step
	 */
	updateLoadingProgress: function(state){
		this.loadingStep --;
		var percent = (1 - (this.loadingStep / this.loadingStepsCount));
        document.fire("ajaxplorer:loader_state_update", {percent:parseFloat(percent)});
		if(state && $('progressState')){
			$('progressState').update(state);
		}
		if(this.loadingStep == 0){
			this.pageLoading = false;
		}
	},
	/**
	 * Callback to be called on close
	 * @param func Function
	 */
	setCloseValidation : function(func){
		this.closeValidation = func;
	},
	
	/**
	 * Callback to be called on close
	 * @param func Function
	 */
	setCloseAction: function(func){
		this.closeFunction = func;
	},
	
	/**
	 * Close action. Remove shadow if any, call close callback if any.
	 */
	close: function(){	
		if(this.closeFunction){
			 this.closeFunction();
			 //this.closeFunction = null;
		}
		if(this.currentResizeListener){
			Event.stopObserving(window, "resize", this.currentResizeListener);
            this.currentResizeListener = null;
		}
	}
});
	
var modal = new Modal();
