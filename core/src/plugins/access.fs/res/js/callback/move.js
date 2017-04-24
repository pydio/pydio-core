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
                const targetPath = dndActionParameter.getTarget().getPath();
                const moveFunction = require('./applyCopyOrMove')(pydio);
                const sourceNode = dndActionParameter.getSource();
                const selectedNodes = selection.getSelectedNodes();
                if(selectedNodes.indexOf(sourceNode) === -1){
                    // Use source node instead of current datamodel selection
                    let newSel = new PydioDataModel();
                    newSel.setContextNode(selection.getContextNode());
                    newSel.setSelectedNodes([dndActionParameter.getSource()]);
                    selection = newSel;
                    moveFunction('move', newSel, targetPath);
                }else{
                    moveFunction('move', selection, targetPath);
                }
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