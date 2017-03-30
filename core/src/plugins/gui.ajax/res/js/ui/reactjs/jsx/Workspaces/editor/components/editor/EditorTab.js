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

const {Paper} = MaterialUI;

import { connect } from 'react-redux';
import * as actions from '../../actions';

import makeMaximise from './make-maximise';

class Tab extends React.Component {
    render() {

        const {id, isActive, style, editorSetActiveTab} = this.props

        const select = () => editorSetActiveTab(id)

        return (
            <AnimatedPaper style={style} maximised={isActive} onClick={!isActive ? select : null}>
                <this.props.child {...this.props} icon={false} />
            </AnimatedPaper>
        )
    }
}

function mapStateToProps(state, ownProps) {
    const { editor, tabs } = state

    let current = tabs.filter(tab => tab.id === ownProps.id)[0]

    return  {
        ...ownProps,
        ...current,
        isActive: editor.activeTabId === current.id
    }
}

const AnimatedPaper = makeMaximise(Paper)

const EditorTab = connect(mapStateToProps, actions)(Tab)

export default EditorTab
