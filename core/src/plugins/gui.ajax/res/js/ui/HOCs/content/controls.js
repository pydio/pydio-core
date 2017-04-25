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

import { IconButton } from 'material-ui';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux'
import { mapStateToProps } from './utils';

import * as actions from './actions';

const getActions = ({editorData}) => ({...actions, ...FuncUtils.getFunctionByName(editorData.editorActions, window)})

const Save = connect(mapStateToProps)((props) => <IconButton onClick={() => getActions(props).onSave(props)} iconClassName="mdi mdi-content-save" tooltip={MessageHash[53]} />)
const Undo = connect(mapStateToProps)((props) => <IconButton onClick={() => getActions(props).onUndo(props)} iconClassName="mdi mdi-undo" tooltip={MessageHash["code_mirror.7"]} />)
const Redo = connect(mapStateToProps)((props) => <IconButton onClick={() => getActions(props).onRedo(props)} iconClassName="mdi mdi-redo" tooltip={MessageHash["code_mirror.8"]} />)

const ToggleLineNumbers = connect(mapStateToProps)((props) => <IconButton onClick={() => getActions(props).onToggleLineNumbers(props)} iconClassName="mdi mdi-format-list-numbers" tooltip={MessageHash["code_mirror.5"]} />)
const ToggleLineWrapping = connect(mapStateToProps)((props) => <IconButton onClick={() => getActions(props).onToggleLineWrapping(props)} iconClassName="mdi mdi-wrap" tooltip={MessageHash["code_mirror.3b"]} />)

export { Save }
export { Undo }
export { Redo }

export { ToggleLineNumbers }
export { ToggleLineWrapping }
