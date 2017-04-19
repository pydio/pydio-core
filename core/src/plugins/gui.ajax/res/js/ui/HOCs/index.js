import {withMenu, withControls} from './actions'
import withErrors from './errors'
import withLoader from './loader'
import withResize from './resize'
import PaletteModifier from './PaletteModifier'
import withContextMenu from './context-menu'
import * as Animations from "./animations";

const PydioHOCs = {
    withMenu,
    withControls,
    withErrors,
    withLoader,
    withContextMenu,
    withResize,
    PaletteModifier,
    Animations
};

export {PydioHOCs as default}
