import {combineReducers} from 'redux'

import tabs from './tabs';
import editor from './editor';

const reducer = combineReducers({
    tabs,
    editor
})

export default reducer
