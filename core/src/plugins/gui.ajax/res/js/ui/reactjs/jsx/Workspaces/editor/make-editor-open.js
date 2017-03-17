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
import { TransitionMotion, spring, presets } from 'react-motion';

const ANIMATION={stifness: 500, damping: 20}
const TRANSLATEY_ORIGIN=800
const TRANSLATEY_TARGET=0

const makeEditorOpen = (Target) => {
    return class extends React.Component {

        getStyles() {
            if (!this.props.children) return []

            let counter = 0;

            return React.Children.map(
                this.props.children,
                child => ({
                    key: `t${counter++}`,
                    data: {element: child},
                    style: {
                        y: spring(TRANSLATEY_TARGET * counter, ANIMATION)
                    }
                }));
        }

        willEnter() {
            return {
                y: TRANSLATEY_ORIGIN
            };
        }

        willLeave() {
            return {
                y: spring(TRANSLATEY_ORIGIN, ANIMATION)
            }
        }

        render() {
            return (
                <TransitionMotion
                    styles={this.getStyles()}
                    willLeave={this.willLeave}
                    willEnter={this.willEnter}
                    onRest={this.onRest}>

                    {styles =>
                        <Target {...this.props}>
                        {styles.map(({key, style, data}) => {
                            let childStyle = {
                                transition: "none",
                                transform: `translateY(${style.y}px)`
                            }

                            let child = React.cloneElement(data.element, {key: key, loaded: style.y === TRANSLATEY_TARGET, style: childStyle})

                            return child
                        })}
                        </Target>
                    }
                </TransitionMotion>
            );
        }
    }
};

export default makeEditorOpen;
