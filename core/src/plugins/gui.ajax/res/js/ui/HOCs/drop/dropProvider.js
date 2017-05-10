import Pydio from 'pydio'
import NativeFileDropProvider from './NativeFileDropProvider'

export default function(PydioComponent, filterFunction = null ){

    return NativeFileDropProvider(PydioComponent, (items, files) => {

        const {pydio, UploaderModel} = global;
        if(!pydio.user || !pydio.user.write || !UploaderModel){
            pydio.UI.displayMessage('ERROR', 'You are not allowed to upload files here');
            return;
        }
        const ctxNode = pydio.getContextHolder().getContextNode();
        const storeInstance = UploaderModel.Store.getInstance();

        storeInstance.handleDropEventResults(items, files, ctxNode, null, filterFunction);
        if(!storeInstance.getAutoStart()){
            pydio.getController().fireAction('upload');
        }
    });

}