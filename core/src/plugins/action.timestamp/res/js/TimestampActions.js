(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static getTimestamp(){

            var onLoad = function(oForm){
                $(oForm).getElementsBySelector('span[id="timestamp_message"]')[0].innerHTML = "CONF_MESSAGE[msgBox1]";
            };
            var onok = function(){
                var oForm = modal.getForm();
                var userSelection = pydio.getUserSelection();
                var selected = userSelection.getFileNames()

                var conn = new Connexion();
                conn.addParameter('get_action', 'get_timestamp');
                conn.addParameter('file', selected);
                conn.addParameter('dir', userSelection.getContextNode().getPath());
                conn.onComplete = function(transport){
                    this.parseXmlMessage(transport.responseXML);
                };
                conn.sendSync();

                hideLightBox(true);
                pydio.fireContextRefresh()
                return false;
            };

            var closeFunc = function(){
                hideLightBox();
                return false;
            };

            modal.showDialogForm('Horodater', 'timestamp_form', onLoad, onok);


        }

    }

    global.TimestampActions = {
        Callbacks: Callbacks
    };

})(window)