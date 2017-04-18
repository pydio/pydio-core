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

import {Component} from 'react'
import {Toolbar, ToolbarGroup} from 'material-ui'

const getDisplayName = (WrappedComponent) => {
    return WrappedComponent.displayName || WrappedComponent.name || 'Component';
}

const withMenu = (WrappedComponent) => {
    return class extends Component {
        static get displayName() {
            return `WithMenu(${getDisplayName(WrappedComponent)})`
        }

        render() {
            const {controls, ...remainingProps} = this.props

            return (
                <div style={{display: "flex", flexDirection: "column", flex: 1, overflow: "auto"}}>
                    {controls && controls.length > 0 &&
                        <Toolbar style={{flexShrink: 0}}>
                            {controls}
                        </Toolbar>
                    }

                    <WrappedComponent {...remainingProps} />
                </div>
            )
        }
    }
}

const toTitleCase = str => str.replace(/\w\S*/g, (txt) => `${txt.charAt(0).toUpperCase()}${txt.substr(1)}`)

const withControls = (controls = {}) => {
    return (WrappedComponent) => {
        return class extends Component {

            static get displayName() {
                return `WithControls(${getDisplayName(WrappedComponent)})`
            }

            static get propTypes() {
                return Object.keys(controls).map(type => ({
                    [`${type}Disabled`]: React.PropTypes.bool,
                    [`on${toTitleCase(type)}`]: React.PropTypes.func
                }))
            }

            static get defaultProps() {
                return Object.keys(controls).map(type => ({
                    [`${type}Disabled`]: false
                }))
            }

            render() {
                let remainingProps = this.props

                const groups = Object.keys(controls)

                // Turn the controls inside the groups into React elements
                let menuControls =
                    groups.map((group) => {
                        return Object.keys(controls[group]).map(type => {
                            let { [`${type}Disabled`]: disabled, [`on${toTitleCase(type)}`]: handler, ...props} = remainingProps
                            remainingProps = props

                            if (typeof handler !== "function") return null

                            return React.cloneElement(controls[group][type](handler), {disabled})
                        }).filter(element => element)
                    }).filter(element => element.length > 0).map((controls, index) => {
                        return <ToolbarGroup firstChild={index === 0} lastChild={index && index === groups.length - 1}>{controls}</ToolbarGroup>
                    })

                return (
                    <WrappedComponent {...remainingProps} controls={menuControls} />
                )
            }
        }
    }
}

export {withControls}
export {withMenu}
