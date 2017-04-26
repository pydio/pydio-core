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
import { mapStateToProps } from './utils';
import { handler } from '../utils';

// Controls definitions
const _Save = (props) => <IconButton onClick={() => handler("onSave", props)} iconClassName="mdi mdi-content-save" />
const _Undo = (props) => <IconButton onClick={() => handler("onUndo", props)} iconClassName="mdi mdi-undo" />
const _Redo = (props) => <IconButton onClick={() => handler("onRedo", props)} iconClassName="mdi mdi-redo" />

const _ToggleLineNumbers = (props) => <IconButton onClick={() => handler("onToggleLineNumbers", props)} iconClassName="mdi mdi-format-list-numbers" />
const _ToggleLineWrapping = (props) => <IconButton onClick={() => handler("onToggleLineWrapping", props)} iconClassName="mdi mdi-wrap" />

const _JumpTo = (props) => <TextField onKeyUp={({key, target}) => key === 'Enter' && handler("onJumpTo", props)(target.value)} hintText="Jump to Line" />
const _Search = (props) => <TextField onKeyUp={({key, target}) => key === 'Enter' && handler("onSearch", props)(target.value)} hintText="Search..." />

// Final export and connection
export const Save = connect(mapStateToProps)(_Save)
export const Undo = connect(mapStateToProps)(_Undo)
export const Redo = connect(mapStateToProps)(_Redo)
export const ToggleLineNumbers = connect(mapStateToProps)(_ToggleLineNumbers)
export const ToggleLineWrapping = connect(mapStateToProps)(_ToggleLineWrapping)
export const JumpTo = connect(mapStateToProps)(_JumpTo)
export const Search = connect(mapStateToProps)(_Search)
