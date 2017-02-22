(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static download(){

            var userSelection = pydio.getUserSelection();
            if((userSelection.isUnique() && !userSelection.hasDir()) || multipleFilesDownloadEnabled)
            {
                if(window.gaTrackEvent){
                    var fileNames = userSelection.getFileNames();
                    for(var i=0; i<fileNames.length;i++){
                        window.gaTrackEvent("Data", "Download", fileNames[i]);
                    }
                }
                var agent = navigator.userAgent;
                if(agent && (agent.indexOf('iPhone')!=-1||agent.indexOf('iPod')!=-1||agent.indexOf('iPad')!=-1||agent.indexOf('iOs')!=-1)){
                    var downloadUrl = ajxpServerAccessPath+'&get_action=download';
                    downloadUrl = userSelection.updateFormOrUrl(null,downloadUrl);
                    document.location.href=downloadUrl;
                }else{
                    if( !userSelection.isUnique() || userSelection.hasDir() ){

                        var zipName = getBaseName(userSelection.getContextNode().getPath());
                        if(zipName == "") zipName = "Archive";
                        var index=1;
                        var buff = zipName;
                        while(userSelection.fileNameExists(zipName + ".zip")){
                            zipName = buff + "-" + index; index ++ ;
                        }

                        var conn = new Connexion();
                        conn.addParameter("get_action", "precompress");
                        conn.addParameter("archive_name", zipName+".zip");
                        conn.addParameter("on_end", "postcompress_download");
                        var selected = userSelection.getFileNames();
                        var dir = userSelection.getContextNode().getPath();
                        for(var i=0;i<selected.length;i++){
                            conn.addParameter("file_"+i, selected[i]);
                            dir = PathUtils.getDirname(selected[i]);
                        }
                        conn.addParameter("dir", dir);
                        conn.onComplete = function(transport){
                            this.parseXmlMessage(transport.responseXML);
                        }.bind(pydio.getController()) ;
                        conn.sendAsync();

                    }else{

                        PydioApi.getClient().downloadSelection(userSelection, $('download_form'));

                    }
                }
            }
            else
            {
                var loadFunc = function(oForm){
                    var dObject = oForm.getElementsBySelector('div[id="multiple_download_container"]')[0];
                    var downloader = new MultiDownloader(dObject, ajxpServerAccessPath+'&action=download&file=');
                    downloader.triggerEnd = function(){hideLightBox()};
                    var fileNames = userSelection.getFileNames();
                    for(var i=0; i<fileNames.length;i++)
                    {
                        downloader.addListRow(fileNames[i]);
                    }
                };
                var closeFunc = function(){
                    hideLightBox();
                    return false;
                };
                modal.showDialogForm('Download Multiple', 'multi_download_form', loadFunc, closeFunc, null, true);
            }

        }

    }

    global.PowerFSActions = {
        Callbacks: Callbacks
    };

})(window)