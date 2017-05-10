export default function (pydio) {

    return function(manager, uploaderArguments){

        pydio.UI.openComponentInModal('FSActions', 'UploadDialog');

    }

}