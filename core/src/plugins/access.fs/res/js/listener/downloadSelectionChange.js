export default function(pydio) {

    return function () {
        const userSelection = pydio.getUserSelection();
        if (pydio.Parameters.get('zipEnabled') && pydio.Parameters.get('multipleFilesDownloadEnabled')) {
            /*
            if ((userSelection.isUnique() && !userSelection.hasDir()) || userSelection.isEmpty()) {
                // Update icon class
            } else {
                 // Update icon class
            }
            */
        } else if (userSelection.hasDir()) {
            this.selectionContext.dir = false;
        }
    }
}