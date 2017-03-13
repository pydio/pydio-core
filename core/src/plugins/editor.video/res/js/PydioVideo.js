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
 * The latest code can be found at <https://pydio.com>.
 */

import Editor from './Editor';

class PydioVideo extends React.Component {

    constructor(props) {
        super(props)

        const {pydio, node, preview} = props

        this.state = {
            preview: preview
        }

        this.getSessionId().then((sessionId) => this.setState({
            url: pydio.Parameters.get('ajxpServerAccess') + '&ajxp_sessid=' + sessionId + '&get_action=read_video_data&file=' + encodeURIComponent(node.getPath())
        }));

        this.onReady = this._handleReady.bind(this)
    }

    // Static functions
    static getPreviewComponent(node, rich = false) {
        if (rich) {
            return {
                element: PydioVideo,
                props: {
                    node: node,
                    rich: rich
                }
            }
        } else {
            // We don't have a player for the file icon
            return null;
        }
    }

    _handleReady() {
        this.setState({
            ready: true
        })
    }

    // Util functions
    getSessionId() {
        const {pydio} = this.props

        return new Promise((resolve, reject) => {
            pydio.ApiClient.request({
                get_action: 'get_sess_id'
            }, function(transport) {
                resolve(transport.responseText)
            })
        });
    }

    // Plugin Main Editor rendering
    render() {

        // Only display the video when we know the URL
        let editor = null;
        if (this.state.url) {
            editor = <Editor url={this.state.url} onReady={this.onReady} />
        }

        return (
            <PydioComponents.AbstractEditor {...this.props} loading={!this.state.ready || !this.state.url} preview={this.state.preview}>
                <div style={{minHeight: 120, flex: 1}}>
                    {editor}
                </div>
            </PydioComponents.AbstractEditor>
        );
    }
}

PydioVideo.propTypes = {
    node: React.PropTypes.instanceOf(AjxpNode).isRequired,
    pydio: React.PropTypes.instanceOf(Pydio).isRequired,

    preview: React.PropTypes.bool.isRequired
}

PydioVideo.defaultProps = {
    preview: false
}

// We need to attach the element to window else it won't be found
window.PydioVideo = PydioVideo

export default PydioVideo
