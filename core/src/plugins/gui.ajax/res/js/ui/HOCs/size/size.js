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

import {ToolbarGroup, ToolbarTitle, DropDownMenu, MenuItem, IconButton, Slider} from 'material-ui';
import ActionAspectRatio from 'material-ui/svg-icons/action/aspect-ratio'

import { connect } from 'react-redux';
import * as Actions from '../../Workspaces/editor/actions';
import {getRatio, getDisplayName, getBoundingRect} from '../utils';

const withResize = (Component) => {
    class WithResize extends React.Component {
        static get displayName() {
            return `WithResize(${getDisplayName(Component)})`
        }

        static get propTypes() {
            return {
                containerWidth: React.PropTypes.number.isRequired,
                containerHeight: React.PropTypes.number.isRequired,
                width: React.PropTypes.number.isRequired,
                height: React.PropTypes.number.isRequired
            }
        }

        componentWillReceiveProps(nextProps) {
            const {scale, containerWidth, width, containerHeight, height} = nextProps

            this.setState({
                scale,
                widthRatio: containerWidth / width,
                heightRatio: containerHeight / height
            })
        }

        componentDidMount() {
            const {id, controls, dispatch} = this.props

            dispatch(Actions.tabAddControls({
                id: id,
                size: this.renderControls()
            }))
        }

        render() {
            const {size} = this.props
            const {...remainingProps} = this.props

            const scale = getRatio[size](this.state)

            return (
                <Component
                    {...remainingProps}

                    scale={scale}
                />
            )
        }
    }

    const mapStateToProps = (state, props) => state.tabs.filter(({editorData, node}) => editorData.id === props.editorData.id && node.getParent() === props.node.getParent())[0]

    return connect(mapStateToProps, Actions)(WithResize)
}

export {withResize}
