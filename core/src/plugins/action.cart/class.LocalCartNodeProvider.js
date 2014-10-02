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
 * A local implementation that explore currently defined
 * classes
 */
Class.create("LocalCartNodeProvider", {
	__implements : "IAjxpNodeProvider",

	initialize : function(){
	},
	
	initProvider : function(properties){
		this.properties = properties;
	},
	
	/**
	 * 
	 * @param node AjxpNode
	 * @param nodeCallback Function
	 * @param childCallback Function
	 */
	loadNode : function(node, nodeCallback, childCallback){
        node.setLoaded(true);
        nodeCallback(node);
        node.getChildren().each(function(n){
            childCallback(n);
        });
	},

    loadLeafNodeSync: function(node, callback){}
	
});