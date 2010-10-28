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
Class.create("AjxpCkEditor", TextEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.editorConfig = {
			resize_enabled:false,
			toolbar : "Ajxp",
			filebrowserBrowseUrl : 'index.php?external_selector_type=ckeditor',
			// IF YOU KNOW THE RELATIVE PATH OF THE IMAGES (BETWEEN REPOSITORY ROOT AND REAL FILE)
			// YOU CAN PASS IT WITH THE relative_path PARAMETER. FOR EXAMPLE : 
			//filebrowserBrowseUrl : 'index.php?external_selector_type=ckeditor&relative_path=files',
			filebrowserImageBrowseUrl : 'index.php?external_selector_type=ckeditor',
			filebrowserFlashBrowseUrl : 'index.php?external_selector_type=ckeditor',
			language : ajaxplorer.currentLanguage,
			fullPage : true,
			toolbar_Ajxp : [
				['Source','Preview','Templates'],
			    ['Undo','Redo','-', 'Cut','Copy','Paste','PasteText','PasteFromWord','-','Print', 'SpellChecker', 'Scayt'],
			    ['Find','Replace','-','SelectAll','RemoveFormat'],
			    ['Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'],
			    '/',
			    ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
			    ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote'],
			    ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
			    ['Link','Unlink','Anchor'],
			    ['Image','Flash','Table','HorizontalRule','Smiley','SpecialChar','PageBreak'],
			    '/',
			    ['Styles','Format','Font','FontSize'],
			    ['TextColor','BGColor'],
			    ['Maximize', 'ShowBlocks','-','About']			
			]
				
		};
	},
	
	
	open : function($super, userSelection){
		this.userSelection = userSelection;
		var fileName = userSelection.getUniqueFileName();
		var textarea;
		this.textareaContainer = new Element('div');
		this.textarea = new Element('textarea');
		this.textarea.name =  this.textarea.id = 'content';
		this.contentMainContainer = this.textareaContainer;
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		this.element.insert(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom(this.textareaContainer, $(modal.elementName));
		this.reloadEditor('content');
		this.element.observe("editor:close", function(){
			CKEDITOR.instances.content.destroy();
		});		
		this.element.observe("editor:resize", function(event){
			this.resizeEditor();
		}.bind(this));
		var destroy = function(){
			if(CKEDITOR.instances.content){
				this.textarea.value = CKEDITOR.instances.content.getData();
				CKEDITOR.instances.content.destroy();			
			}				
		};
		var reInit  = function(){
			CKEDITOR.replace('content', this.editorConfig);
			this.resizeEditor();				
		}
		this.element.observe("editor:enterFS", destroy.bind(this));
		this.element.observe("editor:enterFSend", reInit.bind(this));
		this.element.observe("editor:exitFS", destroy.bind(this));
		this.element.observe("editor:exitFSend", reInit.bind(this));
		this.element.observe("editor:modified", function(e){
			if(!this.isModified){
				this.launchModifiedObserver();
			}
		}.bind(this) );
		// LOAD FILE NOW
		window.setTimeout(this.resizeEditor.bind(this), 700);
		this.loadFileContent(fileName);		
		return;
		
	},
	
	launchModifiedObserver : function(){
		// OBSERVE CHANGES
		this.observerInterval = window.setInterval(function(){
			if(this.isModified || !CKEDITOR.instances.content) return;
			var currentData =  CKEDITOR.instances.content.getData();
			if(!this.prevData) {
				this.prevData = currentData;
				return;
			}
			if(this.prevData != currentData){
				this.setModified(true);
				window.clearInterval(this.observerInterval);
			}
			this.prevData = currentData;
		}.bind(this), 500);
		this.element.observe("editor:close", function(){
			window.clearInterval(this.observerInterval);
		});
	},
	
	reloadEditor : function(instanceId){
		if(!instanceId) instanceId = "code";
		if(CKEDITOR.instances[instanceId]){
			this.textarea.value = CKEDITOR.instances[instanceId].getData();
			CKEDITOR.instances[instanceId].destroy();			
		}
		CKEDITOR.replace(instanceId, this.editorConfig);
	},
	
	resizeEditor : function(){
		var width = this.contentMainContainer.getWidth()-(Prototype.Browser.IE?0:12);		
		var height = this.contentMainContainer.getHeight();
		CKEDITOR.instances.content.resize(width,height);
	},
			
	saveFile : function(){
		var connexion = this.prepareSaveConnexion();
		var value = CKEDITOR.instances.content.getData();
		this.textarea.value = value;		
		connexion.addParameter('content', value);
		connexion.sendAsync();
	},
		
	parseTxt : function(transport){	
		this.textarea.value = transport.responseText;
		CKEDITOR.instances.content.setData(transport.responseText);
		this.removeOnLoad(this.textareaContainer);
		this.setModified(false);
	}

	
});