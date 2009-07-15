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
 * Description : The "online edition" manager, encapsulate the CodePress highlighter for some extensions.
 */
Editor = Class.create({

	initialize: function(oFormObject)
	{
		this.oForm = $(oFormObject);
		this.actionBar = this.oForm.select('.action_bar')[0];		
		this.closeButton = oFormObject.select('a[id="closeButton"]')[0];
		this.saveButton = oFormObject.select('a[id="saveButton"]')[0];
		this.downloadButton = oFormObject.select('a[id="downloadFileButton"]')[0];
		this.ficInput = oFormObject.select('input[name="file"]')[0];
		this.repInput = oFormObject.select('input[name="dir"]')[0];	

		this.fsButton = oFormObject.select('a[id="fsButton"]')[0];
		this.nofsButton = oFormObject.select('a[id="nofsButton"]')[0];
		this.fsButton.onclick = function(){
			this.setFullScreen();
			this.fsButton.hide();
			this.nofsButton.show();
			return false;
		}.bind(this);
		this.nofsButton.onclick = function(){
			this.exitFullScreen();
			this.nofsButton.hide();
			this.fsButton.show();
			return false;
		}.bind(this);
		
		this.closeButton.observe('click', function(){
			if(this.modified && !window.confirm(MessageHash[201])){
					return false;
			}
			if(this.fullscreenMode) this.exitFullScreen();
			this.close();
			hideLightBox(true);
			return false;
		}.bind(this));
		this.saveButton.observe('click', function(){
			this.saveFile();
			return false;
		}.bind(this));
		this.downloadButton.observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload('content.php?action=download&file='+this.currentFile);
			return false;
		}.bind(this));
		modal.setCloseAction(function(){this.close();}.bind(this));
	},
	
	
	createEditor : function(fileName){
	
		var cpStyle = editWithCodePress(getBaseName(fileName));
		var textarea;
		this.textareaContainer = document.createElement('div');
		this.textarea = $(document.createElement('textarea'));
		if(cpStyle != "")
		{
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = hidden.id = 'code';		
			this.oForm.appendChild(hidden);
			this.textarea.name = this.textarea.id = 'cpCode';
			$(this.textarea).addClassName('codepress');
			$(this.textarea).addClassName(cpStyle);
			$(this.textarea).addClassName('linenumbers-on');
			this.currentUseCp = true;
			this.fsButton.setStyle({display:"none"});
		}
		else
		{
			this.textarea.name =  this.textarea.id = 'code';
			this.textarea.addClassName('dialogFocus');
			this.textarea.addClassName('editor');
			this.currentUseCp = false;
		}
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		this.oForm.appendChild(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom($(this.textarea), $(modal.elementName), 5, true);
	},
	
	loadFile : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'edit');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){this.parseTxt(transp);}.bind(this);
		this.changeModifiedStatus(false);
		this.setOnLoad();
		connexion.sendAsync();
	},
	
	saveFile : function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'edit');
		connexion.addParameter('save', '1');
		var value;
		if(this.currentUseCp) value = this.oForm.select('iframe')[0].getCode();
		else value = this.textarea.value;
		connexion.addParameter('code', value);
		connexion.addParameter('file', this.ficInput.value);
		connexion.addParameter('dir', this.repInput.value);	
		connexion.onComplete = function(transp){this.parseXml(transp);}.bind(this);
		this.setOnLoad();
		connexion.setMethod('put');
		connexion.sendAsync();
	},
	
	parseXml : function(transport){
		//alert(transport.responseText);
		this.changeModifiedStatus(false);
		this.removeOnLoad();
	},
	
	parseTxt : function(transport){	
		this.textarea.value = transport.responseText;
		var contentObserver = function(el, value){
			this.changeModifiedStatus(true);
		}.bind(this);
		if(this.currentUseCp) {
			this.textarea.id = 'cpCode_cp';
			code = new CodePress(this.textarea, contentObserver);
			this.cpCodeObject = code;
			this.textarea.parentNode.insertBefore(code, this.textarea);
		}
		else{
			new Form.Element.Observer(this.textarea, 0.2, contentObserver);
		}
		this.removeOnLoad();
		
	},
	
	changeModifiedStatus : function(bModified){
		this.modified = bModified;
		var crtTitle = modal.dialogTitle.select('span.titleString')[0];
		if(this.modified){
			this.saveButton.removeClassName('disabled');
			if(crtTitle.innerHTML.charAt(crtTitle.innerHTML.length - 1) != "*"){
				crtTitle.innerHTML  = crtTitle.innerHTML + '*';
			}
		}else{
			this.saveButton.addClassName('disabled');
			if(crtTitle.innerHTML.charAt(crtTitle.innerHTML.length - 1) == "*"){
				crtTitle.innerHTML  = crtTitle.innerHTML.substring(0, crtTitle.innerHTML.length - 1);
			}		
		}
		// ADD / REMOVE STAR AT THE END OF THE FILENAME
	},
	
	setOnLoad : function(){	
		addLightboxMarkupToElement(this.textareaContainer);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+"/images/loadingImage.gif";
		$(this.textareaContainer).select("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	
	removeOnLoad : function(){
		removeLightboxFromElement(this.textareaContainer);
		this.loading = false;	
	},
	
	close : function(){
		if(this.currentUseCp){
			this.cpCodeObject.close();
			modal.clearContent(modal.dialogContent);		
		}
	},
	
	setFullScreen: function(){
		this.oForm.absolutize();
		$(document.body).insert(this.oForm);
		this.oForm.setStyle({
			top:0,
			left:0,
			backgroundColor:'#fff',
			width:'100%',
			height:document.viewport.getHeight(),
			zIndex:3000});
		this.actionBar.setStyle({marginTop: 0});
		if(!this.currentUseCp){
			this.origContainerHeight = this.textarea.getHeight();
			this.heightObserver = fitHeightToBottom(this.textarea, this.oForm, 0, true);
		}else{
			
		}		
		var listener = this.fullScreenListener.bind(this);
		Event.observe(window, "resize", listener);
		this.oForm.observe("fullscreen:exit", function(e){
			Event.stopObserving(window, "resize", listener);
			//Event.stopObserving(window, "resize", this.heightObserver);
		}.bind(this));		
		this.fullscreenMode = true;
	},
	
	exitFullScreen: function(){
		this.oForm.relativize();
		$$('.dialogContent')[0].insert(this.oForm);
		this.oForm.setStyle({top:0,left:0,zIndex:100});
		this.actionBar.setStyle({marginTop: -10});
		this.oForm.fire("fullscreen:exit");
		if(!this.currentUseCp){
			this.textarea.setStyle({height:this.origContainerHeight});
		}else{
			
		}		
		this.fullscreenMode = false;
	},
	
	fullScreenListener : function(){
		this.oForm.setStyle({
			height:document.viewport.getHeight()
		});
		if(!this.currentUseCp) {fitHeightToBottom(this.textarea, this.oForm, 0, true);}
	}
	
});