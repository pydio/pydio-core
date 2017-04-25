import {URLProvider} from '../urls';
import ResolutionControls from './controls'
import withResolution from './resolution'

const ResolutionURLProvider = URLProvider(["hi", "lo"])

export {ResolutionURLProvider}
export {ResolutionControls}
export {withResolution}
