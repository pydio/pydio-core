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
Class.create("EtherpadLauncher", AbstractEditor, {

    padID:null,
    sessionID: null,
    node: null,

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
			ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=download&file='+this.currentFile);
			return false;
		}.bind(this));
	},
	
	
	open : function($super, nodeOrNodes){
		$super(nodeOrNodes);
        this.node = nodeOrNodes;
		var fileName = nodeOrNodes.getPath();
        var conn = new Connexion();
        conn.addParameter("get_action", "etherpad_create");
        conn.addParameter("file", fileName);
        conn.onComplete = function(transport){
            var data = transport.responseJSON;
            this.padID = data.padID;
            this.sessionID = data.sessionID;
            $("ether_box").down("#ether_box_frame").src = data.url;
            fitHeightToBottom($("ether_box").down("#ether_box_frame"));
        }.bind(this);
        conn.sendAsync();

		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.textarea, "vertical");
		}
        this.element.observeOnce("editor:close", function(){
            var conn = new Connexion();
            conn.addParameter("get_action", "etherpad_close");
            conn.addParameter("file", this.node.getPath());
            conn.addParameter("pad_id", this.padID);
            conn.addParameter("session_id", this.sessionID);
            conn.sendAsync();
        });
	},
	

	saveFile : function(){
        if(!this.padID) return;
        var conn = new Connexion();
        conn.addParameter("get_action", "etherpad_save");
        conn.addParameter("file", this.node.getPath());
        conn.addParameter("pad_id", this.padID);
        conn.sendAsync();
	}
	
});