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

const {EditorActions} = PydioWorkspaces;

class Editor extends Component {

    static get styles() {
        return {
            textarea: {
                width: '100%',
                height: '100%',
                backgroundColor: '#fff',
                fontSize: 13,
            }
        }
    }

    constructor(props) {
        super(props);

        const {pydio, node, id, dispatch} = this.props

        if (typeof dispatch === 'function') {
            // We have a redux dispatch so we use it
            this.setState = (data) => dispatch(EditorActions.tabModify({id, ...data}))
        }
    }

    componentWillMount() {
        this.loadNode(this.props)
    }

    compomentWillReceiveProps(nextProps) {
        if (this.props.node !== nextProps.node) {
            this.loadNode(nextProps)
        }
    }

    loadNode(props) {
        const {pydio, node} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: node.getPath(),
        }, ({responseText}) => {
            this.setState({
                content: responseText
            });
        });
    }

    render() {
        return (
            <textarea
                style={Editor.styles.textarea}
                onChange={({target}) => this.setState({content: target.value})}
                value={this.props.content}
            />
        );
    }
}

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

/*let Viewer = compose(
    withControls(TextEditor.controls),
    withMenu,
    withLoader,
    withErrors
)(props => <textarea {...props} />)*/

export default compose(
    connect()
)(Editor)
