(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static download(){

            const userSelection = pydio.getUserSelection();

            if(!pydio.Parameters.get('multipleFilesDownloadEnabled') && (!userSelection.isUnique() || userSelection.hasDir())){
                pydio.UI.openComponentInModal('FSActions', 'MultiDownloadDialog', {
                    actionName:'download',
                    selection : userSelection,
                    dialogTitleId:88
                });
                return;

            }

            const agent = navigator.userAgent || '';
            const agentIsMobile = (agent.indexOf('iPhone')!=-1||agent.indexOf('iPod')!=-1||agent.indexOf('iPad')!=-1||agent.indexOf('iOs')!=-1);
            const hiddenForm = pydio && pydio.UI && pydio.UI.hasHiddenDownloadForm();

            if(agentIsMobile || !hiddenForm || (userSelection.isUnique() && !userSelection.hasDir() )){
                // Simple file download, or
                // no ability to monitor background task, fallback to normal download
                PydioApi.getClient().downloadSelection(userSelection, 'download');

            }else{

                let zipName = PathUtils.getBasename(userSelection.getContextNode().getPath());
                if(zipName === "") zipName = "Archive";
                let index=1;
                let buff = zipName;
                while(userSelection.fileNameExists(zipName + ".zip")){
                    zipName = buff + "-" + index; index ++ ;
                }

                PydioApi.getClient().downloadSelection(userSelection, 'precompress', {
                    archive_name: zipName + '.zip',
                    on_end : 'postcompress_download'
                });

            }

        }

    }

    global.PowerFSActions = {
        Callbacks: Callbacks
    };

})(window)