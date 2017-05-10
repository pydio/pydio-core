export default function (pydio) {

    return function(manager, otherArguments){
        const editorData = otherArguments && otherArguments.length ? otherArguments[0] : null;
        pydio.UI.openCurrentSelectionInEditor(editorData);
    }

}