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
import { connect } from 'react-redux'
import { compose } from 'redux'

let Viewer = ({url, style}) => {
    return (
        <iframe src={url} style={{...style, height: "100%", border: 0, flex: 1}} className="vertical_fit"></iframe>
    );
};

class Editor extends React.Component {
    componentDidMount() {
        this.loadNode(this.props)
    }

    componentWillReceiveProps(nextProps) {
        if (nextProps.node !== this.props.node) {
            this.loadNode(nextProps)
        }
    }

    loadNode(props) {
        const {node, pydio} = props
        this.setState({url: `${pydio.Parameters.get('ajxpServerAccess')}&get_action=post_to_zohoserver&file=${HasherUtils.base64_encode(node.getPath())}&parent_url=${HasherUtils.base64_encode(DOMUtils.getUrlFromBase())}`});
    }

    componentWillUnmount() {
        const {pydio, node} = this.props

        pydio.ApiClient.request({ get_action: 'retrieve_from_zohoagent'}, ({responseText}) => {
            if (responseText == "MODIFIED") {
                node.getParent().getMetadata().set('preview_seed', Math.round(date.getTime()*Math.random()));
                pydio.fireNodeRefresh(node);
            }
        });
    }

    render() {
        const {url} = this.state || {}

        if (!url) return null

        return (
            <Viewer {...this.props} url={url}/>
        );

    }
}

// Define HOCs
if (typeof PydioHOCs !== "undefined") {
    Viewer = PydioHOCs.withLoader(Viewer)
    Viewer = PydioHOCs.withErrors(Viewer)
}

export default compose(
    connect()
)(Editor)
