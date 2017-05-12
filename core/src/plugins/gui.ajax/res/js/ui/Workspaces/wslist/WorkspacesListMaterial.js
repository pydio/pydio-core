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

const React = require('react')
const {List, Subheader, Divider} = require('material-ui')

import WorkspaceEntryMaterial from './WorkspaceEntryMaterial'

class WorkspacesListMaterial extends React.Component{

    render(){
        const {workspaces,showTreeForWorkspace, filterByType} = this.props;
        let inboxEntry, entries = [], sharedEntries = [], remoteShares = [];

        workspaces.forEach(function(object, key){

            if (object.getId().indexOf('ajxp_') === 0) return;
            if (object.hasContentFilter()) return;
            if (object.getAccessStatus() === 'declined') return;

            const entry = (
                <WorkspaceEntryMaterial
                    {...this.props}
                    key={key}
                    workspace={object}
                    showFoldersTree={showTreeForWorkspace && showTreeForWorkspace===key}
                />
            );
            if (object.getAccessType() == "inbox") {
                inboxEntry = entry;
            } else if(object.getOwner()) {
                if(object.getRepositoryType() === 'remote'){
                    remoteShares.push(entry);
                }else{
                    sharedEntries.push(entry);
                }
            } else {
                entries.push(entry);
            }
        }.bind(this));

        let allEntries;
        if(sharedEntries.length){
            sharedEntries.unshift(<Subheader>Folders shared with me</Subheader>);
        }
        if(inboxEntry){
            if(sharedEntries.length){
                sharedEntries.unshift(<Divider/>);
            }
            sharedEntries.unshift(inboxEntry);
            sharedEntries.unshift(<Subheader>Files Shared with me</Subheader>);
        }
        if(remoteShares.length){
            remoteShares.unshift(<Subheader>Shares from remote servers</Subheader>)
            remoteShares.unshift(<Divider/>)
            sharedEntries = sharedEntries.concat(remoteShares);
        }
        if(filterByType === 'entries'){
            entries.unshift(<Subheader>My Workspaces</Subheader>);
        }
        if(filterByType){
            allEntries = filterByType === 'shared' ? sharedEntries : entries
        }else{
            allEntries = entries.concat(sharedEntries);
        }

        return (
            <List style={this.props.style}>
                {allEntries}
            </List>
        );


    }

}

WorkspacesListMaterial.propTypes = {
    pydio                   : React.PropTypes.instanceOf(Pydio),
    workspaces              : React.PropTypes.instanceOf(Map),
    filterByType            : React.PropTypes.oneOf(['shared', 'entries', 'create']),

    sectionTitleStyle       : React.PropTypes.object,
    showTreeForWorkspace    : React.PropTypes.string,
    onHoverLink             : React.PropTypes.func,
    onOutLink               : React.PropTypes.func,
    className               : React.PropTypes.string,
    style                   : React.PropTypes.object
}



export {WorkspacesListMaterial as default}