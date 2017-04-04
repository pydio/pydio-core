const PydioApi = require('pydio/http/api')

export default function(){
    PydioApi.getClient().request({get_action:'clear_plugins_cache'});
}