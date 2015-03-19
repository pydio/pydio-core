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
Class.create("EtherpadLauncher", AbstractEditor, {

    padID:null,
    sessionID: null,
    node: null,

	initialize: function($super, oFormObject, options)
	{
		$super(oFormObject, options);
		if(!ajaxplorer.user || ajaxplorer.user.canWrite()){
			this.canWrite = true;
			this.actions.get("saveButton").observe('click', function(e){
                Event.stop(e);
                this.saveFile();
				return false;
			}.bind(this));		
		}else{
			this.canWrite = false;
			this.actions.get("saveButton").hide();
		}
        if(options.context.__className == "Modal"){
            this.actions.get("downloadFileButton").observe('click', function(){
                Event.stop(e);
                if(!this.currentFile) return false;
                ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=download&file='+this.currentFile);
                return false;
            }.bind(this));
        }else{
            this.actions.get("downloadFileButton").hide();
        }
	},

	open : function($super, nodeOrNodes){
		$super(nodeOrNodes);
        this.node = nodeOrNodes;
        var frame = this.element.down('#ether_box_frame');
		var fileName = nodeOrNodes.getPath();
        var conn = new Connexion();
        var extension = getFileExtension(fileName);
        if(extension == "pad"){
            // Replace 'Save' by 'Export'
            this.actions.get("saveButton").down(".actionbar_button_label").update(MessageHash['etherpad.10']);
        }
        conn.addParameter("get_action", "etherpad_create");
        conn.addParameter("file", fileName);
        conn.onComplete = function(transport){
            var data = transport.responseJSON;
            this.padID = data.padID;
            this.sessionID = data.sessionID;
            console.log(data.url + '?format=text');
            frame.src = data.url;
            fitHeightToBottom(frame, null, 25);
            if(extension != "pad"){
                this.pe = new PeriodicalExecuter(this.observeChanges.bind(this), 5);
            }
        }.bind(this);
        conn.sendAsync();

		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.textarea, "vertical");
		}

        this.element.observe("editor:enterFSend", function(){
            fitHeightToBottom(frame, null, 25);
        });
        this.element.observe("editor:resize", function(){
            fitHeightToBottom(frame, null, 25);
        });
        this.element.observe("editor:exitFSend", function(){
            fitHeightToBottom(frame, null, 25);
        });

        this.element.observeOnce("editor:close", function(){
            var conn = new Connexion();
            conn.addParameter("get_action", "etherpad_close");
            conn.addParameter("file", this.node.getPath());
            conn.addParameter("pad_id", this.padID);
            conn.addParameter("session_id", this.sessionID);
            conn.sendAsync();
            if(this.pe){
                this.pe.stop();
            }
        }.bind(this));

        this.updateTitle(getBaseName(fileName));
	},
	

	saveFile : function(){
        if(!this.padID || !this.node) return;
        var conn = new Connexion();
        conn.addParameter("get_action", "etherpad_save");
        conn.addParameter("file", this.node.getPath());
        conn.addParameter("pad_id", this.padID);
        conn.sendAsync();
        this.setModified(false);
	},

    observeChanges: function(){
        if(!this.padID || !this.node) return;
        var conn = new Connexion();
        conn.discrete = true;
        conn.onComplete = function(transport){
            var content = transport.responseText;
            if(this.previousContent && this.previousContent != content){
                this.setModified(true);
            }
            this.previousContent = content;
        }.bind(this);
        conn.addParameter("get_action", "etherpad_get_content");
        conn.addParameter("file", this.node.getPath());
        conn.addParameter("pad_id", this.padID);
        conn.sendAsync();

    }
	
});