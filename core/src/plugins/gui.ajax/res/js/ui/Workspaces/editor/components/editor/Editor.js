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
import Pydio from 'pydio'
//import FullScreen from 'react-fullscreen';
import Draggable from 'react-draggable';
import { connect } from 'react-redux';
import { Paper } from 'material-ui';
import Tab from './EditorTab';
import Toolbar from './EditorToolbar';
import makeMinimise from './make-minimise';

const { EditorActions } = Pydio.requireLib('hoc');
const MAX_ITEMS = 4;

// MAIN COMPONENT
class Editor extends React.Component {

    constructor(props) {
        super(props)

        const {tabDelete, tabDeleteAll, editorModify, editorSetActiveTab} = props

        this.state = {
            minimisable: false
        }

        this.minimise = () => editorModify({isPanelActive: false})

        this.closeActiveTab = (e) => {
            const {activeTab} = this.props

            editorSetActiveTab(null)
            tabDelete(activeTab.id)
        }

        this.close = (e) => {
            editorModify({open: false})
            tabDeleteAll()
        }

        // By default, open it up
        editorModify({isPanelActive: true})
    }

    componentWillReceiveProps(nextProps) {

        if (this.state.minimisable) return

        const {translated} = nextProps

        if (!translated) return

        this.recalculate()

        this.setState({
            minimisable: true
        })
    }

    recalculate() {

        const {editorModify} = this.props

        const element = ReactDOM.findDOMNode(this.refs.container)

        if (!element) return

        editorModify({
            panel: {
                rect: element.getBoundingClientRect()
            }
        })
    }

    renderChild() {
        const {activeTab, tabs, editorSetActiveTab} = this.props

        const filteredTabs = tabs.filter(({editorData}) => editorData)

        return filteredTabs.map((tab, index) => {
            let style = {
                display: "flex",
                width: (100 / MAX_ITEMS) + "%",
                height: "40%",
                margin: "10px",
                overflow: "scroll",
                whiteSpace: "nowrap"
            }

            if (filteredTabs.length > MAX_ITEMS) {
                if (index < MAX_ITEMS) {
                    style.flex = 1
                } else {
                    style.flex = 0
                    style.margin = 0
                }
            }

            if (activeTab) {
                if (tab.id === activeTab.id) {
                    style.margin = 0
                    style.flex = 1
                } else {
                    style.flex = 0
                    style.margin = 0
                }
            }

            return <Tab key={`editortab${tab.id}`} id={tab.id} style={{...style}} />
        })
    }

    render() {
        const {style, activeTab, isActive, displayToolbar} = this.props
        const {minimisable} = this.state

        const title = activeTab ? activeTab.title : ""
        const onClose = activeTab ? this.closeActiveTab : this.close
        const onMinimise = minimisable ? this.minimise : null

        let parentStyle = {
            display: "flex",
            flex: 1,
            overflow: "hidden",
            width: "100%",
            height: "100%",
            position: "relative"
        }

        if (!activeTab) {
            parentStyle = {
                ...parentStyle,
                alignItems: "center", // To fix a bug in Safari, we only set it when height not = 100% (aka when there is no active tab)
                justifyContent: "center"
            }
        }

        return (
            <div style={{display: "flex", ...style}}>
                <Draggable cancel=".body" onStop={this.recalculate.bind(this)}>
                    <AnimatedPaper ref="container" onMinimise={this.props.onMinimise}  minimised={!isActive} zDepth={5} style={{display: "flex", flexDirection: "column", overflow: "hidden", width: "100%", height: "100%", transformOrigin: style.transformOrigin}}>
                        {displayToolbar &&
                            <Toolbar style={{flexShrink: 0}} title={title} onClose={onClose} onMinimise={onMinimise} />
                        }

                        <div className="body" style={parentStyle}>
                            {this.renderChild()}
                        </div>
                    </AnimatedPaper>
                </Draggable>
            </div>
        );
    }
};

// ANIMATIONS
const AnimatedPaper = makeMinimise(Paper)

// REDUX - Then connect the redux store
function mapStateToProps(state, ownProps) {
    const { editor, tabs } = state

    const activeTab = tabs.filter(tab => tab.id === editor.activeTabId)[0]

    return  {
        style: {},
        displayToolbar: true,
        ...ownProps,
        activeTab,
        tabs,
        isActive: editor.isPanelActive
    }
}
const ConnectedEditor = connect(mapStateToProps, EditorActions)(Editor)

// EXPORT
export default ConnectedEditor
