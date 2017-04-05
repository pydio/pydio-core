const pydio = global.pydio;

const Listeners  = {
    downloadSelectionChange     : require('./downloadSelectionChange')(pydio),
    downloadAllInit             : require('./downloadAllInit')(pydio),
    compressUiSelectionChange   : require('./compressUiSelectionChange')(pydio),
    copyContextChange           : require('./copyContextChange')(pydio),
    openWithDynamicBuilder      : require('./openWithDynamicBuilder')(pydio),
}

export {Listeners as default}