const Callbacks = {
    switchLanguage : require('./callbacks/switchLanguage'),
    userCreateRepository: require('./callbacks/userCreateRepository'),
    changePass: require('./callbacks/changePass'),
    launchIndexation: require('./callbacks/launchIndexation'),
    toggleBookmark: require('./callbacks/toggleBookmark'),
    clearPluginsCache: require('./callbacks/clearPluginsCache'),
    dismissUserAlert: require('./callbacks/dismissUserAlert'),
    activateDesktopNotifications: require('./callbacks/activateDesktopNotifications')
}

const Navigation = {
    splash: require('./navigation/splash'),
    up: require('./navigation/up'),
    refresh: require('./navigation/refresh'),
    externalSelection: require('./navigation/externalSelection'),
    openGoPro: require('./navigation/openGoPro'),
    switchToSettings: require('./navigation/switchToSettings'),
    switchToUserDashboard: require('./navigation/switchToUserDashboard')
}

import SplashDialog from './dialog/SplashDialog'
import PasswordDialog from './dialog/PasswordDialog'

export {Callbacks, Navigation, SplashDialog, PasswordDialog}