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
import {compose} from 'redux';

import CodeMirrorLoader from './CodeMirrorLoader'
import {parseQuery} from './Utils';

class PydioCodeMirror extends Component {

    constructor(props) {
        super(props)

        const {pydio, node} = this.props

        this.state = {
            url: node.getPath(),
            cursor: {},
            readOnly: false, // TODO replace
            lineNumbers: true, // TODO replace
            lineWrapping: true, // TODO replace
            ready: false
        }
    }

    static get propTypes() {
        return {
            showControls: React.PropTypes.bool.isRequired
        }
    }

    static get defaultProps() {
        return {
            showControls: false
        }
    }

    static get controls() {
        return {
            options: {
                save: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-content-save" tooltip={MessageHash[53]}/>,
                undo: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-undo" tooltip={MessageHash["code_mirror.7"]} />,
                redo: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-redo" tooltip={MessageHash["code_mirror.8"]} />,
                toggleLineNumbers: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-format-list-numbers" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.5"]} />,
                toggleLineWrapping: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-wrap" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.3b"]} />
            },
            actions: {
                jumpTo: (handler) => <TextField hintText={MessageHash["code_mirror.6"]} onKeyUp={handler} />,
                find: (handler) => <TextField hintText={MessageHash["code_mirror.9"]} onKeyUp={handler} />
            }
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
        const {pydio} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: this.state.url
        }, (transport) => this.setState({content: transport.responseText}));
    }

    find(query) {
        const {codemirror, cursor} = this.state

        let cur = codemirror.getSearchCursor(query, cursor.to);

        if (!cur.find()) {
            cur = codemirror.getSearchCursor(query, 0);
            if (!cur.find()) return;
        }

        codemirror.setSelection(cur.from(), cur.to());
        codemirror.scrollIntoView({from: cur.from(), to: cur.to()}, 20);
    }

    jumpTo(line) {
        const {codemirror} = this.state
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

        const {showControls} = this.props

        const {url, codemirror, content, lineNumbers, lineWrapping, error} = this.state

        const {undo, redo} = codemirror && codemirror.historySize() || {}

        if (showControls) {
            return (
                <CodeMirrorLoaderWithControls
                    {...this.props}
                    url={url}
                    content={content}
                    options={{lineNumbers: lineNumbers, lineWrapping: lineWrapping}}
                    error={error}

                    onSave={() => this.save()}
                    undoDisabled={!undo}
                    onUndo={() => this.state.codemirror.undo()}
                    redoDisabled={!redo}
                    onRedo={() => this.state.codemirror.redo()}
                    onToggleLineNumbers={() => this.setState({lineNumbers: !lineNumbers})}
                    onToggleLineWrapping={() => this.setState({lineWrapping: !lineWrapping})}
                    onJumpTo={({key, target}) => key === 'Enter' && this.jumpTo(parseInt(target.value))}
                    onFind={({key, target}) => key === 'Enter' && this.find(parseQuery(target.value))}

                    onLoad={codemirror => this.setState({codemirror})}
                    onChange={content => this.setState({content})}
                    onCursorChange={cursor => this.setState({cursor})}
                />
            )
        } else {
            return (
                <CodeMirrorLoader
                    {...this.props}
                    url={url}
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
}

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

let CodeMirrorLoaderWithControls = compose(
    withControls(PydioCodeMirror.controls),
    withMenu,
    withLoader,
    withErrors
)(CodeMirrorLoader)

// We need to attach the element to window else it won't be found
window.PydioCodeMirror = {
    PydioEditor: PydioCodeMirror,
    SourceEditor: CodeMirrorLoader
}

export default PydioCodeMirror
