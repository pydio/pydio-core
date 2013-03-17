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

        var dataModel = new AjxpDataModel(true);
        var rNodeProvider = new RemoteNodeProvider();
        dataModel.setAjxpNodeProvider(rNodeProvider);
        rNodeProvider.initProvider(ajxpOptions.nodeProviderProperties);
        this._rootNode = new AjxpNode("/", false, "Results", "folder.png", rNodeProvider);
        dataModel.setRootNode(this._rootNode);
        $super($(mainElementName), {
            dataModel:dataModel,
            columnsDef:[{attributeName:"ajxp_label", messageId:1, sortType:'String'},
                {attributeName:"filename", messageString:'Path', sortType:'String'}
            ],
            displayMode: 'detail',
            fixedDisplayMode: 'detail',
            defaultSortTypes:["String", "String"],
            columnsTemplate:"search_results",
            selectable: true,
            draggable: false,
            replaceScroller:true,
            fit:'height',
            detailThumbSize:22
        });

        dataModel.observe("selection_changed", function(){
            if(!this._dataLoaded) return;
             var selectedNode = this._dataModel.getSelectedNodes()[0];
             if(selectedNode) ajaxplorer.goTo(selectedNode);
         }.bind(this));

        document.observe("ajaxplorer:registry_loaded", function(){
            this._rootNode.clear();
            this._dataLoaded = false;
        }.bind(this));

        mainElementName.addClassName('class-FetchedResultPane');
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
        if(show) this.htmlElement.show();
        else this.htmlElement.hide();
	}

});