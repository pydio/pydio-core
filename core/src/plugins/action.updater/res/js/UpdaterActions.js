(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static performUpgrade(){
            
            modal.showDialogForm('', 'upgrade_form', function(oForm){
                
                var conn = new Connexion();
                $(oForm).down('div[id="upgrade_checking"]').show();
                $(oForm).down('div[id="upgrade_checking"]').update('Checking for available upgrades');
                fitHeightToBottom($(oForm), null, 40);
                conn.addParameter("get_action", "get_upgrade_path");
                conn.onComplete = function(transport){
                    var response = transport.responseJSON;
                    if(response && response.packages.length){
                        for(var i=0;i<response.packages.length;i++){
                            response.packages[i] = '<li>'+getBaseName(response.packages[i])+'</li>';
                        }
                        var pList = response.packages.join(" ");
                        $(oForm).down('div[id="upgrade_status"]').show();
                        $(oForm).down('div[id="upgrade_status"]').insert({top:'The following packages will be downloaded and installed : <ul style="margin-top:4px;">'+ pList+'</ul> <br>'});
                        var stepsContainer = $(oForm).down('iframe[id="upgrade_steps"]');
                        fitHeightToBottom(stepsContainer, null, 40);
                        if(response.latest_note){
                            var conn = new Connexion();
                            var url = conn._baseUrl + "&get_action=display_upgrade_note&url=" + encodeURIComponent(response.latest_note);
                            stepsContainer.src = url;
                        }
                        var startButton = $(oForm).down('div[id="start_upgrade_button"]');
                        startButton.observe("click", function(){
                            if(window.confirm('Are you sure you want to perform the upgrade?')){
                                var conn = new Connexion();
                                stepsContainer.src = conn._baseUrl + "&get_action=perform_upgrade";
                            }
                        });
                        $(oForm).down('div[id="upgrade_checking"]').hide();
                        modal.refreshDialogPosition();
                    }else{
                        $(oForm).down('div[id="upgrade_checking"]').update('No necessary upgrade detected');
                    }
                };
                conn.sendAsync();
                
            }, function(oForm){

                var startButton = $(oForm).down('div[id="start_upgrade_button"]');
                startButton.stopObserving("click");
                $(oForm).down('div[id="upgrade_status"]').hide();
                
            });
            
        }
        
        

    }

    class Listeners {

        static initListener(){

            var conn = new Connexion();
            conn.addParameter("get_action", "get_upgrade_path");
            conn.onComplete = function(transport){
                var response = transport.responseJSON;
                if(response && response.packages.length){
                    if(!$('perform_upgrade_button').down('span.badge')){
                        $('perform_upgrade_button').insert('<span class="badge">'+response.packages.length+'</span>');
                    }
                }
            };
            window.setTimeout(function(){
                conn.sendAsync();
            }, 5000);

        }

    }

    global.UpdaterActions = {
        Callbacks: Callbacks,
        Listeners:Listeners
    };

})(window)