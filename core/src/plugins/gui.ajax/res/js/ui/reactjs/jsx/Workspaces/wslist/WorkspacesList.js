import WorkspaceEntry from './WorkspaceEntry'

export default React.createClass({

    propTypes:{
        pydio                   : React.PropTypes.instanceOf(Pydio),
        workspaces              : React.PropTypes.instanceOf(Map),
        showTreeForWorkspace    : React.PropTypes.string,
        onHoverLink             : React.PropTypes.func,
        onOutLink               : React.PropTypes.func,
        className               : React.PropTypes.string,
        style                   : React.PropTypes.object
    },

    createRepositoryEnabled:function(){
        var reg = this.props.pydio.Registry.getXML();
        return XMLUtils.XPathSelectSingleNode(reg, 'actions/action[@name="user_create_repository"]') !== null;
    },

    render: function(){
        var entries = [], sharedEntries = [], inboxEntry;

        this.props.workspaces.forEach(function(object, key){

            if (object.getId().indexOf('ajxp_') === 0) return;
            if (object.hasContentFilter()) return;
            if (object.getAccessStatus() === 'declined') return;

            var entry = (
                <WorkspaceEntry
                    {...this.props}
                    key={key}
                    workspace={object}
                    showFoldersTree={this.props.showTreeForWorkspace && this.props.showTreeForWorkspace===key}
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

        var messages = this.props.pydio.MessageHash;

        if(this.createRepositoryEnabled()){
            var createClick = function(){
                this.props.pydio.Controller.fireAction('user_create_repository');
            }.bind(this);
            var createAction = (
                <div className="workspaces">
                    <div className="workspace-entry" onClick={createClick} title={messages[418]}>
                        <span className="workspace-badge">+</span>
                        <span className="workspace-label">{messages[417]}</span>
                        <span className="workspace-description">{messages[418]}</span>
                    </div>
                </div>
            );
        }

        let workspacesTitle, sharedEntriesTitle, createActionTitle;
        if(entries.length){
            workspacesTitle = <div className="section-title">{messages[468]}</div>;
        }
        if(sharedEntries.length){
            sharedEntriesTitle = <div className="section-title">{messages[469]}</div>;
        }
        if(createAction){
            createActionTitle = <div className="section-title"></div>;
        }

        return (
            <div className={"user-workspaces-list" + (this.props.className ? ' ' + this.props.className  : '')} style={this.props.style}>
                {workspacesTitle}
                <div className="workspaces">
                    {entries}
                </div>
                {sharedEntriesTitle}
                <div className="workspaces">
                    {sharedEntries}
                </div>
                {createActionTitle}
                {createAction}
            </div>
        );
    }
});
