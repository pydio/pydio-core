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

import React, {PureComponent} from 'react'
import { ImageContainer } from './components'

const baseURL = pydio.Parameters.get('ajxpServerAccess');

export default class Preview extends PureComponent {
    render() {
        const {node, ...remainingProps} = this.props

        console.log(`${baseURL}&get_action=imagick_data_proxy&file=${node.getPath()}`)

        return (
            <ImageContainer
                {...remainingProps}
                style={{
                    width: "100%",
                    height: "100%",
                    backgroundImage:`url(${baseURL}&get_action=imagick_data_proxy&file=${node.getPath()})`,
                    backgroundSize : 'cover',
                    backgroundPosition: 'center center'
                }}
            />
        )
    }
}
