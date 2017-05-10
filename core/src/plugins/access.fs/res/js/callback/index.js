let pydio = global.pydio;
let MessageHash = global.MessageHash;

const Callbacks = {
    ls                      : require('./ls')(pydio),
    mkdir                   : require('./mkdir')(pydio),
    mkfile                  : require('./mkfile')(pydio),
    deleteAction            : require('./deleteAction')(pydio),
    rename                  : require('./rename')(pydio),
    applyCopyOrMove         : require('./applyCopyOrMove')(pydio),
    copy                    : require('./copy')(pydio),
    move                    : require('./move')(pydio),
    upload                  : require('./upload')(pydio),
    download                : require('./download')(pydio),
    downloadAll             : require('./downloadAll')(pydio),
    downloadChunked         : require('./downloadChunked')(pydio),
    emptyRecycle            : require('./emptyRecycle')(pydio),
    restore                 : require('./restore')(pydio),
    compressUI              : require('./compressUI')(pydio),
    openInEditor            : require('./openInEditor')(pydio),
    ajxpLink                : require('./ajxpLink')(pydio),
    chmod                   : require('./chmod')(pydio),
    openOtherEditorPicker   : require('./openOtherEditorPicker')(pydio),
}

export {Callbacks as default}