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
Class.create("BrowserOpener", AbstractEditor, {

	initialize: function($super, oFormObject){},
	
	open : function($super, userSelection){
        var fileName =  ajaxplorer.getUserSelection().getUniqueFileName();
        var node = ajaxplorer.getUserSelection().getUniqueNode();
        if(node.getAjxpMime() == "url"){
        	this.openURL(fileName);
        	return;
        } 
        var repo = ajaxplorer.user.getActiveRepository();
        var url = document.location.href.substring(0, document.location.href.lastIndexOf('/'));
        var nonSecureAccessPath = ajxpServerAccessPath.substring(0, ajxpServerAccessPath.lastIndexOf('?'));
        var open_file_url = url + "/" + nonSecureAccessPath + "?get_action=open_file&repository_id=" + repo + "&file=" + encodeURIComponent(fileName);
        myRef = window.open(open_file_url);
        if(!Modernizr.boxshadow){
            window.setTimeout('hideLightBox()', 1500);
        }else{
            hideLightBox();
        }
	},
	
	openURL : function(fileName){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_content');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			var url = transp.responseText;
	        myRef = window.open(url, "AjaXplorer Bookmark", "location=yes,menubar=yes,resizable=yes,scrollbars=yea,toolbar=yes,status=yes");
	        hideLightBox();
		}.bind(this);
		connexion.sendSync();		
	}
});