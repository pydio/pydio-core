export const mapStateToProps = (state, props) => ({
    ...state.tabs.filter(({editorData, node}) => editorData && props.editorData && editorData.id === props.editorData.id && node.getPath() === props.node.getPath())[0],
    ...props
})
