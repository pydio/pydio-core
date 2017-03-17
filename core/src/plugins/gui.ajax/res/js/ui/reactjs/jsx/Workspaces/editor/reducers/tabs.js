import { TAB_CREATE, TAB_DELETE } from '../actions'


export default function tabs(state = [], action) {

    switch (action.type) {
        case TAB_CREATE:
            return [
                {
                    id: state.reduce((maxId, tab) => Math.max(tab.id, maxId), -1) + 1,
                    ...action.data
                },
                ...state
            ]
        case TAB_DELETE:
            return state.filter(tab => tab.id !== action.id)
        default:
            return state
    }
}
