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

import { IconButton, TextField } from 'material-ui';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux'
import { mapStateToProps } from './utils';

import * as actions from './actions';

const getActions = ({editorData}) => ({...actions, ...FuncUtils.getFunctionByName(editorData.editorActions, window)})
const handler = (func, props) => () => getActions(props)[func](props)

const _Save = (props) => <IconButton onClick={handler("onSave", props)} iconClassName="mdi mdi-content-save" tooltip={MessageHash[53]} />
const _Undo = (props) => <IconButton onClick={handler("onUndo", props)} iconClassName="mdi mdi-undo" tooltip={MessageHash["code_mirror.7"]} />
const _Redo = (props) => <IconButton onClick={handler("onRedo", props)} iconClassName="mdi mdi-redo" tooltip={MessageHash["code_mirror.8"]} />
const _ToggleLineNumbers = (props) => <IconButton onClick={handler("onToggleLineNumbers", props)} iconClassName="mdi mdi-format-list-numbers" tooltip={MessageHash["code_mirror.5"]} />
const _ToggleLineWrapping = (props) => <IconButton onClick={handler("onToggleLineWrapping", props)} iconClassName="mdi mdi-wrap" tooltip={MessageHash["code_mirror.3b"]} />
const _JumpTo = (props) => <TextField onChange={handler("onJumpTo", props)(10)} hintText="Jump to Line" />
const _Search = (props) => <TextField onChange={handler("onSearch", props)} hintText="Jump to Line" />

export const Save = connect(mapStateToProps)(_Save)
export const Undo = connect(mapStateToProps)(_Undo)
export const Redo = connect(mapStateToProps)(_Redo)
export const ToggleLineNumbers = connect(mapStateToProps)(_ToggleLineNumbers)
export const ToggleLineWrapping = connect(mapStateToProps)(_ToggleLineWrapping)
export const JumpTo = connect(mapStateToProps)(_JumpTo)
export const Search = connect(mapStateToProps)(_Search)
