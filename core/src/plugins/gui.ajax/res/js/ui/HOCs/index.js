import withContextMenu from './context-menu'
import {withMenu, withControls} from './controls'
import withErrors from './errors'
import withLoader from './loader'
import {SelectionControls, withSelection} from './selection/index'
import {SizeControls, SizeProviders, withResize} from './size/index'
import {withResolution} from './resolution'
import {URLProvider} from './urls'
import PaletteModifier from './PaletteModifier'
import * as Animations from "./animations";

const PydioHOCs = {
    SizeControls,
    SelectionControls,
    withControls,
    withContextMenu,
    withErrors,
    withLoader,
    withMenu,
    withResize,
    withResolution,
    withSelection,
    Animations,
    PaletteModifier,
    URLProvider,
    SizeProviders
};

export {PydioHOCs as default}
