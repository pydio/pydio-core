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

import { ToolbarGroup, IconButton } from 'material-ui';
import { connect } from 'react-redux';
import { mapStateToProps, Actions } from './utils';

const Controls = ({id, selection, playing = false, tabModify, ...remainingProps}) => {

    const handleNodeChange = (node) => tabModify({id, title: node.getLabel(), node})

    const togglePlaying = () => tabModify({id, playing: !playing})

    return (
        <ToolbarGroup {...remainingProps}>
            <IconButton onClick={() => handleNodeChange(selection.previous())} iconClassName="mdi mdi-arrow-left" disabled={!selection.hasPrevious()} />
            <IconButton onClick={() => togglePlaying()} iconClassName={`mdi mdi-${playing ? "pause" : "play"}`} disabled={!selection.hasPrevious() && !selection.hasNext()} />
            <IconButton onClick={() => handleNodeChange(selection.next())} iconClassName="mdi mdi-arrow-right" disabled={!selection.hasNext()} />
        </ToolbarGroup>
    )
}

export default connect(mapStateToProps, Actions)(Controls);
