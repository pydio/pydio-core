export default function(pydio){

    return function(){
        let submit = function(value){
            PydioApi.getClient().request({
                get_action:'mkfile',
                dir: pydio.getContextNode().getPath(),
                filename:value
            });
        };
        pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
            dialogTitleId:156,
            legendId:157,
            fieldLabelId:174,
            dialogSize:'sm',
            submitValue:submit
        });

    }

}

