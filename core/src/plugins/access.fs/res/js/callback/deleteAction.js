export default function (pydio) {

    const {MessageHash} = pydio;

    return function(){
        let message = MessageHash[177];
        const repoHasRecycle = pydio.getContextHolder().getRootNode().getMetadata().get("repo_has_recycle");
        if(repoHasRecycle && pydio.getContextNode().getAjxpMime() != "ajxp_recycle"){
            message = MessageHash[176];
        }
        // Detect shared node
        if(pydio.getPluginConfigs('action.share').size){
            let shared = [];
            pydio.getContextHolder().getSelectedNodes().forEach((n) => {
                if(n.getMetadata().get('ajxp_shared')){
                    shared.push(n);
                }
            });
            if(shared.length){
                const n = shared[0];
                message = (
                    <div>
                        <div>{message}</div>
                        <div style={{color:'#D32F2F', marginTop: 10}}><span className="mdi mdi-alert"/>{MessageHash['share_center.' + (n.isLeaf()?'158':'157')]}</div>
                    </div>
                );
            }
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