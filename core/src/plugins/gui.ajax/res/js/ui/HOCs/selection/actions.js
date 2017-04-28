import { Actions } from '../utils';

// Actions definitions
export const onSelectionChange = ({dispatch, id}) => (node) => dispatch(Actions.tabModify({id, title: node.getLabel(), node}))
export const onTogglePlaying = ({dispatch, id}) => (playing) => dispatch(Actions.tabModify({id, playing}))
