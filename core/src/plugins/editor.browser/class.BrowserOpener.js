/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : The "online edition" manager, encapsulate the CodePress highlighter for some extensions.
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
        hideLightBox();
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