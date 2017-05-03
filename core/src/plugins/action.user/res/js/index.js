import CallbaksWrapper from './Callbacks'
import ModalDashboard from './ModalDashboard'
import ModalAddressBook from './ModalAddressBook'

import WebDAVPane from './WebdavPane'
import WelcomeModal from './WelcomeModal'

const Callbacks = CallbaksWrapper(window.pydio);

export {
    Callbacks, ModalDashboard, ModalAddressBook, WebDAVPane, WelcomeModal
}