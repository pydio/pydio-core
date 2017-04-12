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

const Toolbar = ({elements}) => {

    let counter = 0

    return React.Children.count(elements) === 0 ? null :
        <MaterialUI.Toolbar style={{flexShrink: 0}}>
            {React.Children.map(elements, (element) => React.cloneElement(element, {key: `el_${counter++}`}))}
        </MaterialUI.Toolbar>
}

const withActions = (Component) => {
    return class extends React.Component {
        render() {
            const {actions, ...remainingProps} = this.props

            return (
                <div style={{display: "flex", flexDirection: "column", flex: 1}}>
                    <Toolbar elements={actions} />

                    <Component {...remainingProps} />
                </div>
            )
        }
    }
}

export {withActions as default}
