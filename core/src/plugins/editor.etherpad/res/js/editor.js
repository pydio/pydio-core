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

import React, {Component} from 'react'
import PathUtils from 'pydio/util/path'
import PeriodicalExecuter from 'pydio/util/periodical-executer'
import Pydio from 'pydio'
const {Loader} = Pydio.requireLib('boot')
const {EditorActions} = Pydio.requireLib('hoc')
import {compose} from 'redux'
import {connect} from 'react-redux'

class Viewer extends Component {
    componentDidMount() {
        this.loadNode(this.props)
    }

    componentWillReceiveProps(nextProps) {
        if (nextProps.node !== this.props.node) {
            this.loadNode(nextProps)
        }
    }

    observeChanges(){

        if(!this.padID || !this.props.node) return;
        PydioApi.getClient().request({
            get_action: 'etherpad_get_content',
            file: this.props.node.getPath(),
            pad_id: this.padID
        }, (transport) => {
            var content = transport.responseText;
            if(this.previousContent && this.previousContent != content){
                this.setModified(true);
            }
            this.previousContent = content;
        }, null, {discrete: true});

    }

    setModified(status){
        const {pydio, node, tab, dispatch} = this.props

        const {id} = tab

        dispatch(EditorActions.tabModify({id: id, title: node.getLabel() + (status ? '*' : '')}));

    }

    loadNode(props) {
        const {pydio, node, dispatch, tab} = props;
        const {id} = tab;

        let url;
        let base = DOMUtils.getUrlFromBase();
        const extension = PathUtils.getFileExtension(node.getPath());

        PydioApi.getClient().request({
            get_action: 'etherpad_create',
            file : node.getPath()
        }, (transport) => {
            var data = transport.responseJSON;
            this.padID = data.padID;
            this.sessionID = data.sessionID;
            dispatch(EditorActions.tabModify({id: id, padID: data.padID, frameUrl: data.url, sessionID: data.sessionID}));

            if(extension !== "pad"){
                this.observeChanges();
                this.pe = new PeriodicalExecuter(this.observeChanges.bind(this), 5);
            }
        });

    }

    render() {
        const {tab} = this.props;
        const {frameUrl} = tab;

        if (!frameUrl) {
            return <Loader/>
        }

        return (
            <iframe {...this.props} style={{width: "100%", height: "100%", border: 0}} src={frameUrl} />
        );
    }
}

const editors = pydio.Registry.getActiveExtensionByType("editor")
const conf = editors.filter(({id}) => id === 'editor.etherpad')[0]

const getSelectionFilter = (node) => conf.mimes.indexOf(node.getAjxpMime()) > -1

const getSelection = (node) => new Promise((resolve, reject) => {
    let selection = [];

    node.getParent().getChildren().forEach((child) => selection.push(child));
    selection = selection.filter(getSelectionFilter)

    resolve({
        selection,
        currentIndex: selection.reduce((currentIndex, current, index) => current === node && index || currentIndex, 0)
    })
})

const {withSelection} = PydioHOCs;

const mapStateToProps = (state, props) => {
    const {tabs} = state

    const tab = tabs.filter(({editorData, node}) => (!editorData || editorData.id === props.editorData.id) && node.getPath() === props.node.getPath())[0] || {}

    return {
        id: tab.id,
        tab,
        ...props
    }
}


const Editor = compose(
    withSelection(getSelection),
    connect(mapStateToProps)
)(Viewer)

export default Editor