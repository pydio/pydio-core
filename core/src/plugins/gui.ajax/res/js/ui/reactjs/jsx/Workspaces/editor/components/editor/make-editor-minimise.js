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

const ANIMATION={stifness: 500, damping: 20}
const ORIGIN=1
const TARGET=100

const makeEditorMinimise = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {open: props.open};
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                open: nextProps.open,
                positionOrigin: nextProps.positionOrigin,
                positionTarget: nextProps.positionTarget
            })
        }

        render() {

            const {open, positionOrigin, positionTarget} = this.state
            const motionStyle = {
                scale: open ? spring(TARGET, ANIMATION) : spring(ORIGIN, ANIMATION)
            };

            let transformOrigin = null
            if (positionOrigin && positionTarget) {
                const x = parseInt((positionOrigin.right / positionTarget.right) * 100, 10)
                const y = parseInt((positionOrigin.bottom / positionTarget.bottom) * 100, 10)

                transformOrigin = `${x}% ${y}%`
            }

            let {style} = this.props || {style: {}}
            let {transform} = style || {transform: ""}

            return (
                <Motion style={motionStyle}>
                    {({scale}) => {
                        let float = scale / 100

                        return (
                            <Target
                                {...this.props}
                                style={{
                                    ...this.props.style,
                                    position: "fixed",
                                    top: `1%`,
                                    left: `1%`,
                                    bottom: `1%`,
                                    right: `15%`,
                                    transformOrigin,
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

const TestDiv = () => {
    console.log("Re-rendering")
    return (<div>HELLO</div>)
}

export default makeEditorMinimise;
