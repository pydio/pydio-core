export default function (pydio) {

    return function(){

        const userSelection = pydio.getUserSelection();
        pydio.UI.openComponentInModal('FSActions', 'MultiDownloadDialog', {
            buildChunks:true,
            actionName:'download_chunk',
            chunkAction: 'prepare_chunk_dl',
            selection: userSelection
        });

    }

}