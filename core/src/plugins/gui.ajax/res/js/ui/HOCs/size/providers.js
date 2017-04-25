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

export class ContainerSizeProvider extends React.Component {
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

export class ImageSizeProvider extends React.Component {
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
