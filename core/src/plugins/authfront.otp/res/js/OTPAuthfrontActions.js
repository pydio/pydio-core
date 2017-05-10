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
                    qrCode:responseJSON.qrcode,
                    otpKey:responseJSON.key,
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
                        <ReactQRCode size={200} value={this.state.qrCode} level="L"/>
                        <MaterialUI.TextField floatingLabelText="Google Key" ref="google_otp" defaultValue={this.state.otpKey}/>
                    </div>
                );
            }

            return (
                <div id="otp_setup_screen" box_width="500">
                    <div>{messages['authfront.otp.2']}</div>
                    <div>{messages['authfront.otp.3']}</div>
                    <div>{messages['authfront.otp.4']}</div>
                    {codes}
                    <div class="verif">
                        <MaterialUI.TextField
                            floatingLabelText={messages['authfront.otp.5']}
                            ref="verification"
                        />
                    </div>
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
        LoginDialogModifier: LoginDialogModifier
    };


})(window)