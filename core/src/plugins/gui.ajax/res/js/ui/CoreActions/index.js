(function(global){

    let pydio = global.pydio;
    let MessageHash = global.pydio.MessageHash;

    let SplashDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogSize:'lg',
                dialogIsModal: false,
                dialogPadding: false,
                dialogScrollBody: true
            };
        },
        submit(){
            this.dismiss();
        },

        getInitialState: function(){
            return {aboutContent: null};
        },

        componentDidMount: function(){

            PydioApi.getClient().request({
                get_action:'display_doc',
                doc_file:'CREDITS'
            }, function(transport){
                this.setState({
                    aboutContent: transport.responseText
                });
            }.bind(this));

        },

        render: function(){
            if(!this.state.aboutContent){
                return <PydioReactUI.Loader style={{minHeight: 200}}/>;
            }else{
                let ct = () => {return {__html: this.state.aboutContent}};
                return <div style={{fontSize:13, padding: '0 10px'}} dangerouslySetInnerHTML={ct()}/>;
            }
        }

    });


    let PasswordDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
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
                <PydioReactUI.AsyncComponent
                    namespace="UserAccount"
                    componentName="PasswordForm"
                    pydio={this.props.pydio}
                    ref="passwordForm"
                    onValidStatusChange={this.passValidStatusChange}
                />
            );
        }

    });



    class Callbacks{

        static switchLanguage(){

            modal.showDialogForm('Switch', 'switch_language_form', function(oForm){
                
                if(pydio.user) var userLang = pydio.user.getPreference("lang");
                if(!userLang) userLang = window.pydioBootstrap.parameters.get("currentLanguage");
                var selector = $(oForm).select('select[id="language_selector"]')[0];
                pydio.listLanguagesWithCallback(function(key, label){
                    var option = new Element('option', {value:key,id:'lang_'+key});
                    option.update(label);
                    selector.insert(option);
                });
                selector.setValue(userLang);
                selector.observe("change", function(){
                    var value = selector.getValue();
                    if(pydio.user){
                        pydio.user.setPreference("lang", selector.getValue());
                        pydio.user.savePreference("lang");
                    }
                });
                $(oForm).up('.dialogContent').previous('.dialogTitle').select('span').invoke('remove');

            }, function(oForm){
                
                if(pydio.user){
                    var oUser = pydio.user;
                    if(oUser.getPreference('lang') != null
                        && oUser.getPreference('lang') != ""
                        && oUser.getPreference('lang') != pydio.currentLanguage)
                    {
                        pydio.loadI18NMessages(oUser.getPreference('lang'));
                        pydio.displayMessage('SUCCESS', MessageHash[241]);
                    }
                }else{
                    var selector = $(oForm).select('select[id="language_selector"]')[0];
                    var value = selector.getValue();
                    pydio.loadI18NMessages(value);
                    window.pydioBootstrap.parameters.set("currentLanguage", value);
                }
                
            }, function(oForm){
                pydio.Controller.fireAction("login");
            });

        }

        static userCreateRepository(){

            let onLoad = function(oForm){
                var conn = new Connexion();
                var okButton = oForm.down('.dialogButtons input');
                var backButton = new Element('img', {
                    src:ResourcesManager.resolveImageSource('actions/22/back_22.png'),
                    style:'float: left;margin-top: 7px;margin-left: 4px;cursor: pointer;',
                    title:'Back'
                }).observe('click', function(e){
                    Event.stop(e);
                    new Effect.Parallel([
                        new Effect.Morph('user_templates_selector', {style:'left:0px;'}),
                        new Effect.Morph('user_template_parameters', {style:'left:420px;'}),
                        new Effect.Morph('user_tpl_container', {style:'height:'+conn.ORIGINAL_HEIGHT+'px;'}),
                        new Effect.Opacity(okButton, {sync:true,from:1.0,to:0}),
                        new Effect.Opacity(backButton, {sync:true,from:1.0,to:0})
                    ], {
                        afterFinish:function(){
                            $('user_templates_selector').select('div').invoke('removeClassName', 'selected-focus');
                            modal.refreshDialogPosition();
                        }
                    });
                }).setStyle({opacity:0});
                okButton.setStyle({opacity:0});
                okButton.insert({before:backButton});
                conn.addParameter("get_action", "get_user_templates_definition");
                conn.parseTplParameters = function(tplDiv){
                    if(!conn.TPL_XML) return;
                    if(!conn.FORM_MANAGER) conn.FORM_MANAGER = new FormManager();
                    var tplId = tplDiv.getAttribute('data-templateId');
                    var tplLabel = tplDiv.getAttribute('data-templateLabel');
                    tplDiv.addClassName('selected-focus');
                    $('user_tpl_params_title').update('<img style="top:3px;left:4px;" src="'+conn._baseUrl+'&get_action=get_user_template_logo&template_id='+tplId+'&icon_format=small'+'" class="panelHeaderIcon"> '+MessageHash[421].replace('%s',tplLabel));
                    $('user_template_parameters').select('div.SF_element').invoke('remove');
                    $('user_tpl_params_parameters').update('');
                    var params = conn.FORM_MANAGER.parseParameters(conn.TPL_XML, '//template[@repository_id="'+tplId+'"]/param');
                    //params.push(new Hash({name:"DISPLAY", type:"string", label:"Label", mandatory:"true", description:"Label of your repository"}));
                    conn.FORM_MANAGER.createParametersInputs($('user_tpl_params_parameters'), params, true, null, false, true, false);
                    $('user_tpl_params_parameters').insert(new Element('input', {type:'hidden',name:'template_id',value:tplId}));
                    var elements = $('user_tpl_params_parameters').select("div.SF_element");
                    $('user_template_parameters').down('div.dialogLegend').insert({before:elements[elements.length-1]});
                    var targetHeight = parseInt($('user_template_parameters').getHeight());
                    if(!targetHeight) targetHeight = params.length * 44 + 80;
                    new Effect.Parallel([
                        new Effect.Morph('user_templates_selector', {style:'left:-420px;'}),
                        new Effect.Morph('user_template_parameters', {style:'left:0px;'}),
                        new Effect.Morph('user_tpl_container', {style:'height:'+targetHeight+'px;'}),
                        new Effect.Opacity(okButton, {sync:true,from:0,to:1.0}),
                        new Effect.Opacity(backButton, {sync:true,from:0,to:1.0})
                    ], {afterFinish : function(){modal.refreshDialogPosition();}});
                }
                conn.onComplete = function(transport){
                    conn.TPL_XML = transport.responseXML;
                    var templates = XMLUtils.XPathSelectNodes(conn.TPL_XML, "//template");
                    conn.ORIGINAL_HEIGHT = (templates.length*47 + 21);
                    $('user_tpl_container').setStyle({height:conn.ORIGINAL_HEIGHT+'px'});
                    $('user_templates_selector').update('<div class="panelHeader">'+MessageHash[420]+'</div>');
                    for(var i=0;i<templates.length;i++){
                        var label = templates[i].getAttribute('repository_label');
                        var tplId = templates[i].getAttribute('repository_id');
                        var labelDiv = new Element('div').update(label);
                        labelDiv.setStyle({
                            padding:'12px 6px 12px 35px',
                            fontSize:'1.3em',
                            cursor:'pointer',
                            borderBottom:'1px solid #EEE',
                            backgroundRepeat: 'no-repeat',
                            backgroundPosition: '5px 10px',
                            backgroundImage:'url('+conn._baseUrl+'&get_action=get_user_template_logo&template_id='+tplId+'&icon_format=big)'
                        });
                        var tplDiv = new Element('div', {"data-templateId":tplId,"data-templateLabel":label}).update(labelDiv);
                        tplDiv.observe('click', function(e){
                            conn.parseTplParameters(( e.target.getAttribute("data-templateId")?e.target:e.target.up('div') ));
                        });
                        labelDiv.observe('mouseover', function(e){e.target.up('div').addClassName('selected');}).observe('mouseout', function(e){e.target.up('div').removeClassName('selected');});
                        $('user_templates_selector').insert(tplDiv);
                    }
                };
                conn.sendAsync();

            };

            let onComplete = function(oForm){
                var formManager = new FormManager();
                var params = $H({get_action:'user_create_repository'});
                formManager.serializeParametersInputs($('user_template_parameters'), params, "DRIVER_OPTION_");
                $('user_tpl_params_parameters').select('input[type="hidden"]').each(function(el){
                    params.set(el.name,el.value);
                });
                params.set("DISPLAY", params.get("DRIVER_OPTION_DISPLAY"));
                params.unset("DRIVER_OPTION_DISPLAY");
                params.unset("DRIVER_OPTION_DISPLAY_ajxptype");
                var conn = new Connexion();
                conn.setParameters(params);
                conn.onComplete = function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                    hideLightBox();
                }
                conn.sendAsync();

            };

            modal.showDialogForm("Create", "user_create_repository_form", onLoad, onComplete);

        }

        static userCreateUser(){

            modal.showDialogForm('Update', 'user_create_user', function(oForm){

                var parameters = PydioUsers.Client.getCreateUserParameters().map(function(obj){
                    if(obj.type === 'valid-password') obj.type = 'password-create';
                    return $H(obj)
                });
                var f = new FormManager();
                f.createParametersInputs(oForm.down('#user_create_user'), parameters, true, $H(), false, true);
                modal.refreshDialogPosition();

            }, function(oForm){

                var params = $H();
                var f = new FormManager();
                f.serializeParametersInputs(oForm.down('#user_create_user'), params, 'NEW_');
                var conn = new Connexion();
                params.set("get_action", "user_create_user");
                conn.setParameters(params);
                conn.setMethod("POST");
                conn.onComplete = function(transport){
                    if($("address_book")){
                        $("address_book").ajxpPaneObject.reloadDataModel();
                    }
                };
                conn.sendAsync();

            });

        }

        static userUpdateUser(){

            modal.showDialogForm('Update', 'user_create_user', function(oForm){

                var user = window.actionManager.getDataModel().getUniqueNode();
                if(user && user.getAjxpMime() == "shared_user"){
                    var user_id = PathUtils.getBasename(user.getPath());
                    var conn = new Connexion();
                    conn.setParameters({
                        get_action:'user_update_user',
                        user_id:user_id
                    });
                    conn.onComplete = function(transport){
                        var values = $H(transport.responseJSON);
                        values.set("existing_user_id", user_id);

                        var f = new FormManager();
                        var parameters = [];
                        PydioUsers.Client.getCreateUserParameters().map(function(obj){
                            if(obj.type === 'valid-password') {
                                obj.type = 'password-create';
                            }
                            if(obj.name === 'new_user_id'){
                                obj.name = 'existing_user_id';
                                obj.type = 'hidden';
                            }
                            if(obj.name === 'send_email'){
                                return;
                            }
                            parameters.push($H(obj));
                        });

                        f.createParametersInputs(oForm.down('#user_create_user'), parameters, true, values, false, true);
                        modal.refreshDialogPosition();

                    };
                    conn.sendAsync();
                }

            }, function(oForm){

                var params = $H();
                var f = new FormManager();
                f.serializeParametersInputs(oForm.down('#user_create_user'), params, 'NEW_');
                var conn = new Connexion();
                params.set("get_action", "user_create_user");
                conn.setParameters(params);
                conn.setMethod("POST");
                conn.onComplete = function(transport){
                    if(transport.responseText == "SUCCESS"){
                        if($("address_book")){
                            $("address_book").ajxpPaneObject.reloadDataModel();
                        }
                        pydio.displayMessage("SUCCESS", MessageHash[521]);
                    }
                };
                conn.sendAsync();

            });

        }

        static userCreateTeam(){
            modal.showDialogForm('', 'team_edit_form', function(oForm){
                ResourcesManager.loadClassesAndApply(["TeamEditor"], function(){
                    TeamEditor.prototype.getInstance().open(oForm);
                });
            }, function(oForm){
                TeamEditor.prototype.getInstance().complete(oForm);
            }, function(oForm){
                TeamEditor.prototype.getInstance().close(oForm);
            });
        }

        static userUpdateTeam(manager){
            modal.showDialogForm('', 'team_edit_form', function(oForm){
                var contextHolder = manager.getDataModel();
                ResourcesManager.loadClassesAndApply(["TeamEditor"], function(){
                    TeamEditor.prototype.getInstance().open(oForm, contextHolder);
                });
            }, function(oForm){
                TeamEditor.prototype.getInstance().complete(oForm);
            }, function(oForm){
                TeamEditor.prototype.getInstance().close(oForm);
            });
        }

        static userDeleteTeam(manager){
            if(window.confirm(MessageHash["user_dash.52"])){
                var sel = manager.getDataModel().getUniqueNode();
                var conn = new Connexion();
                conn.setParameters($H({
                    get_action: "user_team_delete",
                    team_id: PathUtils.getBasename(sel.getPath())
                }));
                conn.onComplete = function(){
                    $("team_panel").ajxpPaneObject.reloadDataModel();
                };
                conn.sendAsync();
            }
        }

        static changePass(){

            pydio.UI.openComponentInModal('PydioCoreActions', 'PasswordDialog');

        }

        static launchIndexation(){
            var crtDir = pydio.getContextHolder().getContextNode().getPath();
            if(!pydio.getUserSelection().isEmpty()){
                crtDir = pydio.getUserSelection().getUniqueNode().getPath();
            }
            PydioApi.getClient().request({"get_action":"index", "file":crtDir}, function(transport){});
        }

        static toggleBookmark(){
            pydio.notify("add_bookmark");
        }
        
        static clearPluginsCache(){
            PydioApi.getClient().request({get_action:'clear_plugins_cache'});
        }

        static dismissUserAlert(manager, args){
            var selection = args[0];
            if(selection.getUniqueNode) selection = selection.getUniqueNode();
            if(selection && selection.getMetadata && selection.getMetadata().get("alert_id")){
                var elMeta = selection.getMetadata();
                var params = {
                    get_action:'dismiss_user_alert',
                    alert_id:elMeta.get('alert_id')
                };
                if(elMeta.get("event_occurence")){
                    params['occurrences'] = elMeta.get("event_occurence");
                }
                PydioApi.getClient().request(params, function(){
                    pydio.notify("server_message:tree/reload_user_feed");
                });
            }
        }

        static activateDesktopNotifications(){
            if(global.Notification){
                alert(pydio.MessageHash["notification_center.12"]);
                global.Notification.requestPermission(function(grant) {
                    ['default', 'granted', 'denied'].indexOf(grant) === true
                });
            }else{
                global.alert(pydio.MessageHash["notification_center.13"]);
            }
        }

    }

    class Navigation{

        static splash(){
            pydio.UI.openComponentInModal('PydioCoreActions', 'SplashDialog');
        }

        static up(){
            pydio.fireContextUp();
        }

        static refresh(){
            pydio.fireContextRefresh();
        }

        static externalSelection(){
            var userSelection = pydio.getUserSelection();
            if((userSelection.isUnique() && !userSelection.hasDir()))
            {
                var fileName = userSelection.getUniqueFileName();
                var selectorData = pydio.getController().selectorData;
                if(selectorData.get('type') == "ckeditor"){
                    var ckData = selectorData.get('data');
                    if (ckData['CKEditorFuncNum']) {
                        var imagePath = fileName;
                        if(ckData['relative_path']){
                            imagePath = ckData['relative_path'] + fileName;
                        }
                        global.opener.CKEDITOR.tools.callFunction(ckData['CKEditorFuncNum'], imagePath);
                        global.close();
                    }
                }
            }
        }

        static openGoPro(){
            global.open('https://pydio.com/en/go-pro?referrer=settings');
        }

        static switchToUserDashboard(){
            if(!pydio.repositoryId || pydio.repositoryId != "ajxp_user"){
                pydio.triggerRepositoryChange('ajxp_user');
            }
        }

        static switchToSettings(){
            if(!pydio.repositoryId || pydio.repositoryId != "ajxp_conf"){
                pydio.triggerRepositoryChange('ajxp_conf');
            }
        }

    }

    class Listeners{


    }

    let ns = global.PydioCoreActions || {};
    ns.Callbacks = Callbacks;
    ns.Listeners = Listeners;
    ns.Navigation = Navigation;
    ns.SplashDialog = SplashDialog;
    ns.PasswordDialog = PasswordDialog;
    global.PydioCoreActions = ns;

})(window);