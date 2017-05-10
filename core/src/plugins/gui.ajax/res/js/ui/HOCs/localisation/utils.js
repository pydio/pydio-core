export const mapStateToProps = (state, props) => {
    const {tabs} = state
    const tab = tabs.filter(({editorData, node}) => (!editorData || editorData.id === props.editorData.id) && node.getPath() === props.node.getPath())[0]

    return {
        tab,
        ...props
    }
}
