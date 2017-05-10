import Pydio from 'pydio'
const { EditorActions } = Pydio.requireLib('hoc')

// Actions definitions
export const onToggleResolution = ({dispatch, id, resolution}) => dispatch(EditorActions.tabModify({id, resolution: resolution === "hi" ? "lo" : "hi"}))
