import { EditorActions } from '../utils';

// Actions definitions
export const onSizeChange = ({dispatch, tab}) => (data) => dispatch(EditorActions.tabModify({id: tab.id, ...data}))
