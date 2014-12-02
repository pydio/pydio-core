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
            defaultSortTypes:["String", "String", "String", "MyDate"],
            columnsTemplate:"search_results",
            defaultSortColumn:2,
            defaultSortDescending:false,
            selectable: true,
            draggable: false,
            droppable: false,
            noContextualMenu:true,
            containerDroppableAction:null,
            emptyChildrenMessage:'',
            replaceScroller:true,
            forceClearOnRepoSwitch:false,
            fit:'height',
            detailThumbSize:22,
            updateGlobalContext:false,
            selectionChangeCallback:function(){
                if(!this._dataLoaded) return;
                var selectedNode = this._dataModel.getSelectedNodes()[0];
                if(selectedNode) ajaxplorer.goTo(selectedNode);
            }.bind(this)
        }, ajxpOptions));

        if(this.options.updateGlobalContext){
            this._registerObserver(dataModel, "selection_changed", function(){
                if(!this._dataLoaded) return;
                var selectedNodes = this._dataModel.getSelectedNodes();
                if(selectedNodes){
                    ajaxplorer.getContextHolder().setSelectedNodes(selectedNodes, this);
                }
            }.bind(this), true);
        }else if(this.options.selectionChangeCallback){
            this._registerObserver(dataModel, "selection_changed", this.options.selectionChangeCallback, true);
        }

        if(this.options.forceClearOnRepoSwitch){
            var repoSwitchObserver = function(){
                this._rootNode.clear();
                this._dataLoaded = false;
                if(this.htmlElement && this.htmlElement.visible()){
                    this.showElement(true);
                }
            }.bind(this);
            this._registerObserver(document, "ajaxplorer:repository_list_refreshed", repoSwitchObserver);
        }

        this.hiddenColumns.push("is_file");
        if(this.options.defaultSortColumn != undefined){
            this._sortableTable.sort(this.options.defaultSortColumn , this.options.defaultSortDescending);
        }

        mainElementName.addClassName('class-FetchedResultPane');

        if(ajxpOptions.reloadOnServerMessage){
            this._registerObserver(ajaxplorer, "server_message", function(event){
                var newValue = XPathSelectSingleNode(event, ajxpOptions.reloadOnServerMessage);
                if(newValue) this.reloadDataModel();
            }.bind(this), true);
            this._registerObserver(ajaxplorer, "server_message:" + ajxpOptions.reloadOnServerMessage, function(){
                this.reloadDataModel();
            }.bind(this), true);
        }

        if(ajxpOptions.containerDroppableAction){

            Droppables.add(this.htmlElement, {
                hoverclass:'droppableZone',
                accept:'ajxp_draggable',
                onDrop:function(draggable, droppable, event)
                {
                    if(draggable.getAttribute('user_selection')){
                        ajaxplorer.actionBar.fireAction(ajxpOptions.containerDroppableAction);
                    }else if(draggable.ajxpNode){
                        ajaxplorer.actionBar.fireAction(ajxpOptions.containerDroppableAction, draggable.ajxpNode);
                    }
                }
            });

        }

        //ajaxplorer.registerFocusable(this);

    },

    destroy:function($super){
        $super();
        if(this.options.containerDroppableAction){
            Droppables.remove(this.htmlElement);
        }
    },

    reloadDataModel: function(){
        if(this._dataLoaded){
            if(!this.options.silentLoading){
                this._rootNode.clear();
            }
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
     * @returns AjxpDataModel
     */
    initDataModel: function(ajxpOptions){

        var dataModel = new AjxpDataModel(true);
        var rNodeProvider = new RemoteNodeProvider();
        dataModel.setAjxpNodeProvider(rNodeProvider);
        rNodeProvider.initProvider(ajxpOptions.nodeProviderProperties);
        this._rootNode = new AjxpNode("/", false, ajxpOptions.rootNodeLabel?ajxpOptions.rootNodeLabel:"Results", "folder.png", rNodeProvider);
        dataModel.setRootNode(this._rootNode);
        if(ajxpOptions.emptyChildrenMessage){
            var emptyMessage = MessageHash[ajxpOptions.emptyChildrenMessage]?MessageHash[ajxpOptions.emptyChildrenMessage]:ajxpOptions.emptyChildrenMessage;
            this._rootNode.observe("loaded", function(){
                this.htmlElement.select('div.no-results-found').invoke('remove');
                if(!this._rootNode.getChildren().length){
                    this.htmlElement.insert({bottom:'<div class="no-results-found">'+emptyMessage+'</div>'});
                }
            }.bind(this));
        }

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
        return $H();
    }

});