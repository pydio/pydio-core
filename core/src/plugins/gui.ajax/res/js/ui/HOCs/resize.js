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

import {getDisplayName} from './utils';

const withResize = (Component) => {
    return class extends React.Component {

        static get displayName() {
            return `WithResize(${getDisplayName(Component)})`
        }

        constructor(props) {
            super(props)

            this.state = {}

            this._observer = (e) => this.resize();
        }

        resize() {
            const node = ReactDOM.findDOMNode(this.container)
            const dimensions = node && node.getBoundingClientRect() || {}

            this.setState({
                width: parseInt(dimensions.width),
                height: parseInt(dimensions.height)
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
            const {onResize, width, height, ...remainingProps} = this.props

            return (
                <Component ref={(container) => this.container = container} {...remainingProps} width={this.state.width} height={this.state.height} />
            )
        }
    }
}

export {withResize as default}
