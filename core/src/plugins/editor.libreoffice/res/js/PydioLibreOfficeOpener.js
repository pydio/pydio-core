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

 const Viewer = ({url, style}) => {
     return (
         <iframe src={url} style={{...style, border: 0, flex: 1}} className="vertical_fit"></iframe>
     );
 };

class PydioLibreOfficeOpener extends React.Component {

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
                 pydio.ApiClient.request({ get_action: 'libreoffice_get_file_url', file: fileName}, function (transport) {
                     if (!transport || !transport.responseJSON) {
                         return;
                     }
                     let {host, uri, permission, jwt} = transport.responseJSON;
                     let fileSrcUrl = encodeURIComponent(`${host}${uri}`);
                     this.setState({url: `${iframeUrl}?host=${webSocketUrl}
                         &WOPISrc=${fileSrcUrl}
                         &access_token=${jwt}
                         &permisson=${permission}`});
                 }.bind(this));
       }

       render() {
           return (
               <PydioComponents.AbstractEditor {...this.props}>
                   <Viewer {...this.props} url={this.state.url} />
               </PydioComponents.AbstractEditor>
           );
       }

 }

window.PydioLibreOfficeOpener = PydioLibreOfficeOpener;
