import Pydio from 'pydio';
const { EditorActions } = Pydio.requireLib('hoc');
const { EDITOR_SET_ACTIVE_TAB, EDITOR_MODIFY } = EditorActions;

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
        case TAB_ADD_CONTROLS:
            return state.map((tab) => {
                if (tab.id === action.id) {
                    const controls = tab.controls
                    return {
                        ...tab,
                        controls: {
                            ...controls,
                            ...action
                        }
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
