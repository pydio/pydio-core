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
Class.create("CodePressEditor", TextEditor, {

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
	},
	
	
	open : function($super, userSelection){
		this.userSelection = userSelection;
		var fileName = userSelection.getUniqueFileName();
		// CREATE GUI
		var cpStyle = this.codePressStyle(getBaseName(fileName));
		var textarea;
		this.textareaContainer = document.createElement('div');
		this.textarea = $(document.createElement('textarea'));
		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = hidden.id = 'content';		
		this.element.appendChild(hidden);
		this.textarea.name = this.textarea.id = 'cpCode';
		$(this.textarea).addClassName('codepress');
		$(this.textarea).addClassName(cpStyle);
		//$(this.textarea).addClassName('linenumbers-on');
		this.currentUseCp = true;
		this.contentMainContainer = this.textarea.parentNode;
		this.element.observe("editor:resize", function(event){
			var cpIframe = $(this.contentMainContainer).select('iframe')[0];
			if(!cpIframe) return;
			if(event.memo && Object.isNumber(event.memo)){
				cpIframe.setStyle({height:event.memo});
			}else{
				cpIframe.setStyle({width:'100%'});
				fitHeightToBottom(cpIframe, this.element);
			}
		}.bind(this));
		this.element.observe("editor:enterFS", function(e){this.textarea.value = this.element.select('iframe')[0].getCode();}.bind(this) );
		this.element.observe("editor:exitFS", function(e){this.textarea.value = this.element.select('iframe')[0].getCode();}.bind(this) );
		this.textarea.setStyle({width:'100%'});	
		this.textarea.setAttribute('wrap', 'off');	
		this.element.appendChild(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom($(this.textarea), $(modal.elementName));
		// LOAD FILE NOW
		this.loadFileContent(fileName);
	},
			
	saveFile : function(){
		var connexion = this.prepareSaveConnexion();
		var value;
		value = this.element.select('iframe')[0].getCode();
		this.textarea.value = value;		
		connexion.addParameter('content', value);
		connexion.sendAsync();
	},
		
	parseTxt : function(transport){	
		this.textarea.value = transport.responseText;
		var contentObserver = function(el, value){
			this.setModified(true);
		}.bind(this);

		this.textarea.id = 'cpCode_cp';
		code = new CodePress(this.textarea, contentObserver);
		this.cpCodeObject = code;
		this.textarea.parentNode.insertBefore(code, this.textarea);
		this.contentMainContainer = this.textarea.parentNode;
		this.element.observe("editor:close", function(){
			this.cpCodeObject.close();
			modal.clearContent(modal.dialogContent);		
		}, this );			
		this.removeOnLoad(this.textareaContainer);
				
	},

	codePressStyle : function(fileName)
	{	
		if(Prototype.Browser.Opera) return "";
		if(fileName.search('\.php$|\.php3$|\.php5$|\.phtml$') > -1) return "php";
		else if (fileName.search("\.js$") > -1) return "javascript";
		else if (fileName.search("\.java$") > -1) return "java";
		else if (fileName.search("\.pl$") > -1) return "perl";
		else if (fileName.search("\.sql$") > -1) return "sql";
		else if (fileName.search("\.htm$|\.html$|\.xml$") > -1) return "html";
		else if (fileName.search("\.css$") > -1) return "css";
		else return "";	
	}

	
});