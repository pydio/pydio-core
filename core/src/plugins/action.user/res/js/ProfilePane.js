const React = require('react')
const LangUtils = require('pydio/util/lang')
const {FlatButton, Divider} = require('material-ui')
const Pydio = require('pydio')
const {Manager, FormPanel} = Pydio.requireLib('form')
import PasswordPopover from './PasswordPopover'
import EmailPanel from './EmailPanel'

const FORM_CSS = ` 
.react-mui-context .current-user-edit.pydio-form-panel > .pydio-form-group {
  margin-top: 220px;
}
.react-mui-context .current-user-edit.pydio-form-panel > .pydio-form-group div.form-entry-image {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 200px;
  background-color: #eceff1;
}
.react-mui-context .current-user-edit.pydio-form-panel > .pydio-form-group div.form-entry-image .image-label,
.react-mui-context .current-user-edit.pydio-form-panel > .pydio-form-group div.form-entry-image .form-legend {
  display: none;
}
.react-mui-context .current-user-edit.pydio-form-panel > .pydio-form-group div.form-entry-image .file-dropzone {
  border-radius: 50%;
  width: 160px !important;
  height: 160px !important;
  margin: 20px auto;
}
.react-mui-context .current-user-edit.pydio-form-panel > .pydio-form-group div.form-entry-image .binary-remove-button {
  position: absolute;
  bottom: 5px;
  right: 0;
}

`

let ProfilePane = React.createClass({

    getInitialState: function(){
        let objValues = {}, mailValues = {};
        let pydio = this.props.pydio;
        pydio.user.preferences.forEach(function(v, k){
            if(k === 'gui_preferences') return;
            objValues[k] = v;
        });
        return {
            definitions:Manager.parseParameters(pydio.getXmlRegistry(), "user/preferences/pref[@exposed='true']|//param[contains(@scope,'user') and @expose='true' and not(contains(@name, 'NOTIFICATIONS_EMAIL'))]"),
            mailDefinitions:Manager.parseParameters(pydio.getXmlRegistry(), "user/preferences/pref[@exposed='true']|//param[contains(@scope,'user') and @expose='true' and contains(@name, 'NOTIFICATIONS_EMAIL')]"),
            values:objValues,
            originalValues:LangUtils.deepCopy(objValues),
            dirty: false
        };
    },

    onFormChange: function(newValues, dirty, removeValues){
        this.setState({dirty: dirty, values: newValues}, () => {
            this._updater(this.getButtons());
        });
    },

    getButtons: function(updater = null){
        if(updater) this._updater = updater;
        let button, revert;
        if(this.state.dirty){
            revert = <FlatButton label={"Revert"} onTouchTap={this.revert}/>;
            button = <FlatButton label={this.props.pydio.MessageHash[53]} secondary={true} onTouchTap={this.saveForm}/>;
        }else{
            button = <FlatButton label="Close" onTouchTap={this.props.onDismiss}/>;
        }
        if(this.props.pydio.Controller.getActionByName('pass_change')){
            return [
                <div style={{display:'flex', width: '100%'}}>
                    <PasswordPopover {...this.props}/>
                    <span style={{flex:1}}/>
                    {revert}
                    {button}
                </div>
            ];
        }else{
            return [button];
        }
    },

    getButton: function(actionName, messageId){
        let pydio = this.props.pydio;
        if(!pydio.Controller.getActionByName(actionName)){
            return null;
        }
        let func = function(){
            pydio.Controller.fireAction(actionName);
        };
        return (
            <ReactMUI.RaisedButton label={pydio.MessageHash[messageId]} onClick={func}/>
        );
    },

    revert: function(){
        this.setState({
            values: {...this.state.originalValues},
            dirty: false
        },() => {
            if(this._updater) this._updater(this.getButtons());
        });
    },

    saveForm: function(){
        if(!this.state.dirty){
            this.setState({dirty: false});
            return;
        }
        let pydio = this.props.pydio;
        let postValues = Manager.getValuesForPOST(this.state.definitions, this.state.values, 'PREFERENCES_');
        postValues['get_action'] = 'custom_data_edit';
        PydioApi.getClient().request(postValues, function(transport){
            PydioApi.getClient().parseXmlMessage(transport.responseXML);
            pydio.refreshUserData();
            this.setState({dirty: false}, () => {
                if(this._updater) this._updater(this.getButtons());
            });
        }.bind(this));
    },

    render: function(){
        const {pydio} = this.props;
        const {definitions, values} = this.state;
        return (
            <div>
                <FormPanel
                    className="current-user-edit"
                    parameters={this.state.definitions}
                    values={this.state.values}
                    depth={-1}
                    binary_context={"user_id="+pydio.user.id}
                    onChange={this.onFormChange}
                />
                <Divider/>
                <EmailPanel pydio={this.props.pydio} definitions={this.state.mailDefinitions} values={values} onChange={this.onFormChange}/>
                <style type="text/css" dangerouslySetInnerHTML={{__html: FORM_CSS}}></style>
            </div>
        );
    }

});

export {ProfilePane as default}