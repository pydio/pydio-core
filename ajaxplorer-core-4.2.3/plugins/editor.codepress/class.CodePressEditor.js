/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
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
		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.textarea, "vertical");
		}				
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