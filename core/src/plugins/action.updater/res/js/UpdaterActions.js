(function(global){

    let pydio = global.pydio;

    class Listeners {

        static initListener(){

            var conn = new Connexion();
            conn.addParameter("get_action", "get_upgrade_path");
            conn.onComplete = function(transport){
                var response = transport.responseJSON;
                if(response && response.packages.length){
                    /*
                    if(!$('perform_upgrade_button').down('span.badge')){
                        $('perform_upgrade_button').insert('<span class="badge">'+response.packages.length+'</span>');
                    }
                    */
                    console.log('SHOULD UPDATE UPDATE BUTTON WITH AN ALERT BADGE');
                }
            };
            window.setTimeout(function(){
                conn.sendAsync();
            }, 5000);

        }

    }

    global.UpdaterActions = {
        Listeners:Listeners
    };

})(window)