const PydioApi = require('pydio/http/api')

export default function (pydio) {

    return function(){
        const callback = function(node, newValue){
            if(!node) node = pydio.getUserSelection().getUniqueNode();
            PydioApi.getClient().request({
                get_action:'rename',
                file:node.getPath(),
                filename_new: newValue
            });
        };
        const n = pydio.getUserSelection().getSelectedNodes()[0];
        if(n){
            let res = n.notify("node_action", {type:"prompt-rename", callback:(value)=>{callback(n, value);}});
            if((!res || res[0] !== true) && n.getParent()){
                n.getParent().notify("child_node_action", {type:"prompt-rename", child:n, callback:(value)=>{callback(n, value);}});
            }
        }
    }

}