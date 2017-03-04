import SortableList from './SortableList'
import SimpleList from './list/SimpleList'
import NodeListCustomProvider from './list/NodeListCustomProvider'
import {ListEntry} from './list/ListEntry'
import ListPaginator from './list/ListPaginator'

import {TreeView, DNDTreeView, FoldersTree} from './TreeView'

import LabelWithTip from './LabelWithTip'
import SimpleFigureBadge from './SimpleFigureBadge'
import SearchBox from './SearchBox'
import ClipboardTextField from './ClipboardTextField'

import LegacyUIWrapper from './LegacyUIWrapper'
import ReactEditorOpener from './editor/ReactEditorOpener'
import {PaperEditorLayout, PaperEditorNavEntry, PaperEditorNavHeader} from './editor/PaperEditor'
import AbstractEditor from './editor/AbstractEditor'

import {DNDActionParameter} from './DND'

import UserAvatar from './UserAvatar'

window.PydioComponents = {
    
    SortableList            : SortableList,
    SimpleList              : SimpleList,
    NodeListCustomProvider  : NodeListCustomProvider,
    ListEntry               : ListEntry,
    ListPaginator           : ListPaginator,

    TreeView                : TreeView,
    DNDTreeView             : DNDTreeView,
    FoldersTree             : FoldersTree,
    ClipboardTextField      : ClipboardTextField,
    LabelWithTip            : LabelWithTip,

    SimpleFigureBadge       : SimpleFigureBadge,
    SearchBox               : SearchBox,
    LegacyUIWrapper         : LegacyUIWrapper,

    AbstractEditor          : AbstractEditor,
    ReactEditorOpener       : ReactEditorOpener,
    PaperEditorLayout       : PaperEditorLayout,
    PaperEditorNavEntry     : PaperEditorNavEntry,
    PaperEditorNavHeader    : PaperEditorNavHeader,

    DNDActionParameter      : DNDActionParameter,
    UserAvatar              : UserAvatar

};

import ContextMenuNodeProviderMixin from './menu/ContextMenuNodeProviderMixin'
import ButtonMenu from './menu/ButtonMenu'
import ContextMenu from './menu/ContextMenu'
import IconButtonMenu from './menu/IconButtonMenu'
import MFB from './menu/MFB'
import Toolbar from './menu/Toolbar'

window.PydioMenus = {
    ContextMenuNodeProviderMixin: ContextMenuNodeProviderMixin,
    ContextMenu: ContextMenu,
    Toolbar:Toolbar,
    ButtonMenu: ButtonMenu,
    IconButtonMenu: IconButtonMenu,
    MFB: MFB
};