const { EditorActions } = PydioWorkspaces;

// Actions definitions
export const onToggleResolution = ({dispatch, id, resolution}) => dispatch(EditorActions.tabModify({id, resolution: resolution === "hi" ? "lo" : "hi"}))
