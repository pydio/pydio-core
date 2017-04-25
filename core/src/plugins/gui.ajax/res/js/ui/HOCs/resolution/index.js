import {URLProvider} from '../urls';
import ResolutionControls from './controls'
import withResolution from './resolution'
import * as Actions from '../../Workspaces/editor/actions';

const ResolutionURLProvider = URLProvider(["hi", "lo"])

export const mapStateToProps = (state, props) => ({
    ...state.tabs.filter(({editorData, node}) => editorData.id === props.editorData.id && node.getLabel() === props.node.getLabel())[0],
    ...props
})

export {Actions}
export {ResolutionURLProvider}
export {ResolutionControls}
export {withResolution}
