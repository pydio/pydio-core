const PydioApi = require('pydio/http/api')

export default function (pydio) {

    const {MessageHash} = pydio;
    return function(){

        pydio.UI.openComponentInModal('PydioReactUI', 'ConfirmDialog', {
            message:MessageHash[177],
            dialogTitleId: 220,
            validCallback:function(){
                PydioApi.getClient().request({get_action:'empty_recycle'});
            }
        });

    }

}