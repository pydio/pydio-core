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

import _ from 'lodash';
import { Motion, spring, presets } from 'react-motion';

const ANIMATION={stiffness: 300, damping: 40}
const ORIGIN=0
const TARGET=100

const makeEditorMinimise = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {};
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                minimised: nextProps.minimised
            })
        }

        render() {
            const {minimised} = this.state

            if (typeof minimised === "undefined") {
                return <Target {...this.props} />
            }

            const motionStyle = {
                scale: minimised ? spring(ORIGIN, ANIMATION) : TARGET
            };

            const transform = this.props.style.transform || ""

            return (
                <Motion style={motionStyle} onRest={this.props.onMinimise} >
                    {({scale}) => {
                        let float = scale / 100

                        return (
                            <Target
                                {...this.props}
                                scale={scale}
                                style={{
                                    ...this.props.style,
                                    transition: "none",
                                    transform: `${transform} scale(${float})`
                                }}
                            />
                        )
                    }}
                </Motion>
            );
        }
    }
};

export default makeEditorMinimise;
