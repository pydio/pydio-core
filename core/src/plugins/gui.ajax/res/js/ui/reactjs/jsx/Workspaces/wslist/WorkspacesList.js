import WorkspaceEntry from './WorkspaceEntry'

export default React.createClass({

    propTypes:{
        pydio                   : React.PropTypes.instanceOf(Pydio),
        workspaces              : React.PropTypes.instanceOf(Map),
        showTreeForWorkspace    : React.PropTypes.string,
        onHoverLink             : React.PropTypes.func,
        onOutLink               : React.PropTypes.func,
        className               : React.PropTypes.string,
        style                   : React.PropTypes.object,
        filterByType            : React.PropTypes.oneOf(['shared', 'entries', 'create'])
    },

    createRepositoryEnabled:function(){
        var reg = this.props.pydio.Registry.getXML();
        return XMLUtils.XPathSelectSingleNode(reg, 'actions/action[@name="user_create_repository"]') !== null;
    },

    render: function(){
        var entries = [], sharedEntries = [], inboxEntry;
        const {workspaces, showTreeForWorkspace, pydio, className, style, filterByType} = this.props;

        workspaces.forEach(function(object, key){

            if (object.getId().indexOf('ajxp_') === 0) return;
            if (object.hasContentFilter()) return;
            if (object.getAccessStatus() === 'declined') return;

            var entry = (
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

        var messages = pydio.MessageHash;

        if(this.createRepositoryEnabled()){
            var createClick = function(){
                pydio.Controller.fireAction('user_create_repository');
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
        
        let sections = [];
        if(entries.length){
            sections.push({
                k:'entries', 
                title: <div key="entries-title" className="section-title">{messages[468]}</div>, 
                content: <div key="entries-ws" className="workspaces">{entries}</div>
            });
        }
        if(sharedEntries.length){
            sections.push({
                k:'shared', 
                title: <div key="shared-title" className="section-title">{messages[469]}</div>, 
                content: <div key="shared-ws" className="workspaces">{sharedEntries}</div> 
            });
        }
        if(createAction){
            sections.push({
                k:'create', 
                title: <div key="create-title" className="section-title"></div>, 
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
