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
 * A selector for displaying repository list. Will hook to ajaxplorer:repository_list_refreshed.
 */
Class.create("RepositorySimpleLabel", AjxpPane, {

    _defaultString:'',
    _defaultIcon : 'network-wired.png',
    options : {},

    initialize : function($super, oElement, options){

        options = Object.extend({
            displayLabelLegend: true,
            displayWorkspaceDescription: false
        }, options);

        $super(oElement, options);

        if(this.options.displayLabelLegend) this.htmlElement.update('<div class="repository_legend">Workspace</div>');
        this.htmlElement.insert('<div class="repository_title"></div>');
        if(this.options.displayWorkspaceDescription){
            this.htmlElement.insert('<div class="repository_description"></div>');
        }

        if(options.link){
            var linkTitle;
            if(options.linkTitle){
                if(MessageHash[options.linkTitle]) linkTitle = MessageHash[options.linkTitle];
                else linkTitle = options.linkTitle;
                this.htmlElement.down('div.repository_title').writeAttribute("title", linkTitle);
            }
            this.htmlElement.down("div.repository_title").observe("click", function(){
                if(options.linkTarget && options.linkTarget == 'new'){
                    window.open(options.link);
                }else{
                    document.location.href = options.link;
                }
            });
            this.htmlElement.down("div.repository_title").addClassName("linked");
        }
        this.observer = function(e){

            this.htmlElement.down("div.repository_title").update(this._defaultString);
            if(this.options.displayWorkspaceDescription){
                this.htmlElement.down("div.repository_description").update('');
            }
            var repositoryList = e.memo.list;
            var repositoryId = e.memo.active;
            if(repositoryList && repositoryList.size()){
                var repoObject = repositoryList.get(repositoryId);
                if(repoObject){
                    this.htmlElement.down("div.repository_title").update(repoObject.getLabel());
                    if(this.options.displayWorkspaceDescription){
                        this.htmlElement.down("div.repository_description").update(repoObject.getDescription());
                    }
                }
            }
            var upDiv = this.htmlElement.up('[ajxpClass="AjxpPane"]');
            if(upDiv) upDiv.ajxpPaneObject.resize();
        }.bind(this);
        document.observe("ajaxplorer:repository_list_refreshed", this.observer);
    },

    destroy: function(){

        document.stopObserving("ajaxplorer:repository_list_refreshed", this.observer);

    }

});