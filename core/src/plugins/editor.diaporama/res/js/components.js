/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

import React, {Component} from 'react'

export class Image extends Component {
    static get propTypes() {
        return {
            scale: React.PropTypes.number
        }
    }

    static get styles() {
        return {
            margin: 0,
            transformOrigin: "50% 0"
            //boxShadow: DOMUtils.getBoxShadowDepth(1)
        }
    }

    render() {
        const {width, height, scale, ...remainingProps} = this.props

        return <img {...remainingProps} style={{...Image.styles, width, height, transform: `scale(${scale})`}} />
    }
}

export class ImageContainer extends Component {
    static get propTypes() {
        return {
            src: React.PropTypes.string.isRequired,
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            imgClassName: React.PropTypes.string
        }
    }

    static get IMAGE_PANEL_MARGIN() {
        return 10
    }

    static get styles() {
        return {
            display: "flex",
            flex: 1,
            justifyContent: 'center',
            overflow: 'auto'
        }
    }

    render() {
        const {node, src, style, width, height, imgClassName, scale} = this.props

        return (
            <div style={{...ImageContainer.styles, ...style}}>
                <Image
                    src={src}
                    width={width}
                    height={height}
                    className={imgClassName}
                    scale={scale}
                />
            </div>
        )
    }
}
