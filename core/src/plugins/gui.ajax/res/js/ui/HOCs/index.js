import withActions from './actions'
import withErrors from './errors'
import withLoader from './loader'

window.PydioHOCs = {
    withActions : withActions,
    withErrors  : withErrors,
    withLoader  : withLoader
};
