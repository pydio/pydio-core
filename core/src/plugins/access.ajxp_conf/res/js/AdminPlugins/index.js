import PluginsManager from './core/Manager'
import PluginsList from './core/PluginsList'
import PluginEditor from './core/PluginEditor'
import CoreAndPluginsDashboard from './core/CoreAndPluginsDashboard'

import AuthenticationPluginsDashboard from './auth/AuthenticationPluginsDashboard'
import EditorsDashboard from './editors/EditorsDashboard'
import UpdaterDashboard from './updater/UpdaterDashboard'
import CacheServerDashboard from './cache/CacheServerDashboard'
import DiagnosticDashboard from './diagnostic/DiagnosticDashboard'

window.AdminPlugins = {

    PluginsManager                  : PluginsManager,
    PluginEditor                    : PluginEditor,
    PluginsList                     : PluginsList,
    CoreAndPluginsDashboard         : CoreAndPluginsDashboard,

    AuthenticationPluginsDashboard  : AuthenticationPluginsDashboard,
    EditorsDashboard                : EditorsDashboard,
    UpdaterDashboard                : UpdaterDashboard,
    CacheServerDashboard            : CacheServerDashboard,
    DiagnosticDashboard             : DiagnosticDashboard


};
