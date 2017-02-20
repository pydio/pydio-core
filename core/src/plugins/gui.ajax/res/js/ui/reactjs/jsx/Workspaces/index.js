import OpenNodesModel from './OpenNodesModel'
import MainFilesList from './MainFilesList'
import Breadcrumb from './Breadcrumb'
import SearchForm from './SearchForm'
import FilePreview from './FilePreview'
import FSTemplate from './FSTemplate'
import EditionPanel from './EditionPanel'

window.PydioWorkspaces = {
    OpenNodesModel      : OpenNodesModel,
    MainFilesList       : MainFilesList,
    EditionPanel        : EditionPanel,
    Breadcrumb          : Breadcrumb,
    SearchForm          : SearchForm,
    FilePreview         : FilePreview,
    FSTemplate          : FSTemplate
}

import InfoPanel from './detailpanes/InfoPanel'
import InfoPanelCard from './detailpanes/InfoPanelCard'
import GenericMultiple from './detailpanes/GenericMultiple'
import GenericFile from './detailpanes/GenericFile'
import GenericDir from './detailpanes/GenericDir'
import ImagePreview from './detailpanes/ImagePreview'

window.PydioDetailPanes = {
    InfoPanel: InfoPanel,
    InfoPanelCard: InfoPanelCard,
    GenericMultiple: GenericMultiple,
    GenericFile: GenericFile,
    GenericDir: GenericDir,
    ImagePreview: ImagePreview
};
