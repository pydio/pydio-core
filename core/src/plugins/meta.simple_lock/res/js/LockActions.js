(function(global){

    let pydio = global.pydio;
    let MessageHash = global.pydio.MessageHash;

    class Callbacks{

        static toggleLock(manager, args){

            var conn = new Connexion();
            conn.addParameter("get_action", "sl_lock");
            var n = pydio.getUserSelection().getUniqueNode();
            conn.addParameter("file", n.getPath());
            if(n.getMetadata().get("sl_locked")){
                conn.addParameter("unlock", "true");
            }
            conn.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            };
            conn.sendAsync();

        }

    }

    class Listeners {

        static selectionChange() {

            var action = pydio.getController().getActionByName("sl_lock");
            var n = pydio.getUserSelection().getUniqueNode();
            if(action && n){
                action.selectionContext.allowedMimes = [];
                if(n.getMetadata().get("sl_locked")){
                    action.setIconClassName('icon-unlock');
                    action.setLabel('meta.simple_lock.3');
                    if(!n.getMetadata().get("sl_mylock")){
                        action.selectionContext.allowedMimes = ["fake_extension_that_never_exists"];
                    }
                }else{
                    action.setIconClassName('icon-lock');
                    action.setLabel('meta.simple_lock.1');
                }
            }

        }

    }

    global.LockActions = {
        Callbacks: Callbacks,
        Listeners:Listeners
    };

})(window)