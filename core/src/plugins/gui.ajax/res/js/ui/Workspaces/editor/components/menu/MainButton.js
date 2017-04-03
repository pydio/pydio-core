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

import makeRotate from './make-rotate';

class Button extends React.Component {

    render() {
        const {rotated} = this.props

        let iconClassName = 'mdi mdi-close'
        if (!rotated) {
            iconClassName = 'mdi mdi-pencil'
        }

        return (
            <FloatingActionButton {...this.props} iconClassName={iconClassName}/>
        );
    }
};

const AnimatedButton = makeRotate(Button)

function mapStateToProps(state, ownProps) {
    const { editor } = state

    return  {
        ...editor.menu
    }
}

const ConnectedButton = connect(mapStateToProps, actions)(AnimatedButton)

export default ConnectedButton
