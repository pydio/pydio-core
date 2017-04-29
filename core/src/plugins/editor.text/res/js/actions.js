import Pydio from 'pydio'
const { EditorActions } = Pydio.requireLib('hoc')

// Actions definitions
export const onSave = ({pydio, url, content, dispatch, id}) => {
    return pydio.ApiClient.postPlainTextContent(url, content, (success) => {
        if (!success) {
            dispatch(EditorActions.tabModify({id, error: "There was an error while saving"}))
        }
    })
}
