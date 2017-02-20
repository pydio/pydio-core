(function(global){

    let pydio = global.pydio;

    let LoginPasswordDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogIsModal: true
            };
        },

        submit: function(){
            PydioApi.getClient().request({
                get_action  : 'login',
                userid      : this.refs.login.getValue(),
                password    : this.refs.password.getValue(),
                login_seed  : -1
            }, function(){
                this.dismiss();
                pydio.Registry.load();
            }.bind(this));
        },

        render: function(){
            return (

                <div>
                    <MaterialUI.TextField floatingLabelText="Login" ref="login"/>
                    <MaterialUI.TextField type="password" floatingLabelText="Password" ref="password"/>
                </div>

            );
        }

    });

    class Callbacks{

        static sessionLogout(){
            global.clearRememberData();
            PydioApi.getClient().request({get_action:'logout'});
        }

        static loginPassword() {

            pydio.UI.openComponentInModal('AuthfrontCoreActions', 'LoginPasswordDialog');

        }

        static multiAuth(authSources, masterAuthSource, userIdSeparator){

            modal.showDialogForm('Log In', ($('login_form')?'login_form':'login_form_dynamic'),
                function(oForm){
                    $("generic_dialog_box").setStyle({
                        top:$("progressBox").getStyle('top'),
                        left:$("progressBox").getStyle('left')
                    });
                    if(!Modernizr.input.placeholder) oForm.addClassName('no_placeholder');
                    $("generic_dialog_box").down(".titleString").hide();
                    $("generic_dialog_box").down("#modalCloseBtn").hide();
                    $("generic_dialog_box").down(".dialogTitle").setAttribute("style", $("progressBox").down(".dialogTitle").getAttribute("style"));
                    if(!$("generic_dialog_box").down("#progressCustomMessage")){
                        if($("progressBox").down("#progressCustomMessage")) $("generic_dialog_box").down(".dialogContent").insert({top:$("progressBox").down("#progressCustomMessage").cloneNode(true)});
                    }
                    oForm.setStyle({display:'block'});
                    oForm.up(".dialogContent").setStyle({backgroundImage:'none', borderWidth:0});

                    if(!$('auth_source')){
                        var auth_chooser = '<div class="SF_element"> \
                                        <div class="SF_label"><ajxp:message ajxp_message_id="396">'+MessageHash[396]+'</ajxp:message></div> \
                                        <div class="SF_input"><select id="auth_source" name="auth_source" style="width: 210px; height:28px; padding:3px 0px; font-size:14px;" class="dialogFocus"></select></div> \
                                    </div>';
                        oForm.down('div.SF_element').insert({before:auth_chooser});
                        $H(authSources).each(function(pair){
                            $('auth_source').insert(new Element("option", {value:pair.key}).update(pair.value));
                        });
                    }
                    pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                    if(Prototype.Browser.IE && !oForm.down('input[type="text"]').key_enter_attached){
                        oForm.select('input').invoke("observe", "keydown", function(event){
                            if(event.keyCode == Event.KEY_RETURN){
                                var el = Event.findElement(event);
                                if(el.hasClassName('dialogButton')){
                                    el.click();
                                }else{
                                    el.form.down('input.dialogButton').click();
                                }
                            }
                        });
                        oForm.down('input[type="text"]').key_enter_attached = true;
                    }
                    var authConfs = pydio.getPluginConfigs("auth");
                    if(authConfs && authConfs.get("SECURE_LOGIN_FORM")){
                        try{
                            oForm.down('input[name="remember_me"]').up("div.SF_element").remove();
                            oForm.down('input[name="userid"]').setAttribute("autocomplete", "off");
                            oForm.down('input[name="password"]').setAttribute("autocomplete", "off");
                            oForm.setAttribute("autocomplete", "off");
                        }catch(e){}
                    }
                    if(authConfs && authConfs.get("ENABLE_FORGOT_PASSWORD") && !oForm.down('a.forgot-password-link')){
                        try{
                            oForm.down('input[name="password"]').up("div.SF_element").insert({after:'<div class="SF_element"><a href="#" class="forgot-password-link">AJXP_MESSAGE[479]</a></div>'});
                            oForm.down('a.forgot-password-link').observe("click", function(e){
                                Event.stop(e);
                                pydio.getController().fireAction(authConfs.get("FORGOT_PASSWORD_ACTION"));
                            });
                        }catch(e){ if(console) console.log(e); }
                    }
                    modal.refreshDialogPosition();
                },
                function(){
                    var oForm = modal.getForm();
                    var connexion = new Connexion();
                    connexion.addParameter('get_action', 'login');
                    var selectedSource = oForm.auth_source.value;
                    if(selectedSource == masterAuthSource){
                        connexion.addParameter('userid', oForm.userid.value);
                    }else{
                        connexion.addParameter('userid', selectedSource+userIdSeparator+oForm.userid.value);
                    }
                    connexion.addParameter('login_seed', oForm.login_seed.value);
                    connexion.addParameter('auth_source', selectedSource);
                    connexion.addParameter('remember_me', (oForm.remember_me && oForm.remember_me.checked?"true":"false"));
                    if(oForm.login_seed.value != '-1'){
                        connexion.addParameter('password', HasherUtils.hex_md5(HasherUtils.hex_md5(oForm.password.value)+oForm.login_seed.value));
                    }else{
                        connexion.addParameter('password', oForm.password.value);
                    }
                    if(oForm.captcha_code){
                        connexion.addParameter('captcha_code', oForm.captcha_code.value);
                    }
                    connexion.onComplete = function(transport){
                        var success = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                        if(XPathGetSingleNodeText(transport.responseXML.documentElement, "logging_result/@value") == "-4"){
                            pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                        }
                        if(success){
                            $("generic_dialog_box").down(".dialogTitle").writeAttribute("style", "");
                            oForm.up('.dialogContent').writeAttribute("style", "");
                            $("generic_dialog_box").select("#progressCustomMessage").invoke("remove");
                        }

                    };
                    document.observeOnce("ajaxplorer:user_logged", function(){
                        if($('logging_string') && $('logging_string').down('i')){
                            var ht = $('logging_string').down('i').innerHTML;
                            var exp = ht.split(userIdSeparator);
                            if(exp.length > 1){
                                $('logging_string').down('i').update(exp[1]);
                            }
                        }
                    });
                    connexion.sendAsync();
                    oForm.userid.value = '';
                    oForm.password.value = '';
                    return false;
                });

        }

        static webFTP(){

            modal.setCloseValidation(function(){
                return (!!pydio && !!pydio.user)
            });
            modal.showDialogForm('Log In', 'ftp_login_form',
                function(oForm){
                    pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                },
                function(){
                    var oForm = modal.getForm();

                    var connexion = new Connexion();
                    connexion.addParameter("get_action", "set_ftp_data");
                    oForm.getElements().each(function(el){
                        if(el.name != "userid" && el.name!="password" && el.name != "get_action" && el.name!="login_seed"){
                            connexion.addParameter(el.name, el.getValue());
                        }
                    });
                    connexion.onComplete = function(transport){
                        PydioApi.getClient().submitForm(oForm, true, function(transport){
                            var success = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                            if(success){
                                modal.setCloseValidation(null);
                                hideLightBox(true);
                            }else if(transport.responseXML && XPathGetSingleNodeText(transport.responseXML.documentElement, "logging_result/@value") == "-4"){
                                pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                            }
                        });
                        oForm.reset();
                    };
                    connexion.sendAsync();
                    return false;
                }
            );

        }

    }


    class LegacyCallbacks{

        static sessionLogout(){
            global.clearRememberData();
            PydioApi.getClient().request({get_action:'logout'});
        }

        static loginPassword() {

            modal.setCloseValidation(function(){
                return (!!pydio && !!pydio.user && pydio.user.id == "guest")
            });
            if(pydio.user && pydio.user.id == 'guest') $$('html')[0].addClassName('ajxp_guest_enabled');
            modal.showDialogForm('Log In', ($('login_form')?'login_form':'login_form_dynamic'),
                function(oForm){
                    $("generic_dialog_box").setStyle({
                        top:$("progressBox").getStyle('top'),
                        left:$("progressBox").getStyle('left')
                    });
                    var authConfs = pydio.getPluginConfigs("auth");
                    if(!authConfs) authConfs = $H();
                    if(!Modernizr.input.placeholder) oForm.addClassName('no_placeholder');
                    if(global.ajxpBootstrap.parameters.get('PRESET_LOGIN')){
                        oForm.addClassName('ajxp_preset_login');
                        oForm.down('input[name="userid"]').setValue(global.ajxpBootstrap.parameters.get('PRESET_LOGIN'));
                    }
                    if(global.ajxpBootstrap.parameters.get('PRESET_PASSWORD')){
                        oForm.addClassName('ajxp_preset_password');
                        oForm.down('input[name="password"]').setValue(global.ajxpBootstrap.parameters.get('PRESET_PASSWORD'));
                    }
                    if(global.ajxpBootstrap.parameters.get('PASSWORD_AUTH_ONLY')){
                        oForm.addClassName('ajxp_password_auth');
                        authConfs.set('SECURE_LOGIN_FORM', true);
                        authConfs.set('ENABLE_FORGOT_PASSWORD', false);
                        oForm.down('.dialogLegend').update(pydio.MessageHash[552]);
                    }else{
                        oForm.down('.dialogLegend').update(pydio.MessageHash[180]);
                    }
                    if($("generic_dialog_box").down(".titleString")){
                        $("generic_dialog_box").down(".titleString").hide();
                        $("generic_dialog_box").down("#modalCloseBtn").hide();
                    }
                    $("generic_dialog_box").down(".dialogTitle").setAttribute("style", $("progressBox").down(".dialogTitle").getAttribute("style"));
                    if(!$("generic_dialog_box").down("#progressCustomMessage")){
                        if($("progressBox").down("#progressCustomMessage")) $("generic_dialog_box").down(".dialogContent").insert({top:$("progressBox").down("#progressCustomMessage").cloneNode(true)});
                    }
                    oForm.setStyle({display:'block'});
                    oForm.up(".dialogContent").setStyle({backgroundImage:'none', borderWidth:0});

                    pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                    if(Prototype.Browser.IE && !Modernizr.borderradius && !oForm.down('input[type="text"]').key_enter_attached){
                        oForm.select('input').invoke("observe", "keydown", function(event){
                            if(event.keyCode == Event.KEY_RETURN){
                                var el = Event.findElement(event);
                                if(el.hasClassName('dialogButton')){
                                    el.click();
                                }else{
                                    el.form.down('input.dialogButton').click();
                                }
                            }
                        });
                        oForm.down('input[type="text"]').key_enter_attached = true;
                    }

                    if(authConfs && authConfs.get("SECURE_LOGIN_FORM")){
                        try{
                            oForm.down('input[name="remember_me"]').up("div.SF_element").remove();
                            oForm.down('input[name="userid"]').setAttribute("autocomplete", "off");
                            oForm.down('input[name="password"]').setAttribute("autocomplete", "off");
                            oForm.setAttribute("autocomplete", "off");
                        }catch(e){}
                    }
                    if(authConfs && authConfs.get("ENABLE_FORGOT_PASSWORD") && !oForm.down('a.forgot-password-link')){
                        try{
                            var after = oForm.down('input[name="remember_me"]') ? oForm.down('input[name="remember_me"]').up("div.SF_element") : oForm.down('input[name="password"]').up("div.SF_element");
                            after.insert({after:'<div class="SF_element" id="forgot_element"><span class="icon-question-sign"></span><a href="#" class="forgot-password-link" ajxp_message_id="479">AJXP_MESSAGE[479]</a></div>'});
                            oForm.down('a.forgot-password-link').observe("click", function(e){
                                Event.stop(e);
                                pydio.getController().fireAction(authConfs.get("FORGOT_PASSWORD_ACTION"));
                            });
                        }catch(e){ if(console) console.log(e); }
                    }
                    modal.refreshDialogPosition();
                },
                function(){
                    var oForm = modal.getForm();
                    var connexion = new Connexion();
                    connexion.addParameter('get_action', 'login');
                    connexion.addParameter('userid', global.ajxpBootstrap.parameters.get('PRESET_LOGIN')?global.ajxpBootstrap.parameters.get('PRESET_LOGIN'):oForm.userid.value);
                    connexion.addParameter('login_seed', oForm.login_seed.value);
                    connexion.addParameter('remember_me', (oForm.remember_me && oForm.remember_me.checked?"true":"false"));
                    if(oForm.login_seed.value != '-1'){
                        connexion.addParameter('password', HasherUtils.hex_md5(HasherUtils.hex_md5(oForm.password.value)+oForm.login_seed.value));
                    }else{
                        connexion.addParameter('password', oForm.password.value);
                    }
                    if(oForm.captcha_code){
                        connexion.addParameter('captcha_code', oForm.captcha_code.value);
                    }
                    oForm.select('input[data-ajxpLoginAdditionalParameter]').each(function(i){
                        connexion.addParameter(i.name, i.getValue());
                    });
                    if(oForm.down(".ajxp_login_error")){
                        oForm.down(".ajxp_login_error").remove();
                    }
                    connexion.onComplete = function(transport){
                        oForm.userid.value = '';
                        oForm.password.value = '';
                        var success = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                        if(success){
                            $("generic_dialog_box").down(".dialogTitle").writeAttribute("style", "");
                            oForm.up('.dialogContent').writeAttribute("style", "");
                            $("generic_dialog_box").select("#progressCustomMessage").invoke("remove");
                            modal.setCloseValidation(null);
                            hideLightBox(true);
                        }else{
                            var errorId = PydioApi.getClient().LAST_ERROR_ID;
                            if(errorId == '285' && global.ajxpBootstrap.parameters.get('PASSWORD_AUTH_ONLY')){
                                errorId = '553';
                            }
                            if(transport.responseXML && XPathGetSingleNodeText(transport.responseXML.documentElement, "logging_result/@value") == "-4"){
                                pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                            }
                            if(errorId && oForm.visible() && oForm.down("div.dialogLegend")){
                                oForm.down("div.dialogLegend").insert({bottom:'<div class="ajxp_login_error" style="background-color: #D33131;display: block;font-size: 9px;color: white;border-radius: 3px;padding: 2px 6px;">'+MessageHash[errorId]+'</div>'});
                                Effect.ErrorShake(oForm.down('.ajxp_login_error'));
                            }else{
                                alert(MessageHash[errorId]);
                            }
                        }

                    };
                    connexion.sendAsync();
                    return false;
                }, function(){}, true);
            

        }

        static multiAuth(authSources, masterAuthSource, userIdSeparator){

            modal.showDialogForm('Log In', ($('login_form')?'login_form':'login_form_dynamic'),
                function(oForm){
                    $("generic_dialog_box").setStyle({
                        top:$("progressBox").getStyle('top'),
                        left:$("progressBox").getStyle('left')
                    });
                    if(!Modernizr.input.placeholder) oForm.addClassName('no_placeholder');
                    $("generic_dialog_box").down(".titleString").hide();
                    $("generic_dialog_box").down("#modalCloseBtn").hide();
                    $("generic_dialog_box").down(".dialogTitle").setAttribute("style", $("progressBox").down(".dialogTitle").getAttribute("style"));
                    if(!$("generic_dialog_box").down("#progressCustomMessage")){
                        if($("progressBox").down("#progressCustomMessage")) $("generic_dialog_box").down(".dialogContent").insert({top:$("progressBox").down("#progressCustomMessage").cloneNode(true)});
                    }
                    oForm.setStyle({display:'block'});
                    oForm.up(".dialogContent").setStyle({backgroundImage:'none', borderWidth:0});

                    if(!$('auth_source')){
                        var auth_chooser = '<div class="SF_element"> \
                                        <div class="SF_label"><ajxp:message ajxp_message_id="396">'+MessageHash[396]+'</ajxp:message></div> \
                                        <div class="SF_input"><select id="auth_source" name="auth_source" style="width: 210px; height:28px; padding:3px 0px; font-size:14px;" class="dialogFocus"></select></div> \
                                    </div>';
                        oForm.down('div.SF_element').insert({before:auth_chooser});
                        $H(authSources).each(function(pair){
                            $('auth_source').insert(new Element("option", {value:pair.key}).update(pair.value));
                        });
                    }
                    pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                    if(Prototype.Browser.IE && !oForm.down('input[type="text"]').key_enter_attached){
                        oForm.select('input').invoke("observe", "keydown", function(event){
                            if(event.keyCode == Event.KEY_RETURN){
                                var el = Event.findElement(event);
                                if(el.hasClassName('dialogButton')){
                                    el.click();
                                }else{
                                    el.form.down('input.dialogButton').click();
                                }
                            }
                        });
                        oForm.down('input[type="text"]').key_enter_attached = true;
                    }
                    var authConfs = pydio.getPluginConfigs("auth");
                    if(authConfs && authConfs.get("SECURE_LOGIN_FORM")){
                        try{
                            oForm.down('input[name="remember_me"]').up("div.SF_element").remove();
                            oForm.down('input[name="userid"]').setAttribute("autocomplete", "off");
                            oForm.down('input[name="password"]').setAttribute("autocomplete", "off");
                            oForm.setAttribute("autocomplete", "off");
                        }catch(e){}
                    }
                    if(authConfs && authConfs.get("ENABLE_FORGOT_PASSWORD") && !oForm.down('a.forgot-password-link')){
                        try{
                            oForm.down('input[name="password"]').up("div.SF_element").insert({after:'<div class="SF_element"><a href="#" class="forgot-password-link">AJXP_MESSAGE[479]</a></div>'});
                            oForm.down('a.forgot-password-link').observe("click", function(e){
                                Event.stop(e);
                                pydio.getController().fireAction(authConfs.get("FORGOT_PASSWORD_ACTION"));
                            });
                        }catch(e){ if(console) console.log(e); }
                    }
                    modal.refreshDialogPosition();
                },
                function(){
                    var oForm = modal.getForm();
                    var connexion = new Connexion();
                    connexion.addParameter('get_action', 'login');
                    var selectedSource = oForm.auth_source.value;
                    if(selectedSource == masterAuthSource){
                        connexion.addParameter('userid', oForm.userid.value);
                    }else{
                        connexion.addParameter('userid', selectedSource+userIdSeparator+oForm.userid.value);
                    }
                    connexion.addParameter('login_seed', oForm.login_seed.value);
                    connexion.addParameter('auth_source', selectedSource);
                    connexion.addParameter('remember_me', (oForm.remember_me && oForm.remember_me.checked?"true":"false"));
                    if(oForm.login_seed.value != '-1'){
                        connexion.addParameter('password', HasherUtils.hex_md5(HasherUtils.hex_md5(oForm.password.value)+oForm.login_seed.value));
                    }else{
                        connexion.addParameter('password', oForm.password.value);
                    }
                    if(oForm.captcha_code){
                        connexion.addParameter('captcha_code', oForm.captcha_code.value);
                    }
                    connexion.onComplete = function(transport){
                        var success = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                        if(XPathGetSingleNodeText(transport.responseXML.documentElement, "logging_result/@value") == "-4"){
                            pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                        }
                        if(success){
                            $("generic_dialog_box").down(".dialogTitle").writeAttribute("style", "");
                            oForm.up('.dialogContent').writeAttribute("style", "");
                            $("generic_dialog_box").select("#progressCustomMessage").invoke("remove");
                        }

                    };
                    document.observeOnce("ajaxplorer:user_logged", function(){
                        if($('logging_string') && $('logging_string').down('i')){
                            var ht = $('logging_string').down('i').innerHTML;
                            var exp = ht.split(userIdSeparator);
                            if(exp.length > 1){
                                $('logging_string').down('i').update(exp[1]);
                            }
                        }
                    });
                    connexion.sendAsync();
                    oForm.userid.value = '';
                    oForm.password.value = '';
                    return false;
                });

        }

        static webFTP(){

            modal.setCloseValidation(function(){
                return (!!pydio && !!pydio.user)
            });
            modal.showDialogForm('Log In', 'ftp_login_form',
                function(oForm){
                    pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                },
                function(){
                    var oForm = modal.getForm();

                    var connexion = new Connexion();
                    connexion.addParameter("get_action", "set_ftp_data");
                    oForm.getElements().each(function(el){
                        if(el.name != "userid" && el.name!="password" && el.name != "get_action" && el.name!="login_seed"){
                            connexion.addParameter(el.name, el.getValue());
                        }
                    });
                    connexion.onComplete = function(transport){
                        PydioApi.getClient().submitForm(oForm, true, function(transport){
                            var success = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                            if(success){
                                modal.setCloseValidation(null);
                                hideLightBox(true);
                            }else if(transport.responseXML && XPathGetSingleNodeText(transport.responseXML.documentElement, "logging_result/@value") == "-4"){
                                pydio.UI.loadSeedOrCaptcha(oForm.down('#login_seed'), oForm.down('img#captcha_image'), oForm.down('div.dialogLegend'), 'before');
                            }
                        });
                        oForm.reset();
                    };
                    connexion.sendAsync();
                    return false;
                }
            );

        }

    }


    global.AuthfrontCoreActions = {
        Callbacks: Callbacks,
        LoginPasswordDialog: LoginPasswordDialog
    };

})(window)