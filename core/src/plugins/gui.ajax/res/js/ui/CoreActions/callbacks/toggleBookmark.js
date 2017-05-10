import {pydio} from '../globals'
const PydioApi = require('pydio/http/api')

export default function(){
    const selection = pydio.getContextHolder();
    if(selection.isEmpty() || !selection.isUnique()){
        return;
    }
    const node = selection.getUniqueNode();
    const isBookmarked = node.getMetadata().get('ajxp_bookmarked') === 'true';
    PydioApi.getClient().request({
        get_action:'get_bookmarks',
        bm_action: isBookmarked ? 'delete_bookmark' : 'add_bookmark',
        bm_path:node.getPath()
    }, (t) => {
        selection.requireNodeReload(node);
    });
}