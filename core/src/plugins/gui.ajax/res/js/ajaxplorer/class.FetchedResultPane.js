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

/**
 * The Search Engine abstraction.
 */
Class.create("FetchedResultPane", FilesList, {

    _rootNode : null,
    _dataLoaded : false,

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param mainElementName String
	 * @param ajxpOptions Object
	 */
	initialize: function($super, mainElementName, ajxpOptions)
	{

        var dataModel = this.initDataModel(ajxpOptions);

        $super($(mainElementName), Object.extend({
            dataModel:dataModel,
            columnsDef:[{attributeName:"ajxp_label", messageId:1, sortType:'String'},
                {attributeName:"filename", messageString:'Path', sortType:'String'},
                {attributeName:"is_file", messageString:'Type', sortType:'String', defaultVisibility:'hidden'}
            ],
            displayMode: 'detail',
            fixedDisplayMode: 'detail',
            defaultSortTypes:["String", "String", "String"],
            columnsTemplate:"search_results",
            selectable: true,
            draggable: false,
            replaceScroller:true,
            fit:'height',
            detailThumbSize:22,
            updateGlobalContext:false
        }, ajxpOptions));

        if(this.options.updateGlobalContext){
            dataModel.observe("selection_changed", function(){
                if(!this._dataLoaded) return;
                var selectedNodes = this._dataModel.getSelectedNodes();
                if(selectedNodes){
                    ajaxplorer.getContextHolder().setSelectedNodes(selectedNodes, this);
                }
            }.bind(this));
        }else{
            dataModel.observe("selection_changed", function(){
                if(!this._dataLoaded) return;
                var selectedNode = this._dataModel.getSelectedNodes()[0];
                if(selectedNode) ajaxplorer.goTo(selectedNode);
            }.bind(this));
        }

        document.observe("ajaxplorer:repository_list_refreshed", function(){
            this._rootNode.clear();
            this._dataLoaded = false;
            if(this.htmlElement && this.htmlElement.visible()){
                this.showElement(true);
            }
        }.bind(this));

        this.hiddenColumns.push("is_file");
        this._sortableTable.sort(2, false);

        mainElementName.addClassName('class-FetchedResultPane');

        if(ajxpOptions.reloadOnServerMessage){
            ajaxplorer.observe("server_message", function(event){
                var newValue = XPathSelectSingleNode(event, ajxpOptions.reloadOnServerMessage);
                if(newValue) this.reloadDataModel();
            }.bind(this));
        }

        //ajaxplorer.registerFocusable(this);

    },

    reloadDataModel: function(){
        if(this._dataLoaded){
            this._rootNode.clear();
            this._dataLoaded = false;
            if(this.htmlElement && this.htmlElement.visible()){
                this._dataModel.requireContextChange(this._rootNode, true);
                this._dataLoaded = true;
            }
        }
    },

    /**
     * Can be overriden by the children.
     * @param ajxpOptions
     * @returns {AjxpDataModel}
     */
    initDataModel: function(ajxpOptions){

        var dataModel = new AjxpDataModel(true);
        var rNodeProvider = new RemoteNodeProvider();
        dataModel.setAjxpNodeProvider(rNodeProvider);
        rNodeProvider.initProvider(ajxpOptions.nodeProviderProperties);
        this._rootNode = new AjxpNode("/", false, "Results", "folder.png", rNodeProvider);
        dataModel.setRootNode(this._rootNode);
        return dataModel;

    },


	/**
	 * Show/Hide the widget
	 * @param show Boolean
	 */
	showElement : function(show){
		if(!this.htmlElement) return;
		if(show && !this._dataLoaded) {
            // Load root node and trigger refresh event
            this._dataModel.requireContextChange(this._rootNode, true);
            this._dataLoaded = true;
        }
        if(show) {
            if(this._dataModel.getSelectedNodes()){
                this._dataModel.publish("selection_changed", this._dataModel);
            }
            this.htmlElement.show();
        } else {
            this._dataModel.setSelectedNodes($A());
            this.htmlElement.hide();
        }
	},

    getActions : function(){

    }

});