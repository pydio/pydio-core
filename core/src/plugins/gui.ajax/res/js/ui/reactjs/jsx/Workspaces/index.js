import OpenNodesModel from './OpenNodesModel'
import MainFilesList from './MainFilesList'
import Breadcrumb from './Breadcrumb'
import SearchForm from './SearchForm'
import FilePreview from './FilePreview'
import FSTemplate from './FSTemplate'
import EditionPanel from './EditionPanel'

import WorkspacesList from './wslist/WorkspacesList'
import LeftPanel from './leftnav/LeftPanel'
import DynamicLeftPanel from './leftnav/DynamicLeftPanel'
import UserWidget from './leftnav/UserWidget'

window.PydioWorkspaces = {
    OpenNodesModel      : OpenNodesModel,
    MainFilesList       : MainFilesList,
    EditionPanel        : EditionPanel,
    Breadcrumb          : Breadcrumb,
    SearchForm          : SearchForm,
    FilePreview         : FilePreview,
    FSTemplate          : FSTemplate,
    WorkspacesList      : WorkspacesList,
    LeftPanel           : LeftPanel,
    DynamicLeftPanel    : DynamicLeftPanel,
    UserWidget          : UserWidget
}

import InfoPanel from './detailpanes/InfoPanel'
import InfoPanelCard from './detailpanes/InfoPanelCard'
import GenericMultiple from './detailpanes/GenericMultiple'
import GenericFile from './detailpanes/GenericFile'
import GenericDir from './detailpanes/GenericDir'
import ImagePreview from './detailpanes/ImagePreview'
import RootNode from './detailpanes/RootNode'

window.PydioDetailPanes = {
    InfoPanel: InfoPanel,
    InfoPanelCard: InfoPanelCard,
    GenericMultiple: GenericMultiple,
    GenericFile: GenericFile,
    GenericDir: GenericDir,
    ImagePreview: ImagePreview,
    RootNode:RootNode
};
