export default function(pydio) {

    return function(){

        if(!pydio.Parameters.get('zipEnabled') || !pydio.Parameters.get('multipleFilesDownloadEnabled')){
            this.hide();
            pydio.Controller.actions["delete"]("download_all");
        }

    }

}

