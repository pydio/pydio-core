export default function(pydio){

    return function(){

        let submit = function(value){
            PydioApi.getClient().request({
                get_action:'mkdir',
                dir: pydio.getContextNode().getPath(),
                dirname:value
            });
        };
        pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
            dialogTitleId:154,
            legendId:155,
            fieldLabelId:173,
            dialogSize:'sm',
            submitValue:submit
        });
    }

}