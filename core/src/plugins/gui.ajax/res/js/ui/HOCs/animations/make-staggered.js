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

import React from 'react';
import ReactDOM from 'react-dom';
import shallowCompare from 'react/lib/shallowCompare';
import { StaggeredMotion } from 'react-motion';
import stripStyle from 'react-motion/lib/stripStyle';
import {springify, buildTransform} from './utils';

let counter=0

const DEFAULT_ANIMATION={stiffness: 200, damping: 22, precision: 0.1}

const makeStaggered = (originStyles, targetStyles, animation) => {
    return (Target) => {
        class StaggeredGroup extends React.PureComponent {
            constructor(props) {
                super(props)

                this.state = {
                    styles: this.buildStyles(props)
                }
            }

            componentWillReceiveProps(nextProps) {
                this.setState({
                    styles: this.buildStyles(nextProps)
                });
            }

            buildStyles(props) {
                return React.Children
                    .toArray(props.children)
                    .filter(child => child) // Removing null values
                    .map(child => {
                        return originStyles
                    });
            }

            getStyles(prevStyles) {
                const endValue = React.Children
                    .toArray(this.props.children)
                    .filter(child => child) // Removing null values
                    .map((_, i) => {
                        return !this.props.ready
                            ? originStyles
                            : i === 0
                                ? springify(targetStyles, animation || DEFAULT_ANIMATION)
                                : prevStyles[i - 1]
                                    ? springify(prevStyles[i - 1], animation || DEFAULT_ANIMATION)
                                    : originStyles
                    })

                return endValue;
            }

            render() {
                // Making sure we fliter out properties
                const {ready, ...props} = this.props

                return (
                    <StaggeredMotion
                        defaultStyles={this.state.styles}
                        styles={(styles) => this.getStyles(styles)}
                        >
                        {styles =>
                            <Target {...props}>
                            {React.Children.toArray(props.children).filter(child => child).map((Child, i) => {
                                let style = styles[i] || {}

                                const itemProps = Child.props

                                const transform = buildTransform(style, {
                                    length: 'px', angle: 'deg'
                                });

                                return React.cloneElement(Child, {style: {
                                    ...itemProps.style,
                                    ...style,
                                    transform,
                                    transition: "none"
                                }})
                            })}
                            </Target>
                        }
                    </StaggeredMotion>
                );
            }
        }

        StaggeredGroup.propTypes = {
            ready: React.PropTypes.bool.isRequired
        }

        StaggeredGroup.defaultProps = {
            ready: true
        }

        return StaggeredGroup
    }
}

export default makeStaggered;
