import {pydio, global} from '../globals'

export default function(){

    var userSelection = pydio.getUserSelection();
    if((userSelection.isUnique() && !userSelection.hasDir()))
    {
        var fileName = userSelection.getUniqueFileName();
        var selectorData = pydio.getController().selectorData;
        if(selectorData.get('type') == "ckeditor"){
            var ckData = selectorData.get('data');
            if (ckData['CKEditorFuncNum']) {
                var imagePath = fileName;
                if(ckData['relative_path']){
                    imagePath = ckData['relative_path'] + fileName;
                }
                global.opener.CKEDITOR.tools.callFunction(ckData['CKEditorFuncNum'], imagePath);
                global.close();
            }
        }
    }


}