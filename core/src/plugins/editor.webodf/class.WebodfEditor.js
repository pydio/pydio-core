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
Class.create("WebodfEditor", AbstractEditor, {

    padID:null,
    sessionID: null,
    node: null,

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
        // HIDE SAVE FOR THE MOMENT
        this.actions.get("saveButton").hide();

        if(options.context.__className == "Modal"){
            this.actions.get("downloadFileButton").observe('click', function(){
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
		var fileName = nodeOrNodes.getPath();

        this.contentMainContainer = this.element.down('#webodf_container');
        this.contentMainContainer.src = 'plugins/editor.webodf/frame.php?token='+Connexion.SECURE_TOKEN+'&file='+fileName;

        this.resize();
        this.element.fire("editor:updateTitle", getBaseName(this.node.getPath()));
        this.element.observe('editor:close', function(){
            this.contentMainContainer.src = '';
        }.bind(this));
		if(window.ajxpMobile){
			this.setFullScreen();
			attachMobileScroll(this.textarea, "vertical");
		}


	},
	

	saveFile : function(){
        if(!this.padID || !this.node) return;
        var conn = new Connexion();
        conn.addParameter("get_action", "etherpad_save");
        conn.addParameter("file", this.node.getPath());
        conn.addParameter("pad_id", this.padID);
        conn.sendAsync();
	}
	
});