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
Editor = Class.create(AbstractEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.actions.get("saveButton").observe('click', function(){
			this.saveFile();
			return false;
		}.bind(this));
		this.actions.get("downloadFileButton").observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload('content.php?action=download&file='+this.currentFile);
			return false;
		}.bind(this));
	},
	
	
	open : function($super, userSelection, filesList){
		$super(userSelection, filesList);
		var fileName = userSelection.getUniqueFileName();
		// CREATE GUI
		var cpStyle = editWithCodePress(getBaseName(fileName));
		var textarea;
		this.textareaContainer = document.createElement('div');
		this.textarea = $(document.createElement('textarea'));
		if(cpStyle != "")
		{
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = hidden.id = 'code';		
			this.element.appendChild(hidden);
			this.textarea.name = this.textarea.id = 'cpCode';
			$(this.textarea).addClassName('codepress');
			$(this.textarea).addClassName(cpStyle);
			$(this.textarea).addClassName('linenumbers-on');
			this.currentUseCp = true;
			this.contentMainContainer = this.textarea.parentNode;
			this.element.observe("editor:resize", function(event){
				var cpIframe = $(this.contentMainContainer).select('iframe')[0];
				if(!cpIframe) return;
				if(event.memo && Object.isNumber(event.memo)){
					cpIframe.setStyle({height:event.memo});
				}else{
					cpIframe.setStyle({width:'100%'});
					fitHeightToBottom(cpIframe, this.element, 0, true);
				}
			}.bind(this));
			this.element.observe("editor:enterFS", function(e){this.textarea.value = this.element.select('iframe')[0].getCode();}.bind(this) );
			this.element.observe("editor:exitFS", function(e){this.textarea.value = this.element.select('iframe')[0].getCode();}.bind(this) );
		}
		else
		{
			this.textarea.name =  this.textarea.id = 'code';
			this.textarea.addClassName('dialogFocus');
			this.textarea.addClassName('editor');
			this.currentUseCp = false;
			this.contentMainContainer = this.textarea;
		}
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		this.element.appendChild(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom($(this.textarea), $(modal.elementName), 0, true);
		// LOAD FILE NOW
		this.loadFileContent(fileName);
	},
	
	loadFileContent : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'edit');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			this.parseTxt(transp);
			this.updateTitle(getBaseName(fileName));
		}.bind(this);
		this.setModified(false);
		this.setOnLoad(this.textareaContainer);
		connexion.sendAsync();
	},
	
	saveFile : function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'edit');
		connexion.addParameter('save', '1');
		var value;
		if(this.currentUseCp){
			value = this.element.select('iframe')[0].getCode();
			this.textarea.value = value;
		}else{			
			value = this.textarea.value;
		}
		connexion.addParameter('code', value);
		connexion.addParameter('file', this.userSelection.getUniqueFileName());
		connexion.addParameter('dir', this.userSelection.getCurrentRep());	
		connexion.onComplete = function(transp){
			this.parseXml(transp);			
		}.bind(this);
		this.setOnLoad(this.textareaContainer);
		connexion.setMethod('put');
		connexion.sendAsync();
	},
	
	parseXml : function(transport){
		if(parseInt(transport.responseText).toString() == transport.responseText){
			alert("Cannot write the file to disk (Error code : "+transport.responseText+")");
		}else{
			this.setModified(false);
		}
		this.removeOnLoad(this.textareaContainer);
	},
	
	parseTxt : function(transport){	
		this.textarea.value = transport.responseText;
		var contentObserver = function(el, value){
			this.setModified(true);
		}.bind(this);
		if(this.currentUseCp) {
			this.textarea.id = 'cpCode_cp';
			code = new CodePress(this.textarea, contentObserver);
			this.cpCodeObject = code;
			this.textarea.parentNode.insertBefore(code, this.textarea);
			this.contentMainContainer = this.textarea.parentNode;
			this.element.observe("editor:close", function(){
				this.cpCodeObject.close();
				modal.clearContent(modal.dialogContent);		
			}, this );			
		}
		else{
			new Form.Element.Observer(this.textarea, 0.2, contentObserver);
		}
		this.removeOnLoad(this.textareaContainer);
		
	}
});