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

import Pydio from 'pydio'
import React from 'react';
import { connect } from 'react-redux';
import { compose } from 'redux';
import CodeMirrorLoader from './CodeMirrorLoader';

const {EditorActions} = Pydio.requireLib('hoc');

class Editor extends React.Component {

    constructor(props) {
        super(props)

        const {node, tab, dispatch} = this.props
        const {id} = tab

        if (typeof dispatch === 'function') {
            // We have a redux dispatch so we use it
            this.setState = (data) => dispatch(EditorActions.tabModify({id, ...data}))
        }
    }

    componentDidMount() {
        const {pydio, node} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: node.getPath()
        }, ({responseText}) => this.setState({content: responseText}));
    }

    render() {
        const {node, tab, error, dispatch} = this.props
        const {codemirror, content, lineNumbers, lineWrapping} = tab

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

export const mapStateToProps = (state, props) => {
    const {tabs} = state

    const tab = tabs.filter(({editorData, node}) => editorData.id === props.editorData.id && node.getPath() === props.node.getPath())[0]

    return {
        tab,
        ...props
    }
}

export default connect(mapStateToProps)(Editor)
