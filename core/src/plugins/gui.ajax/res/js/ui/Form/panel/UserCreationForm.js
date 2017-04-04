const React = require('react')
const {Paper, FlatButton} = require('material-ui');
const {User, UsersApi} = require('pydio/http/users-api')
import Manager from '../manager/Manager'
import FormPanel from './FormPanel'

class UserCreationForm extends React.Component{

    getCreateUserParameters(editMode = false){

        let basicParameters = [];
        const pydio = this.props.pydio;
        const {MessageHash} = pydio;
        let prefix = pydio.getPluginConfigs('action.share').get('SHARED_USERS_TMP_PREFIX');
        basicParameters.push({
            description     : MessageHash['533'],
            editable        : false,
            expose          : "true",
            label           : MessageHash['522'],
            name            : (editMode ? "existing_user_id" : "new_user_id"),
            scope           : "user",
            type            : (editMode ? "hidden" : "string"),
            mandatory       : "true",
            "default"       : prefix ? prefix : ''
        },{
            description     : MessageHash['534'],
            editable        : "true",
            expose          : "true",
            label           : MessageHash['523'],
            name            : "new_password",
            scope           : "user",
            type            : "valid-password",
            mandatory       : "true"
        });

        const params = global.pydio.getPluginConfigs('conf').get('NEWUSERS_EDIT_PARAMETERS').split(',');
        for(let i=0;i<params.length;i++){
            params[i] = "user/preferences/pref[@exposed]|//param[@name='"+params[i]+"']";
        }
        const xPath = params.join('|');
        Manager.parseParameters(this.props.pydio.getXmlRegistry(), xPath).map(function(el){
            basicParameters.push(el);
        });
        if(!editMode){
            basicParameters.push({
                description : MessageHash['536'],
                editable    : "true",
                expose      : "true",
                label       : MessageHash['535'],
                name        : "send_email",
                scope       : "user",
                type        : "boolean",
                mandatory   : true
            });
        }
        return basicParameters;
    }



    getDefaultProps(){
        return {editMode: false};
    }

    getParameters(){
        if(!this._parsedParameters){
            this._parsedParameters = this.getCreateUserParameters(this.props.editMode);
        }
        return this._parsedParameters;
    }

    getValuesForPost(prefix){
        return Manager.getValuesForPOST(this.getParameters(),this.state.values,prefix);
    }

    constructor(props, context){
        super(props, context);

        const {pydio, newUserName, editMode, userData} = this.props;
        let userPrefix = pydio.getPluginConfigs('action.share').get('SHARED_USERS_TMP_PREFIX');
        if(!userPrefix || newUserName.startsWith(userPrefix)) userPrefix = '';
        const idKey = editMode ? 'existing_user_id' : 'new_user_id';
        let values = {
            new_password:'',
            send_email:true
        };
        if(editMode){
            values['existing_user_id'] = this.props.newUserName;
            if(userData){
                values['lang'] = userData.lang;
                values[userPrefix + 'USER_DISPLAY_NAME'] = userData.USER_DISPLAY_NAME;
                values[userPrefix + 'email'] = userData.email;
            }
        }else{
            values['new_user_id'] = userPrefix + newUserName;
            values['lang'] = pydio.currentLanguage;
        }
        this.state = { values: values };
    }

    onValuesChange(newValues){
        this.setState({values:newValues});
    }

    submitCreationForm(){

        const prefix = 'NEW_';
        const values = this.getValuesForPost(prefix);
        UsersApi.createUserFromPost(values, function(values, jsonReponse){
            let id;
            if(jsonReponse['createdUserId']){
                id = jsonReponse['createdUserId'];
            }else{
                id = values[prefix + 'new_user_id'];
            }
            const display = values[prefix + 'USER_DISPLAY_NAME'] || id;
            const fakeUser = new User(id, display, 'user');
            this.props.onUserCreated(fakeUser);
        }.bind(this));

    }

    cancelCreationForm(){
        this.props.onCancel();
    }

    render(){
        const pydio = this.props.pydio;
        return (
            <Paper zDepth={this.props.zDepth !== undefined ? this.props.zDepth : 2} style={{height: 250, overflowY: 'auto', ...this.props.style}}>
                <FormPanel
                    className="reset-pydio-forms"
                    depth={-1}
                    parameters={this.getParameters()}
                    values={this.state.values}
                    onChange={this.onValuesChange.bind(this)}
                />
                <div style={{padding:16, textAlign:'right', paddingTop:0}}>
                    <FlatButton label={this.props.editMode ? pydio.MessageHash[519] : pydio.MessageHash[484]} secondary={true} onTouchTap={this.submitCreationForm.bind(this)} />
                    <FlatButton label={pydio.MessageHash[49]} onTouchTap={this.cancelCreationForm.bind(this)} />
                </div>
            </Paper>
        )
    }
}

UserCreationForm.propTypes = {
    newUserName     : React.PropTypes.string,
    onUserCreated   : React.PropTypes.func.isRequired,
    onCancel        : React.PropTypes.func.isRequired,
    editMode        : React.PropTypes.bool,
    userData        : React.PropTypes.object
};

export {UserCreationForm as default}