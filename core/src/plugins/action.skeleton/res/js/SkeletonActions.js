(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static alertButton() {
            var confs = pydio.getPluginConfigs("ajxp_plugin[@name='skeleton']");
            var target = confs.get('CUSTOM_BUTTON_TARGET');
            if(window.confirm(MessageHash['skeleton.3'].replace('%s', target))){
                window.open(target, "my_popup");
            }
        }

        static frameButton(){
            var dialogLoadFunction = function(){
                var conn = new Connexion();
                conn.addParameter("get_action", "my_skeleton_button_frame");
                conn.onComplete = function(transport){
                    $('loaded_content').update(transport.responseText);
                }
                conn.sendAsync();
            };
            modal.showDialogForm("My Link", "my_skeleton_form", dialogLoadFunction);
        }

    }

    global.SkeletonActions = {
        Callbacks: Callbacks
    };

})(window)