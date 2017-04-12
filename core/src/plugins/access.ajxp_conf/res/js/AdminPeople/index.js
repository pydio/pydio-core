import Dashboard from './board/Dashboard'
import CreateUserForm from './forms/CreateUserForm'
import CreateRoleOrGroupForm from './forms/CreateRoleOrGroupForm'
import Editor from './editor/Editor'
import UserPasswordDialog from './editor/user/UserPasswordDialog'
import UserRolesPicker from './editor/user/UserRolesPicker'
import WorkspacesList from './editor/panel/WorkspacesList'
import SharesList from './editor/panel/SharesList'
import {RoleMessagesConsumerMixin} from './editor/util/MessagesMixin'
import ParameterCreate from './editor/parameters/ParameterCreate'

window.AdminPeople = {
    RoleEditor              : Editor,
    RoleMessagesConsumerMixin,
    UserPasswordDialog,
    UserRolesPicker,
    WorkspacesList,
    SharesList,
    CreateUserForm,
    CreateRoleOrGroupForm,
    ParameterCreate,

    Dashboard
};