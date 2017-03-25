export default React.createClass({

    propTypes:{
        newUserName:React.PropTypes.string.isRequired,
        onUserCreated: React.PropTypes.func.isRequired,
        onCancel: React.PropTypes.func.isRequired
    },

    getParameters: function(){
        if(!this._parsedParameters){
            this._parsedParameters = PydioUsers.Client.getCreateUserParameters();
        }
        return this._parsedParameters;
    },

    getValuesForPost: function(prefix){
        return PydioForm.Manager.getValuesForPOST(this.getParameters(),this.state.values,prefix);
    },

    getInitialState: function(){
        let userPrefix = pydio.getPluginConfigs('action.share').get('SHARED_USERS_TMP_PREFIX');
        if(!userPrefix || this.props.newUserName.startsWith(userPrefix)) userPrefix = '';
        return {
            values:{
                new_user_id:userPrefix + this.props.newUserName,
                lang:pydio.currentLanguage,
                new_password:'',
                send_email:true
            }
        };
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
                    <MaterialUI.FlatButton label={pydio.MessageHash[484]} secondary={true} onTouchTap={this.submitCreationForm} />
                    <MaterialUI.FlatButton label={pydio.MessageHash[49]} onTouchTap={this.cancelCreationForm} />
                </div>
            </MaterialUI.Paper>
        )
    }
});
