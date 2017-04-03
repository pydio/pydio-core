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
            sharedEntries.unshift(<MaterialUI.Subheader>Shared folders</MaterialUI.Subheader>);
        }
        if(inboxEntry){
            sharedEntries.unshift(inboxEntry);
            sharedEntries.unshift(<MaterialUI.Subheader>Files Inbox</MaterialUI.Subheader>);
        }
        if(remoteShares.length){
            remoteShares.unshift(<MaterialUI.Subheader>Shares from remote servers</MaterialUI.Subheader>)
            sharedEntries = sharedEntries.concat(remoteShares);
        }
        if(filterByType){
            allEntries = filterByType === 'shared' ? sharedEntries : entries
        }else{
            allEntries = entries.concat(sharedEntries);
        }

        return (
            <MaterialUI.List style={this.props.style}>
                {allEntries}
            </MaterialUI.List>
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