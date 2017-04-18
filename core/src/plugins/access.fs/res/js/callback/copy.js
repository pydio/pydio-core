export default function (pydio) {

    const {MessageHash} = pydio;
    return function(){
        // Todo
        // + Handle copy in same folder, move in same folder
        let selection = pydio.getUserSelection();
        const submit = function(path, wsId = null){
            require('./applyCopyOrMove')(pydio)('copy', selection, path, wsId);
        };

        pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
            isMove: false,
            dialogTitle:MessageHash[159],
            submitValue:submit
        });

    }

}