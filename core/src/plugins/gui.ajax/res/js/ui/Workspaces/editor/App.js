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
import Pydio from 'pydio';
import { connect } from 'react-redux';
import { Editor } from './components/editor';
import { Menu } from './components/menu';
import makeEditorOpen from './make-editor-open';

const { EditorActions } = Pydio.requireLib('hoc');

class App extends React.Component {

    constructor(props) {
        super(props)

        const {editorModify, editorSetActiveTab} = props

        editorModify({open: false})
        editorSetActiveTab(null)

        this.onEditorMinimise = () => this.setState({editorMinimised: !this.props.displayPanel})

        this.state = {
            editorMinimised: false
        }
    }

    componentWillReceiveProps(nextProps) {
        const {editorModify, tabs, displayPanel, positionOrigin, positionTarget} = nextProps

        editorModify({open: tabs.length > 0})

        if (displayPanel) {

            this.setState({
                editorMinimised: false
            })

            let transformOrigin = ""
            if (positionOrigin && positionTarget) {
                const x = parseInt(positionTarget.left - positionOrigin.left + ((positionTarget.right - positionTarget.left) / 2))
                const y = parseInt(positionTarget.top - positionOrigin.top + ((positionTarget.bottom - positionTarget.top) / 2))

                this.setState({
                    transformOrigin: `${x}px ${y}px`
                })
            }
        }
    }

    render() {

        const {display, displayPanel} = this.props
        const {editorMinimised} = this.state

        let editorStyle = {
            display: "none"
        }

        let overlayStyle = {
            display: "none"
        }

        if (!editorMinimised) {
            editorStyle = {
                position: "fixed",
                top: "1%",
                left: "5%",
                right: "15%",
                bottom: "1%",
                transformOrigin: this.state.transformOrigin
            }

            overlayStyle = {position: "fixed", top: 0, bottom: 0, right: 0, left: 0, background: "#000000", opacity: "0.5", transition: "opacity .5s ease-in"}
        }

        if (!displayPanel) {
            overlayStyle = {opacity: 0, transition: "opacity .5s ease-in"}
        }

        let menuStyle = {
            position: "fixed",
            bottom: "50px",
            right: "50px",
            cursor: "pointer",
            transform: "translate(50%, 50%)",
            zIndex: 5
        }

        return (
            <div>
                { display ? <div style={overlayStyle} /> : null }
                <AnimationGroup>
                    { display ? <Editor style={editorStyle} onMinimise={this.onEditorMinimise.bind(this)} /> : null }
                    { display ? <Menu style={menuStyle} /> : null }
                </AnimationGroup>
            </div>
        )
    }
}

const Animation = (props) => {
    return (
        <div {...props} />
    );
};

const AnimationGroup = makeEditorOpen(Animation)

// REDUX - Then connect the redux store
function mapStateToProps(state, ownProps) {
    const {editor, tabs} = state

    return {
        ...ownProps,
        tabs,
        display: editor.open,
        displayPanel: editor.isPanelActive,
        displayMenu: editor.isMenuActive,
        positionOrigin: editor.panel && editor.panel.rect,
        positionTarget: editor.menu && editor.menu.rect
    }
}
const ConnectedApp = connect(mapStateToProps, EditorActions)(App)

export default ConnectedApp;
