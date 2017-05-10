export const mapStateToProps = (state, props) => {
    const {tabs} = state
    const tab = tabs.filter(({editorData, node}) => editorData.id === props.editorData.id && node.getParent() === props.node.getParent())[0] || {}

    return {
        tab,
        ...props
    }
}
