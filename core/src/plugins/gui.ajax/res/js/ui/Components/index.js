import SortableList from './list/SortableList'
import SimpleList from './list/SimpleList'
import NodeListCustomProvider from './list/NodeListCustomProvider'
import {ListEntry} from './list/ListEntry'
import ListPaginator from './list/ListPaginator'

import {TreeView, DNDTreeView, FoldersTree} from './views/TreeView'

import LabelWithTip from './elements/LabelWithTip'
import SimpleFigureBadge from './elements/SimpleFigureBadge'
import SearchBox from './views/SearchBox'
import ClipboardTextField from './elements/ClipboardTextField'
import EmptyStateView from './views/EmptyStateView'

import ReactEditorOpener from './editor/ReactEditorOpener'
import {PaperEditorLayout, PaperEditorNavEntry, PaperEditorNavHeader} from './editor/PaperEditor'
import DynamicGrid from './dynamic-grid/DynamicGrid'
import Store from './dynamic-grid/Store'
import GridItemMixin from './dynamic-grid/GridItemMixin'
import asGridItem from './dynamic-grid/asGridItem'

import {DNDActionParameter} from './util/DND'

import UserAvatar from './users/avatar/UserAvatar'
import UsersCompleter from './users/UsersCompleter'
import TeamCreationForm from './users/TeamCreationForm'

import ButtonMenu from './menu/ButtonMenu'
import ContextMenu from './menu/ContextMenu'
import IconButtonMenu from './menu/IconButtonMenu'
import Toolbar from './menu/Toolbar'

import AddressBook from './users/addressbook/AddressBook'

const PydioComponents = {

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
    EmptyStateView          : EmptyStateView,
    SimpleFigureBadge       : SimpleFigureBadge,
    SearchBox               : SearchBox,

    ReactEditorOpener       : ReactEditorOpener,
    PaperEditorLayout       : PaperEditorLayout,
    PaperEditorNavEntry     : PaperEditorNavEntry,
    PaperEditorNavHeader    : PaperEditorNavHeader,

    DynamicGrid             : DynamicGrid,
    DynamicGridItemMixin    : GridItemMixin,
    asGridItem              : asGridItem,
    DynamicGridStore        : Store,

    DNDActionParameter      : DNDActionParameter,

    UserAvatar              : UserAvatar,
    UsersCompleter          : UsersCompleter,
    TeamCreationForm        : TeamCreationForm,
    AddressBook             : AddressBook,

    ContextMenu             : ContextMenu,
    Toolbar                 : Toolbar,
    ButtonMenu              : ButtonMenu,
    IconButtonMenu          : IconButtonMenu
};

export {PydioComponents as default}
