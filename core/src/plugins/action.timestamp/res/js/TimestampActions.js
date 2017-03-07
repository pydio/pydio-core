(function(global){

    const pydio = global.pydio;
    const MessageHash = pydio.MessageHash;

    class Callbacks{

        static getTimestamp(){

            const userSelection = pydio.getUserSelection();

            pydio.UI.openComponentInModal('PydioReactUI', 'ConfirmDialog', {
                message         : MessageHash['timestamp.5'],
                dialogTitleId   : 443,
                validCallback:function(){
                    PydioApi.getClient().request({
                        get_action: 'get_timestamp',
                        file      : userSelection.getUniqueNode().getPath()
                    },  function(transport){
                        PydioApi.getClient().parseXmlMessage(transport.responseXML);
                    });
                }
            });

        }

    }

    global.TimestampActions = {
        Callbacks: Callbacks
    };

})(window)