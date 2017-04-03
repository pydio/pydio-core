import OpenNodesModel from './OpenNodesModel'
import MainFilesList from './MainFilesList'
import Breadcrumb from './Breadcrumb'
import SearchForm from './SearchForm'
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
import InfoGenericMultiple from './detailpanes/GenericMultiple'
import InfoGenericFile from './detailpanes/GenericFile'
import InfoGenericDir from './detailpanes/GenericDir'
import InfoRootNode from './detailpanes/RootNode'

window.PydioWorkspaces = {
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
    InfoGenericMultiple,
    InfoGenericFile,
    InfoGenericDir,
    InfoRootNode
}