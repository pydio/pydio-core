(function(global){

    class Callbacks{

        static validate(){

            var dialogLoadFunction = function(){
                var conn = new Connexion();
                conn.addParameter("get_action", "load_disclaimer");
                conn.onComplete = function(transport){
                    var resp = transport.responseText;
                    var state = resp.substring(0, resp.indexOf(":")) == "yes" ? true : false;
                    var text = resp.substring(resp.indexOf(":")+1);
                    modal.getForm().down('div#disclaimer_content').update(text);
                    modal.getForm().down('input#accept_disclaimer').checked = state;
                }
                conn.sendAsync();
            };
            var completeFunction = function(){
                var value = modal.getForm().down("input#accept_disclaimer").checked ? "true" : "false";
                var conn = new Connexion();
                conn.addParameter("get_action", "validate_disclaimer");
                conn.addParameter("validate", value);
                conn.onComplete = function(transport){
                    if(value == "true"){
                        window.setTimeout(function(){
                            ajaxplorer.loadXmlRegistry();
                        }, 400);
                    }
                    hideLightBox();
                }
                conn.sendAsync();
                return false;
            };
            modal.showDialogForm("Validation", "disclaimer_form", dialogLoadFunction, completeFunction);
            
        }

    }

    global.DisclaimerActions = {
        Callbacks: Callbacks
    };

})(window)