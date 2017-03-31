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

import Editor from './Editor';
import {MenuOptions, MenuActions} from './Menu';

class PydioCodeMirror extends React.Component {

    constructor(props) {
        super(props)

        const {pydio, node} = this.props

        this.state = {
            url: node.getPath(),
            readOnly: false, // TODO replace
            lineNumbers: true, // TODO replace
            lineWrapping: true, // TODO replace
            ready: false
        }

        this.onLoad = this.onLoad.bind(this)
        this.onChange = this.onChange.bind(this)
        this.onCursorChange = this.onCursorChange.bind(this)

        this.save = this.save.bind(this)
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

    componentWillMount() {
        console.log("Mounting")
    }

    componentDidMount() {
        const {pydio} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: this.state.url
        }, (transport) => this.setState({content: transport.responseText}));
    }

    componentWillUnmount() {
        console.log("Unmounting")
    }

    onLoad(codemirror) {
        this.setState({ codemirror: codemirror })
        // this.props.onLoad()
    }

    onChange(content) {
        this.setState({ content: content })
    }

    onCursorChange(cursor) {
        this.setState({ cursor: cursor })
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
        return (
            <CompositeEditor
                {...this.props}
                actions={this.state.codemirror ? [
                    <MenuOptions pydio={this.props.pydio} codemirror={this.state.codemirror} onSave={this.save.bind(this)} />,
                    <MenuActions pydio={this.props.pydio} codemirror={this.state.codemirror} cursor={this.state.cursor} />
                ] : []}
                error={this.state.error}
                url={this.state.url}
                options={{lineNumbers: this.state.lineNumbers, lineWrapping: this.state.lineWrapping}}
                content={this.state.content}
                onLoad={this.onLoad}
                onChange={this.onChange}
                onCursorChange={this.onCursorChange}
            />
        );
    }
}

let CompositeEditor = Editor

// Define HOCs
if (typeof PydioHOCs !== "undefined") {
    CompositeEditor = PydioHOCs.withActions(CompositeEditor);
    CompositeEditor = PydioHOCs.withLoader(CompositeEditor)
    CompositeEditor = PydioHOCs.withErrors(CompositeEditor)
}

// We need to attach the element to window else it won't be found
window.PydioCodeMirror = {
    PydioEditor: PydioCodeMirror,
    SourceEditor: Editor
}

export default PydioCodeMirror
