const PathUtils = require('pydio/util/path')

export default function(pydio) {

    const openOtherEditorPicker = require('../callback/openOtherEditorPicker')(pydio)
    let {MessageHash} = pydio;

    return function () {

        let builderMenuItems = [];
        if (pydio.getUserSelection().isEmpty()) {
            return builderMenuItems;
        }
        const node = pydio.getUserSelection().getUniqueNode();
        const selectedMime = PathUtils.getAjxpMimeType(node);
        const nodeHasReadonly = node.getMetadata().get("ajxp_readonly") === "true";

        const user = pydio.user;
        // Patch editors list before looking for available ones
        if (user && user.getPreference("gui_preferences", true) && user.getPreference("gui_preferences", true)["other_editor_extensions"]) {
            const otherRegistered = user.getPreference("gui_preferences", true)["other_editor_extensions"];
            Object.keys(otherRegistered).forEach(function (key) {
                let editor;
                pydio.Registry.getActiveExtensionByType("editor").forEach(function (ed) {
                    if (ed.editorClass == otherRegistered[key]) {
                        editor = ed;
                    }
                });
                if (editor && editor.mimes.indexOf(key) === -1) {
                    editor.mimes.push(key);
                }
            }.bind(this));
        }

        const editors = pydio.Registry.findEditorsForMime(selectedMime);
        let index = 0, sepAdded = false;
        if (editors.length) {
            editors.forEach(function (el) {
                if (!el.openable) return;
                if (el.write && nodeHasReadonly) return;
                if (el.mimes.indexOf('*') > -1) {
                    if (!sepAdded && index > 0) {
                        builderMenuItems.push({separator: true});
                    }
                    sepAdded = true;
                }
                builderMenuItems.push({
                    name: el.text,
                    alt: el.title,
                    isDefault: (index == 0),
                    icon_class: el.icon_class,
                    callback: function (e) {
                        this.apply([el]);
                    }.bind(this)
                });
                index++;
            }.bind(this));
            builderMenuItems.push({
                name: MessageHash['openother.1'],
                alt: MessageHash['openother.2'],
                isDefault: (index === 0),
                icon_class: 'icon-list-alt',
                callback: openOtherEditorPicker
            });
        }
        if (!index) {
            builderMenuItems.push({
                name: MessageHash[324],
                alt: MessageHash[324],
                callback: function (e) {
                }
            });
        }
        return builderMenuItems;

    }
}