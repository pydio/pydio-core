(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static duoPrompt() {
            
            modal.showDialogForm('', 'duosecurity_box', function(oForm){

                oForm.down("#duosecurity_box").insert('<iframe id="duo_iframe" width="420" height="330" frameborder="0"></iframe>');
                oForm.insert('<form method="POST" id="duo_form"></form>');
                modal.refreshDialogPosition();
                new PeriodicalExecuter(function(pe){
                    var sigResponse = oForm.down('input[name="sig_response"]');
                    if(! sigResponse ) return;
                    pe.stop();
                    var conn = new Connexion();
                    conn.setParameters({
                        sig_response: sigResponse.getValue(),
                        get_action  : 'duo_post_verification_code'
                    });
                    conn.onComplete = function(){
                        window.setTimeout(function(){
                            pydio.loadXmlRegistry();
                        }, 400);
                        hideLightBox();
                    };
                    conn.sendAsync();
                }, 1);
                Duo.init({
                    host:pydio.getPluginConfigs('authfront.duosecurity').get('DUO_AUTH_HOST'),
                    sig_request:pydio.getPluginConfigs('authfront.duosecurity').get('DUO_AUTH_LAST_SIGNATURE'),
                    post_action:''
                });
                Duo.ready();

            }, null, false, true);
            
        }

    }

    global.DuoSecurityActions = {
        Callbacks: Callbacks
    };

})(window)