import {pydio} from '../globals'
const PydioApi = require('pydio/http/api')

export default function(){

    let crtDir = pydio.getContextHolder().getContextNode().getPath();
    if(!pydio.getUserSelection().isEmpty()){
        crtDir = pydio.getUserSelection().getUniqueNode().getPath();
    }
    PydioApi.getClient().request({
        get_action:"index",
        file:crtDir
    }, function(transport){});


}