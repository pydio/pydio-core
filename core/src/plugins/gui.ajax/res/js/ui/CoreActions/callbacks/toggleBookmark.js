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

import {pydio} from '../globals'
const PydioApi = require('pydio/http/api')

export default function(){
    const selection = pydio.getContextHolder();
    if(selection.isEmpty() || !selection.isUnique()){
        return;
    }
    const node = selection.getUniqueNode();
    const isBookmarked = node.getMetadata().get('ajxp_bookmarked') === 'true';
    PydioApi.getClient().request({
        get_action:'get_bookmarks',
        bm_action: isBookmarked ? 'delete_bookmark' : 'add_bookmark',
        bm_path:node.getPath()
    }, (t) => {
        selection.requireNodeReload(node);
    });
}