// Backward Compat Table
const
    Connexion = require('./http/Connexion'),
    MetaCacheService = require('./http/MetaCacheService'),
    PydioApi = require('./http/PydioApi'),
    ResourcesManager = require('./http/ResourcesManager'),
    Logger = require('./lang/Logger'),
    Observable = require('./lang/Observable'),
    Action = require('./model/Action'),
    AjxpNode = require('./model/AjxpNode'),
    Controller = require('./model/Controller'),
    EmptyNodeProvider = require('./model/EmptyNodeProvider'),
    PydioDataModel = require('./model/PydioDataModel'),
    Registry = require('./model/Registry'),
    RemoteNodeProvider = require('./model/RemoteNodeProvider'),
    Repository = require('./model/Repository'),
    User = require('./model/User'),
    ContextMenu = require('./model/ContextMenu'),
    CookiesManager = require('./util/CookiesManager'),
    DOMUtils = require('./util/DOMUtils'),
    FuncUtils = require('./util/FuncUtils'),
    HasherUtils = require('./util/HasherUtils'),
    LangUtils = require('./util/LangUtils'),
    PassUtils = require('./util/PassUtils'),
    PathUtils = require('./util/PathUtils'),
    PeriodicalExecuter = require('./util/PeriodicalExecuter'),
    ActivityMonitor = require('./util/ActivityMonitor'),
    XMLUtils = require('./util/XMLUtils'),
    Pydio = require('./Pydio');

import * as UsersApi from './http/PydioUsersApi';
const PydioUsers = {
    Client: UsersApi.UsersApi,
    User  : UsersApi.User
}

const namespace = {
    Connexion,
    MetaCacheService,
    PydioApi,
    PydioUsers,
    ResourcesManager,
    Logger,
    Observable,
    Action,
    AjxpNode,
    Controller,
    EmptyNodeProvider,
    PydioDataModel,
    Registry,
    RemoteNodeProvider,
    Repository,
    User,
    ContextMenu,
    CookiesManager,
    DOMUtils,
    FuncUtils,
    HasherUtils,
    LangUtils,
    PassUtils,
    PathUtils,
    PeriodicalExecuter,
    ActivityMonitor,
    XMLUtils,
    Pydio
};

Object.assign(window, {...namespace, PydioCore: namespace});
