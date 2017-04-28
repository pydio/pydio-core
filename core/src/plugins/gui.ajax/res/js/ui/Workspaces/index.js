import OpenNodesModel from './OpenNodesModel'
import MainFilesList from './MainFilesList'
import Breadcrumb from './Breadcrumb'
import {SearchForm} from './search'
import FilePreview from './FilePreview'
import FSTemplate from './FSTemplate'
import EditionPanel from './EditionPanel'

import WorkspacesList from './wslist/WorkspacesList'
import WorkspacesListMaterial from './wslist/WorkspacesListMaterial'
import LeftPanel from './leftnav/LeftPanel'
import DynamicLeftPanel from './leftnav/DynamicLeftPanel'
import UserWidget from './leftnav/UserWidget'

import InfoPanel from './detailpanes/InfoPanel'
import InfoPanelCard from './detailpanes/InfoPanelCard'
import InfoRootNode from './detailpanes/RootNode'

import GenericInfoCard from './detailpanes/GenericInfoCard'
import FileInfoCard from './detailpanes/FileInfoCard'

import * as EditorActions from './editor/actions'

const classes = {
    OpenNodesModel,
    MainFilesList,
    EditionPanel,
    Breadcrumb,
    SearchForm,
    FilePreview,
    FSTemplate,
    WorkspacesList,
    WorkspacesListMaterial,
    LeftPanel,
    DynamicLeftPanel,
    UserWidget,

    InfoPanel,
    InfoPanelCard,
    InfoRootNode,
    FileInfoCard,
    GenericInfoCard,
    EditorActions
}

export {classes as default}
