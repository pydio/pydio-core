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
import React, {Component} from 'react'
import { connect } from 'react-redux'
import { compose } from 'redux'
import Player from './Player';

class Editor extends React.Component {

    static get styles() {
        return {
            container: {
                minHeight: 120,
                flex: 1,
                padding: 0,
                width: "100%"
            }
        }
    }

    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            pydio: React.PropTypes.instanceOf(Pydio).isRequired,

            preview: React.PropTypes.bool.isRequired
        }
    }

    static get defaultProps() {
        return {
            preview: false
        }
    }

    componentDidMount() {
        this.loadNode(this.props)
    }

    componentWillReceiveProps(nextProps) {
        if (nextProps.node !== this.props.node) {
            this.loadNode(nextProps)
        }
    }

    loadNode(props) {
        const {pydio, node} = props

        this.getSessionId().then((sessionId) => this.setState({
            url: pydio.Parameters.get('ajxpServerAccess') + '&ajxp_sessid=' + sessionId + '&get_action=read_video_data&file=' + encodeURIComponent(node.getPath())
        }));
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
        const {url} = this.state || {}

        // Only display the video when we know the URL
        const editor = url ? <Player url={url} /> : null;

        return (
            <Viewer style={Editor.styles.container}>{editor}</Viewer>
        );
    }
}

const {withSelection, withMenu, withLoader, withErrors, withControls} = PydioHOCs;

let Viewer = compose(
    withMenu,
    withLoader,
    withErrors
)(props => <div {...props} />)

export default compose(
    connect(),
    withSelection((node) => true)
)(Editor)
