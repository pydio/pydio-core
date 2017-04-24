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
import {ToolbarGroup, IconButton} from 'material-ui';

import * as Actions from '../../Workspaces/editor/actions';

import { connect } from 'react-redux';
import { getDisplayName } from '../utils';

const SelectionControls = ({id, node, selection, playing, tabModify, ...remainingProps}) => {

    const handleNodeChange = (newNode) => tabModify({id, node: newNode})

    const togglePlaying = () => tabModify({id, playing: !playing})

    return <ToolbarGroup {...remainingProps}>
        <IconButton onClick={() => handleNodeChange(selection.previous())} iconClassName="mdi mdi-arrow-left" disabled={!selection.hasPrevious()} />
        <IconButton onClick={() => togglePlaying()} iconClassName={`mdi mdi-${playing ? "pause" : "play"}`} disabled={!selection.hasPrevious() && !selection.hasNext()} />
        <IconButton onClick={() => handleNodeChange(selection.next())} iconClassName="mdi mdi-arrow-right" disabled={!selection.hasNext()} />
    </ToolbarGroup>
}

const mapStateToProps = (state, props) => state.tabs.filter(({editorData, node}) => editorData.id === props.editorData.id && node.getParent() === props.node.getParent())[0]

export default connect(mapStateToProps, Actions)(SelectionControls);
