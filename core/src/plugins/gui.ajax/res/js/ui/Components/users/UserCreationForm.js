export default React.createClass({

    propTypes:{
        newUserName:React.PropTypes.string,
        onUserCreated: React.PropTypes.func.isRequired,
        onCancel: React.PropTypes.func.isRequired,
        editMode: React.PropTypes.bool,
        userData: React.PropTypes.object
    },

    getDefaultProps: function(){
        return {editMode: false};
    },

    getParameters: function(){
        if(!this._parsedParameters){
            this._parsedParameters = PydioUsers.Client.getCreateUserParameters(this.props.editMode);
        }
        return this._parsedParameters;
    },

    getValuesForPost: function(prefix){
        return PydioForm.Manager.getValuesForPOST(this.getParameters(),this.state.values,prefix);
    },

    getInitialState: function(){
        let userPrefix = pydio.getPluginConfigs('action.share').get('SHARED_USERS_TMP_PREFIX');
        if(!userPrefix || this.props.newUserName.startsWith(userPrefix)) userPrefix = '';
        const idKey = this.props.editMode ? 'existing_user_id' : 'new_user_id';
        let values = {
            new_password:'',
            send_email:true
        };
        if(this.props.editMode){
            values['existing_user_id'] = this.props.newUserName;
            if(this.props.userData){
                values['lang'] = this.props.userData.lang;
                values[userPrefix + 'USER_DISPLAY_NAME'] = this.props.userData.USER_DISPLAY_NAME;
                values[userPrefix + 'email'] = this.props.userData.email;
            }
        }else{
            values['new_user_id'] = userPrefix + this.props.newUserName;
            values['lang'] = pydio.currentLanguage;
        }
        return { values: values };
    },

    onValuesChange:function(newValues){
        this.setState({values:newValues});
    },

    submitCreationForm: function(){

        const prefix = PydioUsers.Client.getCreateUserPostPrefix();
        const values = this.getValuesForPost(prefix);
        PydioUsers.Client.createUserFromPost(values, function(values, jsonReponse){
            let id;
            if(jsonReponse['createdUserId']){
                id = jsonReponse['createdUserId'];
            }else{
                id = values[prefix + 'new_user_id'];
            }
            const display = values[prefix + 'USER_DISPLAY_NAME'] || id;
            const fakeUser = new PydioUsers.User(id, display, 'user');
            this.props.onUserCreated(fakeUser);
        }.bind(this));

    },

    cancelCreationForm:function(){
        this.props.onCancel();
    },

    render:function(){
        const pydio = this.props.pydio;
        return (
            <MaterialUI.Paper zDepth={this.props.zDepth !== undefined ? this.props.zDepth : 2} style={{height: 250, overflowY: 'auto', ...this.props.style}}>
                <PydioForm.FormPanel
                    className="reset-pydio-forms"
                    depth={-1}
                    parameters={this.getParameters()}
                    values={this.state.values}
                    onChange={this.onValuesChange}
                />
                <div style={{padding:16, textAlign:'right', paddingTop:0}}>
                    <MaterialUI.FlatButton label={this.props.editMode ? pydio.MessageHash[519] : pydio.MessageHash[484]} secondary={true} onTouchTap={this.submitCreationForm} />
                    <MaterialUI.FlatButton label={pydio.MessageHash[49]} onTouchTap={this.cancelCreationForm} />
                </div>
            </MaterialUI.Paper>
        )
    }
});
