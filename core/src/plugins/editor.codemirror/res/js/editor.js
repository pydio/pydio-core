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

import React from 'react';
import { connect } from 'react-redux';
import { compose } from 'redux';

import CodeMirrorLoader from './CodeMirrorLoader'

const {EditorActions} = PydioWorkspaces;

class Editor extends React.Component {

    constructor(props) {
        super(props)

        const {pydio, node, id, dispatch} = this.props

        if (typeof dispatch === 'function') {
            // We have a redux dispatch so we use it
            this.setState = (data) => dispatch(EditorActions.tabModify({id, ...data}))
        }
    }

    // Static functions
    static getPreviewComponent(node, rich = false) {
        if (rich) {
            return {
                element: PydioCodeMirror,
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

    componentDidMount() {
        const {pydio, url} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: url
        }, (transport) => this.setState({content: transport.responseText}));
    }

    render() {
        const {node, codemirror, content, lineNumbers, lineWrapping, error, dispatch} = this.props

        return (
            <CodeMirrorLoader
                {...this.props}
                url={node.getPath()}
                content={content}
                options={{lineNumbers: lineNumbers, lineWrapping: lineWrapping}}
                error={error}

                onLoad={codemirror => this.setState({codemirror})}
                onChange={content => this.setState({content})}
                onCursorChange={cursor => this.setState({cursor})}
            />
        )
    }
}

/*const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

let CodeMirrorLoaderWithControls = compose(
    withControls(PydioCodeMirror.controls),
    withMenu,
    withLoader,
    withErrors
)(CodeMirrorLoader)*/

/* We need to attach the element to window else it won't be found
window.PydioCodeMirror = {
    PydioEditor: connect()(PydioCodeMirror),
    SourceEditor: CodeMirrorLoader
}*/

export default connect()(Editor)
