import withActions from './actions'
import withErrors from './errors'
import withLoader from './loader'
import PaletteModifier from './PaletteModifier'
import withContextMenu from './context-menu'
import * as Animations from "./animations";

const PydioHOCs = {
    withActions : withActions,
    withErrors  : withErrors,
    withLoader  : withLoader,
    withContextMenu: withContextMenu,
    PaletteModifier,
    Animations  : Animations
};

export {PydioHOCs as default}
