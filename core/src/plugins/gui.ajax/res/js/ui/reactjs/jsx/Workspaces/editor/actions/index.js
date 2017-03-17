// EDITOR actions
export const EDITOR_SET_ACTIVE_TAB = 'EDITOR_SET_ACTIVE_TAB'
export const editorSetActiveTab = (activeTabId) => ({
    type: EDITOR_SET_ACTIVE_TAB,
    activeTabId
})

export const EDITOR_MODIFY_MENU = 'EDITOR_MODIFY_MENU'
export const editorModifyMenu = (data) => ({
    type: EDITOR_MODIFY_MENU,
    ...data
})

export const EDITOR_MODIFY_PANEL = 'EDITOR_MODIFY_PANEL'
export const editorModifyPanel = (data) => ({
    type: EDITOR_MODIFY_PANEL,
    ...data
})

// TABS action
export const TAB_CREATE = 'TAB_CREATE'
export const tabCreate = (data) => ({
    type: TAB_CREATE,
    data
})

export const TAB_DELETE = 'TAB_DELETE'
export const tabDelete = (id) => ({
    type: TAB_DELETE,
    id
})
