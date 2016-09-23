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
 * The latest code can be found at <https://pydio.com>.
 */
Class.create("TextEditor", AbstractEditor, {

	initialize: function($super, oFormObject, options)
	{
		$super(oFormObject, options);
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
	},
	
	
	open : function($super, nodeOrNodes){
		$super(nodeOrNodes);
		var fileName = nodeOrNodes.getPath();
		var textarea;
		this.textareaContainer = new Element('div');
		this.textarea = new Element('textarea', {
            id:'content',
            name:'content',
            style:'margin:0; border:0; width: 100%;',
            className:'dialogFocus editor',
            wrap: 'off'
        });
		this.currentUseCp = false;
		this.contentMainContainer = this.textarea;
		if(!this.canWrite){
			this.textarea.readOnly = true;
		}
		this.element.appendChild(this.textareaContainer);
		this.textareaContainer.appendChild(this.textarea);
		fitHeightToBottom($(this.textarea), $(this.editorOptions.context.elementName));
		// LOAD FILE NOW
		this.loadFileContent(fileName);
		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.textarea, "vertical");
		}
        this.element.observeOnce("editor:close", function(){
            //ajaxplorer.fireNodeRefresh(nodeOrNodes);
        });
        this.textarea.observe("focus", function(){
            pydio.UI.disableAllKeyBindings()
        });
        this.textarea.observe("blur", function(){
            pydio.UI.enableAllKeyBindings()
        });
        this.element.down('.action_bar').addClassName('full_width_action_bar');
	},
	
	loadFileContent : function(fileName){
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_content');
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
        
        this.setOnLoad(this.textareaContainer);
        PydioApi.getClient().postPlainTextContent(this.inputNode.getPath(), this.textarea.value, function(success){
            if(success){
                this.setModified(false);
            }
            this.removeOnLoad(this.textareaContainer)
        }.bind(this));
        
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