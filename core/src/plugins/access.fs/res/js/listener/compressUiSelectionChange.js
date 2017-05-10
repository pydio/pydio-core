export default function(pydio){

    return function(){
        const userSelection = pydio.getUserSelection();
        if(!pydio.Parameters.get('zipEnabled') || !pydio.Parameters.get('multipleFilesDownloadEnabled')){
            if(userSelection.isUnique()) this.selectionContext.multipleOnly = true;
            else this.selectionContext.unique = true;
        }
    }

}