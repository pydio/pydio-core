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

import CodeMirror from './CodeMirror';

import SystemJS from 'systemjs';

window.define = SystemJS.amdDefine;
window.require = window.requirejs = SystemJS.amdRequire;

SystemJS.config({
    baseURL: 'plugins/editor.codemirror/node_modules',
    packages: {
        'codemirror': {},
        '.': {}
    }
});

class Editor extends React.Component {

    constructor(props) {
        super(props)

        const {pydio, node, url} = props

        let loaded = new Promise((resolve, reject) => {
            SystemJS.import('codemirror/lib/codemirror').then((m) => {
                let CodeMirror = m
                SystemJS.import('codemirror/addon/search/search')
                SystemJS.import('codemirror/addon/mode/loadmode').then(() => {
                    SystemJS.import('codemirror/mode/meta').then(() => {
                        CodeMirror.modeURL = 'codemirror/mode/%N/%N.js'
                        resolve(CodeMirror)
                    })
                })
            })
        });

        this.state = {
            url: url,
            loaded: loaded
        }
    }

    // Handling loading
    componentDidMount() {
        this.state.loaded.then((CodeMirror) => {
            this.setState({codemirrorInstance: CodeMirror})
            this.props.onReady()
        })
    }

    render() {

        // If Code Mirror library is not loaded, do not go further
        if (!this.state.codemirrorInstance) return null

        return (
            <CodeMirror
                name={this.state.url}
                value={this.props.content}
                codeMirrorInstance={this.state.codemirrorInstance}
                options={this.props.options}

                onLoad={this.props.onLoad}
                onChange={this.props.onChange}
                onCursorChange={this.props.onCursorChange}
            />
        )
    }
}

Editor.propTypes = {
    url: React.PropTypes.string.isRequired,

    onReady: React.PropTypes.func.isRequired,
    onChange: React.PropTypes.func.isRequired
}

export default Editor;
