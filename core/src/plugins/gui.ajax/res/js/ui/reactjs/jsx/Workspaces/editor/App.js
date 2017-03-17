/**
 * Copyright (c) 2013-present, Facebook, Inc. All rights reserved.
 *
 * This file provided by Facebook is for non-commercial testing and evaluation
 * purposes only. Facebook reserves all rights not expressly granted.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * FACEBOOK BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
import { connect } from 'react-redux';
import * as actions from './actions'

import {Editor} from './components/editor';
import {Menu} from './components/menu';

import makeEditorOpen from './make-editor-open';

class App extends React.Component {

    constructor(props) {
        super(props)

        const {editorModifyMenu, editorModifyPanel, editorSetActiveTab} = props

        editorModifyMenu({})
        editorModifyPanel({})
        editorSetActiveTab(null)
    }

    render() {

        let style = {
            position: "fixed",
            bottom: "50px",
            right: "50px",
            cursor: "pointer",
            transform: "translate(50%, 50%)",
            zIndex: 5
        }

        let editor = null
        let menu = null
        let overlayStyle = null

        if (this.props.display) {
            editor = <Editor />
            menu = <Menu style={style} />
        }

        if (this.props.display && this.props.panel && this.props.panel.open) {
            overlayStyle = {position: "fixed", top: 0, bottom: 0, right: 0, right: 0, left: 0, background: "#000000", opacity: 0.5, transition: "all 0.5s ease-in"}
        }

        return (
            <div>
                <div style={overlayStyle} />
                <AnimationGroup>
                    {editor}
                </AnimationGroup>
                {menu}
            </div>
        )
    }
}

const Animation = (props) => {
    return (
        <div {...props}>
            {props.children}
        </div>
    );
};

const AnimationGroup = makeEditorOpen(Animation)

// REDUX - Then connect the redux store
function mapStateToProps(state, ownProps) {
    const {tabs, editor} = state
    return {
        display: tabs.length > 0,
        panel: editor.panel
    }
}
const ConnectedApp = connect(mapStateToProps, actions)(App)

export default ConnectedApp;
export {default as reducers} from './reducers';
