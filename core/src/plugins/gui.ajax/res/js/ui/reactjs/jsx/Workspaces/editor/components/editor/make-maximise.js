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

const ANIMATION={stiffness: 400, damping: 30}
const TARGET=100

const makeMinimise = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {maximised: props.maximised};
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                maximised: nextProps.maximised
            })
        }

        render() {
            const {maximised} = this.state
            const motionStyle = {
                width: maximised ? spring(TARGET, ANIMATION) : spring(parseInt(this.props.style.width.replace(/%$/, '')), ANIMATION),
                height: maximised ? spring(TARGET, ANIMATION) : spring(parseInt(this.props.style.height.replace(/%$/, '')), ANIMATION)
            };

            let {style} = this.props || {style: {}}

            return (
                <Motion style={motionStyle}>
                    {({width, height}) => {
                        return (
                            <Target
                                {...this.props}
                                style={{
                                    ...this.props.style,
                                    width: `${width}%`,
                                    height: `${height}%`,
                                    transition: "none"
                                }}
                            />
                        )
                    }}
                </Motion>
            );
        }
    }
};

export default makeMinimise;
