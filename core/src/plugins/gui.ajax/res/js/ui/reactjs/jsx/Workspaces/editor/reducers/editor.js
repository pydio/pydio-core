import { EDITOR_SET_ACTIVE_TAB, EDITOR_MODIFY_PANEL, EDITOR_MODIFY_MENU } from '../actions'

export default function editor(state = {}, action) {

    const {type} = action

    delete action.type

    switch (type) {
        case EDITOR_SET_ACTIVE_TAB:
            return {
                ...state,
                ...action
            }
        case EDITOR_MODIFY_MENU:
            return {
                ...state,
                menu: {
                    ...state.menu,
                    ...action
                }
            }
        case EDITOR_MODIFY_PANEL:
            return {
                ...state,
                panel: {
                    ...state.panel,
                    ...action
                }
            }

        default:
            return state
    }
}
