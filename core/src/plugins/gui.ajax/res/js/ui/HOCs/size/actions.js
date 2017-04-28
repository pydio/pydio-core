import { EditorActions } from '../utils';

// Actions definitions
export const onSizeChange = ({dispatch, id}) => (data) => dispatch(EditorActions.tabModify({id, ...data}))
