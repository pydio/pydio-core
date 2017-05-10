(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static duoPrompt() {
            pydio.UI.openComponentInModal('DuoSecurityActions', 'Dialog');
        }

    }

    const DuoSecDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: "Duo Security Configuration",
                dialogIsModal: true
            };
        },

        componentDidMount:function(){

            const {pydio, onDismiss} = this.props;
            const {XMLUtils, PydioApi} = global.PydioCore;
            const oForm = this.refs.form;

            Duo.init({
                host        : pydio.getPluginConfigs('authfront.duosecurity').get('DUO_AUTH_HOST'),
                sig_request : pydio.getPluginConfigs('authfront.duosecurity').get('DUO_AUTH_LAST_SIGNATURE'),
                post_action : '',
                submit_callback : function(oForm){
                    const value = oForm.elements['sig_response'].value;
                    PydioApi.getClient().request({
                        sig_response: value,
                        get_action  : 'duo_post_verification_code'
                    }, () => {
                        global.setTimeout(function(){
                            pydio.loadXmlRegistry();
                        }, 400);
                    });
                    onDismiss();
                }
            });
        },

        shouldComponentUpdate:function(){
            return false;
        },

        render: function(){
            return (
                <div>
                    <iframe id="duo_iframe" width={370} height={330} frameBorder={0}></iframe>
                </div>
            );
        }

    });

    global.DuoSecurityActions = {
        Callbacks: Callbacks,
        Dialog   : DuoSecDialog
    };

})(window);