import withContextMenu from './context-menu'
import {withMenu, withControls} from './controls'
import withErrors from './errors'
import withLoader from './loader'
import {ContentActions, ContentControls} from './content/index'
import {SelectionProviders, SelectionActions, SelectionControls, withSelection} from './selection/index'
import {SizeActions, SizeControls, SizeProviders, withResize} from './size/index'
import {ResolutionActions, ResolutionControls, withResolution} from './resolution/index'
import {LocalisationActions, LocalisationControls} from './localisation/index'
import {URLProvider} from './urls'
import PaletteModifier from './PaletteModifier'
import * as Animations from "./animations";
import reducers from './editor/reducers/index'
import * as actions from './editor/actions';
import withVerticalScroll from './scrollbar/withVerticalScroll';

const PydioHOCs = {
    ContentActions,
    EditorActions: actions,
    EditorReducers: reducers,
    ContentControls,
    ResolutionActions,
    ResolutionControls,
    SizeActions,
    SizeControls,
    SelectionProviders,
    SelectionActions,
    SelectionControls,
    LocalisationActions,
    LocalisationControls,
    withControls,
    withContextMenu,
    withErrors,
    withLoader,
    withMenu,
    withResize,
    withResolution,
    withSelection,
    withVerticalScroll,
    Animations,
    PaletteModifier,
    URLProvider,
    SizeProviders
};

export {PydioHOCs as default}
