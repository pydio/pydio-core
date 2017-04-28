import { parseQuery } from './utils';

const { EditorActions } = PydioWorkspaces;

// Actions definitions
export const onSave = ({pydio, url, content, dispatch, id}) => {
    return pydio.ApiClient.postPlainTextContent(url, content, (success) => {
        if (!success) {
            dispatch(EditorActions.tabModify({id, error: "There was an error while saving"}))
        }
    })
}

export const onUndo = ({codemirror}) => codemirror.undo()
export const onRedo = ({codemirror}) => codemirror.redo()
export const onToggleLineNumbers = ({dispatch, id, lineNumbers}) => dispatch(EditorActions.tabModify({id, lineNumbers: !lineNumbers}))
export const onToggleLineWrapping = ({dispatch, id, lineWrapping}) => dispatch(EditorActions.tabModify({id, lineWrapping: !lineWrapping}))

export const onSearch = ({codemirror, cursor}) => (value) => {
    const query = parseQuery(value)

    let cur = codemirror.getSearchCursor(query, cursor.to);

    if (!cur.find()) {
        cur = codemirror.getSearchCursor(query, 0);
        if (!cur.find()) return;
    }

    codemirror.setSelection(cur.from(), cur.to());
    codemirror.scrollIntoView({from: cur.from(), to: cur.to()}, 20);
}

export const onJumpTo = ({codemirror}) => (value) => {
    const line = parseInt(value)
    const cur = codemirror.getCursor();

    codemirror.focus();
    codemirror.setCursor(line - 1, cur.ch);
    codemirror.scrollIntoView({line: line - 1, ch: cur.ch}, 20);
}
