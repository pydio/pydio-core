(function(global){

    let pydio = global.pydio;

    let LoginDialogMixin = {

        getInitialState: function(){
            return {
                globalParameters: pydio.Parameters,
                authParameters: pydio.getPluginConfigs('auth'),
                errorId: null,
                displayCaptcha: false
            };
        },

        postLoginData: function(client){

            const passwordOnly = this.state.globalParameters.get('PASSWORD_AUTH_ONLY');
            let login;
            if(passwordOnly){
                login = this.state.globalParameters.get('PRESET_LOGIN');
            }else{
                login = this.refs.login.getValue();
            }
            let params = {
                get_action  : 'login',
                userid      : login,
                password    : this.refs.password.getValue(),
                login_seed  : -1
            };
            if(this.refs.captcha_input){
                params['captcha_code'] = this.refs.captcha_input.getValue();
            }
            if(this.refs.remember_me && this.refs.remember_me.isChecked()){
                params['remember_me'] = 'true' ;
            }
            if(this.props.modifiers){
                this.props.modifiers.map(function(m){
                    m.enrichSubmitParameters(this.props, this.state, this.refs, params);
                }.bind(this));
            }
            client.request(params, function(responseObject){
                let success = client.parseXmlMessage(responseObject.responseXML);
                if(success){
                    this.dismiss();
                }else{
                    let errorId = PydioApi.getClient().LAST_ERROR_ID;
                    if(errorId == '285' && passwordOnly){
                        errorId = '553';
                    }
                    this.setState({errorId: errorId});
                    if(responseObject.responseXML && XMLUtils.XPathGetSingleNodeText(responseObject.responseXML.documentElement, "logging_result/@value") === '-4'){
                        this.setState({displayCaptcha: true});
                    }

                }
            }.bind(this));

        }
    };

    let LoginPasswordDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin,
            LoginDialogMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: pydio.MessageHash[163],
                dialogIsModal: true,
                dialogSize:'sm'
            };
        },

        componentWillReceiveProps: function(){
            this.setState({displayCaptcha: false});
            PydioApi.getClient().request({get_action:"get_seed"}, function(transport){
                if(transport.responseJSON)  this.setState({displayCaptcha: true});
            }.bind(this));
        },

        submit: function(){
            let client = PydioApi.getClient();
            this.postLoginData(client);
        },

        fireForgotPassword: function(e){
            e.stopPropagation();
            pydio.getController().fireAction(this.state.authParameters.get("FORGOT_PASSWORD_ACTION"));
        },

        useBlur: function(){
            return true;
        },

        render: function(){
            const passwordOnly = this.state.globalParameters.get('PASSWORD_AUTH_ONLY');
            const secureLoginForm = passwordOnly || this.state.authParameters.get('SECURE_LOGIN_FORM');
            const forgotPasswordLink = this.state.authParameters.get('ENABLE_FORGOT_PASSWORD') && !passwordOnly;

            let errorMessage;
            if(this.state.errorId){
                errorMessage = <div className="ajxp_login_error">{pydio.MessageHash[this.state.errorId]}</div>;
            }
            let captcha;
            if(this.state.displayCaptcha){
                captcha = (
                    <div className="captcha_container">
                        <img src={this.state.globalParameters.get('ajxpServerAccess') + '&get_action=get_captcha&sid='+Math.random()}/>
                        <MaterialUI.TextField floatingLabelText={pydio.MessageHash[390]} ref="captcha_input"/>
                    </div>
                );
            }
            let remember;
            if(!secureLoginForm){
                remember = (
                    <div className="remember_container">
                        <MaterialUI.Checkbox ref="remember_me" label={pydio.MessageHash[261]}/>
                    </div>
                );
            }
            let forgotLink;
            if(forgotPasswordLink){
                forgotLink = (
                    <div className="forgot-password-link"><a style={{cursor:'pointer'}} onClick={this.fireForgotPassword}>{pydio.MessageHash[479]}</a></div>
                );
            }
            let additionalComponentsTop, additionalComponentsBottom;
            if(this.props.modifiers){
                let comps = {top: [], bottom: []};
                this.props.modifiers.map(function(m){
                    m.renderAdditionalComponents(this.props, this.state, comps);
                }.bind(this));
                if(comps.top.length) additionalComponentsTop = <div>{comps.top}</div>;
                if(comps.bottom.length) additionalComponentsBottom = <div>{comps.bottom}</div>;
            }

            const custom = this.props.pydio.Parameters.get('customWording');
            let logoUrl = custom.icon;
            if(custom.icon_binary_url){
                logoUrl = this.props.pydio.Parameters.get('ajxpServerAccess') + '&' + custom.icon_binary_url;
            }

            const logoStyle = {
                backgroundSize: 'contain',
                backgroundImage: 'url('+logoUrl+')',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat',
                position:'absolute',
                top: -130,
                left: 0,
                width: 320,
                height: 120
            };
            return (

                <div>
                    <div style={logoStyle}></div>
                    <div className="dialogLegend">{pydio.MessageHash[passwordOnly ? 552 : 180]}</div>
                    {captcha}
                    {additionalComponentsTop}
                    <form autoComplete={secureLoginForm?"off":"on"}>
                        {!passwordOnly && <MaterialUI.TextField
                            className="blurDialogTextField"
                            autoComplete={secureLoginForm?"off":"on"}
                            floatingLabelText={pydio.MessageHash[181]}
                            ref="login"
                            onKeyDown={this.submitOnEnterKey}
                        />}
                        <MaterialUI.TextField
                            className="blurDialogTextField"
                            autoComplete={secureLoginForm?"off":"on"}
                            type="password"
                            floatingLabelText={pydio.MessageHash[182]}
                            ref="password"
                            onKeyDown={this.submitOnEnterKey}
                        />
                    </form>
                    {additionalComponentsBottom}
                    {remember}
                    {forgotLink}
                    {errorMessage}
                </div>

            );
        }

    });

    let MultiAuthSelector = React.createClass({

        getValue: function(){
            return this.state.value;
        },

        getInitialState: function(){
            return {value:Object.keys(this.props.authSources).shift()}
        },

        onChange: function(object, key, payload){
            this.setState({value: payload});
        },

        render: function(){
            let menuItems = [];
            for (let key in this.props.authSources){
                menuItems.push(<MaterialUI.MenuItem value={key} primaryText={this.props.authSources[key]}/>);
            }
            return (
                <MaterialUI.SelectField
                    value={this.state.value}
                    onChange={this.onChange}
                    floatingLabelText="Login as..."
                >{menuItems}</MaterialUI.SelectField>);

        }
    });

    class MultiAuthModifier extends PydioReactUI.AbstractDialogModifier{

        constructor(){
            super();
        }

        enrichSubmitParameters(props, state, refs, params){

            const selectedSource = refs.multi_selector.getValue();
            params['auth_source'] = selectedSource
            if(props.masterAuthSource && selectedSource === props.masterAuthSource){
                params['userid'] = selectedSource + props.userIdSeparator + params['userid'];
            }

        }

        renderAdditionalComponents(props, state, accumulator){

            if(!props.authSources){
                console.error('Could not find authSources');
                return;
            }
            accumulator.top.push( <MultiAuthSelector ref="multi_selector" {...props} parentState={state}/> );

        }

    }


    let WebFTPDialog = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin,
            LoginDialogMixin
        ],

        getDefaultProps: function () {
            return {
                dialogTitle: pydio.MessageHash[163],
                dialogIsModal: true
            };
        },

        submit: function(){

            let client = PydioApi.getClient();
            client.request({
                get_action      :'set_ftp_data',
                FTP_HOST        :this.refs.FTP_HOST.getValue(),
                FTP_PORT        :this.refs.FTP_PORT.getValue(),
                PATH            :this.refs.PATH.getValue(),
                CHARSET         :this.refs.CHARSET.getValue(),
                FTP_SECURE      :this.refs.FTP_SECURE.isToggled()?'TRUE':'FALSE',
                FTP_DIRECT      :this.refs.FTP_DIRECT.isToggled()?'TRUE':'FALSE',
            }, function(){

                this.postLoginData(client);

            }.bind(this));

        },

        render: function(){

            let messages = pydio.MessageHash;
            let tFieldStyle={width:'100%'};
            let errorMessage;
            if(this.state.errorId){
                errorMessage = <div class="ajxp_login_error">{pydio.MessageHash[this.state.errorId]}</div>;
            }
            let captcha;
            if(this.state.displayCaptcha){
                captcha = (
                    <div className="captcha_container">
                        <img src={this.state.globalParameters.get('ajxpServerAccess') + '&get_action=get_captcha&sid='+Math.random()}/>
                        <MaterialUI.TextField floatingLabelText={pydio.MessageHash[390]} ref="captcha_input"/>
                    </div>
                );
            }

            return (
                <div>
                    {captcha}
                    <table cellpadding="2" border="0" cellspacing="0">
                        <tr>
                            <td colspan="2">
                                <div class="dialogLegend">{messages['ftp_auth.1']}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <MaterialUI.TextField style={tFieldStyle} ref="FTP_HOST" floatingLabelText={messages['ftp_auth.2']}/>
                            </td>
                            <td>
                                <MaterialUI.TextField ref="FTP_PORT" style={tFieldStyle} defaultValue="21" floatingLabelText={messages['ftp_auth.8']}/>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <MaterialUI.TextField style={tFieldStyle} ref="userid" floatingLabelText={messages['181']}/>
                            </td>
                            <td>
                                <MaterialUI.TextField style={tFieldStyle} ref="password" type="password" floatingLabelText={messages['182']}/>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="dialogLegend">{messages['ftp_auth.3']}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <MaterialUI.TextField style={tFieldStyle} ref="PATH" defaultValue="/" floatingLabelText={messages['ftp_auth.4']}/>
                            </td>
                            <td>
                                <MaterialUI.Toggle ref="FTP_SECURE" label={messages['ftp_auth.5']} labelPosition="right"/>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <MaterialUI.TextField style={tFieldStyle} ref="CHARSET" floatingLabelText={messages['ftp_auth.6']}/>
                            </td>
                            <td>
                                <MaterialUI.Toggle ref="FTP_DIRECT" label={messages['ftp_auth.7']} labelPosition="right"/>
                            </td>
                        </tr>
                    </table>
                    {errorMessage}
                </div>
            );

        }

    });

    class Callbacks{

        static sessionLogout(){

            PydioApi.clearRememberData();
            let client = PydioApi.getClient();
            client.request({get_action:'logout'}, function(responseObject){
                client.parseXmlMessage(responseObject.responseXML);
            });

        }

        static loginPassword(props = {}) {
            
            pydio.UI.openComponentInModal('AuthfrontCoreActions', 'LoginPasswordDialog', props);

        }

        static webFTP(){

            pydio.UI.openComponentInModal('AuthfrontCoreActions', 'WebFTPDialog');

            return;

        }

    }

    const ResetPasswordRequire = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        statics: {
            open : () => {
                pydio.UI.openComponentInModal('AuthfrontCoreActions', 'ResetPasswordRequire');
            }
        },

        getDefaultProps: function(){
            return {
                dialogTitle: pydio.MessageHash['gui.user.1'],
                dialogIsModal: true,
                dialogSize:'sm'
            };
        },

        useBlur: function(){
            return true;
        },


        submit: function(){
            const value = this.refs.input && this.refs.input.getValue();
            if(!value) return;
            PydioApi.getClient().request({
                get_action: 'reset-password-ask',
                email: value
            }, () => {
                this.setState({valueSubmitted: true});
            });
        },

        render: function(){
            const mess = this.props.pydio.MessageHash;
            const valueSubmitted = this.state && this.state.valueSubmitted;
            return (
                <div>
                    {!valueSubmitted &&
                        <div>
                            <div className="dialogLegend">{mess['gui.user.3']}</div>
                            <MaterialUI.TextField
                                className="blurDialogTextField"
                                ref="input"
                                fullWidth={true}
                                floatingLabelText={mess['gui.user.4']}
                            />
                        </div>
                    }
                    {valueSubmitted &&
                        <div>{mess['gui.user.5']}</div>
                    }
                </div>
            );

        }


    });

    const ResetPasswordDialog = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        statics: {
            open : () => {
                pydio.UI.openComponentInModal('AuthfrontCoreActions', 'ResetPasswordDialog');
            }
        },

        getDefaultProps: function(){
            return {
                dialogTitle: pydio.MessageHash['gui.user.1'],
                dialogIsModal: true,
                dialogSize:'sm'
            };
        },

        getInitialState: function(){
            return {valueSubmitted: false, formLoaded: false, passValue:null, userId:null};
        },

        useBlur: function(){
            return true;
        },


        submit: function(){
            const {pydio} = this.props;

            if(this.state.valueSubmitted){
                this.props.onDismiss();
                pydio.Controller.fireAction('login');
                return;
            }

            const mess = pydio.MessageHash;
            PydioApi.getClient().request({
                get_action  : 'reset-password',
                key         : pydio.Parameters.get('USER_ACTION_KEY'),
                user_id     : this.state.userId,
                new_pass    : this.state.passValue
            }, (transp) => {
                if(transp.responseText === 'PASS_ERROR'){
                    global.alert(mess[240]);
                }else{
                    this.setState({valueSubmitted: true});
                }
            });
        },

        componentDidMount: function(){
            Promise.resolve(require('pydio').requireLib('form', true)).then(()=>{
                this.setState({formLoaded: true});
            });
        },

        onPassChange: function(newValue, oldValue){
            this.setState({passValue: newValue});
        },

        onUserIdChange: function(event, newValue){
            this.setState({userId: newValue});
        },

        render: function(){
            const mess = this.props.pydio.MessageHash;
            const {valueSubmitted, formLoaded, passValue, userId} = this.state;
            if(!valueSubmitted && formLoaded){

                return (
                    <div>
                        <div className="dialogLegend">{mess['gui.user.8']}</div>
                        <MaterialUI.TextField
                            className="blurDialogTextField"
                            value={userId}
                            floatingLabelText={mess['gui.user.4']}
                            onChange={this.onUserIdChange.bind(this)}
                        />
                        <PydioForm.ValidPassword
                            className="blurDialogTextField"
                            onChange={this.onPassChange.bind(this)}
                            attributes={{name:'password',label:mess[198]}}
                            value={passValue}
                        />
                    </div>

                );

            }else if(valueSubmitted){

                return (
                    <div>{mess['gui.user.6']}</div>
                );

            }else{
                return <PydioReactUI.Loader/>
            }

        }


    });

    global.AuthfrontCoreActions = {
        Callbacks: Callbacks,
        LoginPasswordDialog: LoginPasswordDialog,
        ResetPasswordRequire: ResetPasswordRequire,
        ResetPasswordDialog: ResetPasswordDialog,
        WebFTPDialog: WebFTPDialog,
        MultiAuthModifier: MultiAuthModifier
    };

})(window)