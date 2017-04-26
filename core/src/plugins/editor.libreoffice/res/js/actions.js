class Callbacks {


    static dynamicBuilder(controller) {

        const pydio = window.pydio;
        const MessageHash = pydio.MessageHash;
        const exts = {
            doc:'file-word',
            docx:'file-word',
            odt:'file-word',
            odg:'file-chart',
            odp:'file-powerpoint',
            ods:'file-excel',
            pot:'file-powerpoint',
            pptx:'file-powerpoint',
            rtf:'file-word',
            xls:'file-excel',
            xlsx:'file-excel'
        };

        const dir = pydio.getContextHolder().getContextNode().getPath();

        let builderMenuItems = [];

        Object.keys(exts).forEach((k) => {

            if(!MessageHash['libreoffice.ext.' + k]) return;

            builderMenuItems.push({
                name:MessageHash['libreoffice.ext.' + k],
                alt:MessageHash['libreoffice.ext.' + k],
                icon_class:'mdi mdi-' + exts[k],
                callback:function(e){
                    PydioApi.getClient().request({
                        get_action: 'libreoffice_mkempty_file',
                        dir       : dir,
                        format    : k
                    });
                }.bind(this)
            });

        });

        return builderMenuItems;

    }

}

window.PydioLibreOfficeActions = {Callbacks};
