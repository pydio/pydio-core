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
Class.create("TextEditor", AbstractEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		if(!ajaxplorer.user || ajaxplorer.user.canWrite()){
			this.canWrite = true;
			this.actions.get("saveButton").observe('click', function(){
				this.saveFile();
				return false;
			}.bind(this));		
		}else{
			this.canWrite = false;
			this.actions.get("saveButton").hide();
		}
		this.actions.get("downloadFileButton").observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload('content.php?action=download&file='+this.currentFile);
			return false;
		}.bind(this));
	},
	
	
	open : function($super, userSelection){
		$super(userSelection);
		var fileName = userSelection.getUniqueFileName();
		var textarea;
		this.textareaContainer = document.createElement('div');
		this.textarea = $(document.createElement('textarea'));
		this.textarea.name =  this.textarea.id = 'code';
		this.textarea.addClassName('dialogFocus');
		this.textarea.addClassName('editor');
		this.currentUseCp = false;
		this.contentMainContainer = this.textarea;
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		if(!this.canWrite){
			this.textarea.readOnly = true;
		}
		this.element.appendChild(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom($(this.textarea), $(modal.elementName));
		// LOAD FILE NOW
		this.loadFileContent(fileName);
	},
	
	loadFileContent : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'open_with');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			this.parseTxt(transp);
			this.updateTitle(getBaseName(fileName));
		}.bind(this);
		this.setModified(false);
		this.setOnLoad(this.textareaContainer);
		connexion.sendAsync();
	},
	
	prepareSaveConnexion : function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'open_with');
		connexion.addParameter('save', '1');
		connexion.addParameter('file', this.userSelection.getUniqueFileName());
		connexion.addParameter('dir', this.userSelection.getCurrentRep());	
		connexion.onComplete = function(transp){
			this.parseXml(transp);			
		}.bind(this);
		this.setOnLoad(this.textareaContainer);
		connexion.setMethod('put');		
		return connexion;
	},
	
	saveFile : function(){
		var connexion = this.prepareSaveConnexion();
		connexion.addParameter('code', this.textarea.value);		
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
		if(this.canWrite){
			var contentObserver = function(el, value){
				this.setModified(true);
			}.bind(this);
			new Form.Element.Observer(this.textarea, 0.2, contentObserver);
		}
		this.removeOnLoad(this.textareaContainer);
		
	}
});