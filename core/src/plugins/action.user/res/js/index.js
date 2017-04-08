import CallbaksWrapper from './Callbacks'
import ModalDashboard from './ModalDashboard'
import ModalAddressBook from './ModalAddressBook'

import WebDAVPane from './WebdavPane'

const Callbacks = CallbaksWrapper(window.pydio);

export {
    Callbacks, ModalDashboard, ModalAddressBook, WebDAVPane
}