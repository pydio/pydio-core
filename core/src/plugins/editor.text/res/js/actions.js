const { EditorActions } = PydioWorkspaces;

// Actions definitions
export const onSave = ({pydio, url, content, dispatch, id}) => {
    return pydio.ApiClient.postPlainTextContent(url, content, (success) => {
        if (!success) {
            dispatch(EditorActions.tabModify({id, error: "There was an error while saving"}))
        }
    })
}
