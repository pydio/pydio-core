export default function (pydio) {

    return function(){
        pydio.UI.openComponentInModal('FSActions', 'OtherEditorPickerDialog', {
            selection: pydio.getUserSelection(),
            pydio    : pydio
        });
    }

}