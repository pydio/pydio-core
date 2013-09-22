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
			this.actions.get("saveButton").observe('click', function(){
				this.saveFile();
				return false;
			}.bind(this));		
		}else{
			this.canWrite = false;
			this.actions.get("saveButton").hide();
		}
        if(options.context.__className == "Modal"){
            this.actions.get("downloadFileButton").observe('click', function(){
                if(!this.currentFile) return;
                ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=download&file='+this.currentFile);
                return false;
            }.bind(this));
        }else{
            this.actions.get("downloadFileButton").hide();
        }
	},

    initEmptyPadPane : function(form){

        var selector = form.down('[name="pad_list"]');
        var textInput = form.down('[name="new_pad_name"]');
        var joinButton = form.down('#join_pad');
        fitHeightToBottom(form.down("#ether_frame"), null, null, true);

        var con = new Connexion();
        con.setParameters(new Hash({
            get_action: 'etherpad_proxy_api',
            api_action: 'list_pads'
        }));
        con.onComplete = function(transport){
            var pads = $A(transport.responseJSON.padIDs);
            var frees = $A();
            var files = $A();
            pads.each(function(el){
                var label = el.split('$').pop();
                if(label.startsWith('FREEPAD__')){
                    frees.push(label);
                }else{
                    files.push(label);
                }
            });
            selector.insert(new Element('optgroup',{label:MessageHash['etherpad.5']}));
            frees.each(function(e){
                selector.insert(new Element('option', {value:e}).update(e.replace('FREEPAD__', '')));
            });
            selector.insert(new Element('option', {value:-1}).update(MessageHash['etherpad.9']));

            if(files.size()){
                selector.insert(new Element('optgroup', {label:MessageHash['etherpad.6']}));
                files.each(function(e){
                    selector.insert(new Element('option', {value:e}).update(e));
                });
            }

            selector.observe("change", function(){
                textInput[(selector.getValue() == -1)?'show':'hide']();
            });
            textInput[(selector.getValue() == -1)?'show':'hide']();
        };
        con.sendAsync();

        joinButton.observe("click", function(){

            var conn = new Connexion();
            conn.addParameter("get_action", "etherpad_create");
            if(selector.getValue() == -1){
                conn.addParameter("pad_name", textInput.getValue());
                conn.addParameter("pad_type", 'free');
            }else{
                conn.addParameter("pad_name", selector.getValue());
            }
            conn.onComplete = function(transport){
                var data = transport.responseJSON;
                $("etherpad_container").down("#ether_frame").src = data.url;
            }
            conn.sendAsync();
        });

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

        this.element.observe("editor:enterFSend", function(){
            fitHeightToBottom($("ether_box").down("#ether_box_frame"));
        });
        this.element.observe("editor:resize", function(){
            fitHeightToBottom($("ether_box").down("#ether_box_frame"));
        });
        this.element.observe("editor:exitFSend", function(){
            fitHeightToBottom($("ether_box").down("#ether_box_frame"));
        });

        this.element.observeOnce("editor:close", function(){
            var conn = new Connexion();
            conn.addParameter("get_action", "etherpad_close");
            conn.addParameter("file", this.node.getPath());
            conn.addParameter("pad_id", this.padID);
            conn.addParameter("session_id", this.sessionID);
            conn.sendAsync();
        }.bind(this));
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