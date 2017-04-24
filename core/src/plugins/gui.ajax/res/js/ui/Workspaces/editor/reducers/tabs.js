import { TAB_CREATE, TAB_MODIFY, TAB_DELETE, TAB_DELETE_ALL } from '../actions'


export default function tabs(state = [], action) {

    switch (action.type) {
        case TAB_CREATE:
            return [
                {
                    id: state.reduce((maxId, tab) => Math.max(tab.id, maxId), -1) + 1,
                    ...action
                },
                ...state
            ]
        case TAB_MODIFY:
            return state.map((tab) => {
                if (tab.id === action.id) {
                    return {
                        ...tab,
                        ...action
                    }
                }

                return tab
            })
        case TAB_DELETE:
            return state.filter(tab => tab.id !== action.id)
        case TAB_DELETE_ALL:
            return []
        default:
            return state
    }
}
