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
 * The latest code can be found at <http://pyd.io/>.
 */
(function(global) {

    var MainPanel = React.createClass({

        propTypes: {
            closeAjxpDialog: React.PropTypes.func.isRequired,
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            selection:React.PropTypes.instanceOf(PydioDataModel).isRequired
        },

        refreshDialogPosition:function(){
            global.pydio.UI.modal.refreshDialogPosition();
        },

        modelUpdated: function(eventData){
            if(this.isMounted()){
                this.setState({
                    status: eventData.status,
                    model:eventData.model
                }, function(){
                    this.refreshDialogPosition();
                }.bind(this));
                if(eventData.status == "saved"){
                    this.props.pydio.fireNodeRefresh(this.props.selection.getUniqueNode());
                }
            }
        },

        getInitialState: function(){
            return {
                status: 'idle',
                mailerData: false,
                model: new ReactModel.Share(this.props.pydio, this.props.selection)
            };
        },

        showMailer:function(subject, message, users = []){
            if(ReactModel.Share.forceMailerOldSchool()){
                subject = encodeURIComponent(subject);
                message = encodeURIComponent(message);
                global.location.href = "mailto:custom-email@domain.com?Subject="+subject+"&Body="+message;
                return;
            }
            global.ResourcesManager.loadClassesAndApply(['PydioMailer'], function(){
                this.setState({
                    mailerData: {
                        subject:subject,
                        message:message,
                        users:users
                    }
                });
            }.bind(this));
        },

        dismissMailer:function(){
            this.setState({mailerData:false});
        },

        componentDidMount: function(){
            this.state.model.observe("status_changed", this.modelUpdated);
        },

        clicked: function(){
            this.props.closeAjxpDialog();
        },

        render: function(){
            var model = this.state.model;
            var panels = [];
            var showMailer = ReactModel.Share.mailerActive() ? this.showMailer : null;
            var auth = ReactModel.Share.getAuthorizations(this.props.pydio);
            if((model.getNode().isLeaf() && auth.file_public_link) || (!model.getNode().isLeaf() && auth.folder_public_link)){
                var publicLinks = model.getPublicLinks();
                if(publicLinks.length){
                    var linkData = publicLinks[0];
                }
                panels.push(
                    <ReactMUI.Tab key="public-link" label={'Public Link' + (model.hasPublicLink()?' (active)':'')}>
                        <PublicLinkPanel
                            showMailer={showMailer}
                            linkData={linkData}
                            pydio={this.props.pydio}
                            shareModel={model}
                            authorizations={auth}
                        />
                    </ReactMUI.Tab>
                );
            }
            if( (model.getNode().isLeaf() && auth.file_workspaces) || (!model.getNode().isLeaf() && auth.folder_workspaces)){
                var users = model.getSharedUsers();
                var ocsUsers = model.getOcsLinks();
                var totalUsers = users.length + ocsUsers.length;
                panels.push(
                    <ReactMUI.Tab key="target-users" label={'Users' + (totalUsers?' ('+totalUsers+')':'')}>
                        <UsersPanel
                            showMailer={showMailer}
                            shareModel={model}
                        />
                    </ReactMUI.Tab>
                );
            }
            if(panels.length > 0){
                panels.push(
                    <ReactMUI.Tab  key="share-permissions" label="Advanced">
                        <AdvancedPanel
                            showMailer={showMailer}
                            pydio={this.props.pydio}
                            shareModel={model}
                        />
                    </ReactMUI.Tab>
                );
            }
            if(this.state.mailerData){
                var mailer = (<PydioMailer.Pane
                    {...this.state.mailerData}
                    onDismiss={this.dismissMailer}
                    overlay={true}
                    className="share-center-mailer"
                    panelTitle={this.props.pydio.MessageHash["share_center.45"]}
                />);
            }

            return(
                <div style={{width:420}}>
                    <HeaderPanel {...this.props} shareModel={this.state.model}/>
                    <ReactMUI.Tabs onChange={this.refreshDialogPosition}>{panels}</ReactMUI.Tabs>
                    <ButtonsPanel {...this.props} shareModel={this.state.model} onClick={this.clicked}/>
                    {mailer}
                </div>
            );
        }

    });

    var HeaderPanel = React.createClass({
        render: function(){
            return (
                <div className="headerPanel">
                    <div style={{fontSize: 24, color:'white', padding:'20px 16px 14px'}}>Share {PathUtils.getBasename(this.props.shareModel.getNode().getPath())}</div>
                </div>
            );
        }
    });

    var ButtonsPanel = React.createClass({
        propTypes: {
            onClick: React.PropTypes.func.isRequired
        },
        triggerModelSave: function(){
            this.props.shareModel.save();
        },
        triggerModelRevert:function(){
            this.props.shareModel.revertChanges();
        },
        render: function(){
            if(this.props.shareModel.getStatus() == 'modified'){
                return (
                    <div style={{padding:16,textAlign:'right'}}>
                        <a className="revert-button" onClick={this.triggerModelRevert}>Revert Changes</a>
                        <ReactMUI.FlatButton secondary={true} label="Save" onClick={this.triggerModelSave}/>
                        <ReactMUI.FlatButton secondary={false} label="Close" onClick={this.props.onClick}/>
                    </div>
                );
            }else{
                return (
                    <div style={{padding:16,textAlign:'right'}}>
                        <ReactMUI.FlatButton secondary={false} label="Close" onClick={this.props.onClick}/>
                    </div>
                );
            }
        }
    });

    /**************************/
    /* USERS PANEL
    /**************************/
    var UsersPanel = React.createClass({
        propTypes:{
            shareModel:React.PropTypes.instanceOf(ReactModel.Share),
            showMailer:React.PropTypes.func
        },
        onUserUpdate: function(operation, userId, userData){
            this.props.shareModel.updateSharedUser(operation, userId, userData);
        },
        onSaveSelection:function(){
            var label = window.prompt(global.pydio.MessageHash[510]);
            if(!label) return;
            this.props.shareModel.saveSelectionAsTeam(label);
        },
        valueSelected: function(id, label, type){
            var newEntry = {
                ID: id,
                RIGHT:'r',
                LABEL: label,
                TYPE:type
            };
            this.props.shareModel.updateSharedUser('add', newEntry.ID, newEntry);
        },
        completerRenderSuggestion: function(userObject){
            return (
                <UserBadge
                    label={userObject.getExtendedLabel() || userObject.getLabel()}
                    avatar={userObject.getAvatar()}
                    type={userObject.getGroup() ? 'group' : (userObject.getTemporary()?'temporary' : (userObject.getExternal()?'tmp_user':'user'))}
                />
            );
        },

        sendInvitations:function(userObjects){
            var mailData = this.props.shareModel.prepareEmail("repository");
            this.props.showMailer(mailData.subject, mailData.message, userObjects);
        },

        render: function(){
            var currentUsers = this.props.shareModel.getSharedUsers();
            const excludes = currentUsers.map(function(u){return u.ID});
            return (
                <div style={{padding:'30px 20px 10px'}}>
                    <UsersCompleter.Input
                        fieldLabel="Pick a user or create one"
                        renderSuggestion={this.completerRenderSuggestion}
                        onValueSelected={this.valueSelected}
                        excludes={excludes}
                    />
                    <SharedUsersBox
                        users={currentUsers}
                        userObjects={this.props.shareModel.getSharedUsersAsObjects()}
                        sendInvitations={this.props.showMailer ? this.sendInvitations : null}
                        onUserUpdate={this.onUserUpdate}
                        saveSelectionAsTeam={PydioUsers.Client.saveSelectionSupported()?this.onSaveSelection:null}
                    />
                    <RemoteUsers shareModel={this.props.shareModel}/>
                </div>
            );
        }
    });

    var UserBadge = React.createClass({
        propTypes: {
            label: React.PropTypes.string,
            avatar: React.PropTypes.string,
            type:React.PropTypes.string,
            menus: React.PropTypes.object
        },
        getInitialState(){
            return {showMenu:false};
        },
        showMenu: function () {
            this.setState({showMenu: true});
        },
        /****************************/
        /* WARNING: PROTOTYPE CODE
         */
        hideMenu: function(event){
            if(event && event.target.up('.mui-icon-button')){
                return;
            }
            this.setState({showMenu: false});
        },
        componentDidMount: function(){
            this._observer = this.hideMenu.bind(this);
            document.observe('click', this._observer);
        },
        componentWillUnmount: function(){
            document.stopObserving('click', this._observer);
        },
        /*
        /* END PROTOTYPE CODE
        /***************************/

        menuClicked:function(event, index, menuItem){
            if(menuItem.payload){
                menuItem.payload();
            }
            this.hideMenu();
        },
        renderMenu: function(){
            if (!this.props.menus) {
                return null;
            }
            var menuAnchor = <ReactMUI.IconButton iconClassName="icon-ellipsis-vertical" onClick={this.showMenu}/>;
            if(this.state.showMenu) {
                const menuItems = this.props.menus.map(function(m){
                    var text = m.text;
                    if(m.checked){
                        text = <span><span className="icon-check"/>{m.text}</span>;
                    }
                    return {text:text, payload:m.callback};
                });
                var menuBox = <ReactMUI.Menu onItemClick={this.menuClicked} zDepth={0} menuItems={menuItems}/>;
            }
            return (
                <div className="user-badge-menu-box">
                    {menuAnchor}
                    {menuBox}
                </div>
            );
        },

        render: function () {
            var avatar;
            /*
            if (this.props.avatar) {
                avatar = (
                    <span className="user-badge-avatar">
                        <img src="" width={40} height={40}
                             src={global.pydio.Parameters.get('ajxpServerAccess')+'&get_action=get_binary_param&binary_id='+this.props.avatar}/>
                    </span>
                );
            }else{
                avatar = <span className="icon-user"/>;
            }*/
            if(this.props.type == 'group') {
                avatar = <span className="avatar icon-group"/>;
            }else if(this.props.type == 'temporary') {
                avatar = <span className="avatar icon-plus"/>;
            }else if(this.props.type == 'remote_user'){
                avatar = <span className="avatar icon-cloud"/>;
            }else{
                avatar = <span className="avatar icon-user"/>;
            }
            var menu = this.renderMenu();
            return (
                <div className={"user-badge user-type-" + this.props.type}>
                    {menu}
                    {avatar}
                    <span className="user-badge-label">{this.props.label}</span>
                    {this.props.children}
                </div>
            );
        }
    });

    var SharedUsersBox = React.createClass({
        propTypes: {
            users:React.PropTypes.array.isRequired,
            userObjects:React.PropTypes.object.isRequired,
            onUserUpdate:React.PropTypes.func.isRequired,
            saveSelectionAsTeam:React.PropTypes.func,
            sendInvitations:React.PropTypes.func
        },
        sendInvitationToAllUsers:function(){
            this.props.sendInvitations(this.props.userObjects);
        },
        clearAllUsers:function(){
            this.props.users.map(function(entry){
                this.props.onUserUpdate('remove', entry.ID, entry);
            }.bind(this));
        },
        render: function(){
            // sort by group/user then by ID;
            const userEntries = this.props.users.sort(function(a,b) {
                return (b.TYPE == "group") ? 1 : ((a.TYPE == "group") ? -1 : (a.ID > b.ID) ? 1 : ((b.ID > a.ID) ? -1 : 0));
            } ).map(function(u){
                return <SharedUserEntry
                    userEntry={u}
                    userObject={this.props.userObjects[u.ID]}
                    key={u.ID}
                    shareModel={this.props.shareModel}
                    onUserUpdate={this.props.onUserUpdate}
                    sendInvitations={this.props.sendInvitations}
                />
            }.bind(this));
            var actionLinks = [];
            if(this.props.users.length){
                actionLinks.push(<a key="clear" onClick={this.clearAllUsers}>Remove All</a>);
            }
            if(this.props.sendInvitations && this.props.users.length){
                actionLinks.push(<a key="invite" onClick={this.sendInvitationToAllUsers}>Send Invitation</a>);
            }
            if(this.props.saveSelectionAsTeam && this.props.users.length > 1){
                actionLinks.push(<a key="team" onClick={this.props.saveSelectionAsTeam}>Save as a Team</a>);
            }
            if(actionLinks.length){
                var linkActions = <div className="additional-actions-links">{actionLinks}</div>;
            }
            var rwHeader;
            if(this.props.users.length){
                rwHeader = (
                    <div>
                        <div className="shared-users-rights-header">
                            <span className="read">Read</span>
                            <span className="read">Modify</span>
                        </div>
                    </div>
                );
            }else{
                rwHeader = (
                    <div className="section-legend" style={{padding:10}}>Use the field above to pick a user from the internal directory or from your personal address book. If you want to create a new user, just type in a new identifier and hit Enter.</div>
                );
            }
            return (
                <div style={{marginTop: 10}}>
                    {rwHeader}
                    <div>{userEntries}</div>
                    {linkActions}
                </div>
            );
        }
    });

    var SharedUserEntry = React.createClass({
        propTypes: {
            userEntry:React.PropTypes.object.isRequired,
            userObject:React.PropTypes.instanceOf(PydioUsers.User).isRequired,
            onUserUpdate:React.PropTypes.func.isRequired,
            sendInvitations:React.PropTypes.func
        },
        onRemove:function(){
            this.props.onUserUpdate('remove', this.props.userEntry.ID, this.props.userEntry);
        },
        onToggleWatch:function(){
            this.props.onUserUpdate('update_right', this.props.userEntry.ID, {right:'watch', add:!this.props.userEntry['WATCH']});
        },
        onInvite:function(){
            var targets = {};
            targets[this.props.userObject.getId()] = this.props.userObject;
            this.props.sendInvitations(targets);
        },
        onUpdateRight:function(event){
            var target = event.target;
            this.props.onUserUpdate('update_right', this.props.userEntry.ID, {right:target.name, add:target.checked});
        },
        render: function(){
            var menuItems = [];
            if(this.props.userEntry.TYPE != 'group'){
                menuItems.push({text:'Notify on content changes', callback:this.onToggleWatch, checked:this.props.userEntry.WATCH});
                if(this.props.sendInvitations){
                    menuItems.push({text:'Send invitation', callback:this.onInvite});
                }
            }
            menuItems.push({text:'Remove', callback:this.onRemove});
            return (
                <UserBadge
                    label={this.props.userEntry.LABEL || this.props.userEntry.ID }
                    avatar={this.props.userEntry.AVATAR}
                    type={this.props.userEntry.TYPE}
                    menus={menuItems}
                >
                    <span className="user-badge-rights-container">
                        <input type="checkbox" name="read" checked={this.props.userEntry.RIGHT.indexOf('r') !== -1} onChange={this.onUpdateRight}/>
                        <input type="checkbox" name="write" checked={this.props.userEntry.RIGHT.indexOf('w') !== -1} onChange={this.onUpdateRight}/>
                    </span>
                </UserBadge>
            );
        }
    });

    var RemoteUsers = React.createClass({

        propTypes:{
            shareModel: React.PropTypes.instanceOf(ReactModel.Share)
        },

        getInitialState: function(){
            return {addDisabled: true};
        },

        addUser:function(){
            var h = this.refs["host"].getValue();
            var u = this.refs["user"].getValue();
            this.props.shareModel.createRemoteLink(h, u);
        },

        removeUser: function(linkId){
            this.props.shareModel.removeRemoteLink(linkId);
        },

        monitorInput:function(){
            var h = this.refs["host"].getValue();
            var u = this.refs["user"].getValue();
            this.setState({addDisabled:!(h && u)});
        },

        render: function(){

            var inv = this.props.shareModel.getOcsLinks().map(function(link){
                var rem = function(){
                    this.removeUser(link.hash);
                }.bind(this);
                var status;
                if(!link.invitation){
                    status = 'not sent';
                }else {
                    if(link.invitation.STATUS == 1){
                        status = 'pending';
                    }else if(link.invitation.STATUS == 2){
                        status = 'accepted';
                    }else if(link.invitation.STATUS == 4){
                        status = 'rejected';
                    }
                }
                var menuItems = [{text:'Remove', callback:rem}];
                var host = link.HOST || link.invitation.HOST;
                var user = link.USER || link.invitation.USER;
                return (
                    <UserBadge
                        label={user + " @ " + host + " (" + status + ")"}
                        avatar={null}
                        type={"remote_user"}
                        menus={menuItems}
                    />
                );
            }.bind(this));
            return (
                <div>
                    <h3>Remote Users</h3>
                    <div className="remote-users-add reset-pydio-forms">
                        <ReactMUI.TextField className="host" ref="host" floatingLabelText="Remote Host" onChange={this.monitorInput}/>
                        <ReactMUI.TextField className="user" ref="user" type="text" floatingLabelText="RemoteUser" onChange={this.monitorInput}/>
                        <ReactMUI.IconButton tooltip="Add" iconClassName="icon-plus-sign" onClick={this.addUser} disabled={this.state.addDisabled}/>
                    </div>
                    <div>{inv}</div>
                </div>
            );

        }
    });

    /**************************/
    /* PUBLIC LINK PANEL
     /**************************/
    var PublicLinkPanel = React.createClass({

        propTypes: {
            linkData:React.PropTypes.object,
            pydio:React.PropTypes.instanceOf(Pydio),
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            authorizations: React.PropTypes.object,
            showMailer:React.PropTypes.func
        },

        toggleLink: function(event){
            this.props.shareModel.togglePublicLink();
        },

        render: function(){

            var publicLinkPanes;
            if(this.props.linkData){
                publicLinkPanes = [
                    <PublicLinkField
                        showMailer={this.props.showMailer}
                        linkData={this.props.linkData}
                        shareModel={this.props.shareModel}
                        editAllowed={this.props.authorizations.editable_hash}
                        key="public-link"
                    />,
                    <PublicLinkPermissions
                        linkData={this.props.linkData}
                        shareModel={this.props.shareModel}
                        key="public-perm" />,
                    <PublicLinkSecureOptions
                        linkData={this.props.linkData}
                        shareModel={this.props.shareModel}
                        key="public-secure"
                    />
                ];
            }else{
                publicLinkPanes = [
                    <div className="section-legend" style={{marginTop:20}}>This file is not currently publicly accessible. Enabling a public link will create a unique access that you can send to anyone. If you want to share with existing Pydio users, use the "Users" tab.</div>
                ];
            }

            return (
                <div style={{padding:16}} className="reset-pydio-forms">
                    <ReactMUI.Checkbox onCheck={this.toggleLink} checked={!!this.props.linkData} label="Enable Public Link"/>
                    {publicLinkPanes}
                </div>
            );
        }
    });

    var PublicLinkField = React.createClass({
        propTypes: {
            linkData:React.PropTypes.object.isRequired,
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            editAllowed: React.PropTypes.bool,
            onChange: React.PropTypes.func,
            showMailer:React.PropTypes.func
        },
        getInitialState: function(){
            return {editLink: false, copyMessage:''};
        },
        toggleEditMode: function(){
            if(this.state.editLink && this.state.customLink){
                this.props.shareModel.updateCustomLink(this.props.linkData.hash, this.state.customLink);
            }
            this.setState({editLink: !this.state.editLink});
        },
        changeLink:function(event){
            this.setState({customLink: event.target.value});
        },
        clearCopyMessage:function(){
            global.setTimeout(function(){
                this.setState({copyMessage:''});
            }.bind(this), 5000);
        },

        attachClipboard: function(){
            this.detachClipboard();
            if(this.refs['copy-button']){
                this._clip = new Clipboard(this.refs['copy-button'].getDOMNode(), {
                    text: function(trigger) {
                        return this.props.linkData['public_link'];
                    }.bind(this)
                });
                this._clip.on('success', function(){
                    this.setState({copyMessage:'Link has been copied to clipboard!'}, this.clearCopyMessage);
                }.bind(this));
                this._clip.on('error', function(){
                    var copyMessage;
                    if( global.navigator.platform.indexOf("Mac") === 0 ){
                        copyMessage = global.pydio.MessageHash['share_center.144'];
                    }else{
                        copyMessage = global.pydio.MessageHash['share_center.143'];
                    }
                    this.refs['public-link-field'].focus();
                    this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
                }.bind(this));
            }
        },
        detachClipboard: function(){
            if(this._clip){
                this._clip.destroy();
            }
        },

        componentDidUpdate: function(prevProps, prevState){
            this.attachClipboard();
        },

        componentDidMount: function(){
            this.attachClipboard();
        },

        componentWillUnmount: function(){
            this.detachClipboard();
        },

        openMailer: function(){
            var mailData = this.props.shareModel.prepareEmail("link", this.props.linkData.hash);
            this.props.showMailer(mailData.subject, mailData.message, []);
        },

        render: function(){
            var publicLink = this.props.linkData['public_link'];
            var editAllowed = this.props.editAllowed && !this.props.linkData['hash_is_shorten'];
            if(this.state.editLink && editAllowed){
                return (
                    <div className="public-link-container edit-link">
                        <span>{publicLink.split('://')[0]}://[..]/{PathUtils.getBasename(PathUtils.getDirname(publicLink)) + '/'}</span>
                        <ReactMUI.TextField onChange={this.changeLink} value={this.state.customLink || this.props.linkData['hash']}/>
                        <ReactMUI.RaisedButton label="Ok" onClick={this.toggleEditMode}/>
                        <div className="section-legend">You can customize the last part of the link.</div>
                    </div>
                );
            }else{
                var copyButton = <span ref="copy-button" className="copy-link-button icon-paste" title="Copy link to clipboard"/>;
                var setHtml = function(){
                    return {__html:this.state.copyMessage};
                }.bind(this);
                var focus = function(e){
                    e.target.select();
                };
                var actionLinks = [];
                if(this.props.showMailer){
                    actionLinks.push(<a key="invitation" onClick={this.openMailer}>Send Invitation</a>);
                }
                if(editAllowed){
                    actionLinks.push(<a key="customize" onClick={this.toggleEditMode}>Customize link</a>);
                }
                if(actionLinks.length){
                    actionLinks = (
                        <div className="additional-actions-links">{actionLinks}</div>
                    ) ;
                }else{
                    actionLinks = null;
                }
                return (
                    <div className="public-link-container">
                        <ReactMUI.TextField
                            className="public-link"
                            type="text"
                            name="Link"
                            ref="public-link-field"
                            value={publicLink}
                            onFocus={focus}
                        /> {copyButton}
                        <div style={{textAlign:'center'}} className="section-legend" dangerouslySetInnerHTML={setHtml()}/>
                        {actionLinks}
                    </div>
                );
            }
        }
    });

    var PublicLinkPermissions = React.createClass({

        propTypes: {
            linkData: React.PropTypes.object.isRequired,
            shareModel: React.PropTypes.instanceOf(ReactModel.Share)
        },

        changePermission: function(event){
            var name = event.target.name;
            var checked = event.target.checked;
            this.props.shareModel.setPublicLinkPermission(this.props.linkData.hash, name, checked);
        },

        render: function(){
            var linkId = this.props.linkData.hash;
            var perms = [], previewWarning;
            perms.push({NAME:'read',LABEL:'Preview'});
            perms.push({NAME:'download', LABEL:'Download'});
            if(!this.props.shareModel.getNode().isLeaf()){
                perms.push({NAME:'write', LABEL:'Upload'});
            }
            if(this.props.shareModel.isPublicLinkPreviewDisabled() && this.props.shareModel.getPublicLinkPermission(linkId, 'read')){
                previewWarning = <div>Warning, there is no embedded viewer for this file, except opening in a new tab of your browser: this may trigger a download automatically when user opens the link.</div>;
            }
            return (
                <div>
                    <h3>Permissions</h3>
                    <div className="section-legend">How users are allowed to preview/modify content.</div>
                    <div style={{margin:'10px 0 20px'}}>
                        {perms.map(function(p){
                            return (
                                <div style={{display:'inline-block',width:'30%'}}>
                                    <ReactMUI.Checkbox
                                        type="checkbox"
                                        name={p.NAME}
                                        label={p.LABEL}
                                        onCheck={this.changePermission}
                                        checked={this.props.shareModel.getPublicLinkPermission(linkId, p.NAME)}
                                    />
                                </div>
                            );
                        }.bind(this))}
                        {previewWarning}
                    </div>
                </div>
            );
        }
    });

    var PublicLinkSecureOptions = React.createClass({

        propTypes: {
            linkData: React.PropTypes.object.isRequired,
            shareModel: React.PropTypes.instanceOf(ReactModel.Share)
        },

        updateDLExpirationField: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.setExpirationFor(this.props.linkData.hash, "downloads", newValue);
        },

        updateDaysExpirationField: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.setExpirationFor(this.props.linkData.hash, "days", newValue);
        },

        resetPassword: function(){
            this.props.shareModel.resetPassword(this.props.linkData.hash);
        },

        updatePassword: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.updatePassword(this.props.linkData.hash, newValue);
        },

        render: function(){
            var linkId = this.props.linkData.hash;
            var passPlaceHolder, resetPassword;
            if(this.props.shareModel.hasHiddenPassword(linkId)){
                passPlaceHolder = '********';
                resetPassword = <ReactMUI.FlatButton secondary={true} onClick={this.resetPassword} label="Reset Password"/>
            }
            return (
                <div>
                    <h3>Secure Access</h3>
                    <div className="section-legend">Protect the link with a password, or make it expire automatically.</div>
                    <div className="password-container">
                        <div style={{width:'50%', display:'inline-block'}}>
                            <ReactMUI.TextField
                                floatingLabelText="Password Protection"
                                disabled={passPlaceHolder ? true : false}
                                value={passPlaceHolder? passPlaceHolder : this.props.shareModel.getPassword(linkId)}
                                onChange={this.updatePassword}
                            />
                        </div>
                        <div style={{width:'50%', display:'inline-block'}}>
                            {resetPassword}
                        </div>
                    </div>
                    <div className="expires">
                        <div style={{width:'50%', display:'inline-block'}}>
                            <ReactMUI.TextField
                                   floatingLabelText="Expire after (days)"
                                   value={this.props.shareModel.getExpirationFor(linkId, 'days') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'days')}
                                   onChange={this.updateDaysExpirationField}/>
                            </div>
                        <div style={{width:'50%', display:'inline-block'}}>
                            <ReactMUI.TextField
                               floatingLabelText="Expire after (downloads)"
                               value={this.props.shareModel.getExpirationFor(linkId, 'downloads') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'downloads')}
                               onChange={this.updateDLExpirationField}
                            />
                        </div>
                    </div>
                </div>
            );
        }
    });

    /**************************/
    /* ADVANCED PANEL
    /**************************/
    var AdvancedPanel = React.createClass({
        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio),
            shareModel:React.PropTypes.instanceOf(ReactModel.Share)
        },
        render: function(){

            var layoutData = ReactModel.Share.compileLayoutData(this.props.pydio, this.props.shareModel.getNode());
            if(!this.props.shareModel.getNode().isLeaf() && layoutData.length > 1 && this.props.shareModel.hasPublicLink()){
                var layoutPane = <PublicLinkTemplate {...this.props} linkData={this.props.shareModel.getPublicLinks()[0]} layoutData={layoutData}/>;
            }

            return (
                <div style={{padding:16}}>
                    <LabelDescriptionPanel {...this.props}/>
                    <NotificationPanel {...this.props}/>
                    {layoutPane}
                    <VisibilityPanel  {...this.props}/>
                </div>
            );
        }
    });

    var LabelDescriptionPanel = React.createClass({

        updateLabel: function(event){
            this.props.shareModel.setGlobal("label", event.currentTarget.value);
        },

        updateDescription: function(event){
            this.props.shareModel.setGlobal("description", event.currentTarget.value);
        },

        render: function(){
            if(!this.props.shareModel.getNode().isLeaf()){
                var label = (
                    <ReactMUI.TextField
                        floatingLabelText="Label"
                        name="label"
                        onChange={this.updateLabel}
                        value={this.props.shareModel.getGlobal('label')}
                    />
                );
                var labelLegend = (
                    <div className="form-legend">Title displayed to the user</div>
                );
            }
            return (
                <div className="reset-pydio-forms">
                    <h3 style={{paddingTop:0}}>Additional description</h3>
                    <div className="label-desc-edit">
                        {label}
                        {labelLegend}
                        <ReactMUI.TextField
                            floatingLabelText="Description"
                            name="description"
                            onChange={this.updateDescription}
                            value={this.props.shareModel.getGlobal('description')}
                        />
                        <div className="form-legend">Add an optional comment</div>
                    </div>
                </div>
            );
        }
    });

    var NotificationPanel = React.createClass({

        dropDownChange:function(event, index, item){
            this.props.shareModel.setGlobal('watch', (index!=0));
        },

        render: function(){
            var menuItems = [
                {payload:'no_watch', text:'Do not notify me'},
                {payload:'watch_read', text:'Notify me when share is accessed'},
                {payload:'watch_write', text:'Notify me when share is modified'}
            ];
            var selectedIndex = this.props.shareModel.getGlobal('watch') ? 1 : 0;
            return (
                <div>
                    <h3>Notification</h3>
                    <ReactMUI.DropDownMenu
                        autoWidth={false}
                        className="full-width"
                        menuItems={menuItems}
                        selectedIndex={selectedIndex}
                        onChange={this.dropDownChange}
                    />
                    <div className="form-legend">Be alerted whenever a user accesses a file.</div>
                </div>
            );
        }
    });

    var PublicLinkTemplate = React.createClass({

        propTypes:{
            linkData:React.PropTypes.object
        },

        onDropDownChange: function(event, index, item){
            this.props.shareModel.setTemplate(this.props.linkData.hash, item.payload);
        },

        render: function(){
            var index = 0, crtIndex = 0;
            var selected = this.props.shareModel.getTemplate(this.props.linkData.hash);
            var menuItems=this.props.layoutData.map(function(l){
                if(selected && l.LAYOUT_ELEMENT == selected) {
                    crtIndex = index;
                }
                index ++;
                return {payload:l.LAYOUT_ELEMENT, text:l.LAYOUT_LABEL};
            });
            return (
                <div>
                    <h3>Link Layout</h3>
                    <ReactMUI.DropDownMenu
                        autoWidth={false}
                        className="full-width"
                        menuItems={menuItems}
                        selectedIndex={crtIndex}
                        onChange={this.onDropDownChange}
                    />
                    <div className="form-legend">You can select the appearance of the link as it will appear in the browser.</div>
                </div>
            );
        }
    });

    var VisibilityPanel = React.createClass({
        toggleVisibility: function(){
            this.props.shareModel.toggleVisibility();
        },
        transferOwnership: function(){
            this.props.shareModel.setNewShareOwner(this.refs['newOwner'].getValue());
        },
        render: function(){
            var currentIsOwner = global.pydio.user.id == this.props.shareModel.getShareOwner();

            var legend;
            if(currentIsOwner){
                legend = "Other users who access to the current workspace can update the links parameters, but they cannot delete it. Only you can stop this share or toggle its visibility";
            }else{
                legend = "Other users who access to the current workspace can update the links parameters. You are not the owner of the share so you cannot delete it or toggle its visibility";
            }
            var showToggle = (
                <div>
                    <ReactMUI.Checkbox type="checkbox"
                           name="share_visibility"
                           disabled={!currentIsOwner}
                           onCheck={this.toggleVisibility}
                           checked={this.props.shareModel.isPublic()}
                           label="Visible to other users"
                    />
                    <div className="section-legend">{legend}</div>
                </div>
            );
            if(this.props.shareModel.isPublic() && currentIsOwner){
                var showTransfer = (
                    <div className="ownership-form">
                        <h4>Transfer Ownership</h4>
                        <div className="section-legend">If the share is publicly visible and you want another user to manage this share, you can transfer its ownership.</div>
                        <div>
                            <ReactMUI.TextField ref="newOwner" floatingLabelText="Transfer to ... (user identifier)"/>
                            <ReactMUI.RaisedButton label="Transfer" onClick={this.transferOwnership}/>
                        </div>
                    </div>
                );
            }
            return (
                <div className="reset-pydio-forms">
                    <h3>Share Visibility</h3>
                    {showToggle}
                    {showTransfer}
                </div>
            );
        }
    });

    var DialogNamespace = global.ShareDialog || {};
    DialogNamespace.MainPanel = MainPanel;
    global.ShareDialog = DialogNamespace;

})(window);