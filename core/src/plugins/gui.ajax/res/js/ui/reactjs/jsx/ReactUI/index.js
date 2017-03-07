// Import Builder class
import Builder from './Builder'
import TemplateBuilder from './TemplateBuilder'
import AsyncComponent from './AsyncComponent'

import AsyncModal from './modal/AsyncModal'
import ActionDialogMixin from './modal/ActionDialogMixin'
import CancelButtonProviderMixin from './modal/CancelButtonProviderMixin'
import SubmitButtonProviderMixin from './modal/SubmitButtonProviderMixin'
import Modal from './modal/Modal'
import ConfirmDialog from './modal/ConfirmDialog'
import PromptDialog from './modal/PromptDialog'

import MessageBar from './modal/MessageBar'
import AbstractDialogModifier from './modal/AbstractDialogModifier'
import Loader from './Loader'
import Router from './router/Router'
import NetworkLoader from './modal/NetworkLoader'
import HiddenDownloadForm from './HiddenDownloadForm'

import PydioContextProvider from './PydioContextProvider'
import PydioContextConsumerMixin from './PydioContextConsumerMixin'

window.PydioReactUI = {
    Builder                     : Builder,
    TemplateBuilder             : TemplateBuilder,
    AsyncComponent              : AsyncComponent,

    AsyncModal                  : AsyncModal,
    ActionDialogMixin           : ActionDialogMixin,
    CancelButtonProviderMixin   : CancelButtonProviderMixin,
    SubmitButtonProviderMixin   : SubmitButtonProviderMixin,
    AbstractDialogModifier      : AbstractDialogModifier,
    Modal                       : Modal,
    ConfirmDialog               : ConfirmDialog,
    PromptDialog                : PromptDialog,

    Loader                      : Loader,
    Router                      : Router,
    MessageBar                  : MessageBar,
    NetworkLoader               : NetworkLoader,
    HiddenDownloadForm          : HiddenDownloadForm,

    PydioContextProvider        : PydioContextProvider,
    PydioContextConsumerMixin   : PydioContextConsumerMixin

};
