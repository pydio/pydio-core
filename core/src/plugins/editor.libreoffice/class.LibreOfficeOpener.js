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

Class.create("LibreOfficeOpener", AbstractEditor, {

	fullscreenMode: false,

	// initialize LibreOfficeOpener
	initialize : function($super, oFormObject, options) {
        $super(oFormObject, options);
		this.element =  $(oFormObject);

		this.editorOptions = options;
		//this.defaultActions = new Hash();
		this.createTitleSpans();

		this.container = $(oFormObject).select('div[id="libreOfficeContainer"]')[0];
		fitHeightToBottom($(this.container), $(this.editorOptions.context.elementName));
		this.contentMainContainer = new Element("iframe", {
			style:"border:none;width:100%;"
		});
		this.container.update(this.contentMainContainer);

		var configs = pydio.getPluginConfigs("editor.libreoffice"),
			iframeUrl = configs.get('LIBREOFFICE_IFRAME_URL'),
			webSocketSecure = configs.get('LIBREOFFICE_WEBSOCKET_SECURE'),
			webSocketProtocol = webSocketSecure ? 'wss' : 'ws',
			webSocketHost = configs.get('LIBREOFFICE_WEBSOCKET_HOST'),
			webSocketPort = configs.get('LIBREOFFICE_WEBSOCKET_PORT');

		this.iframeUrl = iframeUrl;
		this.webSocketUrl = encodeURIComponent(webSocketProtocol + '://' + webSocketHost + ':' + webSocketPort);

		this.contentMainContainer.observe("focus", function(){
			pydio.UI.disableAllKeyBindings()
		});
		this.contentMainContainer.observe("blur", function(){
			pydio.UI.enableAllKeyBindings()
		});
		this.contentMainContainer.observe("load", function() {
			this.resize();
		}.bind(this));
	},

	// Open the LibreOffice Editor
	open : function($super, node) {

		$super(node);
		this.setOnLoad(true);

		this.currentNode = node;
		var fileName = this.currentNode.getPath();

		// Change content here
		PydioApi.getClient().request({ get_action: 'libreoffice_get_file_url', file: fileName}, function (transport) {

			var json = transport.responseJSON,
				host = json.host,
				uri = json.uri,
				permission = json.permission,
				fileSrcUrl,
				token = json.jwt;

			fileSrcUrl = encodeURIComponent(host + uri);

			this.contentMainContainer.src = this.iframeUrl + '?host=' + this.webSocketUrl + '&WOPISrc=' + fileSrcUrl + '&access_token=' + token + '&permission=' + permission;

			this.removeOnLoad();
		}.bind(this));

		// Set the tab label.
		this.updateTitle(getBaseName(fileName));
	},

    resize : function($super, size){
		$super(size);
		fitHeightToBottom(this.element);
		fitHeightToBottom(this.container);
		fitHeightToBottom(this.contentMainContainer);
    },

	setOnLoad: function(openMessage){
		addLightboxMarkupToElement(this.container);
		this.loading = true;
	},

	removeOnLoad: function(){
		removeLightboxFromElement(this.container);
		this.loading = false;
	}
});
