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
Class.create("AjxpCkEditor", TextEditor, {

    editorInstanceId:null,

	initialize: function($super, oFormObject, options)
	{
		$super(oFormObject, options);
        window.CKEDITOR_BASEPATH = CKEDITOR.basePath = getUrlFromBase() +"plugins/editor.ckeditor/ckeditor/";

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
		
		if(window.ajxpMobile){
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
				    ['Bold','Italic','Underline', '-', 'NumberedList','BulletedList'],
				    ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock']
				]
					
			};			
		}
	},	
	
	
	open : function($super, node){
		this.inputNode = node;
		var fileName = node.getPath();
		var textarea;
        this.editorInstanceId = slugString(node.getPath());

		this.textareaContainer = new Element('div');
		this.textarea = new Element('textarea');
		this.textarea.name =  this.textarea.id = this.editorInstanceId;
		this.contentMainContainer = this.textareaContainer;
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		this.element.insert(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		//fitHeightToBottom(this.textareaContainer, $(modal.elementName));
		this.reloadEditor(this.editorInstanceId);
		this.element.observe("editor:close", function(){
			CKEDITOR.instances[this.editorInstanceId].destroy();
        }.bind(this));
		this.element.observe("editor:resize", function(event){
			this.resizeEditor();
		}.bind(this));
		var destroy = function(){
			if(CKEDITOR.instances[this.editorInstanceId]){
				this.textarea.value = CKEDITOR.instances[this.editorInstanceId].getData();
				CKEDITOR.instances[this.editorInstanceId].destroy();
			}				
        }.bind(this);
		var reInit  = function(){
			CKEDITOR.replace(this.editorInstanceId, this.editorConfig);
			window.setTimeout(function(){
				this.resizeEditor();
				this.bindCkEditorEvents();								
			}.bind(this), 100);
        }.bind(this);
		this.element.observe("editor:enterFS", destroy.bind(this));
		this.element.observe("editor:enterFSend", reInit.bind(this));
		this.element.observe("editor:exitFS", destroy.bind(this));
		this.element.observe("editor:exitFSend", reInit.bind(this));
		// LOAD FILE NOW
		window.setTimeout(this.resizeEditor.bind(this), 400);
		this.loadFileContent(fileName);	
		this.bindCkEditorEvents();		
		if(window.ajxpMobile){
			this.setFullScreen();
		}

	},
	
	bindCkEditorEvents : function(){
		if(this.isModified) return;// useless
		
		window.setTimeout(function(){
			var editor = CKEDITOR.instances[this.editorInstanceId];
			if(!editor) {
				return;
			}
			var setModified = function(){this.setModified(true)}.bind(this);
			var keyDown = function(event){
	 			if ( !event.data.$.ctrlKey && !event.data.$.metaKey )
	 					this.setModified(true);
	 		}.bind(this);
			// We'll save snapshots before and after executing a command.
	 		editor.on( 'afterCommandExec', setModified );
	 		// Save snapshots before doing custom changes.
	 		editor.on( 'saveSnapshot', setModified );
	 		// Registering keydown on every document recreation.(#3844)
	 		editor.on( 'contentDom', function(e)
	 		{
	 			if(!e.editor.document) return;
	 			e.editor.document.on( 'keydown', keyDown);
	 		});
	 		if(editor.document){
	 			editor.document.on('keydown', keyDown);
	 		}
	 		// FIX FOR CKEDITORS > 3.4.3, THEY INSERT DOUBLE OVERLAY
	 		editor.on( 'dialogShow' , function(e) {
	 			var covers = $$("div.cke_dialog_background_cover");
	 			if(covers.length > 1){
	 				covers[0].remove();
	 			}
	 		} );
		}.bind(this), 0);
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
		if(CKEDITOR.instances[this.editorInstanceId] && CKEDITOR.instances[this.editorInstanceId].container){
            var width = this.contentMainContainer.getWidth();
            var height = this.contentMainContainer.getHeight();
            CKEDITOR.instances[this.editorInstanceId].resize(width,height);
		}
	},
			
	saveFile : function(){
		var connexion = this.prepareSaveConnexion();
		var value = CKEDITOR.instances[this.editorInstanceId].getData();
		this.textarea.value = value;		
		connexion.addParameter('content', value);
		connexion.sendAsync();
	},
		
	parseTxt : function(transport){	
		this.textarea.value = transport.responseText;
        window.setTimeout(function(){
            CKEDITOR.instances[this.editorInstanceId].setData(transport.responseText);
            this.removeOnLoad(this.textareaContainer);
            this.setModified(false);
        }.bind(this), 400);
	}

	
});