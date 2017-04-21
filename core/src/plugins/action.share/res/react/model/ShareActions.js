(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static share(){
            pydio.UI.openComponentInModal('ShareDialog', 'MainPanel', {pydio:pydio, selection:pydio.getUserSelection()});
        }
        
        static editShare(){
            pydio.UI.openComponentInModal('ShareDialog', 'MainPanel', {pydio:pydio, selection:pydio.getUserSelection()});
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
            pydio.UI.openComponentInModal('ShareDialog', 'MainPanel', {pydio:pydio, readonly:true, selection:dataModel});
        }

        static openUserShareView(){

            pydio.UI.openComponentInModal('UserShares', 'ShareViewModal', {
                pydio:pydio,
                currentUser:true,
                filters:{
                    parent_repository_id:"250",
                    share_type:"share_center.238"
                }
            });

        }

    }

    class Listeners{

        static hookAfterDelete(){

            if(Listeners.INSTANCE) return;
            // Modify the Delete window
            // Uses only pure-JS
            pydio.observe("afterApply-delete", function(){
                try{
                    var u = pydio.getContextHolder().getUniqueNode();
                    if(u.getMetadata().get("ajxp_shared")){
                        var f = document.querySelectorAll("#generic_dialog_box #delete_message")[0];
                        var alert = f.querySelectorAll("#share_delete_alert");
                        if(!alert.length){
                            var message;
                            if(u.isLeaf()){
                                message = global.MessageHash["share_center.158"];
                            }else{
                                message = global.MessageHash["share_center.157"];
                            }
                            f.innerHTML += "<div id='share_delete_alert' style='padding-top: 10px;color: rgb(192, 0, 0);'><span style='float: left;display: block;height: 60px;margin: 4px 7px 4px 0;font-size: 2.4em;' class='icon-warning-sign'></span>"+message+"</div>";
                        }
                    }
                }catch(e){
                    if(console) console.log(e);
                }
            });

            Listeners.INSTANCE = true;
        }

    }

    global.ShareActions = {
        Callbacks:Callbacks,
        Listeners: Listeners
    };

})(window)