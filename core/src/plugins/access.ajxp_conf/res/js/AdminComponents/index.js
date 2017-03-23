import AdminDashboard from './board/AdminDashboard'
import SimpleDashboard from './board/SimpleDashboard'
import GroupAdminDashboard from './board/GroupAdminDashboard'

import {MessagesConsumerMixin, PydioConsumerMixin} from './util/Mixins'
import NavigationHelper from './util/NavigationHelper'
import MenuItemListener from './util/MenuItemListener'
import DNDActionsManager from './util/DNDActionsManager'

import ParametersPicker from './dialog/ParametersPicker'
import PermissionMaskEditor from './permissions/PermissionMaskEditor'
import PermissionMaskEditorFree from './permissions/PermissionMaskEditorFree'

window.AdminComponents = {
    MessagesConsumerMixin   : MessagesConsumerMixin,
    PydioConsumerMixin      : PydioConsumerMixin,
    NavigationHelper        : NavigationHelper,
    MenuItemListener        : MenuItemListener,
    DNDActionsManager       : DNDActionsManager,

    ParametersPicker        : ParametersPicker,
    PermissionMaskEditor    : PermissionMaskEditor,
    PermissionMaskEditorFree: PermissionMaskEditorFree,

    AdminDashboard          : AdminDashboard,
    SimpleDashboard         : SimpleDashboard,
    GroupAdminDashboard     : GroupAdminDashboard,
};