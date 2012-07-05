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
            height:'257px',
            position:'relative'
        });
        this.container = this.element;
		if(!$('historydownload_iframe')){
			$('hidden_frames').insert('<iframe id="historydownload_iframe" name="historydownload_iframe" style="display:none"></iframe>');
		}

        this.dlAction = new Action({
            name: "dl_history",
            text: "Download version",
            title: "Download selected file version on your computer",
            callback: this.dlActionCallback.bind(this)
        });
        this.revertAction = new Action({
            name: "revert_history",
            text: "Revert",
            title: "Directly revert the file to the selected version",
            callback: this.revertActionCallback.bind(this)
        });
        this.toolbarObject = new ActionsToolbar(this.toolbar, {
            buttonRenderer : 'this',
            skipBubbling: true,
            toolbarsList : $A(['history'])
        });
        this.toolbar.insert(this.toolbarObject.renderToolbarAction(this.dlAction));
        this.toolbar.insert(this.toolbarObject.renderToolbarAction(this.revertAction));
        this.toolbarObject.resize();
        this.dlAction.show();
        this.dlAction.disable();
        this.revertAction.show();
        this.revertAction.disable();

        this.versionsDm = new AjxpDataModel(true);
        this.versionsRoot = new AjxpNode("/", false, "Versions", "folder.png");

        this.versionsDm.observe("selection_changed", function(event){
            var dm = event.memo;
            var selection = dm.getSelectedNodes();
            if(selection.length) {
                this.dlAction.enable();
                this.revertAction.enable();
            }
            else {
                this.dlAction.disable();
                this.revertAction.disable();
            }
        }.bind(this));

	},
	
	open: function(currentRep){
		var selection = ajaxplorer.getUserSelection();
		if(currentRep || selection.isEmpty()){
			var ajxpNode = ajaxplorer.getContextNode();
		}else{
			var ajxpNode = selection.getUniqueNode();
		}
		this.fileName = ajxpNode.getPath();
		this.isFile = ajxpNode.isLeaf();
        this.currentFileMetadata = ajxpNode.getMetadata();

        var provider = new RemoteNodeProvider();
        provider.initProvider(
            {get_action:'git_history',file:ajxpNode.getPath()}
        );
        this.versionsDm.setRootNode(this.versionsRoot);
        this.versionsDm.setAjxpNodeProvider(provider);

        this.versionsDm.requireContextChange(this.versionsRoot, true);



        if(this.isFile){
            this.filesList = new FilesList(this.element, {
                dataModel:this.versionsDm,
                columnsDef:[{attributeName:"ajxp_label", messageId:1, sortType:'String'},
                            {attributeName:"ajxp_modiftime", messageId:4, sortType:'String'},
                            {attributeName:"MESSAGE", messageString:'Author', sortType:'String'},
                            {attributeName:"EVENT", messageString:'Event', sortType:'String'}
                ],
                defaultSortTypes:["String", "Date", "String", "String"],
                columnsTemplate:"history_file",
                selectable: true,
                draggable: false,
                replaceScroller:true
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
                replaceScroller:true
            });
        }
    },

    dlActionCallback: function(){

    },

    revertActionCallback: function(){
        var conf = window.confirm("Are you sure you want to revert? This will create a new version anyway.");
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