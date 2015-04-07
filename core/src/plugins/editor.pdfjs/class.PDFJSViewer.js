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

/*
 * Adapted from "editor.text/class.TextEditor.js" to provide PDF viewing
 * by Kristian Garnét.
 */

Class.create("PDFJSViewer", AbstractEditor, {

	initialize : function($super, oFormObject, options) {
		$super(oFormObject, options);
		this.canWrite = false; // It'a only a viewer.
	},

	open : function($super, nodeOrNodes) {
		$super(nodeOrNodes);

		// Get the URL for current workspace path.
		var url = document.location.href.split('#').shift().split('?').shift();
		if(url[(url.length-1)] == '/'){
			url = url.substr(0, url.length-1);
		}else if(url.lastIndexOf('/') > -1){
			url = url.substr(0, url.lastIndexOf('/'));
		}
		if($$('base').length){
			url = $$("base")[0].getAttribute("href");
			if(!url.startsWith('http') && !url.startsWith('https')){
				url = document.location.origin + url;
			}
		}

		// Get the direct PDF file link valid for this session.
		var fileName = nodeOrNodes.getPath();
		var pdfurl = encodeURIComponent(url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=get_content&file=base64encoded:' + base64_encode(fileName));

		// Hide the Pydio action bar.
		this.element.down('.editor_action_bar').setStyle({display:'none'});

		// Set up the main container and load the PDF file.
		this.contentMainContainer = this.element.down('iframe');
		this.contentMainContainer.src = 'plugins/editor.pdfjs/pdfjs/web/viewer.html?file=' + pdfurl;

		this.contentMainContainer.observe("focus", function(){
			ajaxplorer.disableAllKeyBindings()
		});
		this.contentMainContainer.observe("blur", function(){
			ajaxplorer.enableAllKeyBindings()
		});

		// Set the tab label.
		this.updateTitle(getBaseName(fileName));

	},
    resize : function(size){
        if(size){
            this.contentMainContainer.setStyle({height:size+'px'});
            if(this.IEorigWidth) this.contentMainContainer.setStyle({width:this.IEorigWidth});
        }else{
            if(this.fullScreenMode){
                fitHeightToBottom(this.contentMainContainer, this.element);
                if(this.IEorigWidth) this.contentMainContainer.setStyle({width:this.element.getWidth()});
            }else{
                if(this.editorOptions.context.elementName){
                    fitHeightToBottom(this.contentMainContainer, $(this.editorOptions.context.elementName), 5);
                }else{
                    fitHeightToBottom($(this.element));
                    fitHeightToBottom($(this.contentMainContainer), $(this.element));
                }
                if(this.IEorigWidth) this.contentMainContainer.setStyle({width:this.IEorigWidth});
            }
        }
        this.element.fire("editor:resize", size);
    }

});
