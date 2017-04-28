import { Actions } from '../utils';

// Actions definitions
export const onSizeChange = ({dispatch, id}) => (data) => dispatch(Actions.tabModify({id, ...data}))
