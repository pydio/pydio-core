import Pydio from 'pydio';
import * as EditorActions from '../actions'
const { EDITOR_SET_ACTIVE_TAB, EDITOR_MODIFY } = EditorActions;

export default function editor(state = {}, action) {

    const {type} = action

    delete action.type

    switch (type) {
        case EDITOR_SET_ACTIVE_TAB:
            return {
                ...state,
                ...action
            }
        case EDITOR_MODIFY:
            return {
                ...state,
                ...action
            }

        default:
            return state
    }
}
