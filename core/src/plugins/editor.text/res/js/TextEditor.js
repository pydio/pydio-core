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
import {compose} from 'redux'

class TextEditor extends Component {

    static get styles() {
        return {
            textArea: {
                width: '100%',
                height: '100%',
                backgroundColor: '#fff',
                fontSize: 13,
            }
        }
    }

    constructor(props) {
        super(props);

        this.handleChange = this.handleChange.bind(this);
        this.hasBeenModified = this.hasBeenModified.bind(this);
        this.state = {}
    }

    componentWillMount() {
        let {pydio, node} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: node.getPath(),
        }, function (transport) {
            this.setState({originalText: transport.responseText});
            this.setState({textContent: transport.responseText});
        }.bind(this));
    }

    saveContent() {
        let {pydio, node} = this.props
        pydio.ApiClient.postPlainTextContent(node.getPath(), this.state.textContent, (success) => {
            this.setState({originalText: this.state.textContent});
        }.bind(this));
    }

    hasBeenModified() {
        return (this.state.originalText != this.state.textContent)
    }

    handleChange(event) {
      this.setState({textContent: event.target.value});
    }

    render() {
        return (
            <Viewer
                saveDisabled={!this.hasBeenModified()}
                onSave={()=> this.saveContent()}
                onChange={() => this.handleChange()}
                value={this.state.textContent}
            />
        );
    }
}

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

let Viewer = compose(
    withControls(TextEditor.controls),
    withMenu,
    withLoader,
    withErrors
)(props => <textarea {...props} />)

// Define HOCs
if (typeof PydioHOCs !== "undefined") {
    Viewer = PydioHOCs.withActions(Viewer);
    Viewer = PydioHOCs.withLoader(Viewer)
    Viewer = PydioHOCs.withErrors(Viewer)
}

window.TextEditor = TextEditor;
