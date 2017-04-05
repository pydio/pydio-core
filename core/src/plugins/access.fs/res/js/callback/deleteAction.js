export default function (pydio) {

    const {MessageHash} = pydio;

    return function(){
        let message = MessageHash[177];
        const repoHasRecycle = pydio.getContextHolder().getRootNode().getMetadata().get("repo_has_recycle");
        if(repoHasRecycle && pydio.getContextNode().getAjxpMime() != "ajxp_recycle"){
            message = MessageHash[176];
        }
        pydio.UI.openComponentInModal('PydioReactUI', 'ConfirmDialog', {
            message:message,
            dialogTitleId: 7,
            validCallback:function(){
                PydioApi.getClient().postSelectionWithAction('delete');
            }
        });
    };

}