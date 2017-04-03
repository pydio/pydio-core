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
import { Motion, spring, presets } from 'react-motion';

const ANIMATION={stifness: 500, damping: 20}
const ORIGIN = -720
const TARGET = 0

const makeRotate = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {
                rotate: false
            };
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                rotate: nextProps.open
            })
        }

        render() {
            const style = {
                rotate: this.state.rotate ? ORIGIN : TARGET
            };
            return (
                <Motion style={style}>
                    {({rotate}) => {
                        let rotated = rotate === ORIGIN

                        return (
                            <Target
                                {...this.props}
                                rotated={rotated}
                                style={{
                                    ...this.props.style,
                                    transform: `${this.props.style.transform} rotate(${rotate}deg)`
                                }}
                            />
                        )
                    }}
                </Motion>
            );
        }
    }
};

export default makeRotate;
