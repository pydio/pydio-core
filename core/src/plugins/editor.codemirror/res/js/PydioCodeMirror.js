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

import CodeMirror from './CodeMirror';

class PydioCodeMirror extends React.Component {

    constructor(props) {
        super(props)

        const {pydio, node} = this.props

        this.state = {
            name: node.getPath(),
            readOnly: false, // TODO replace
            lineNumbers: true, // TODO replace
            lineWrapping: true, // TODO replace
        }

        this.onUndo = this._handleUndo.bind(this)
        this.onRedo = this._handleRedo.bind(this)
        this.onToggleLineNumbers = this._handleToggleLineNumbers.bind(this)
        this.onToggleTextWrap = this._handleToggleTextWrap.bind(this)
        this.onJumpToLine = this._handleJumpToLine.bind(this)
        this.onTextSearch = this._handleTextSearch.bind(this)

        this.onDone = this._handleDone.bind(this)
        this.onFound = this._handleFound.bind(this)
        this.onJumped = this._handleJumped.bind(this)
        this.onChange = this._handleChange.bind(this)
        this.onSave = this._handleSave.bind(this)
    }

    componentDidMount() {
        this.loadFileContent()
    }

    loadFileContent() {
        PydioApi.getClient().request({
            get_action: 'get_content',
            file: this.state.name
        }, function(transport) {
            this.setState({
                code: transport.responseText
            })
        }.bind(this));
    }

    _handleSave() {
        PydioApi.getClient().postPlainTextContent(this.state.name, this.state.code, (success) => this.setState({modified: !success}));
    }

    _handleChange(code, e) {
        this.setState({
            code: code,
            modified: true
        })
    }

    _handleDone() {
        this.setState({
            undo: false,
            redo: false,
            save: false
        })
    }

    _handleFound(pos) {
        this.setState({
            lastFoundPos: pos,
            search: null
        })
    }

    _handleJumped(pos) {
        this.setState({
            jump: null
        })
    }

    _handleUndo() {
        this.setState({
            undo: true
        })
    }

    _handleRedo() {
        this.setState({
            redo: true
        })
    }

    _handleToggleLineNumbers() {
        this.setState({
            lineNumbers: !this.state.lineNumbers
        })
    }

    _handleToggleTextWrap() {
        this.setState({
            lineWrapping: !this.state.lineWrapping
        })
    }

    _handleTextSearch(e) {
        switch (e.key) {
            case 'Enter':
                this.setState({
                    search: {
                        pos: this.state.lastFoundPos,
                        query: parseQuery(e.target.value),
                    }
                })
                break
            default:
                this.setState({
                    lastFoundPos: 0
                })
        }
    }

    _handleJumpToLine(e) {
        switch (e.key) {
            case 'Enter':
                this.setState({
                    jump: {
                        line: parseInt(e.target.value)
                    }
                })
                break
        }
    }

    getMenuActions() {
        const {MessageHash} = this.props.pydio

        return [(
            <MaterialUI.ToolbarGroup key="left" firstChild={true}>
                <MaterialUI.IconButton disabled={!this.state.modified} iconClassName="mdi mdi-content-save" tooltipPosition="bottom-right" tooltip={MessageHash[53]} onClick={this.onSave} />

                <MaterialUI.IconButton disabled={!this.state.modified} iconClassName="mdi mdi-undo" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.7"]} onClick={this.onUndo}/>
                <MaterialUI.IconButton disabled={!this.state.modified} iconClassName="mdi mdi-redo" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.8"]} onClick={this.onRedo}/>

                <MaterialUI.IconButton iconClassName="mdi mdi-format-list-numbers" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.5"]} onClick={this.onToggleLineNumbers}/>
                <MaterialUI.IconButton iconClassName="mdi mdi-wrap" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.3b"]} onClick={this.onToggleTextWrap}/>
            </MaterialUI.ToolbarGroup>
        ), (
            <MaterialUI.ToolbarGroup key="right" lastChild={true}>
                <MaterialUI.TextField hintText={MessageHash["code_mirror.6"]} onKeyUp={this.onJumpToLine} />
                <MaterialUI.TextField hintText={MessageHash["code_mirror.9"]} onKeyUp={this.onTextSearch}/>
            </MaterialUI.ToolbarGroup>
        )];
    }


    render() {
        let menuActions = this.getMenuActions()

        let options = {
            readOnly: this.state.readOnly,
			lineNumbers: this.state.lineNumbers,
            lineWrapping: this.state.lineWrapping
		};

        let actions = {
            undo: this.state.undo,
            redo: this.state.redo,
            search: this.state.search,
            jump: this.state.jump
        }

        if (!this.state.code) {
            return (
                <PydioComponents.AbstractEditor {...this.props} actions={[]}>
                    <PydioReactUI.Loader/>
                </PydioComponents.AbstractEditor>
            )
        }

        return (
            <PydioComponents.AbstractEditor {...this.props} actions={menuActions}>
                <CodeMirror name={this.state.name} value={this.state.code} options={options} actions={actions} onChange={this.onChange} onFound={this.onFound} onJumped={this.onJumped} onDone={this.onDone} />
            </PydioComponents.AbstractEditor>
        );
    }
}

function parseString(string) {
    return string.replace(/\\(.)/g, function(_, ch) {
      if (ch == "n") return "\n"
      if (ch == "r") return "\r"
      return ch
    })
  }

function parseQuery(query) {
    var isRE = query.match(/^\/(.*)\/([a-z]*)$/);
    if (isRE) {
        try {
            query = new RegExp(isRE[1], isRE[2].indexOf("i") == -1 ? "" : "i");
        } catch(e) {

        } // Not a regular expression after all, do a string search
    } else {
        query = parseString(query)
    }
    if (typeof query == "string" ? query == "" : query.test("")) query = /x^/;

    return query;
}

// We need to attach the element to window else it won't be found
window.PydioCodeMirror = PydioCodeMirror

export default PydioCodeMirror
