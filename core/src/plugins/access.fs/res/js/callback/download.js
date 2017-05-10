const PydioApi = require('pydio/http/api')

export default function (pydio) {

    return function(){
        const userSelection = pydio.getUserSelection();
        if(( userSelection.isUnique() && !userSelection.hasDir() ) || pydio.Parameters.get('multipleFilesDownloadEnabled')){
            PydioApi.getClient().downloadSelection(userSelection, 'download');
        } else {
            pydio.UI.openComponentInModal('FSActions', 'MultiDownloadDialog', {
                actionName:'download',
                selection : userSelection,
                dialogTitleId:88
            });
        }
    }

}