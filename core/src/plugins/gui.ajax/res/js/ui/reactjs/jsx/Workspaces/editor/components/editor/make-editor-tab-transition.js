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
import { TransitionMotion, spring } from 'react-motion';

const ORIGIN=0
const TARGET=1
const ANIMATION={stifness: 390, damping: 20}

const makeEditorTabTransition = (Target) => {
    return class extends React.Component {
        getDefaultStyles() {
            if (!this.props.children) return []

            let counter = 0

            return React.Children.map(this.props.children, child => ({key: `t${counter++}`, data: {element: child}, style: {opacity: ORIGIN}}));
        }

        getStyles() {

            if (!this.props.children) return []

            let counter = 0
            return React.Children.map(this.props.children, child => ({key: `t${counter++}`, data: {element: child}, style: {opacity: spring(TARGET, ANIMATION)}}));
        }

        willEnter() {
            return {
                opacity: TARGET
            };
        }

        willLeave() {
            return {
                opacity: ORIGIN
            }
        }

        render() {
            return (
                <TransitionMotion
                    defaultStyles={this.getDefaultStyles()}
                    styles={this.getStyles()}
                    willLeave={this.willLeave}
                    willEnter={this.willEnter}>
                    {styles =>
                        <Target {...this.props}>
                        {styles.map(({key, style, data}) => {
                            let loaded = style.opacity === 0

                            let child = React.cloneElement(data.element, {key: key, loaded: loaded, style: style})

                            return child
                        })}
                        </Target>
                    }
                </TransitionMotion>
            );
        }
    }
};

export default makeEditorTabTransition;
