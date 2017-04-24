/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */

import React from 'react';
import ReactDOM from 'react-dom';

import {ToolbarGroup, IconButton} from 'material-ui';

import { connect } from 'react-redux';
import * as Actions from '../Workspaces/editor/actions';
import {getDisplayName} from './utils';

import {URLProvider} from './urls';

const withResolution = (sizes, highResolution, lowResolution) => {
    return (Component) => {
        class WithResolution extends React.Component {
            static get displayName() {
                return `WithResolution(${getDisplayName(Component)})`
            }

            static get propTypes() {
                return {
                    node: React.PropTypes.instanceOf(AjxpNode).isRequired
                }
            }

            constructor(props) {
                super(props)

                this.state = {
                    original: true
                }
            }

            onHi() {
                const {node} = this.props

                return highResolution(node)
            }

            onLo() {
                const {node} = this.props
                const viewportRef = (DOMUtils.getViewportHeight() + DOMUtils.getViewportWidth()) / 2;

                const thumbLimit = sizes.reduce((current, size) => {
                    return viewportRef > parseInt(size) && parseInt(size) || current
                }, 0);

                if (thumbLimit > 0) {
                    return lowResolution(node, thumbLimit)
                }

                return highResolution(node)
            }

            renderControls() {
                const {original} = this.state

                return [
                    <IconButton onClick={() => this.setState({original: !original})} iconClassName={`mdi mdi-crop-${original ? "square" : "free"}`} />
                ]
            }

            render() {
                const {original} = this.state
                const {controls, ...remainingProps} = this.props

                return (
                    <ResolutionURLProvider
                        urlType={original ? "hi" : "lo"}
                        onHi={() => this.onHi()}
                        onLo={() => this.onLo()}
                    >
                        {url =>
                            <Component
                                {...remainingProps}

                                src={url}
                            />
                        }
                    </ResolutionURLProvider>
                )
            }
        }

        const mapStateToProps = (state, props) => state.tabs.filter(tab => props.node && tab.id === props.node.getLabel())[0]

        return connect(mapStateToProps)(WithResolution)
    }
}

const ResolutionURLProvider = URLProvider(["hi", "lo"])

export {withResolution}
