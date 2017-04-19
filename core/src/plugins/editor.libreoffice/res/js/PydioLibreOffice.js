/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */

import React, {Component} from 'react'
import {compose} from 'redux'

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

const Viewer = compose(
    withMenu,
    withLoader,
    withErrors
)(({url, style}) => <iframe src={url} style={{...style, width: "100%", height: "100%", border: 0, flex: 1}}></iframe>)

class PydioLibreOffice extends React.Component {

    constructor(props) {
        super(props)

        this.state = {}
    }

    componentWillMount() {
        let configs = this.props.pydio.getPluginConfigs("editor.libreoffice"),
        iframeUrl = configs.get('LIBREOFFICE_IFRAME_URL'),
        webSocketSecure = configs.get('LIBREOFFICE_WEBSOCKET_SECURE'),
        webSocketHost = configs.get('LIBREOFFICE_WEBSOCKET_HOST'),
        webSocketPort = configs.get('LIBREOFFICE_WEBSOCKET_PORT');

        let webSocketProtocol = webSocketSecure ? 'wss' : 'ws',
        webSocketUrl = encodeURIComponent(`${webSocketProtocol}://${webSocketHost}:${webSocketPort}`);

        let fileName = this.props.node.getPath();
        pydio.ApiClient.request({
            get_action: 'libreoffice_get_file_url',
            file: fileName
        }, ({responseJSON = {}}) => {
            let {host, uri, permission, jwt} = responseJSON;
            let fileSrcUrl = encodeURIComponent(`${host}${uri}`);
            this.setState({url: `${iframeUrl}?host=${webSocketUrl}&WOPISrc=${fileSrcUrl}&access_token=${jwt}&permisson=${permission}`});
        });
    }

    render() {
        return (
            <Viewer {...this.props} url={this.state.url} />
        );
    }
}

window.PydioLibreOffice = PydioLibreOffice;
