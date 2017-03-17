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

// IMPORT
import { connect } from 'react-redux';
import * as actions from '../../actions';

const {Paper} = MaterialUI;

import Tab from './EditorTab';
import Toolbar from './EditorToolbar';

import makeEditorMinimise from './make-editor-minimise';
import makeEditorTabTransition from './make-editor-tab-transition';

// MAIN COMPONENT
class Editor extends React.Component {

    constructor(props) {
        super(props)

        const {tabDelete, editorModifyPanel, editorModifyMenu, editorSetActiveTab} = props

        this.minimise = () => {
            editorModifyPanel({open: false})
            editorModifyMenu({open: false})
        }

        this.closeActiveTab = () => {
            const {activeTabId} = this.props

            editorSetActiveTab(null)
            tabDelete(activeTabId)
        }
    }

    componentDidUpdate() {
        const {editorModifyPanel, loaded, positionTarget} = this.props

        if (!loaded || positionTarget) return

        const element = ReactDOM.findDOMNode(this)

        if (!element) return

        editorModifyPanel({
            rect: element.getBoundingClientRect()
        })
    }

    renderChild() {
        const {activeTabId, tabs} = this.props

        return tabs.map((tab) => {

            const style = {
                position: "absolute",
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                display: "flex",
                transition: "transform 0.3s ease-in"
            }

            const activeStyle = activeTabId !== tab.id ? {transform: "translateX(-100%)"} : {transform: "translateX(0)"}

            return <Tab key={`editortab${tab.id}`} id={tab.id} style={{...style, ...activeStyle}} />
        })
    }

    render() {
        const {activeTab, tabs} = this.props
        const title = activeTab ? activeTab.title : ""

        return (
            <Paper zDepth={5} style={{...this.props.style, display: "flex", flexDirection: "column", overflow: "hidden"}}>
                <Toolbar style={{flexShrink: 0}} title={title} onClose={this.closeActiveTab} onMinimise={this.minimise}/>

                <div style={{position: "relative", flex: 1}}>
                    {this.props.loaded && this.renderChild()}
                </div>

            </Paper>
        );
    }
};

// ANIMATIONS - First chaining the animations (that might already depend on the store)
let AnimatedEditor = Editor
AnimatedEditor = makeEditorMinimise(AnimatedEditor)
AnimatedEditor = makeEditorTabTransition(AnimatedEditor)

// REDUX - Then connect the redux store
function mapStateToProps(state, ownProps) {
    const { editor, tabs } = state

    const activeTabId = editor.activeTabId || (tabs.length > 0 && tabs[0].id)
    const activeTab = tabs.filter(tab => tab.id === activeTabId)[0]

    return  {
        open: typeof activeTabId !== "boolean" && editor.panel.open,
        positionOrigin: editor.menu.rect,
        positionTarget: editor.panel.rect,
        activeTabId: activeTabId,
        activeTab: activeTab,
        tabs
    }
}
const ConnectedEditor = connect(mapStateToProps, actions)(AnimatedEditor)

// EXPORT
export default ConnectedEditor
