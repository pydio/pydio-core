import { EditorActions } from '../utils';

// Actions definitions
export const onLocate = ({node}) => {
    const editors = pydio.Registry.findEditorsForMime("ol_layer");

    if (editors.length) {
        pydio.UI.openCurrentSelectionInEditor(editors[0], node);
    }
}
