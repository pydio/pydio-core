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

    var UserBadge;
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
                model: new ReactModel.Share(this.props.pydio, this.props.selection)
            };
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
            var auth = ReactModel.Share.getAuthorizations(this.props.pydio);
            if((model.getNode().isLeaf() && auth.file_public_link) || (!model.getNode().isLeaf() && auth.folder_public_link)){
                panels.push(
                    <ReactMUI.Tab key="public-link" label={'Public Link' + (model.hasPublicLink()?' (active)':'')}>
                        <PublicLinkPanel pydio={this.props.pydio} shareModel={model} authorizations={auth}/>
                    </ReactMUI.Tab>
                );
            }
            if( (model.getNode().isLeaf() && auth.file_workspaces) || (!model.getNode().isLeaf() && auth.folder_workspaces)){
                var users = model.getSharedUsers();
                panels.push(
                    <ReactMUI.Tab key="target-users" label={'Users' + (users.length?' ('+users.length+')':'')}>
                        <UsersPanel shareModel={model}/>
                    </ReactMUI.Tab>
                );
            }
            if(panels.length > 0){
                panels.push(
                    <ReactMUI.Tab  key="share-permissions" label="Advanced">
                        <AdvancedPanel pydio={this.props.pydio} shareModel={model}/>
                    </ReactMUI.Tab>
                );
            }

            return(
                <div style={{width:420}}>
                    <HeaderPanel {...this.props} shareModel={this.state.model}/>
                    <ReactMUI.Tabs onChange={this.refreshDialogPosition}>{panels}</ReactMUI.Tabs>
                    <ButtonsPanel {...this.props} shareModel={this.state.model} onClick={this.clicked}/>
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
            shareModel:React.PropTypes.instanceOf(ReactModel.Share)
        },
        onUsersUpdate: function(operation, userId, userData){
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
        render: function(){
            var currentUsers = this.props.shareModel.getSharedUsers();
            const excludes = currentUsers.map(function(u){return u.ID});
            return (
                <div style={{padding:'30px 20px 10px'}}>
                    <UsersLoader
                        onValueSelected={this.valueSelected}
                        excludes={excludes}
                    />
                    <SharedUsersBox
                        users={currentUsers}
                        onUsersUpdate={this.onUsersUpdate}
                        saveSelectionAsTeam={this.props.shareModel.saveSelectionSupported(global.pydio)?this.onSaveSelection:null}
                    />
                </div>
            );
        }
    });

    UserBadge = React.createClass({
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
            }else if(this.props.type == 'temporary'){
                avatar = <span className="avatar icon-plus"/>;
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
            onUsersUpdate:React.PropTypes.func.isRequired,
            saveSelectionAsTeam:React.PropTypes.func
        },
        render: function(){
            // sort by group/user then by ID;
            const userEntries = this.props.users.sort(function(a,b) {
                return (b.TYPE == "group") ? 1 : ((a.TYPE == "group") ? -1 : (a.ID > b.ID) ? 1 : ((b.ID > a.ID) ? -1 : 0));
            } ).map(function(u){
                return <SharedUserEntry userEntry={u} key={u.ID} onUserUpdate={this.props.onUsersUpdate}/>
            }.bind(this));
            if(this.props.saveSelectionAsTeam && this.props.users.length > 1){
                var saveSelection = (
                    <div className="save-as-team-container">
                        <a onClick={this.props.saveSelectionAsTeam}>Save users as a team</a>
                    </div>
                );
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
                    {saveSelection}
                </div>
            );
        }
    });

    var SharedUserEntry = React.createClass({
        propTypes: {
            userEntry:React.PropTypes.object.isRequired,
            onUserUpdate:React.PropTypes.func.isRequired
        },
        onRemove:function(){
            this.props.onUserUpdate('remove', this.props.userEntry.ID, this.props.userEntry);
        },
        onToggleWatch:function(){
            this.props.onUserUpdate('update_right', this.props.userEntry.ID, {right:'watch', add:!this.props.userEntry['WATCH']});
        },
        onInvite:function(){

        },
        onUpdateRight:function(event){
            var target = event.target;
            this.props.onUserUpdate('update_right', this.props.userEntry.ID, {right:target.name, add:target.checked});
        },
        render: function(){
            var menuItems = [];
            if(this.props.userEntry.TYPE != 'group'){
                menuItems.push({text:'Notify on content changes', callback:this.onToggleWatch, checked:this.props.userEntry.WATCH});
                menuItems.push({text:'Send invitation', callback:this.onInvite});
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

    var UsersLoader = React.createClass({

        propTypes:{
            onValueSelected:React.PropTypes.func.isRequired,
            excludeValues:React.PropTypes.array.isRequired
        },

        getInitialState:function(){
            return {createUser: null};
        },

        getSuggestions(input, callback){

            var excludes = this.props.excludes;
            PydioApi.getClient().request({get_action:'user_list_authorized_users', value:input, format:'xml'}, function(transport){
                const lis = XMLUtils.XPathSelectNodes(transport.responseXML, '//li');
                var suggestions = [];
                lis.map(function(li){
                    var id = li.getAttribute('data-entry_id');
                    if(id && excludes.indexOf(id) !== -1){
                        return;
                    }
                    var spanLabel = XMLUtils.XPathGetSingleNodeText(li, 'span[@class="user_entry_label"]');
                    suggestions.push({
                        label:li.getAttribute('data-label'),
                        spanLabel:spanLabel,
                        id:li.getAttribute('data-entry_id'),
                        type:li.getAttribute('class'),
                        group:li.getAttribute('data-group'),
                        avatar:li.getAttribute('data-avatar'),
                        temporary:li.getAttribute('data-temporary')?true:false,
                        external:li.getAttribute('data-external') == 'true'
                    });
                });
                callback(null, suggestions);
            });

        },

        renderSuggestion: function(suggestion, input){
            return (
                <UserBadge
                    label={suggestion['spanLabel'] || suggestion['label']}
                    avatar={suggestion.avatar}
                    type={suggestion.group ? 'group' : (suggestion.temporary?'temporary' : (suggestion.external?'tmp_user':'user'))}
                />
            );
        },

        suggestionValue: function(suggestion){
            return '';//suggestion.id || suggestion.label;
        },

        onSuggestionSelected: function(suggestion, event){
            if(!suggestion) return;
            var blur = true;
            if(suggestion.group){
                this.props.onValueSelected(suggestion.group, suggestion.label, 'group');
            }else if(suggestion.id) {
                this.props.onValueSelected(suggestion.id, suggestion.label, suggestion.external?'tmp_user':'user');
            }else if(suggestion.temporary){
                this.setState({createUser:suggestion.label});
                blur = false;
            }
            if(blur){
                this.refs.autosuggest.refs.input.getDOMNode().blur();
                global.setTimeout(function(){
                    this.refs.autosuggest.refs.input.getDOMNode().blur();
                }.bind(this), 50);
            }
        },

        submitCreationForm: function(){

            var values = this.refs['creationForm'].getValuesForPost();
            values['get_action'] = 'user_create_user';
            PydioApi.getClient().request(values, function(transport){
                console.log(transport.responseText);
                var display = values.NEW_USER_DISPLAY_NAME || values.NEW_new_user_id;
                this.props.onValueSelected(values.NEW_new_user_id, display, 'user');
                this.setState({createUser:null});
            }.bind(this));

        },

        cancelCreationForm:function(){
            this.setState({createUser:null});
        },

        render: function(){

            if(this.state.createUser){

                return (
                    <div className="react-autosuggest">
                        <input type="text" id="users-autosuggest" className="react-autosuggest__input" value={'Create User ' + this.state.createUser}/>
                        <div className="react-autosuggest__suggestions">
                            <UserCreationForm ref="creationForm" newUserName={this.state.createUser} />
                            <div style={{padding:16, textAlign:'right', paddingTop:0}}>
                                <ReactMUI.FlatButton label="Save & add" secondary={true} onClick={this.submitCreationForm} />
                                <ReactMUI.FlatButton label="Cancel" onClick={this.cancelCreationForm} />
                            </div>
                        </div>

                    </div>
                );

            }else{
                const inputAttributes = {
                    id: 'users-autosuggest',
                    name: 'users-autosuggest',
                    className: 'react-autosuggest__input',
                    placeholder: 'Filter users or create one...',
                    value: ''   // Initial value
                };
                return (
                    <div style={{position:'relative'}}>
                        <span className="suggest-search icon-search"/>
                        <ReactAutoSuggest
                            ref="autosuggest"
                            showWhen = {input => true }
                            inputAttributes={inputAttributes}
                            suggestions={this.getSuggestions}
                            suggestionRenderer={this.renderSuggestion}
                            suggestionValue={this.suggestionValue}
                            onSuggestionSelected={this.onSuggestionSelected}
                        />
                    </div>
                );
            }
        }

    });

    var UserCreationForm = React.createClass({

        propTypes:{
            newUserName:React.PropTypes.string.isRequired
        },

        getParameters: function(){

            if(this._parsedParameters){
                return this._parsedParameters;
            }

            var basicParameters = [];
            basicParameters.push({
                description: MessageHash['533'],
                editable: false,
                expose: "true",
                label: MessageHash['522'],
                name: "new_user_id",
                scope: "user",
                type: "string",
                mandatory: "true"
            },{
                description: MessageHash['534'],
                editable: "true",
                expose: "true",
                label: MessageHash['523'],
                name: "new_password",
                scope: "user",
                type: "password",
                mandatory: "true"
            },{
                description: MessageHash['536'],
                editable: "true",
                expose: "true",
                label: MessageHash['535'],
                name: "send_email",
                scope: "user",
                type: "boolean",
                mandatory: true
            });

            var params = global.pydio.getPluginConfigs('conf').get('NEWUSERS_EDIT_PARAMETERS').split(',');
            for(var i=0;i<params.length;i++){
                params[i] = "user/preferences/pref[@exposed]|//param[@name='"+params[i]+"']";
            }
            var xPath = params.join('|');
            PydioForm.Manager.parseParameters(global.pydio.getXmlRegistry(), xPath).map(function(el){
                basicParameters.push(el);
            });

            this._parsedParameters = basicParameters;

            return basicParameters;
        },

        getValuesForPost: function(){
            return PydioForm.Manager.getValuesForPOST(this.getParameters(), this.state.values, 'NEW_');
        },

        getInitialState: function(){
            return {
                values:{
                    new_user_id:this.props.newUserName,
                    lang:global.pydio.currentLanguage,
                    new_password:'',
                    send_email:true
                }
            };
        },

        onValuesChange:function(newValues){
            this.setState({values:newValues});
        },

        render:function(){
            return <PydioForm.FormPanel
                className="reset-pydio-forms"
                depth={-1}
                parameters={this.getParameters()}
                values={this.state.values}
                onChange={this.onValuesChange}
            />
        }
    });

    /**************************/
    /* PUBLIC LINK PANEL
     /**************************/
    var PublicLinkPanel = React.createClass({

        propTypes: {
            pydio:React.PropTypes.instanceOf(Pydio),
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            authorizations: React.PropTypes.object
        },

        toggleLink: function(event){
            this.props.shareModel.togglePublicLink();
        },

        render: function(){


            if(this.props.shareModel.hasPublicLink()){
                var publicLinkPanes = [
                    <PublicLinkPermissions shareModel={this.props.shareModel} key="public-perm" />,
                    <PublicLinkSecureOptions shareModel={this.props.shareModel} key="public-secure" />
                ];
            }
            return (
                <div style={{padding:16}} className="reset-pydio-forms">
                    <ReactMUI.Checkbox onCheck={this.toggleLink} checked={this.props.shareModel.getPublicLink()?true:false} label="Enable Public Link"/>
                    <PublicLinkField shareModel={this.props.shareModel} editAllowed={this.props.authorizations.editable_hash} />
                    {publicLinkPanes}
                </div>
            );
        }
    });

    var PublicLinkField = React.createClass({
        propTypes: {
            shareModel: React.PropTypes.instanceOf(ReactModel.Share),
            editAllowed: React.PropTypes.bool,
            onChange: React.PropTypes.func
        },
        getInitialState: function(){
            return {editLink: false, copyMessage:''};
        },
        toggleEditMode: function(){
            if(this.state.editLink && this.state.customLink){
                this.props.shareModel.updateCustomLink(this.state.customLink);
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
                        return this.props.shareModel.getPublicLink();
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

        render: function(){
            var publicLink = this.props.shareModel.getPublicLink();
            var editAllowed = this.props.editAllowed && !this.props.shareModel.publicLinkIsShorten();
            if(this.state.editLink && editAllowed){
                return (
                    <div className="public-link-container edit-link">
                        <span>{publicLink.split('://')[0]}://[..]/{PathUtils.getBasename(PathUtils.getDirname(publicLink)) + '/'}</span>
                        <ReactMUI.TextField onChange={this.changeLink} value={this.state.customLink || this.props.shareModel.getPublicLinkHash()}/>
                        <ReactMUI.RaisedButton label="Ok" onClick={this.toggleEditMode}/>
                        <div className="section-legend">You can customize the last part of the link.</div>
                    </div>
                );
            }else{
                if(editAllowed && publicLink){
                    var editButton = <span className="custom-link-button icon-edit-sign" title="Customize link last part" onClick={this.toggleEditMode}/>;
                }
                var copyButton = <span ref="copy-button" className="copy-link-button icon-paste" title="Copy link to clipboard"/>;
                var setHtml = function(){
                    return {__html:this.state.copyMessage};
                }.bind(this);
                if(publicLink){
                    var focus = function(e){
                        e.target.select();
                    };
                    return (
                        <div className="public-link-container">
                            <ReactMUI.TextField
                                className="public-link"
                                type="text"
                                name="Link"
                                ref="public-link-field"
                                value={this.props.shareModel.getPublicLink()}
                                onFocus={focus}
                            /> {editButton} {copyButton}
                            <div style={{textAlign:'center'}} className="section-legend" dangerouslySetInnerHTML={setHtml()}/>
                        </div>
                    );
                }else{
                    return (
                        <div className="section-legend" style={{marginTop:20}}>This file is not currently publicly accessible. Enabling a public link will create a unique access that you can send to anyone. If you want to share with existing Pydio users, use the "Users" tab.</div>
                    );
                }
            }
        }
    });

    var PublicLinkPermissions = React.createClass({

        changePermission: function(event){
            var name = event.target.name;
            var checked = event.target.checked;
            this.props.shareModel.setPublicLinkPermission(name, checked);
        },

        render: function(){
            var perms = [], previewWarning;
            perms.push({NAME:'read',LABEL:'Preview'});
            perms.push({NAME:'download', LABEL:'Download'});
            if(!this.props.shareModel.getNode().isLeaf()){
                perms.push({NAME:'write', LABEL:'Upload'});
            }
            if(this.props.shareModel.isPublicLinkPreviewDisabled() && this.props.shareModel.getPublicLinkPermission('read')){
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
                                        checked={this.props.shareModel.getPublicLinkPermission(p.NAME)}
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

        updateDLExpirationField: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.setExpirationFor("downloads", newValue);
        },

        updateDaysExpirationField: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.setExpirationFor("days", newValue);
        },

        resetPassword: function(){
            this.props.shareModel.resetPassword();
        },

        updatePassword: function(event){
            var newValue = event.currentTarget.getValue();
            this.props.shareModel.updatePassword(newValue);
        },

        render: function(){
            var passPlaceHolder, resetPassword;
            if(this.props.shareModel.hasHiddenPassword()){
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
                                value={passPlaceHolder? passPlaceHolder : this.props.shareModel.getPassword()}
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
                                   value={this.props.shareModel.getExpirationFor('days') === 0 ? "" : this.props.shareModel.getExpirationFor('days')}
                                   onChange={this.updateDaysExpirationField}/>
                            </div>
                        <div style={{width:'50%', display:'inline-block'}}>
                            <ReactMUI.TextField
                               floatingLabelText="Expire after (downloads)"
                               value={this.props.shareModel.getExpirationFor('downloads') === 0 ? "" : this.props.shareModel.getExpirationFor('downloads')}
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
                var layoutPane = <PublicLinkTemplate {...this.props} layoutData={layoutData}/>;
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

        onDropDownChange: function(event, index, item){
            this.props.shareModel.setTemplate(item.payload);
        },

        render: function(){
            var index = 0, crtIndex = 0;
            var selected = this.props.shareModel.getTemplate();
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