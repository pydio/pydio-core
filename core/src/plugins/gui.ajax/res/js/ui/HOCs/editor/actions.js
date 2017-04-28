// EDITOR actions
export const EDITOR_SET_ACTIVE_TAB = 'EDITOR_SET_ACTIVE_TAB'
export const editorSetActiveTab = (activeTabId) => ({
    type: EDITOR_SET_ACTIVE_TAB,
    activeTabId
})

export const EDITOR_MODIFY = 'EDITOR_MODIFY'
export const editorModify = (data) => ({
    type: EDITOR_MODIFY,
    ...data
})

// TABS action
export const TAB_CREATE = 'TAB_CREATE'
export const tabCreate = (data) => ({
    type: TAB_CREATE,
    ...data
})

export const TAB_MODIFY = 'TAB_MODIFY'
export const tabModify = (data) => ({
    type: TAB_MODIFY,
    ...data
})

export const TAB_ADD_CONTROLS = 'TAB_ADD_CONTROLS'
export const tabAddControls = (data) => ({
    type: TAB_ADD_CONTROLS,
    ...data
})

export const TAB_DELETE = 'TAB_DELETE'
export const tabDelete = (id) => ({
    type: TAB_DELETE,
    id
})

export const TAB_DELETE_ALL = 'TAB_DELETE_ALL'
export const tabDeleteAll = () => ({
    type: TAB_DELETE_ALL
})
