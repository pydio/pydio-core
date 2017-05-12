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

import React from 'react'
import Pydio from 'pydio'
const {withVerticalScroll} = Pydio.requireLib('hoc')
import WorkspaceEntry from './WorkspaceEntry'
import XMLUtils from 'pydio/util/xml'

let WorkspacesList = React.createClass({

    propTypes:{
        pydio                   : React.PropTypes.instanceOf(Pydio),
        workspaces              : React.PropTypes.instanceOf(Map),
        showTreeForWorkspace    : React.PropTypes.string,
        onHoverLink             : React.PropTypes.func,
        onOutLink               : React.PropTypes.func,
        className               : React.PropTypes.string,
        style                   : React.PropTypes.object,
        sectionTitleStyle       : React.PropTypes.object,
        filterByType            : React.PropTypes.oneOf(['shared', 'entries', 'create'])
    },

    createRepositoryEnabled:function(){
        const reg = this.props.pydio.Registry.getXML();
        return XMLUtils.XPathSelectSingleNode(reg, 'actions/action[@name="user_create_repository"]') !== null;
    },

    render: function(){
        let entries = [], sharedEntries = [], inboxEntry, createAction;
        const {workspaces, showTreeForWorkspace, pydio, className, style, filterByType} = this.props;

        workspaces.forEach(function(object, key){

            if (object.getId().indexOf('ajxp_') === 0) return;
            if (object.hasContentFilter()) return;
            if (object.getAccessStatus() === 'declined') return;

            const entry = (
                <WorkspaceEntry
                    {...this.props}
                    key={key}
                    workspace={object}
                    showFoldersTree={showTreeForWorkspace && showTreeForWorkspace===key}
                />
            );
            if (object.getAccessType() == "inbox") {
                inboxEntry = entry;
            } else if(object.getOwner()) {
                sharedEntries.push(entry);
            } else {
                entries.push(entry);
            }
        }.bind(this));

        if(inboxEntry){
            sharedEntries.unshift(inboxEntry);
        }

        const messages = pydio.MessageHash;

        if(this.createRepositoryEnabled()){
            const createClick = function(){
                pydio.Controller.fireAction('user_create_repository');
            }.bind(this);
            createAction = (
                <div className="workspaces" key="create-action">
                    <div className="workspace-entry" onClick={createClick} title={messages[418]}>
                        <span className="workspace-badge">+</span>
                        <span className="workspace-label">{messages[417]}</span>
                        <span className="workspace-description">{messages[418]}</span>
                    </div>
                </div>
            );
        }
        
        let sections = [];
        if(entries.length){
            sections.push({
                k:'entries', 
                title: <div key="entries-title" className="section-title" style={this.props.sectionTitleStyle}>{messages[468]}</div>,
                content: <div key="entries-ws" className="workspaces">{entries}</div>
            });
        }
        if(sharedEntries.length){
            sections.push({
                k:'shared', 
                title: <div key="shared-title" className="section-title" style={this.props.sectionTitleStyle}>{messages[469]}</div>,
                content: <div key="shared-ws" className="workspaces">{sharedEntries}</div> 
            });
        }
        if(createAction){
            sections.push({
                k:'create', 
                title: <div key="create-title" className="section-title" style={this.props.sectionTitleStyle}></div>,
                content: createAction
            });
        }

        let classNames = ['user-workspaces-list'];
        if(className) classNames.push(className);

        if(filterByType){
            let ret;
            sections.map(function(s){
                if(filterByType && filterByType === s.k){
                    ret = <div className={classNames.join(' ')} style={style}>{s.title}{s.content}</div>
                }
            });
            return ret;
        }

        let elements = [];
        sections.map(function(s) {
            elements.push(s.title);
            elements.push(s.content);
        });
        return (
            <div className={classNames.join(' ')} style={style}>
                {elements}
            </div>
        );
    }
});

WorkspacesList = withVerticalScroll(WorkspacesList);

export {WorkspacesList as default}