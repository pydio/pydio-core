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

class Editor extends Component {

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

        this.setState({
            url: pydio.Parameters.get('ajxpServerAccess') + '&get_action=audio_proxy&file=' + encodeURIComponent(HasherUtils.base64_encode(node.getPath())) + '&z=' + guid(),
            mimeType: "audio/" + node.getAjxpMime()
        })
    }

    // Static functions
    static getPreviewComponent(node, rich = false) {
        return {
            element: PydioSoundManager,
            props: {
                node: node,
                rich: rich
            }
        }
    }

    render() {
        const {mimeType, url} = this.state || {}

        if (!url) return null

        return (
            <Player rich={!this.props.icon && this.props.rich} onReady={this.props.onLoad}>
                <a type={mimeType} href={url} />
            </Player>
        );
    }
}

function guid() {
    return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
}

function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
        .toString(16)
        .substring(1);
}

const {withSelection, withMenu, withLoader, withErrors, withControls} = PydioHOCs;

// let ExtendedPlayer = compose(
//     withMenu,
//     withErrors
// )(props => <Player {...props} />)

const editors = pydio.Registry.getActiveExtensionByType("editor")
const conf = editors.filter(({id}) => id === 'editor.soundmanager')[0]

const getSelectionFilter = (node) => conf.mimes.indexOf(node.getAjxpMime()) > -1

const getSelection = (node) => new Promise((resolve, reject) => {
    let selection = [];

    node.getParent().getChildren().forEach((child) => selection.push(child));
    selection = selection.filter(getSelectionFilter)

    resolve({
        selection,
        currentIndex: selection.reduce((currentIndex, current, index) => current === node && index || currentIndex, 0)
    })
})

export default compose(
    withSelection(getSelection),
    connect()
)(Editor)
