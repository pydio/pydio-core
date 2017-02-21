// Import Builder class
import Builder from './Builder'
import TemplateBuilder from './TemplateBuilder'
import AsyncComponent from './AsyncComponent'

import AsyncModal from './modal/AsyncModal'
import ActionDialogMixin from './modal/ActionDialogMixin'
import CancelButtonProviderMixin from './modal/CancelButtonProviderMixin'
import SubmitButtonProviderMixin from './modal/SubmitButtonProviderMixin'
import Modal from './modal/Modal'
import AbstractDialogModifier from './modal/AbstractDialogModifier'

window.PydioReactUI = {
    Builder             : Builder,
    TemplateBuilder     : TemplateBuilder,
    AsyncComponent      : AsyncComponent,

    AsyncModal              : AsyncModal,
    ActionDialogMixin       : ActionDialogMixin,
    CancelButtonProviderMixin : CancelButtonProviderMixin,
    SubmitButtonProviderMixin : SubmitButtonProviderMixin,
    AbstractDialogModifier      : AbstractDialogModifier,
    Modal                       : Modal

};