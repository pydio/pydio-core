const PydioApi = require('pydio/http/api')

export default function (pydio) {

    return function(){

        if(pydio.getContextHolder().isMultiple()){
            const ctxNode = pydio.getContextHolder().getContextNode();
            pydio.getContextHolder().getSelectedNodes().forEach((n) => {
                let tmpModel = new PydioDataModel();
                tmpModel.setContextNode(ctxNode);
                tmpModel.setSelectedNodes([n]);
                PydioApi.getClient().postSelectionWithAction('restore', null, tmpModel);
            });
        }else{
            PydioApi.getClient().postSelectionWithAction('restore');
        }

    }

}