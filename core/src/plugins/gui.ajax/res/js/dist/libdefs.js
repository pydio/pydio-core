const PydioCoreRequires = {
    'lang/Observable.js'        :'pydio/lang/observable',
    'lang/Logger.js'            :'pydio/lang/logger',
    'util/LangUtils.js'         :'pydio/util/lang',
    'util/FuncUtils.js'         :'pydio/util/func',
    'util/XMLUtils.js'          :'pydio/util/xml',
    'util/PathUtils.js'         :'pydio/util/path',
    'util/HasherUtils.js'       :'pydio/util/hasher',
    'util/PassUtils.js'         :'pydio/util/pass',
    'util/DOMUtils.js'          :'pydio/util/dom',
    'util/CookiesManager.js'    :'pydio/util/cookies',
    'util/PeriodicalExecuter.js':'pydio/util/periodical-executer',
    'util/ActivityMonitor.js'   :'pydio/util/activity-monitor',
    'model/AjxpNode.js'         :'pydio/model/node',
    'model/User.js'             :'pydio/model/user',
    'model/RemoteNodeProvider.js':'pydio/model/remote-node-provider',
    'model/EmptyNodeProvider.js':'pydio/model/empty-node-provider',
    'model/Repository.js'       :'pydio/model/repository',
    'model/Action.js'           :'pydio/model/action',
    'model/Controller.js'       :'pydio/model/controller',
    'model/PydioDataModel.js'   :'pydio/model/data-model',
    'model/Registry.js'         :'pydio/model/registry',
    'model/ContextMenu'         :'pydio/model/context-menu',
    'http/Connexion.js'         :'pydio/http/connexion',
    'http/ResourcesManager.js'  :'pydio/http/resources-manager',
    'http/PydioApi.js'          :'pydio/http/api',
    'http/PydioUsersApi.js'     :'pydio/http/users-api',
    'http/MetaCacheService.js'  :'pydio/http/meta-cache-service',
    'Pydio'                     :'pydio'
};

const LibRequires = [ // modules we want to require and export
    'react',
    'react-dom',
    'react-addons-pure-render-mixin',
    'react-addons-css-transition-group',
    'react-addons-update',
    'material-ui-legacy',
    'material-ui',
    'material-ui/styles',
    'color',
    'react-infinite',
    'react-draggable',
    'react-grid-layout',
    'react-chartjs',
    'react-select',
    'react-dnd',
    'react-dnd-html5-backend',
    'lodash/function/flow',
    'lodash.debounce',
    'classnames',
    'react-autosuggest',
    'clipboard',
    'qrcode.react',
    'cronstrue',
    'react-tap-event-plugin',
    'whatwg-fetch',
    'systemjs',
    'redux'
]

const Externals = Object.keys(PydioCoreRequires).map(function(key){
    return PydioCoreRequires[key];
}).concat(LibRequires);

const DistConfig = {
    PydioCoreRequires,
    LibRequires,
    Externals
};

module.exports = DistConfig;
