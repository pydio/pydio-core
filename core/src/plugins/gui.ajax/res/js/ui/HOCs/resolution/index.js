import {URLProvider} from '../urls';
import * as ResolutionControls from './controls'
import * as ResolutionActions from './actions'
import withResolution from './resolution'

const ResolutionURLProvider = URLProvider(["hi", "lo"])

export {ResolutionURLProvider}
export {ResolutionActions}
export {ResolutionControls}
export {withResolution}
