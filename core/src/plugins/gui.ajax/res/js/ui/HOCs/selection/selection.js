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
import { connect } from 'react-redux';
import SelectionModel from './model';
import { Actions as EditorActions, getDisplayName } from '../utils';
import { mapStateToProps } from './utils';

const withSelection = (getSelection) => {
    return (Component) => {
        class WithSelection extends React.Component {
            constructor(props) {
                super(props)

                const {node, id, dispatch} = this.props

                if (typeof dispatch === 'function') {
                    // We have a redux dispatch so we use it
                    this.setState = (data) => dispatch(EditorActions.tabModify({id, ...data}))
                }
            }

            static get displayName() {
                return `WithSelection(${getDisplayName(Component)})`
            }

            static get propTypes() {
                return {
                    node: React.PropTypes.instanceOf(AjxpNode).isRequired
                }
            }

            componentDidMount() {
                const {id, node, tabModify} = this.props

                getSelection(node).then(({selection, currentIndex}) => this.setState({id, selection: new SelectionModel(selection, currentIndex)}))
            }

            render() {
                const {selection, playing, dispatch, ...remainingProps} = this.props

                if (!selection) return null

                return (
                    <Component
                        {...remainingProps}
                        node={selection.current()}
                        selectionPlaying={playing}
                        onRequestSelectionPlay={() => this.setState({id, node: selection.nextOrFirst(), title: selection.currentNode.getLabel()})}
                    />
                )
            }
        }

        return connect(mapStateToProps)(WithSelection)
    }
}

export default withSelection
