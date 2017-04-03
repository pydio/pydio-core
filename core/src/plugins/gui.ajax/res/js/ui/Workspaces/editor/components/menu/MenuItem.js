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
import * as actions from '../../actions';

const {FloatingActionButton} = MaterialUI;

class MenuItem extends React.PureComponent {

    constructor(props) {
        super(props)

        const {editorSetActiveTab, editorModify} = props

        this.onClick = () => {
            editorModify({isPanelActive: true})
            editorSetActiveTab(this.props.id)
        }
    }

    render() {
        const {style, tab} = this.props

        if (!tab) return null

        const textStyle = {
            position: "absolute",
            top: 0,
            bottom: 0,
            width: 100,
            maxWidth: 100,
            textAlign: "center",
            left: -120,
            lineHeight: "30px",
            margin: "5px 0",
            padding: "0 5px",
            borderRadius: 4,
            background: "#000000",
            textOverflow: "ellipsis",
            whiteSpace: "nowrap",
            overflow: "hidden",
            color: "#ffffff",
            opacity: "0.7"
        }

        return (
            <div style={style} onClick={this.onClick}>
                <span style={textStyle}>{tab.title}</span>
                <FloatingActionButton mini={true} ref="container" backgroundColor="#FFFFFF" zDepth={2}  iconStyle={{backgroundColor: "#FFFFFF"}}>
                    <tab.icon {...this.props.tab} style={{fill: "#000000", flex: 1, alignItems: "center", justifyContent: "center", fontSize: 28, color: "#607d8b"}} icon={true} />
                </FloatingActionButton>
            </div>
        );
    }
}

function mapStateToProps(state, ownProps) {
    const { tabs } = state

    let current = tabs.filter(tab => tab.id === ownProps.id)[0]

    return  {
        ...ownProps,
        tab: current
    }
}

const ConnectedMenuItem = connect(mapStateToProps, actions)(MenuItem)

export default ConnectedMenuItem
