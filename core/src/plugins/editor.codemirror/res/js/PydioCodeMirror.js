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

import React, {Component} from 'react';
import {IconButton, TextField} from 'material-ui';
import { connect } from 'react-redux';
import { compose } from 'redux';

import CodeMirrorLoader from './CodeMirrorLoader'
import {parseQuery} from './Utils';

const {EditorActions} = PydioWorkspaces;

class PydioCodeMirror extends Component {

    constructor(props) {
        super(props)

        const {pydio, node, id, dispatch} = this.props

        this.setState = (data) => dispatch(EditorActions.tabModify({id, ...data}))
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

    find(query) {
        const {codemirror, cursor} = this.props

        let cur = codemirror.getSearchCursor(query, cursor.to);

        if (!cur.find()) {
            cur = codemirror.getSearchCursor(query, 0);
            if (!cur.find()) return;
        }

        codemirror.setSelection(cur.from(), cur.to());
        codemirror.scrollIntoView({from: cur.from(), to: cur.to()}, 20);
    }

    jumpTo(line) {
        const {codemirror} = this.props
        const cur = codemirror.getCursor();

        codemirror.focus();
        codemirror.setCursor(line - 1, cur.ch);
        codemirror.scrollIntoView({line: line - 1, ch: cur.ch}, 20);
    }

    save() {
        const {pydio} = this.props;

        pydio.ApiClient.postPlainTextContent(this.state.url, this.state.content, (success) => {
            if (!success) {
                this.setState({error: "There was an error while saving"})
            }
        });
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

// We need to attach the element to window else it won't be found
window.PydioCodeMirror = {
    PydioEditor: connect()(PydioCodeMirror),
    Actions: {
        onUndo: ({codemirror}) => codemirror.undo(),
        onRedo: ({codemirror}) => codemirror.redo(),
        onToggleLineNumbers: () => {
            console.log(props)
            EditorActions.tabModify({id, lineNumbers: !lineNumbers})
        },
        onToggleLineWrapping: ({id, lineWrapping}) => EditorActions.tabModify({id, lineNumbers: !lineWrapping})
    },
    SourceEditor: CodeMirrorLoader
}

export default PydioCodeMirror
