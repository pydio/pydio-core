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
 * Description : Simple display of SVN logs.
 */
Class.create("HistoryBrowser", {

    toolbar:null,
    element: null,

    initialize:function(form){

        this.toolbar = form.down('div.action_bar');
		this.element = form.down('[id="versions_table"]');

        this.element.up('div.dialogContent').setStyle({padding:0});
        this.element.setStyle({
            height:'347px',
            position:'relative'
        });
        this.container = this.element;
		if(!$('historydownload_iframe')){
			$('hidden_frames').insert('<iframe id="historydownload_iframe" name="historydownload_iframe" style="display:none"></iframe>');
		}

        this.dlAction = new Action({
            name: "dl_history",
            text: MessageHash['meta.git.3'],
            title: MessageHash['meta.git.4'],
            callback: function(){
                this.dlActionCallback("dl");
            }.bind(this)
        });
        this.openAction = new Action({
            name: "open_history",
            text: MessageHash['meta.git.5'],
            title: MessageHash['meta.git.6'],
            callback: function(){
                this.dlActionCallback("open");
            }.bind(this)
        });
        this.revertAction = new Action({
            name: "revert_history",
            text: MessageHash['meta.git.7'],
            title: MessageHash['meta.git.8'],
            callback: this.revertActionCallback.bind(this)
        });
        this.toolbarObject = new ActionsToolbar(this.toolbar, {
            buttonRenderer : 'this',
            skipBubbling: true,
            toolbarsList : $A(['history'])
        });
        this.toolbar.insert(this.toolbarObject.renderToolbarAction(this.revertAction));
        this.toolbar.insert("<div class='separator'></div>");
        this.toolbar.insert(this.toolbarObject.renderToolbarAction(this.dlAction));
        this.toolbar.insert(this.toolbarObject.renderToolbarAction(this.openAction));
        this.toolbarObject.resize();
        this.dlAction.show(); this.dlAction.disable();
        this.revertAction.show(); this.revertAction.disable();
        this.openAction.show(); this.openAction.disable();

        this.versionsDm = new AjxpDataModel(true);
        this.versionsRoot = new AjxpNode("/", false, "Versions", "folder.png");

        this.versionsDm.observe("selection_changed", function(event){
            var dm = event.memo;
            var selection = dm.getSelectedNodes();
            if(selection.length) {
                this.dlAction.enable();
                this.revertAction.enable();
                this.openAction.enable();
            }
            else {
                this.dlAction.disable();
                this.revertAction.disable();
                this.openAction.disable();
            }
        }.bind(this));

	},
	
	open: function(currentRep){
		var selection = ajaxplorer.getUserSelection();
		if(currentRep || selection.isEmpty()){
			this.ajxpNode = ajaxplorer.getContextNode();
		}else{
            this.ajxpNode = selection.getUniqueNode();
		}
		this.fileName = this.ajxpNode.getPath();
		this.isFile = this.ajxpNode.isLeaf();
        this.currentFileMetadata = this.ajxpNode.getMetadata();

        var provider = new RemoteNodeProvider();
        provider.initProvider(
            {get_action:'git_history',file:this.ajxpNode.getPath()}
        );
        this.versionsDm.setRootNode(this.versionsRoot);
        this.versionsDm.setAjxpNodeProvider(provider);

        this.versionsDm.requireContextChange(this.versionsRoot, true);



        if(this.isFile){
            this.filesList = new FilesList(this.element, {
                dataModel:this.versionsDm,
                columnsDef:[{attributeName:"index", messageId:'meta.git.9', sortType:'String', fixedWidth:'5'},
                            {attributeName:"ajxp_modiftime", messageId:'meta.git.10', sortType:'String', fixedWidth:"40"},
                            {attributeName:"MESSAGE", messageId:'meta.git.11', sortType:'String', fixedWidth:"20"},
                            {attributeName:"EVENT", messageId:'meta.git.12', sortType:'String', fixedWidth:"20"}//,
                            //{attributeName:"ajxp_label", messageId:1, sortType:'String'}
                ],
                defaultSortTypes:["Number", "Date", "String", "String"],
                columnsTemplate:"history_file",
                selectable: true,
                draggable: false,
                replaceScroller:true,
                displayMode: "list"
            });
        }else{
            this.filesList = new FilesList(this.element, {
                dataModel:this.versionsDm,
                columnsDef:[{attributeName:"revision", messageString:'#', sortType:'Number'},
                            {attributeName:"revision_log", messageString:'Message', sortType:'String'},
                            {attributeName:"revision_date", messageId:4, sortType:'String'},
                            {attributeName:"author", messageString:'Author', sortType:'String'},
                            {attributeName:"links", messageString:'Actions', sortType:'String'}
                ],
                defaultSortTypes:["Number", "String", "String", "String", "String"],
                columnsTemplate:"svnlog_folder",
                selectable: false,
                draggable: false,
                replaceScroller:true,
                displayMode: "list"
            });
        }
    },

    dlActionCallback: function(action){
        var connex = new Connexion();
        if(action == "dl") connex.addSecureToken();
        var selectedNode = this.versionsDm.getSelectedNodes()[0];
        var params = {
            get_action  : 'git_getfile',
            file        : selectedNode.getMetadata().get("FILE"),
            commit_id   : selectedNode.getMetadata().get("ID"),
            attach      : (action == "dl" ? "download": "inline")
           };
        var src = connex._baseUrl;
        for(var key in params){
            if(params.hasOwnProperty(key)){
                src += "&" + key + "=" + encodeURIComponent(params[key]);
            }
        }
        if(action == "dl"){
            $("historydownload_iframe").setAttribute("src", src);
        }else{
            window.open(src);
        }
    },

    revertActionCallback: function(){
        var conf = window.confirm(MessageHash["meta.git.13"]);
        if(!conf) return;
        var connex = new Connexion();
        var selectedNode = this.versionsDm.getSelectedNodes()[0];
        connex.setParameters($H({
            get_action  : 'git_revertfile',
            original_file: this.ajxpNode.getPath(),
            file        : selectedNode.getMetadata().get("FILE"),
            commit_id   : selectedNode.getMetadata().get("ID")
        }));
        connex.onComplete = function(transport){
            ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
            hideLightBox();
        };
        connex.sendAsync();
    },

	close:function(){
		
	},
	setOnLoad:function(){
		addLightboxMarkupToElement(this.container);
		var img = new Element("img", {
			src:ajxpResourcesFolder+'/images/loadingImage.gif',
			style:'margin-top:80px;'
		});
		this.container.down("#element_overlay").insert(img);		
	},
	removeOnLoad:function(){
		removeLightboxFromElement(this.container);
	}
});