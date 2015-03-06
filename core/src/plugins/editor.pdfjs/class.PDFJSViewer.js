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

	initialize: function($super, oFormObject, options)
	{
		$super(oFormObject, options);
		this.canWrite = false; // It'a only a viewer.
	},

	open : function($super, nodeOrNodes){
		$super(nodeOrNodes);

		// Get the URL for current workspace path.
		var url = document.location.href.split('#').shift().split('?').shift();
		if(url[(url.length-1)] == '/'){
			url = url.substr(0, url.length-1);
		}else if(url.lastIndexOf('/') > -1){
			url = url.substr(0, url.lastIndexOf('/'));
		}

		// Get the direct PDF file link valid for our session.
		var fileName = nodeOrNodes.getPath();
		DEFAULT_URL = url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=read_pdf_data&file='+encodeURIComponent(fileName);

		// Set up the main container.
		this.contentMainContainer = document.getElementById('outerContainer');

		this.element.observeOnce("editor:close", function(){});
		this.contentMainContainer.observe("focus", function(){
			ajaxplorer.disableAllKeyBindings()
		});
		this.contentMainContainer.observe("blur", function(){
			ajaxplorer.enableAllKeyBindings()
		});

		// Set the tab label.
		this.updateTitle(getBaseName(fileName));

		// Load the PDF file.
		PDFViewerApplication.initialize().then(webViewerInitialized);

		// Toolbar layout fix for Chromium.
		setTimeout(function() {
			document.getElementById('toolbarViewer').setAttribute('style', 'display:none;');
			setTimeout(function() {
				document.getElementById('toolbarViewer').setAttribute('style', '');
			}, 100);
		}, 100);
	}
});
