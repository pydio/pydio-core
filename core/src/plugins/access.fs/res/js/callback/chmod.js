export default function (pydio) {

    return function(){
        pydio.UI.openComponentInModal('FSActions', 'PermissionsDialog', {
            dialogTitleId: 287,
            selection: pydio.getUserSelection(),
        });
    }

}