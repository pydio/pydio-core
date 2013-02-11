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
 * A selector for displaying repository list. Will hook to ajaxplorer:repository_list_refreshed.
 */
Class.create("RepositorySimpleLabel", {
    __implements : "IAjxpWidget",
    _defaultString:'No Repository',
    _defaultIcon : 'network-wired.png',
    options : {},

    initialize : function(oElement, options){
        if(options) this.options = options;
        this.element = oElement;

        this.element.update(this._defaultString);
        document.observe("ajaxplorer:repository_list_refreshed", function(e){

            var repositoryList = e.memo.list;
            var repositoryId = e.memo.active;
            if(repositoryList && repositoryList.size()){
                repositoryList.each(function(pair){
                    var repoObject = pair.value;
                    var key = pair.key;
                    var selected = (key == repositoryId ? true:false);
                    if(selected){
                        this.element.update(repoObject.getLabel());
                    }
                }.bind(this) );
            }
        }.bind(this));
    },

    resize : function(){},
    showElement : function(){},
    getDomNode : function(){},
    destroy : function(){}

});