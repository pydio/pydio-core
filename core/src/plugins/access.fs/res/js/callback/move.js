const {DNDActionParameter} = require('pydio').requireLib('components')

export default function (pydio) {

    const {MessageHash} = pydio;
    return function(controller, dndActionParameter = null){

        if(dndActionParameter && dndActionParameter instanceof DNDActionParameter){
            if(dndActionParameter.getStep() === DNDActionParameter.STEP_CAN_DROP){

                if(dndActionParameter.getTarget().isLeaf()){
                    throw new Error('Cannot drop');
                }else {
                    return false;
                }

            }else if(dndActionParameter.getStep() === DNDActionParameter.STEP_END_DRAG){
                let selection = controller.getDataModel();
                let path = dndActionParameter.getTarget().getPath();
                require('./applyCopyOrMove')(pydio)('move', selection, path);
                return;
            }

            return;
        }

        let selection = pydio.getUserSelection();
        const submit = function(path, wsId = null){
            require('./applyCopyOrMove')(pydio)('move', selection, path, wsId);
        };

        pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
            isMove: true,
            dialogTitle:MessageHash[160],
            submitValue:submit
        });

    }

}