const Confirm = React.createClass({

    propTypes:{
        pydio:React.PropTypes.instanceOf(Pydio),
        onDecline:React.PropTypes.func,
        onAccept:React.PropTypes.func,
        mode:React.PropTypes.oneOf(['new_share','reject_accepted'])
    },

    componentDidMount: function () {
        this.refs.dialog.show()
    },

    render: function () {
        var messages = this.props.pydio.MessageHash,
            messageTitle = messages[545],
            messageBody = messages[546],
            actions = [
                { text: messages[548], ref: 'decline', onClick: this.props.onDecline},
                { text: messages[547], ref: 'accept', onClick: this.props.onAccept}
            ];
        if(this.props.mode == 'reject_accepted'){
            messageBody = messages[549];
            actions = [
                { text: messages[54], ref: 'decline', onClick: this.props.onDecline},
                { text: messages[551], ref: 'accept', onClick: this.props.onAccept}
            ];
        }

        for (var key in this.props.replacements) {
            messageTitle = messageTitle.replace(new RegExp(key), this.props.replacements[key]);
            messageBody = messageBody.replace(new RegExp(key), this.props.replacements[key]);
        }

        return <div className='react-mui-context' style={{position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', background: 'transparent'}}>
            <ReactMUI.Dialog
                ref="dialog"
                title={messageTitle}
                actions={actions}
                modal={false}
                dismissOnClickAway={true}
                onDismiss={this.props.onDismiss.bind(this)}
                open={true}
            >
                {messageBody}
            </ReactMUI.Dialog>
        </div>
    }
});

let WorkspaceEntry =React.createClass({

    mixins:[PydioComponents.MessagesConsumerMixin],

    propTypes:{
        pydio:React.PropTypes.instanceOf(Pydio).isRequired,
        workspace:React.PropTypes.instanceOf(Repository).isRequired,
        showFoldersTree:React.PropTypes.bool,
        onHoverLink:React.PropTypes.func,
        onOutLink:React.PropTypes.func
    },

    getInitialState:function(){
        return {
            openAlert:false,
            openFoldersTree: false,
            currentContextNode: this.props.pydio.getContextHolder().getContextNode()
        };
    },

    getLetterBadge:function(){
        return {__html:this.props.workspace.getHtmlBadge(true)};
    },

    componentDidMount: function(){
        if(this.props.showFoldersTree){
            this._monitorFolder = function(){
                this.setState({currentContextNode: this.props.pydio.getContextHolder().getContextNode()});
            }.bind(this);
            this.props.pydio.getContextHolder().observe("context_changed", this._monitorFolder);
        }
    },

    componentWillUnmount: function(){
        if(this._monitorFolder){
            this.props.pydio.getContextHolder().stopObserving("context_changed", this._monitorFolder);
        }
    },

    handleAccept: function () {
        PydioApi.getClient().request({
            'get_action': 'accept_invitation',
            'remote_share_id': this.props.workspace.getShareId()
        }, function () {
            // Switching status to decline
            this.props.workspace.setAccessStatus('accepted');

            this.handleCloseAlert();
            this.onClick();

        }.bind(this), function () {
            this.handleCloseAlert();
        }.bind(this));
    },

    handleDecline: function () {
        PydioApi.getClient().request({
            'get_action': 'reject_invitation',
            'remote_share_id': this.props.workspace.getShareId()
        }, function () {
            // Switching status to decline
            this.props.workspace.setAccessStatus('declined');

            this.props.pydio.fire("repository_list_refreshed", {
                list: this.props.pydio.user.getRepositoriesList(),
                active: this.props.pydio.user.getActiveRepository()
            });

            this.handleCloseAlert();
        }.bind(this), function () {
            this.handleCloseAlert();
        }.bind(this));
    },

    handleOpenAlert: function (mode = 'new_share', event) {
        event.stopPropagation();
        this.wrapper = document.body.appendChild(document.createElement('div'));
        this.wrapper.style.zIndex = 11;
        var replacements = {
            '%%OWNER%%': this.props.workspace.getOwner()
        };
        ReactDOM.render(
            <Confirm
                {...this.props}
                mode={mode}
                replacements={replacements}
                onAccept={mode == 'new_share' ? this.handleAccept.bind(this) : this.handleDecline.bind(this)}
                onDecline={mode == 'new_share' ? this.handleDecline.bind(this) : this.handleCloseAlert.bind(this)}
                onDismiss={this.handleCloseAlert}
            />, this.wrapper);
    },

    handleCloseAlert: function() {
        ReactDOM.unmountComponentAtNode(this.wrapper);
        this.wrapper.remove();
    },

    handleRemoveTplBasedWorkspace: function(event){
        event.stopPropagation();
        if(!global.confirm(this.props.pydio.MessageHash['424'])){
            return;
        }
        PydioApi.getClient().request({get_action:'user_delete_repository', repository_id:this.props.workspace.getId()}, function(transport){
            PydioApi.getClient().parseXmlMessage(transport.responseXML);
        });
    },

    onClick:function() {
        if(this.props.workspace.getId() === this.props.pydio.user.activeRepository && this.props.showFoldersTree){
            this.props.pydio.goTo('/');
        }else{
            this.props.pydio.triggerRepositoryChange(this.props.workspace.getId());
        }
    },

    toggleFoldersPanelOpen: function(ev){
        ev.stopPropagation();
        this.setState({openFoldersTree: !this.state.openFoldersTree});
    },

    render:function(){
        var current = (this.props.pydio.user.getActiveRepository() == this.props.workspace.getId()),
            currentClass="workspace-entry",
            messages = this.props.pydio.MessageHash,
            onHover, onOut, onClick,
            additionalAction,
            badge, badgeNum, newWorkspace;

        const selectedItemStyle = {
            backgroundColor: this.props.muiTheme.palette.accent2Color,
            color: 'white'
        };
        let style = {};

        if (current) {
            currentClass +=" workspace-current";
            if(!this.state.openFoldersTree || (this.state.currentContextNode && this.state.currentContextNode.getPath() === '/')){
                style = {...selectedItemStyle};
            }else{
                style = {fontWeight: 500}
            }
        }

        currentClass += " workspace-access-" + this.props.workspace.getAccessType();

        if (this.props.onHoverLink) {
            onHover = function(event){
                this.props.onHoverLink(event, this.props.workspace)
            }.bind(this);
        }

        if (this.props.onOutLink) {
            onOut = function(event){
                this.props.onOutLink(event, this.props.ws)
            }.bind(this);
        }

        onClick = this.onClick;

        // Icons
        if (this.props.workspace.getAccessType() == "inbox") {
            var status = this.props.workspace.getAccessStatus();

            if (!isNaN(status) && status > 0) {
                badgeNum = <span className="workspace-num-badge">{status}</span>;
            }

            badge = <span className="workspace-badge"><span className="access-icon"/></span>;
        } else if(this.props.workspace.getOwner()){
            var overlay = <span className="badge-overlay mdi mdi-share-variant"/>;
            if(this.props.workspace.getRepositoryType() == "remote"){
                overlay = <span className="badge-overlay icon-cloud"/>;
            }
            badge = <span className="workspace-badge"><span className="mdi mdi-folder"/>{overlay}</span>;
        } else{
            badge = <span className="workspace-badge"><span>{this.props.workspace.getLettersBadge()}</span></span>;
        }

        if (this.props.workspace.getOwner() && !this.props.workspace.getAccessStatus() && !this.props.workspace.getLastConnection()) {
            newWorkspace = <span className="workspace-new">NEW</span>;
            // Dialog for remote shares
            if (this.props.workspace.getRepositoryType() == "remote") {
                onClick = this.handleOpenAlert.bind(this, 'new_share');
            }
        }else if(this.props.workspace.getRepositoryType() == "remote" && !current){
            // Remote share but already accepted, add delete
            additionalAction = <span className="workspace-additional-action mdi mdi-close" onClick={this.handleOpenAlert.bind(this, 'reject_accepted')} title={messages['550']}/>;
        }else if(this.props.workspace.userEditable && !current){
            additionalAction = <span className="workspace-additional-action mdi mdi-close" onClick={this.handleRemoveTplBasedWorkspace} title={messages['423']}/>;
        }

        if(this.props.showFoldersTree){
            let fTCName = this.state.openFoldersTree ? "workspace-additional-action icon-angle-up" : "workspace-additional-action icon-angle-down";
            additionalAction = <span className={fTCName} onClick={this.toggleFoldersPanelOpen}></span>;
        }

        let wsBlock = (
            <div
                className={currentClass}
                onClick={onClick}
                title={this.props.workspace.getDescription()}
                onMouseOver={onHover}
                onMouseOut={onOut}
                style={style}
            >
                {badge}
                <span className="workspace-label-container">
                    <span className="workspace-label">{this.props.workspace.getLabel()}{newWorkspace}{badgeNum}</span>
                    <span className="workspace-description">{this.props.workspace.getDescription()}</span>
                </span>
                {additionalAction}
            </div>
        );

        if(this.props.showFoldersTree){
            return (
                <div>
                    {wsBlock}
                    <PydioComponents.FoldersTree
                        pydio={this.props.pydio}
                        dataModel={this.props.pydio.getContextHolder()}
                        className={this.state.openFoldersTree?"open":"closed"}
                        draggable={true}
                        selectedItemStyle={selectedItemStyle}
                    />
                </div>
            )
        }else{
            return wsBlock;
        }

    }

});

WorkspaceEntry = MaterialUI.Style.muiThemeable()(WorkspaceEntry);
export {WorkspaceEntry as default}