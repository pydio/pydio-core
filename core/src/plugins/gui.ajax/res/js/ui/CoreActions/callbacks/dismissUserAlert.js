import {pydio} from '../globals'
const PydioApi = require('pydio/http/api')

export default function (manager, args){
    let selection = args[0];
    if(selection.getUniqueNode) {
        selection = selection.getUniqueNode();
    }
    if(selection && selection.getMetadata && selection.getMetadata().get("alert_id")){
        const elMeta = selection.getMetadata();
        let params = {
            get_action:'dismiss_user_alert',
            alert_id:elMeta.get('alert_id')
        };
        if(elMeta.get("event_occurence")){
            params['occurrences'] = elMeta.get("event_occurence");
        }
        PydioApi.getClient().request(params, function(){
            pydio.notify("server_message:tree/reload_user_feed");
        });
    }
}
