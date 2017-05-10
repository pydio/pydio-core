const PydioApi = require('pydio/http/api')

export default function (pydio) {

    return function(type, selection, path, wsId){
        let action, params = {dest:path};
        if(wsId) {
            action = 'cross_copy';
            params['dest_repository_id'] = wsId;
            if(type === 'move') params['moving_files'] = 'true';
        } else {
            action = type;
        }
        PydioApi.getClient().postSelectionWithAction(action, null, selection, params);
    }

}