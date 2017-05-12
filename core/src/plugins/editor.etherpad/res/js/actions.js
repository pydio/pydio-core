import Pydio from 'pydio'
const { EditorActions } = Pydio.requireLib('hoc')

// Actions definitions
export const onSave = ({pydio, content, dispatch, padID, node, id}) => {

    return pydio.ApiClient.request({
        get_action:'etherpad_save',
        file: node.getPath(),
        pad_id: padID

    }, () => {
        dispatch(EditorActions.tabModify({id, title: node.getLabel()}));
    })

}
