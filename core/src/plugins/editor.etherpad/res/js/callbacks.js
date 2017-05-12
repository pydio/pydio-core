class Actions{

    static makePad(){

        let d = new Date().getTime();
        const uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = (d + Math.random()*16)%16 | 0;
            d = Math.floor(d/16);
            return (c=='x' ? r : (r&0x7|0x8)).toString(16);
        });

        const submit = function(value){
            PydioApi.getClient().request({
                get_action  :'mkfile',
                dir         : pydio.getContextNode().getPath(),
                filename    : value + '.pad',
                content     : uuid
            });
        };

        pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
            dialogTitleId:'etherpad.1',
            legendId:'etherpad.1b',
            fieldLabelId:'etherpad.8',
            dialogSize:'sm',
            submitValue:submit
        });

    }

}

export {Actions as default}