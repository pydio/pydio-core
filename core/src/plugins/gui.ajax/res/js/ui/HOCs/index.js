import withContextMenu from './context-menu'
import {withMenu, withControls} from './controls'
import withErrors from './errors'
import withLoader from './loader'
import {ContentActions, ContentControls} from './content/index'
import {SelectionControls, withSelection} from './selection/index'
import {SizeControls, SizeProviders, withResize} from './size/index'
import {ResolutionControls, withResolution} from './resolution/index'
import {URLProvider} from './urls'
import PaletteModifier from './PaletteModifier'
import * as Animations from "./animations";


const PydioHOCs = {
    ContentActions,
    ContentControls,
    ResolutionControls,
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
