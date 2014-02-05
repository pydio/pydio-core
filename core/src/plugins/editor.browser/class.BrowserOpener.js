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
Class.create("BrowserOpener", AbstractEditor, {

	initialize: function($super, oFormObject, options){
        this.editorOptions = options;
        this.element = oFormObject;
    },
	
	open : function($super, node){
        if(node.getAjxpMime() == "url"){
        	this.openURL(node.getPath());
        	return;
        } 
        var repo = ajaxplorer.user.getActiveRepository();
        var loc = document.location.href;
        if(loc.indexOf("?") !== -1) loc = loc.substring(0, loc.indexOf("?"));
        var url = loc.substring(0, loc.lastIndexOf('/'));
        if($$('base').length){
            url = $$("base")[0].getAttribute("href");
            if(url.substr(-1) == '/') url = url.substr(0, url.length - 1);
        }
        var nonSecureAccessPath = ajxpServerAccessPath.substring(0, ajxpServerAccessPath.lastIndexOf('?'));
        var open_file_url = url + "/" + nonSecureAccessPath + "?get_action=open_file&repository_id=" + repo + "&file=" + encodeURIComponent(node.getPath());

        if(this.editorOptions.context.__className == 'Modal'){
            var myRef = window.open(open_file_url);
            if(!Modernizr.boxshadow){
                window.setTimeout('hideLightBox()', 1500);
            }else{
                hideLightBox();
            }
        }else{
            this.element.fire("editor:updateTitle", node.getLabel());
            this.contentMainContainer = new Element('iframe', {
                width:'100%',
                height:'100%',
                src:open_file_url,
                border:0,
                style: 'border: 0'
            });
            this.element.update(this.contentMainContainer);
        }
	},
	
	openURL : function(fileName){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_content');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			var url = transp.responseText;
            if(this.editorOptions.context.__className == 'Modal'){
                myRef = window.open(url, "Pydio Bookmark", "location=yes,menubar=yes,resizable=yes,scrollbars=yea,toolbar=yes,status=yes");
                hideLightBox();
            }else{
                this.element.fire("editor:updateTitle", url);
                this.contentMainContainer = new Element('iframe', {
                    width:'100%',
                    height:'100%',
                    src:url,
                    border:0,
                    style: 'border: 0'
                });
                this.element.update(this.contentMainContainer);
            }
		}.bind(this);
		connexion.sendSync();		
	},

    /**
     * Resizes the main container
     * @param size int|null
     */
    resize : function(size){
        if(size){
            this.element.setStyle({height:size+"px"});
            if(this.contentMainContainer) this.contentMainContainer.setStyle({height:size+"px"});
        }else{
            fitHeightToBottom(this.element);
            if(this.contentMainContainer) fitHeightToBottom(this.contentMainContainer, this.element);
        }
        this.element.fire("editor:resize", size);
    }

});