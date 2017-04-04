import {pydio} from '../globals'
const React = require('react')
const PydioApi = require('pydio/http/api')
const BootUI = require('pydio/http/resources-manager').requireLib('boot');
const {ActionDialogMixin, SubmitButtonProviderMixin, CancelButtonProviderMixin, AsyncComponent} = BootUI;

const PasswordDialog = React.createClass({

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],
    getInitialState: function(){
        return {passValid: false};
    },
    getDefaultProps: function(){
        return {
            dialogTitle: pydio.MessageHash[194],
            dialogIsModal: true
        };
    },
    submit(){
        if(!this.state.passValid){
            return false;
        }
        this.refs.passwordForm.getComponent().post(function(value){
            if(value) this.dismiss();
        }.bind(this));
    },

    passValidStatusChange: function(status){
        this.setState({passValid: status});
    },

    render: function(){

        return (
            <AsyncComponent
                namespace="UserAccount"
                componentName="PasswordForm"
                pydio={this.props.pydio}
                ref="passwordForm"
                onValidStatusChange={this.passValidStatusChange}
            />
        );
    }

});

export default PasswordDialog