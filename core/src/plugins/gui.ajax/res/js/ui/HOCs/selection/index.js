import SelectionModel from './model'
import SelectionControls from './controls'
import withSelection from './selection'

export const mapStateToProps = (state, props) => ({
    ...state.tabs.filter(({editorData, node}) => editorData.id === props.editorData.id && node.getParent() === props.node.getParent())[0],
    ...props
})

export {Actions}
export {SelectionModel}
export {SelectionControls}
export {withSelection}
