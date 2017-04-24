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
import * as Actions from '../Workspaces/editor/actions';
import {getRatio, getDisplayName, getBoundingRect} from './utils';

class ContainerSizeProvider extends React.Component {
    constructor(props) {
        super(props)

        this.state = {}

        this._observer = (e) => this.resize();
    }

    resize() {
        const node = ReactDOM.findDOMNode(this)
        const dimensions = node && getBoundingRect(node) || {}

        this.setState({
            containerWidth: parseInt(dimensions.width),
            containerHeight: parseInt(dimensions.height)
        })
    }

    componentDidMount() {
        DOMUtils.observeWindowResize(this._observer);

        this.resize()
    }

    componentWillUnmount() {
        DOMUtils.stopObservingWindowResize(this._observer);
    }

    render() {
        return this.props.children(this.state)
    }
}

class ImageSizeProvider extends React.Component {
    static get propTypes() {
        return {
            url: React.PropTypes.string.isRequired,
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
        }
    }

    constructor(props) {
        super(props)

        this.state = {
            imgWidth: 200,
            imgHeight: 200
        }
    }

    componentWillReceiveProps(nextProps) {
        const {url, node} = nextProps

        const that = this
        const meta = node.getMetadata()

        DOMUtils.imageLoader(url, function() {
            if (!meta.has('image_width')){
                meta.set("image_width", this.width);
                meta.set("image_height", this.height);
            }

            that.setState({imgWidth: this.width, imgHeight: this.height})
        }, function() {
            if (meta.has('image_width')) {
                that.setState({imgWidth: meta.get('image_width'), imgHeight: meta.get('image_height')})
            }
        })
    }

    render() {
        return this.props.children(this.state)
    }
}

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

        constructor(props) {
            super(props)

            this.state = {
                size: "contain"
            }
        }

        componentWillReceiveProps(nextProps) {
            const {containerWidth, width, containerHeight, height} = nextProps

            this.setState({
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

        renderControls() {
            const {size} = this.state
            const scale = getRatio[size](this.state)

            return [
                <IconButton tooltip="Aspect Ratio" onClick={() => this.setState({size: "contain"})}><ActionAspectRatio /></IconButton>,
                <DropDownMenu>
                    <MenuItem primaryText={`${parseInt(scale * 100)}%`} />
                    <Slider axis="y" style={{width: "100%", height: 150, display: "flex", justifyContent: "center"}} sliderStyle={{margin: 0}} value={scale} min={0.25} max={4} defaultValue={1} onChange={(_, scale) => this.setState({size: "auto", scale})} />
                </DropDownMenu>
            ]
        }

        render() {
            const {size} = this.state
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

    const mapStateToProps = (state, ownProps) => {
        const {node} = ownProps
        const {tabs} = state

        let current = tabs.filter(tab => tab.id === node.getLabel())[0]

        return  {
            ...ownProps,
            ...current
        }
    }

    return connect(mapStateToProps)(WithResize)
}

export {withResize}
export {ContainerSizeProvider}
export {ImageSizeProvider}
