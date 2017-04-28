import withContextMenu from './context-menu'
import {withMenu, withControls} from './controls'
import withErrors from './errors'
import withLoader from './loader'
import {ContentActions, ContentControls} from './content/index'
import {SelectionProviders, SelectionActions, SelectionControls, withSelection} from './selection/index'
import {SizeActions, SizeControls, SizeProviders, withResize} from './size/index'
import {ResolutionActions, ResolutionControls, withResolution} from './resolution/index'
import {URLProvider} from './urls'
import PaletteModifier from './PaletteModifier'
import * as Animations from "./animations";


const PydioHOCs = {
    ContentActions,
    ContentControls,
    ResolutionActions,
    ResolutionControls,
    SizeActions,
    SizeControls,
    SelectionProviders,
    SelectionActions,
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
