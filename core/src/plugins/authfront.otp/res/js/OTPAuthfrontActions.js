/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

(function(global){

    let pydio = global.pydio;

    class LoginDialogModifier extends PydioReactUI.AbstractDialogModifier{

        constructor(){
            super();
            this._modifyLoginScreen = pydio.getPluginConfigs('authfront.otp').get('MODIFY_LOGIN_SCREEN');
        }

        enrichSubmitParameters(props, state, refs, params){

            if(this._modifyLoginScreen){
                params['otp_code'] = refs.otp_code.getValue();
            }

        }

        renderAdditionalComponents(props, state, accumulator){

            if(this._modifyLoginScreen){
                accumulator.bottom.push(<MaterialUI.TextField ref="otp_code" floatingLabelText={pydio.MessageHash['authfront.otp.10']}/>);
            }else{
                accumulator.bottom.push(<div><span className="mdi mdi-alert"/> {pydio.MessageHash['authfront.otp.9']}</div>);
            }

        }

    }

    let OTPSetupScreen = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        submit: function(){

            if(!this.refs.verification.getValue()){
                pydio.displayMessage('ERROR', 'Please set up verification code');
                return false;
            }
            PydioApi.getClient().request({
                get_action  :   "otp_show_setup_screen",
                step        :   "verify",
                otp         :   this.refs.verification.getValue()
            }, function(t){
                if(t.responseJSON && t.responseJSON.RESULT === "OK"){
                    location.reload();
                }
            });

        },

        getInitialState: function(){
            return {loaded: false}
        },

        componentWillReceiveProps: function(){
            PydioApi.getClient().request({
                get_action:'otp_show_setup_screen'
            }, function(t){
                if(!t.responseJSON) return;
                this.setState({
                    qrCode:t.responseJSON.qrcode,
                    otpKey:t.responseJSON.key,
                    loaded:true
                });
            }.bind(this));
        },

        render: function(){
            let messages = pydio.MessageHash;
            let codes;
            if(this.state.loaded){
                codes = (
                    <div class="codes">
                        <div style={{textAlign:"center"}}><ReactQRCode size={100} value={this.state.qrCode} level="L"/></div>
                        <MaterialUI.TextField fullWidth={true} floatingLabelText="Google Key" ref="google_otp" defaultValue={this.state.otpKey}/>
                    </div>
                );
            }

            return (
                <div id="otp_setup_screen">
                    <p>{messages['authfront.otp.2']}</p>
                    <p><big>1.</big> {messages['authfront.otp.3']}</p>
                    <p><big>2.</big> {messages['authfront.otp.4']}</p>
                    {codes}
                    <p style={{paddingBottom: 0, marginBottom: 0}}><big>3.</big> {messages['authfront.otp.5']}</p>
                    <MaterialUI.TextField
                        fullWidth={true}
                        floatingLabelText="Code"
                        ref="verification"
                    />
                </div>
            );

        }

    });

    class Callbacks{

        static setupScreen(){

            pydio.UI.openComponentInModal('OTPAuthfrontActions', 'OTPSetupScreen');

        }

    }

    global.OTPAuthfrontActions = {
        Callbacks: Callbacks,
        LoginDialogModifier: LoginDialogModifier,
        OTPSetupScreen: OTPSetupScreen
    };


})(window)