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

let counter=0

const makeAsync = (WrappedComponent) => {
    return class AsyncGroup extends React.PureComponent {
        constructor(props) {
            super(props)

            this.state = {
                ...this.buildPromises(props)
            }
        }

        componentDidMount() {
            this.waitAndSee()
        }

        waitAndSee() {
            Promise.all(this.state.promises).then((values) => this.setState({ready: true}))
        }

        buildPromises(props) {
            let onloads = []
            let promises = React.Children
                .toArray(props.children)
                .filter(child => child)
                .map(child => new Promise((resolve, reject) => {
                    if (typeof child.props.onLoad !== "function") return resolve()

                    let timeout = setTimeout(resolve, 3000);

                    onloads.push(() => {
                        window.clearTimeout(timeout)

                        child.props.onLoad()

                        setTimeout(resolve, 1000)
                    })
                }));

            return {
                promises,
                onloads,
                ready: false
            }
        }

        render() {
            const {...props} = this.props

            //console.log("Make Async", this.state.ready)
             //, {onLoad: this.state.onloads[i]}))}

            return (
                <WrappedComponent {...props} ready={this.state.ready}>
                    {React.Children.toArray(props.children).filter(child => child).map((Child, i) => React.cloneElement(Child, {onLoad: () => {}}))}
                </WrappedComponent>
            );
        }
    }
};

export default makeAsync;
