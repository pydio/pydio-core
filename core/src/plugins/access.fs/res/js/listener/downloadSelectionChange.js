export default function(pydio) {

    return function () {
        const userSelection = pydio.getUserSelection();
        if (pydio.Parameters.get('zipEnabled') && pydio.Parameters.get('multipleFilesDownloadEnabled')) {
            if ((userSelection.isUnique() && !userSelection.hasDir()) || userSelection.isEmpty()) {
                this.setIconSrc('download_manager.png');
            } else {
                this.setIconSrc('accessories-archiver.png');
            }
        } else if (userSelection.hasDir()) {
            this.selectionContext.dir = false;
        }
    }
}