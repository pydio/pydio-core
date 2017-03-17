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

class Tab extends React.Component {
    constructor(props) {
        super(props)

        this.close = () => {
            const {id, editorModifyTab} = props

            editorModifyTab(id, {
                open: false
            })
        }
    }

    render() {

        // Making sure the editor will load only once
        if (!this.props.editorData) return null

        return (
            <div style={this.props.style}>
                <this.props.child {...this.props} icon={false} />
            </div>
        )
    }
}

function mapStateToProps(state, ownProps) {
    const { tabs } = state

    let current = tabs.filter(tab => tab.id === ownProps.id)[0]

    return  {
        ...ownProps,
        ...current
    }
}

const EditorTab = connect(mapStateToProps, actions)(Tab)

export default EditorTab
