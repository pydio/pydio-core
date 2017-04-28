import { EditorActions } from '../utils';

// Actions definitions
export const onSelectionChange = ({dispatch, id}) => (node) => dispatch(EditorActions.tabModify({id, title: node.getLabel(), node}))
export const onTogglePlaying = ({dispatch, id}) => (playing) => dispatch(EditorActions.tabModify({id, playing}))
