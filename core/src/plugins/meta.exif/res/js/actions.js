export function openGpsLocator() {
    const {pydio, node} = this.props

    const editors = pydio.Registry.findEditorsForMime("ol_layer");
    if (editors.length) {
        pydio.UI.openCurrentSelectionInEditor(editors[0], this.props.node);
    }
}
