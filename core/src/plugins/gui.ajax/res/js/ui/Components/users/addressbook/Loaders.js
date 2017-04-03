class Loaders{

    static childrenAsPromise(item, leaf = false){

        const {childrenLoader, itemsLoader, leafLoaded, collectionsLoaded, leafs, collections} = item;
        let loader = leaf ? itemsLoader : childrenLoader;
        let loaded = leaf ? leafLoaded : collectionsLoaded;
        return new Promise((resolve, reject) => {
            if(!loaded && loader){
                loader(item, (newChildren)=>{
                    if(leaf) {
                        item.leafs = newChildren;
                        item.leafLoaded = true;
                    }else {
                        item.collections = newChildren;
                        item.collectionsLoaded = true;
                    }
                    resolve(newChildren);
                });
            }else{
                const res = ( leaf ? leafs : collections ) || [];
                resolve(res);
            }
        });

    }

    static listUsers(params, callback, parent = null){
        let baseParams = {get_action:'user_list_authorized_users',format:'json'};
        baseParams = {...baseParams, ...params};
        let cb = callback;
        if(parent){
            cb = (children) => {
                callback(children.map(function(c){ c._parent = parent; return c; }));
            };
        }
        PydioApi.getClient().request(baseParams, function(transport){
            cb(transport.responseJSON);
        });
    }

    static loadTeams(entry, callback){
        const wrapped = (children) => {
            children.map(function(child){
                child.icon = 'mdi mdi-account-multiple';
                child.itemsLoader = Loaders.loadTeamUsers;
                child.actions = {
                    type    :'team',
                    create  :'Add User to Team',
                    remove  :'Remove from Team',
                    multiple: true
                };
                child._notSelectable=true;
            });
            callback(children);
        };
        Loaders.listUsers({filter_value:8}, wrapped, entry);
    }

    static loadGroups(entry, callback){
        const wrapped = (children) => {
            children.map(function(child){
                child.icon = 'mdi mdi-account-multiple';
                child.childrenLoader = Loaders.loadGroups;
                child.itemsLoader = Loaders.loadGroupUsers;
            });
            callback(children);
        };
        const path = entry.id.replace('AJXP_GRP_', '');
        Loaders.listUsers({filter_value:4, group_path:path}, wrapped, entry);
    }

    static loadExternalUsers(entry, callback){
        Loaders.listUsers({filter_value:2}, callback, entry);
    }

    static loadGroupUsers(entry, callback){
        const path = entry.id.replace('AJXP_GRP_', '');
        Loaders.listUsers({filter_value:1, group_path:path}, callback, entry);
    }

    static loadTeamUsers(entry, callback){
        Loaders.listUsers({filter_value:3, group_path:entry.id}, callback, entry);
    }

}

export {Loaders as default}