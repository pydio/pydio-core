(function(global){

    let pydio = global.pydio;
    let MessageHash = global.pydio.MessageHash;

    class Callbacks{

        static computeQuota(manager, args){

            FuncUtils.bufferCallback("ajxp_meta_quota_loader", 5, function(){
                
                if(global.ajxp_meta_quota_loading) return;
                var c = new Connexion();
                c.setParameters({get_action:'monitor_quota'});
                c.discrete = true;
                c.onComplete = function(transport){
                    global.ajxp_meta_quota_loading = false;
                    var action = pydio.getController().getActionByName("monitor_quota");
                    if(!action) return;
                    var data = transport.responseJSON;
                    pydio.meta_quota_text = PathUtils.roundFileSize(data.USAGE, MessageHash["byte_unit_symbol"]) + "/" + PathUtils.roundFileSize(data.TOTAL, MessageHash["byte_unit_symbol"]);
                    action.options.text = pydio.meta_quota_text;
                    if($('ajxp_quota_panel_content')){
                        $('ajxp_quota_panel_content').update(pydio.meta_quota_text);
                    }
                    action.refreshInstances();
                };
                global.ajxp_meta_quota_loading = true;
                c.sendAsync();
            });

        }
        
        

    }

    class Listeners {

        static contextChange() {
            
            if(!pydio.meta_quota_pe_created){
                var configs = pydio.getPluginConfigs("mq");
                if(configs){
                    pydio.observe("server_message", function(event){
                        var newValue = XPathSelectSingleNode(event, "/tree/metaquota");
                        if(newValue){
                            var action = pydio.getController().getActionByName("monitor_quota");
                            if(!action) return;
                            pydio.meta_quota_text = PathUtils.roundFileSize(newValue.getAttribute("usage"), MessageHash["byte_unit_symbol"]) + "/" + PathUtils.roundFileSize(newValue.getAttribute("total"), MessageHash["byte_unit_symbol"]);
                            action.options.text = pydio.meta_quota_text;
                            if($('ajxp_quota_panel_content')){
                                $('ajxp_quota_panel_content').update(pydio.meta_quota_text);
                            }
                            action.refreshInstances();
                        }
                    });
                    pydio.getController().fireAction('monitor_quota');
                }else{
                    new PeriodicalExecuter(function(pe){
                        var action = pydio.getController().getActionByName("monitor_quota");
                        if(!action) {
                            pe.stop();
                            pydio.meta_quota_pe_created = false;
                            return;
                        }
                        pydio.getController().fireAction('monitor_quota');
                    }, 20);
                }
                pydio.meta_quota_pe_created = true;
            }


        }

    }

    global.QuotaActions = {
        Callbacks: Callbacks,
        Listeners:Listeners
    };

})(window)