(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static createCypheredFolder(){

            modal.showDialogForm('', 'encfs_cypher', null, function(oForm){

                var passValue = $(oForm).down('input[name="password"]').getValue();
                var passValue2 = $(oForm).down('input[name="password_confirm"]').getValue();
                if(passValue != passValue2){
                    pydio.displayMessage('ERROR', 'Warning, both passwords differ');
                    return;
                }
                var conn = new Connexion();
                conn.setParameters($H({
                    get_action:'encfs.cypher_folder',
                    file:pydio.getUserSelection().getUniqueNode().getPath(),
                    pass: passValue
                }));
                conn.onComplete = function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                };
                conn.sendAsync();
                hideLightBox();

            })

        }

        static cypherFolder(){

            var conn = new Connexion();
            conn.setParameters($H({
                get_action:'encfs.cypher_folder',
                file:pydio.getUserSelection().getUniqueNode().getPath()
            }));
            conn.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            };
            conn.sendAsync();
            hideLightBox();

        }

        static uncypherFolder(){

            modal.showDialogForm('', 'encfs_mount', null, function(oForm){

                var passValue = $(oForm).down('input[name="password"]').getValue();
                var conn = new Connexion();
                conn.setParameters($H({
                    get_action:'encfs.uncypher_folder',
                    file:pydio.getUserSelection().getUniqueNode().getPath(),
                    pass: passValue
                }));
                conn.onComplete = function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                };
                conn.sendAsync();
                hideLightBox();


            });
        }

    }

    global.EncFSActions = {
        Callbacks: Callbacks
    };

})(window)