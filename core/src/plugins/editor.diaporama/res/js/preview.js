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



import React, {Component} from 'react'
import { ImageContainer } from './components'

const baseURL = pydio.Parameters.get('ajxpServerAccess');

const Preview = ({node, ...remainingProps}) => {
    const repositoryId = node.getMetadata().get("repository_id")

    let repoString = "";
    if (typeof pydio !== "undefined" && repositoryId && repositoryId !== pydio.repositoryId){
        repoString = "&tmp_repository_id=" + repositoryId;
    }

    const mtimeString = node.buildRandomSeed();

    return (
        <ImageContainer
            {...remainingProps}
            src={`${baseURL}${repoString}${mtimeString}&action=preview_data_proxy&get_thumb=true&file=${encodeURIComponent(node.getPath())}`}
            imgStyle={{
                width: "100%",
                height: "100%",
                flex: 1
            }}
        />
    )
}

export default Preview
