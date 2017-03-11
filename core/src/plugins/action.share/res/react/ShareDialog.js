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
(function(global) {

    var ContextConsumerMixin = {
        contextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func,
            isReadonly:React.PropTypes.func
        }
    };

    var MainPanel = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin
        ],
        
        getDefaultProps: function(){
            return {
                dialogTitle:'',
                dialogIsModal:false,
                dialogPadding:false
            };
        },

        propTypes: {
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            selection:React.PropTypes.instanceOf(PydioDataModel).isRequired,
            readonly:React.PropTypes.bool
        },

        childContextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func,
            isReadonly:React.PropTypes.func
        },

        getChildContext: function() {
            var messages = this.props.pydio.MessageHash;
            return {
                messages: messages,
                getMessage: function(messageId, namespace='share_center'){
                    try{
                        return messages[namespace + (namespace?".":"") + messageId] || messageId;
                    }catch(e){
                        return messageId;
                    }
                },
                isReadonly: function(){
                    return this.props.readonly;
                }.bind(this)
            };
        },

        modelUpdated: function(eventData){
            if(this.isMounted()){
                this.setState({
                    status: eventData.status,
                    model:eventData.model
                });
            }
        },

        getInitialState: function(){
            return {
                status: 'idle',
                mailerData: false,
                model: new ReactModel.Share(this.props.pydio, this.props.selection.getUniqueNode(), this.props.selection)
            };
        },

        showMailer:function(subject, message, users = []){
            if(ReactModel.Share.forceMailerOldSchool()){
                subject = encodeURIComponent(subject);
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
            this.state.model.initLoad();
        },

        clicked: function(){
            this.dismiss();
        },

        getMessage: function(key, namespace = 'share_center'){
            return this.props.pydio.MessageHash[namespace + (namespace?'.':'') + key];
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
                    <ReactMUI.Tab key="public-link" label={this.getMessage(121) + (model.hasPublicLink()?' (' + this.getMessage(178) + ')':'')}>
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
                    <ReactMUI.Tab key="target-users" label={this.getMessage(249, '') + (totalUsers?' ('+totalUsers+')':'')}>
                        <UsersPanel
                            showMailer={showMailer}
                            shareModel={model}
                        />
                    </ReactMUI.Tab>
                );
            }
            if(panels.length > 0){
                panels.push(
                    <ReactMUI.Tab  key="share-permissions" label={this.getMessage(486, '')}>
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
                <div className="react_share_form" style={{width:420}}>
                    <HeaderPanel {...this.props} shareModel={this.state.model}/>
                    <ReactMUI.Tabs>{panels}</ReactMUI.Tabs>
                    <ButtonsPanel {...this.props} shareModel={this.state.model} onClick={this.clicked}/>
                    {mailer}
                </div>
            );
        }

    });

    var HeaderPanel = React.createClass({
        mixins:[ContextConsumerMixin],
        render: function(){

            let nodePath = this.props.shareModel.getNode().getPath();
            /*
            if(this.props.shareModel.getNode().getMetadata().get("original_path")){
                nodePath = this.props.shareModel.getNode().getMetadata().get("original_path");
            }
            */
            return (
                <div className="headerPanel">
                    <div>{this.context.getMessage('44').replace('%s', PathUtils.getBasename(nodePath))}</div>
                </div>
            );
        }
    });

    var ButtonsPanel = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes: {
            onClick: React.PropTypes.func.isRequired
        },
        getInitialState: function(){
            return {disabled: false};
        },
        disableSave: function(){
            this.setState({disabled: true});
        },
        enableSave: function(){
            this.setState({disabled:false});
        },
        componentDidMount: function(){
            this.props.shareModel.observe('saving', this.disableSave);
            this.props.shareModel.observe('saved', this.enableSave);
        },
        componendWillUnmount: function(){
            this.props.shareModel.stopObserving('saving', this.disableSave);
            this.props.shareModel.stopObserving('saved', this.enableSave);
        },
        triggerModelSave: function(){
            this.props.shareModel.save();
        },
        triggerModelRevert:function(){
            this.props.shareModel.revertChanges();
        },
        disableAllShare:function(){
            this.props.shareModel.stopSharing(this.props.onClick.bind(this));
        },
        render: function(){
            if(this.props.shareModel.getStatus() == 'modified'){
                return (
                    <div style={{padding:16,textAlign:'right'}}>
                        <a className="revert-button" onClick={this.triggerModelRevert}>{this.context.getMessage('179')}</a>
                        <ReactMUI.FlatButton secondary={true} disabled={this.state.disabled} label={this.context.getMessage('53', '')} onClick={this.triggerModelSave}/>
                        <ReactMUI.FlatButton secondary={false} label={this.context.getMessage('86', '')} onClick={this.props.onClick}/>
                    </div>
                );
            }else{
                var unshareButton;
                if((this.props.shareModel.hasActiveShares() && (this.props.shareModel.currentIsOwner())) || this.props.shareModel.getStatus() === 'error' || global.pydio.user.activeRepository === "ajxp_conf"){
                    unshareButton = (<ReactMUI.FlatButton  disabled={this.state.disabled} secondary={true} label={this.context.getMessage('6')} onClick={this.disableAllShare}/>);
                }
                return (
                    <div style={{padding:16,textAlign:'right'}}>
                        {unshareButton}
                        <ReactMUI.FlatButton secondary={false} label={this.context.getMessage('86', '')} onClick={this.props.onClick}/>
                    </div>
                );
            }
        }
    });

    /**************************/
    /* USERS PANEL
    /**************************/
    var UsersPanel = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes:{
            shareModel:React.PropTypes.instanceOf(ReactModel.Share),
            showMailer:React.PropTypes.func
        },

        onUserUpdate: function(operation, userId, userData){
            this.props.shareModel.updateSharedUser(operation, userId, userData);
        },

        onSaveSelection:function(){
            var label = window.prompt(this.context.getMessage(510, ''));
            if(!label) return;
            this.props.shareModel.saveSelectionAsTeam(label);
        },

        sendInvitations:function(userObjects){
            try{
                var mailData = this.props.shareModel.prepareEmail("repository");
                this.props.showMailer(mailData.subject, mailData.message, userObjects);
            }catch(e){
                global.alert(e.message);
            }
        },

        render: function(){
            var currentUsers = this.props.shareModel.getSharedUsers();
            var federatedEnabled = ReactModel.Share.federatedSharingEnabled();
            if(federatedEnabled){
                var remoteUsersBlock = (
                    <RemoteUsers
                        shareModel={this.props.shareModel}
                        onUserUpdate={this.onUserUpdate}
                    />
                );
            }
            return (
                <div style={federatedEnabled?{padding:'0 16px 10px'}:{padding:'20px 16px 10px'}}>
                    <SharedUsers
                        showTitle={federatedEnabled}
                        users={currentUsers}
                        userObjects={this.props.shareModel.getSharedUsersAsObjects()}
                        sendInvitations={this.props.showMailer ? this.sendInvitations : null}
                        onUserUpdate={this.onUserUpdate}
                        saveSelectionAsTeam={PydioUsers.Client.saveSelectionSupported()?this.onSaveSelection:null}
                    />
                    {remoteUsersBlock}
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

        renderMenu: function(){
            if (!this.props.menus || !this.props.menus.length) {
                return null;
            }
            const menuItems = this.props.menus.map(function(m){
                let rightIcon;
                if(m.checked){
                    rightIcon = <span className="icon-check"/>;
                }
                return (
                    <MaterialUI.MenuItem
                        primaryText={m.text}
                        onTouchTap={m.callback}
                        rightIcon={rightIcon}/>
                );
            });
            const iconStyle = {fontSize: 18};
            return(
                <MaterialUI.IconMenu
                    iconButtonElement={<MaterialUI.IconButton style={{padding: 16}} iconStyle={iconStyle} iconClassName="icon-ellipsis-vertical"/>}
                    anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                    targetOrigin={{horizontal: 'right', vertical: 'top'}}
                >
                    {menuItems}
                </MaterialUI.IconMenu>
            );
        },

        render: function () {
            var avatar;
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
                    {avatar}
                    <span className="user-badge-label">{this.props.label}</span>
                    {this.props.children}
                    {menu}
                </div>
            );
        }
    });

    var SharedUsers = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes: {
            users:React.PropTypes.array.isRequired,
            userObjects:React.PropTypes.object.isRequired,
            onUserUpdate:React.PropTypes.func.isRequired,
            saveSelectionAsTeam:React.PropTypes.func,
            sendInvitations:React.PropTypes.func,
            showTitle:React.PropTypes.bool
        },
        sendInvitationToAllUsers:function(){
            this.props.sendInvitations(this.props.userObjects);
        },
        clearAllUsers:function(){
            this.props.users.map(function(entry){
                this.props.onUserUpdate('remove', entry.ID, entry);
            }.bind(this));
        },
        valueSelected: function(userObject){
            var newEntry = {
                ID      : userObject.getId(),
                RIGHT   :'r',
                LABEL   : userObject.getLabel(),
                TYPE    : userObject.getGroup() ? 'group' : 'user'
            };
            this.props.onUserUpdate('add', newEntry.ID, newEntry);
        },
        completerRenderSuggestion: function(userObject){
            return (
                <UserBadge
                    label={(userObject.getExtendedLabel() || userObject.getLabel())}
                    avatar={userObject.getAvatar()}
                    type={userObject.getGroup() ? 'group' : (userObject.getTemporary()?'temporary' : (userObject.getExternal()?'tmp_user':'user'))}
                />
            );
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
            if(this.props.users.length && !this.context.isReadonly()){
                actionLinks.push(<a key="clear" onClick={this.clearAllUsers}>{this.context.getMessage('180')}</a>);
            }
            if(this.props.sendInvitations && this.props.users.length){
                actionLinks.push(<a key="invite" onClick={this.sendInvitationToAllUsers}>{this.context.getMessage('45')}</a>);
            }
            if(this.props.saveSelectionAsTeam && this.props.users.length > 1 && !this.context.isReadonly()){
                actionLinks.push(<a key="team" onClick={this.props.saveSelectionAsTeam}>{this.context.getMessage('509', '')}</a>);
            }
            if(actionLinks.length){
                var linkActions = <div className="additional-actions-links">{actionLinks}</div>;
            }
            var rwHeader;
            if(this.props.users.length){
                rwHeader = (
                    <div>
                        <div className="shared-users-rights-header">
                            <span className="read">{this.context.getMessage('361', '')}</span>
                            <span className="read">{this.context.getMessage('181')}</span>
                        </div>
                    </div>
                );
            }
            if(!this.context.isReadonly()){
                const excludes = this.props.users.map(function(u){return u.ID});
                var usersInput = (
                    <PydioComponents.UsersCompleter
                        className="share-form-users"
                        fieldLabel={this.context.getMessage('34')}
                        renderSuggestion={this.completerRenderSuggestion}
                        onValueSelected={this.valueSelected}
                        excludes={excludes}
                    />
                );
            }
            var title;
            if(this.props.showTitle){
                title = <h3>{this.context.getMessage('217')}</h3>;
            }
            return (
                <div>
                    {title}
                    <div className="section-legend">{this.context.getMessage('182')}</div>
                    {usersInput}
                    {rwHeader}
                    <div>{userEntries}</div>
                    {linkActions}
                </div>
            );
        }
    });

    var SharedUserEntry = React.createClass({

        mixins:[ContextConsumerMixin],

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
                if(!this.context.isReadonly()){
                    // Toggle Notif
                    menuItems.push({
                        text:this.context.getMessage('183'),
                        callback:this.onToggleWatch,
                        checked:this.props.userEntry.WATCH
                    });
                }
                if(this.props.sendInvitations){
                    // Send invitation
                    menuItems.push({
                        text:this.context.getMessage('45'),
                        callback:this.onInvite
                    });
                }
            }
            if(!this.context.isReadonly()){
                // Remove Entry
                menuItems.push({
                    text:this.context.getMessage('257', ''),
                    callback:this.onRemove
                });
            }
            return (
                <UserBadge
                    label={this.props.userEntry.LABEL || this.props.userEntry.ID }
                    avatar={this.props.userEntry.AVATAR}
                    type={this.props.userEntry.TYPE}
                    menus={menuItems}
                >
                    <span className="user-badge-rights-container">
                        <input type="checkbox" name="read" disabled={this.context.isReadonly()} checked={this.props.userEntry.RIGHT.indexOf('r') !== -1} onChange={this.onUpdateRight}/>
                        <input type="checkbox" name="write" disabled={this.context.isReadonly()} checked={this.props.userEntry.RIGHT.indexOf('w') !== -1} onChange={this.onUpdateRight}/>
                    </span>
                </UserBadge>
            );
        }
    });

    var RemoteUsers = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes:{
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            onUserUpdate:React.PropTypes.func.isRequired
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

        renderForm: function(){
            if(this.context.isReadonly()){
                return null;
            }
            return (
                <div className="remote-users-add reset-pydio-forms">
                    <ReactMUI.TextField className="host" ref="host" floatingLabelText={this.context.getMessage('209')} onChange={this.monitorInput}/>
                    <ReactMUI.TextField className="user" ref="user" type="text" floatingLabelText={this.context.getMessage('210')} onChange={this.monitorInput}/>
                    <ReactMUI.IconButton tooltip={this.context.getMessage('45')} iconClassName="icon-plus-sign" onClick={this.addUser} disabled={this.state.addDisabled}/>
                </div>
            );
        },

        render: function() {
            var ocsLinks = this.props.shareModel.getOcsLinksByStatus(),
                inv, rwHeader, hasActiveOcsLink = false;

            inv = ocsLinks.map(function(link){
                hasActiveOcsLink = (!hasActiveOcsLink && link && link.invitation && link.invitation.STATUS == 2) ? true : hasActiveOcsLink;

                return (
                    <RemoteUserEntry
                        shareModel={this.props.shareModel}
                        linkData={link}
                        onRemoveUser={this.removeUser}
                        onUserUpdate={this.props.onUserUpdate}
                    />
                );
            }.bind(this));

            if(hasActiveOcsLink){
                rwHeader = (
                    <div>
                        <div className="shared-users-rights-header">
                            <span className="read">{this.context.getMessage('361', '')}</span>
                            <span className="read">{this.context.getMessage('181')}</span>
                        </div>
                    </div>
                );
            }

            return (
                <div style={{marginTop:16}}>
                    <h3>{this.context.getMessage('207')}</h3>
                    <div className="section-legend">{this.context.getMessage('208')}</div>
                    {this.renderForm()}
                    <div>
                        {rwHeader}
                        {inv}
                    </div>
                </div>
            );
        }
    });

    var RemoteUserEntry = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes:{
            shareModel:React.PropTypes.instanceOf(ReactModel.Share),
            linkData:React.PropTypes.object.isRequired,
            onRemoveUser:React.PropTypes.func.isRequired,
            onUserUpdate:React.PropTypes.func.isRequired
        },

        getInitialState(){
            return {
                internalUser: this.props.shareModel.getSharedUser(this.props.linkData['internal_user_id'])
            };
        },

        componentWillReceiveProps(newProps, oldProps){
            this.setState({
                internalUser:newProps.shareModel.getSharedUser(newProps.linkData['internal_user_id'])
            });
        },

        getStatus:function(){
            var link = this.props.linkData;
            if(!link.invitation) return -1;
            else return link.invitation.STATUS;
        },

        getStatusString: function(){
            const statuses = {'s-1':214, 's1':211, 's2':212, 's4':213};
            return this.context.getMessage(statuses['s'+this.getStatus()]);
        },

        buildLabel: function(){
            var link = this.props.linkData;
            var host = link.HOST || (link.invitation ? link.invitation.HOST : null);
            var user = link.USER || (link.invitation ? link.invitation.USER : null);
            if(!host || !user) return "Error";
            return user + " @ " + host ;
        },

        removeUser: function(){
            this.props.onRemoveUser(this.props.linkData['hash']);
        },

        onUpdateRight:function(event){
            var target = event.target;
            this.props.onUserUpdate('update_right', this.state.internalUser.ID, {right:target.name, add:target.checked});
        },

        render: function(){
            var menuItems = [];
            if(!this.context.isReadonly()){
                menuItems = [{
                    text:this.context.getMessage('257', ''),
                    callback:this.removeUser
                }];
            }
            var status = this.getStatus();
            var additionalItem;
            if(status == 2){
                additionalItem = (
                    <span className="user-badge-rights-container">
                        <input type="checkbox" name="read"  disabled={this.context.isReadonly()} checked={this.state.internalUser.RIGHT.indexOf('r') !== -1} onChange={this.onUpdateRight}/>
                        <input type="checkbox" name="write" disabled={this.context.isReadonly()} checked={this.state.internalUser.RIGHT.indexOf('w') !== -1} onChange={this.onUpdateRight}/>
                    </span>
                );
            }else{
                additionalItem = (
                    <span className="user-badge-rights-container">{this.getStatusString()}</span>
                );
            }

            return (
                <UserBadge
                    label={this.buildLabel()}
                    avatar={null}
                    type={"remote_user"}
                    menus={menuItems}
                >
                    {additionalItem}
                </UserBadge>
            );
        }

    });

    /**************************/
    /* PUBLIC LINK PANEL
     /**************************/
    var PublicLinkPanel = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes: {
            linkData:React.PropTypes.object,
            pydio:React.PropTypes.instanceOf(Pydio),
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            authorizations: React.PropTypes.object,
            showMailer:React.PropTypes.func
        },

        disableSave: function(){
            this.setState({disabled: true});
        },
        enableSave: function(){
            this.setState({disabled:false});
        },
        componentDidMount: function(){
            this.props.shareModel.observe('saving', this.disableSave);
            this.props.shareModel.observe('saved', this.enableSave);
        },
        componendWillUnmount: function(){
            this.props.shareModel.stopObserving('saving', this.disableSave);
            this.props.shareModel.stopObserving('saved', this.enableSave);
        },

        toggleLink: function(){
            var publicLinks = this.props.shareModel.getPublicLinks();
            if(this.state.showTemporaryPassword){
                this.setState({showTemporaryPassword: false, temporaryPassword: null});
            }else if(!publicLinks.length && ReactModel.Share.getAuthorizations(this.props.pydio).password_mandatory){
                this.setState({showTemporaryPassword: true, temporaryPassword: ''});
            }else{
                this.props.shareModel.togglePublicLink();
            }
        },

        getInitialState: function(){
            return {showTemporaryPassword: false, temporaryPassword: null, disabled: false};
        },

        updateTemporaryPassword: function(value, event){
            if(value == undefined) value = event.currentTarget.getValue();
            this.setState({temporaryPassword:value});
        },

        enableLinkWithPassword:function(){
            this.props.shareModel.enablePublicLinkWithPassword(this.state.temporaryPassword);
            this.setState({showTemporaryPassword:false, temporaryPassword:null});
        },

        render: function(){

            var publicLinkPanes;
            if(this.props.linkData) {
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
                        key="public-perm"/>,
                    <PublicLinkSecureOptions
                        linkData={this.props.linkData}
                        shareModel={this.props.shareModel}
                        pydio={this.props.pydio}
                        key="public-secure"
                    />
                ];
            }else if(this.state.showTemporaryPassword){
                publicLinkPanes = [
                    <div>
                        <div className="section-legend" style={{marginTop:20}}>{this.context.getMessage('215')}</div>
                        <div>
                            <div style={{float:'left'}}>
                                <PydioForm.ValidPassword
                                    attributes={{label:this.context.getMessage('23')}}
                                    value={this.state.temporaryPassword}
                                    onChange={this.updateTemporaryPassword}
                                />
                            </div>
                            <div style={{marginLeft:7,marginTop: 26,float:'left'}}>
                                <ReactMUI.RaisedButton label={this.context.getMessage('92')} secondary={true} onClick={this.enableLinkWithPassword}/>
                            </div>
                        </div>
                    </div>
                ];

            }else{
                publicLinkPanes = [
                    <div className="section-legend" style={{marginTop:20}}>{this.context.getMessage('190')}</div>
                ];
            }
            var checked = !!this.props.linkData;
            var disableForNotOwner = false;
            if(checked && !this.props.shareModel.currentIsOwner()){
                disableForNotOwner = true;
            }
            return (
                <div style={{padding:16}} className="reset-pydio-forms ie_material_checkbox_fix">
                    <ReactMUI.Checkbox
                        disabled={this.context.isReadonly() || disableForNotOwner || this.state.disabled}
                        onCheck={this.toggleLink}
                        checked={!!this.props.linkData || this.state.showTemporaryPassword}
                        label={this.context.getMessage('189')}
                    />
                    {publicLinkPanes}
                </div>
            );
        }
    });

    var PublicLinkField = React.createClass({

        mixins:[ContextConsumerMixin],

        propTypes: {
            linkData:React.PropTypes.object.isRequired,
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            editAllowed: React.PropTypes.bool,
            onChange: React.PropTypes.func,
            showMailer:React.PropTypes.func
        },
        getInitialState: function(){
            return {editLink: false, copyMessage:'', showQRCode: false};
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
                this._clip = new Clipboard(this.refs['copy-button'], {
                    text: function(trigger) {
                        return this.props.linkData['public_link'];
                    }.bind(this)
                });
                this._clip.on('success', function(){
                    this.setState({copyMessage:this.context.getMessage('192')}, this.clearCopyMessage);
                }.bind(this));
                this._clip.on('error', function(){
                    var copyMessage;
                    if( global.navigator.platform.indexOf("Mac") === 0 ){
                        copyMessage = this.context.getMessage('144');
                    }else{
                        copyMessage = this.context.getMessage('143');
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

        toggleQRCode: function(){
            this.setState({showQRCode:!this.state.showQRCode});
        },

        render: function(){
            var publicLink = this.props.linkData['public_link'];
            var editAllowed = this.props.editAllowed && !this.props.linkData['hash_is_shorten'] && !this.context.isReadonly() && this.props.shareModel.currentIsOwner();
            if(this.state.editLink && editAllowed){
                return (
                    <div className={"public-link-container edit-link"}>
                        <span>{publicLink.split('://')[0]}://[..]/{PathUtils.getBasename(PathUtils.getDirname(publicLink)) + '/'}</span>
                        <ReactMUI.TextField onChange={this.changeLink} value={this.state.customLink !== undefined ? this.state.customLink : this.props.linkData['hash']}/>
                        <ReactMUI.RaisedButton label="Ok" onClick={this.toggleEditMode}/>
                        <div className="section-legend">{this.context.getMessage('194')}</div>
                    </div>
                );
            }else{
                var copyButton = <span ref="copy-button" className="copy-link-button icon-paste" title={this.context.getMessage('191')}/>;
                var setHtml = function(){
                    return {__html:this.state.copyMessage};
                }.bind(this);
                var focus = function(e){
                    e.target.select();
                };
                var actionLinks = [];
                if(this.props.showMailer){
                    actionLinks.push(<a key="invitation" onClick={this.openMailer}>{this.context.getMessage('45')}</a>);
                }
                if(editAllowed){
                    actionLinks.push(<a key="customize" onClick={this.toggleEditMode}>{this.context.getMessage('193')}</a>);
                }
                if(ReactModel.Share.qrcodeEnabled()){
                    actionLinks.push(<a className={this.state.showQRCode?'qrcode-active':''} key="qrcode" onClick={this.toggleQRCode}>{this.context.getMessage('94')}</a>)
                }
                if(actionLinks.length){
                    actionLinks = (
                        <div className="additional-actions-links">{actionLinks}</div>
                    ) ;
                }else{
                    actionLinks = null;
                }
                if(this.state.showQRCode){
                    var qrCode = <div className="qrCode"><ReactQRCode size={128} value={publicLink} level="Q"/></div>;
                }
                return (
                    <div className="public-link-container">
                        <ReactMUI.TextField
                            className={"public-link" + (this.props.linkData['is_expired'] ? ' link-expired':'')}
                            type="text"
                            name="Link"
                            ref="public-link-field"
                            value={publicLink}
                            onFocus={focus}
                        /> {copyButton}
                        <div style={{textAlign:'center'}} className="section-legend" dangerouslySetInnerHTML={setHtml()}/>
                        {actionLinks}
                        {qrCode}
                    </div>
                );
            }
        }
    });

    var PublicLinkPermissions = React.createClass({

        mixins:[ContextConsumerMixin],

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
            var currentIsFolder = !this.props.shareModel.getNode().isLeaf();
            perms.push({
                NAME:'read',
                LABEL:this.context.getMessage('72'),
                DISABLED:currentIsFolder && !this.props.shareModel.getPublicLinkPermission(linkId, 'write')
            });
            perms.push({
                NAME:'download',
                LABEL:this.context.getMessage('73')
            });
            if(currentIsFolder){
                perms.push({
                    NAME:'write',
                    LABEL:this.context.getMessage('74')
                });
            }else if(this.props.shareModel.fileHasWriteableEditors()){
                perms.push({
                    NAME:'write',
                    LABEL:this.context.getMessage('74b')
                });
            }
            if(this.props.shareModel.isPublicLinkPreviewDisabled() && this.props.shareModel.getPublicLinkPermission(linkId, 'read')){
                previewWarning = <div>{this.context.getMessage('195')}</div>;
            }
            return (
                <div>
                    <h3>{this.context.getMessage('71')}</h3>
                    <div className="section-legend">{this.context.getMessage('70r')}</div>
                    <div style={{margin:'10px 0 20px'}} className="ie_material_checkbox_fix">
                        {perms.map(function(p){
                            return (
                                <div style={{display:'inline-block',width:'30%'}}>
                                    <ReactMUI.Checkbox
                                        disabled={p.DISABLED || this.context.isReadonly()}
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

        mixins:[ContextConsumerMixin],

        propTypes: {
            linkData: React.PropTypes.object.isRequired,
            shareModel: React.PropTypes.instanceOf(ReactModel.Share)
        },

        updateDLExpirationField: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.setExpirationFor(this.props.linkData.hash, "downloads", newValue);
        },

        updateDaysExpirationField: function(event, newValue){
            if(!newValue){
                newValue = event.currentTarget.getValue();
            }
            this.props.shareModel.setExpirationFor(this.props.linkData.hash, "days", newValue);
        },

        onDateChange: function(event, value){
            var today = new Date();
            var date1 = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
            var date2 = Date.UTC(value.getFullYear(), value.getMonth(), value.getDate());
            var ms = Math.abs(date1-date2);
            var integerVal = Math.floor(ms/1000/60/60/24); //floor should be unnecessary, but just in case
            this.updateDaysExpirationField(event, integerVal);
        },

        resetPassword: function(){
            this.props.shareModel.resetPassword(this.props.linkData.hash);
        },

        updatePassword: function(newValue, oldValue){
            //var newValue = event.currentTarget.getValue();
            this.props.shareModel.updatePassword(this.props.linkData.hash, newValue);
        },

        renderPasswordContainer: function(){
            var linkId = this.props.linkData.hash;
            var passwordField;
            if(this.props.shareModel.hasHiddenPassword(linkId)){
                var resetPassword = (
                    <ReactMUI.FlatButton
                        disabled={this.context.isReadonly()}
                        secondary={true}
                        onClick={this.resetPassword}
                        label={this.context.getMessage('174')}
                    />
                );
                passwordField = (
                    <ReactMUI.TextField
                        floatingLabelText={this.context.getMessage('23')}
                        disabled={true}
                        value={'********'}
                        onChange={this.updatePassword}
                    />
                );
            }else if(!this.context.isReadonly()){
                passwordField = (
                    <PydioForm.ValidPassword
                        attributes={{label:this.context.getMessage('23')}}
                        value={this.props.shareModel.getPassword(linkId)}
                        onChange={this.updatePassword}
                    />
                );
            }
            if(passwordField){
                return (
                    <div className="password-container">
                        <div style={{width:'50%', display:'inline-block'}}>
                            <span className="ajxp_icon_span icon-lock"/>
                            {passwordField}
                        </div>
                        <div style={{width:'50%', display:'inline-block'}}>
                            {resetPassword}
                        </div>
                    </div>
                );
            }else{
                return null;
            }
        },

        formatDate : function(dateObject){
            var dateFormatDay = this.context.getMessage('date_format', '').split(' ').shift();
            return dateFormatDay
                .replace('Y', dateObject.getFullYear())
                .replace('m', dateObject.getMonth() + 1)
                .replace('d', dateObject.getDate());
        },

        render: function(){
            var linkId = this.props.linkData.hash;
            var passContainer = this.renderPasswordContainer();
            var crtLinkDLAllowed = this.props.shareModel.getPublicLinkPermission(linkId, 'download');
            var dlLimitValue = this.props.shareModel.getExpirationFor(linkId, 'downloads') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'downloads');
            var expirationDateValue = this.props.shareModel.getExpirationFor(linkId, 'days') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'days');
            var calIcon = <span className="ajxp_icon_span icon-calendar"/>;
            var expDate = null;
            var maxDate = null, maxDownloads = null, dateExpired = false, dlExpired = false;
            var auth = ReactModel.Share.getAuthorizations(this.props.pydio);
            var today = new Date();
            if(parseInt(auth.max_expiration) > 0){
                maxDate = new Date();
                maxDate.setDate(today.getDate() + parseInt(auth.max_expiration));
            }
            if(parseInt(auth.max_downloads) > 0){
                // todo: limit the field values by default?
                maxDownloads = parseInt(auth.max_downloads);
            }
            if(expirationDateValue){
                if(expirationDateValue < 0){
                    dateExpired = true;
                }
                expDate = new Date();
                expDate.setDate(today.getDate() + parseInt(expirationDateValue));
                var clearValue = function(){
                    this.props.shareModel.setExpirationFor(linkId, "days", "");
                    ReactDOM.findDOMNode(this.refs['expirationDate']).querySelector(".mui-text-field-input").value = "";
                }.bind(this);
                calIcon = <span className="ajxp_icon_span mdi mdi-close-circle" onClick={clearValue}/>;
                var calLabel = <span className="calLabelHasValue">{this.context.getMessage(dateExpired?'21b':'21')}</span>
            }
            if(dlLimitValue){
                var dlCounter = this.props.shareModel.getDownloadCounter(linkId);
                var resetDl = function(){
                    if(window.confirm(this.context.getMessage('106'))){
                        this.props.shareModel.resetDownloadCounter(linkId, function(){});
                    }
                }.bind(this);
                if(dlCounter) {
                    var resetLink = <a style={{cursor:'pointer'}} onClick={resetDl} title={this.context.getMessage('17')}>({this.context.getMessage('16')})</a>;
                    if(dlCounter >= dlLimitValue){
                        dlExpired = true;
                    }
                }
                var dlCounterString = <span className="dlCounterString">{dlCounter+ '/'+ dlLimitValue} {resetLink}</span>;
            }
            return (
                <div>
                    <h3 style={{paddingTop:0}}>{this.context.getMessage('196')}</h3>
                    <div className="section-legend">{this.context.getMessage('24')}</div>
                    {passContainer}
                    <div className="expires">
                        <div style={{width:'50%', display:'inline-block', position:'relative'}} className={dateExpired?'limit-block-expired':null}>
                            {calIcon}
                            {calLabel}
                            <ReactMUI.DatePicker
                                ref="expirationDate"
                                disabled={this.context.isReadonly()}
                                onChange={this.onDateChange}
                                key="start"
                                hintText={this.context.getMessage(dateExpired?'21b':'21')}
                                autoOk={true}
                                minDate={new Date()}
                                maxDate={maxDate}
                                defaultDate={expDate}
                                showYearSelector={true}
                                onShow={null}
                                onDismiss={null}
                                formatDate={this.formatDate}
                            />
                        </div>
                        <div style={{width:'50%', display:crtLinkDLAllowed?'inline-block':'none', position:'relative'}} className={dlExpired?'limit-block-expired':null}>
                            <span className="ajxp_icon_span mdi mdi-download"/>
                            <ReactMUI.TextField
                                type="number"
                                disabled={this.context.isReadonly()}
                                floatingLabelText={this.context.getMessage(dlExpired?'22b':'22')}
                                value={this.props.shareModel.getExpirationFor(linkId, 'downloads') === 0 ? "" : this.props.shareModel.getExpirationFor(linkId, 'downloads')}
                                onChange={this.updateDLExpirationField}
                            />
                            {dlCounterString}
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
            if(!this.props.shareModel.currentRepoIsUserScope()){
                var visibilityPanel = <VisibilityPanel  {...this.props}/>;
            }
            return (
                <div style={{padding:16}}>
                    <LabelDescriptionPanel {...this.props}/>
                    <NotificationPanel {...this.props}/>
                    {layoutPane}
                    {visibilityPanel}
                </div>
            );
        }
    });

    var LabelDescriptionPanel = React.createClass({

        mixins:[ContextConsumerMixin],

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
                        disabled={this.context.isReadonly()}
                        floatingLabelText={this.context.getMessage('35')}
                        name="label"
                        onChange={this.updateLabel}
                        value={this.props.shareModel.getGlobal('label')}
                    />
                );
                var labelLegend = (
                    <div className="form-legend">{this.context.getMessage('146')}</div>
                );
            }
            return (
                <div className="reset-pydio-forms">
                    <h3 style={{paddingTop:0}}>{this.context.getMessage('145')}</h3>
                    <div className="label-desc-edit">
                        {label}
                        {labelLegend}
                        <ReactMUI.TextField
                            disabled={this.context.isReadonly()}
                            floatingLabelText={this.context.getMessage('145')}
                            name="description"
                            onChange={this.updateDescription}
                            value={this.props.shareModel.getGlobal('description')}
                        />
                        <div className="form-legend">{this.context.getMessage('197')}</div>
                    </div>
                </div>
            );
        }
    });

    var NotificationPanel = React.createClass({

        mixins:[ContextConsumerMixin],

        dropDownChange:function(event, index, item){
            this.props.shareModel.setGlobal('watch', (index!=0));
        },

        render: function(){
            var menuItems = [
                {payload:'no_watch', text:this.context.getMessage('187')},
                {payload:'watch_read', text:this.context.getMessage('184')}
                /*,{payload:'watch_write', text:'Notify me when share is modified'}*/
            ];
            var selectedIndex = this.props.shareModel.getGlobal('watch') ? 1 : 0;
            var element;
            if(this.context.isReadonly()){
                element = <ReactMUI.TextField disabled={true} value={menuItems[selectedIndex].text} style={{width:'100%'}}/>
            }else{
                element = (
                    <ReactMUI.DropDownMenu
                        autoWidth={false}
                        className="full-width"
                        menuItems={menuItems}
                        selectedIndex={selectedIndex}
                        onChange={this.dropDownChange}
                    />
                );
            }
            return (
                <div className="reset-pydio-forms">
                    <h3>{this.context.getMessage('218')}</h3>
                    {element}
                    <div className="form-legend">{this.context.getMessage('188')}</div>
                </div>
            );
        }
    });

    var PublicLinkTemplate = React.createClass({

        mixins:[ContextConsumerMixin],

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
            var element;
            if(this.context.isReadonly()){
                element = <ReactMUI.TextField disabled={true} value={menuItems[crtIndex].text} style={{width:'100%'}}/>
            }else{
                element = (
                    <ReactMUI.DropDownMenu
                        autoWidth={false}
                        className="full-width"
                        menuItems={menuItems}
                        selectedIndex={crtIndex}
                        onChange={this.onDropDownChange}
                    />
                );
            }
            return (
                <div className="reset-pydio-forms">
                    <h3>{this.context.getMessage('151')}</h3>
                    {element}
                    <div className="form-legend">{this.context.getMessage('198')}</div>
                </div>
            );
        }
    });

    var VisibilityPanel = React.createClass({

        mixins:[ContextConsumerMixin],

        toggleVisibility: function(){
            this.props.shareModel.toggleVisibility();
        },
        transferOwnership: function(){
            this.props.shareModel.setNewShareOwner(this.refs['newOwner'].getValue());
        },
        render: function(){
            var currentIsOwner = this.props.shareModel.currentIsOwner();

            var legend;
            if(this.props.shareModel.isPublic()){
                if(currentIsOwner){
                    legend = this.context.getMessage('201');
                }else{
                    legend = this.context.getMessage('202');
                }
            }else{
                legend = this.context.getMessage('206');
            }
            var showToggle = (
                <div>
                    <ReactMUI.Checkbox type="checkbox"
                           name="share_visibility"
                           disabled={!currentIsOwner || this.context.isReadonly()}
                           onCheck={this.toggleVisibility}
                           checked={this.props.shareModel.isPublic()}
                           label={this.context.getMessage('200')}
                    />
                    <div className="section-legend">{legend}</div>
                </div>
            );
            if(this.props.shareModel.isPublic() && currentIsOwner && !this.context.isReadonly()){
                var showTransfer = (
                    <div className="ownership-form">
                        <h4>{this.context.getMessage('203')}</h4>
                        <div className="section-legend">{this.context.getMessage('204')}</div>
                        <div>
                            <ReactMUI.TextField ref="newOwner" floatingLabelText={this.context.getMessage('205')}/>
                            <ReactMUI.RaisedButton label={this.context.getMessage('203b')} onClick={this.transferOwnership}/>
                        </div>
                    </div>
                );
            }
            return (
                <div className="reset-pydio-forms ie_material_checkbox_fix">
                    <h3>{this.context.getMessage('199')}</h3>
                    {showToggle}
                    {showTransfer}
                </div>
            );
        }
    });

    var DialogNamespace = global.ShareDialog || {};

    DialogNamespace.MainPanel = MainPanel;
    DialogNamespace.PublicLinkField = PublicLinkField;
    DialogNamespace.PublicLinkPanel = PublicLinkPanel;

    global.ShareDialog = DialogNamespace;

})(window);
