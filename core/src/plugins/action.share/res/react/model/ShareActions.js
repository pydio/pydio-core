(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static share(){
            if(pydio.UI.modalSupportsComponents){
                pydio.UI.openComponentInModal('ShareDialog', 'MainPanel', {selection:pydio.getUserSelection()});
            }else{
                if(!pydio.getController().shareDialogLoader) {
                    pydio.getController().shareDialogLoader = new AjxpReactDialogLoader('ShareDialog', 'MainPanel', {
                        selection:pydio.getUserSelection()
                    });
                }
                pydio.getController().shareDialogLoader.openDialog('react_share_form', true);
            }
        }
        
        static editShare(){
            if(!pydio.getController().shareDialogLoader) {
                pydio.getController().shareDialogLoader = new AjxpReactDialogLoader('ShareDialog', 'MainPanel', {
                    selection:pydio.getUserSelection()
                });
            }
            pydio.getController().shareDialogLoader.openDialog('react_share_form', true);
        }
        
        static loadList(){
            if(window.actionManager){
                window.actionManager.getDataModel().requireContextChange(window.actionManager.getDataModel().getRootNode(), true);
            }
        }
        
        static clearExpired(){
            var conn = new Connexion();
            conn.addParameter("get_action", "sharelist-clearExpired");
            var dm = window.actionManager.getDataModel();
            conn.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
                if(window.actionManager){
                    dm.requireContextChange(dm.getRootNode(), true);
                }
            };
            conn.sendAsync();
        }
        
        static editFromList(){
            var dataModel;
            if(window.actionArguments && window.actionArguments.length){
                dataModel = window.actionArguments[0];
            }else if(window.actionManager){
                dataModel = window.actionManager.getDataModel();
            }
            var dialog = new AjxpReactDialogLoader('ShareDialog', 'MainPanel', {selection:dataModel, readonly:true});
            dialog.openDialog('react_share_form', true);
        }

    }

    global.ShareActions = {
        Callbacks:Callbacks
    };

})(window)