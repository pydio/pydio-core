class OpenNodesModel extends Observable{

    constructor(){
        super();
        this._openNodes = [];
        this._updatedTitles = new Map();
        pydio.UI.registerEditorOpener(this);
        pydio.observe("repository_list_refreshed", function(){
            this._openNodes = [];
        }.bind(this));
    }

    static getInstance(){
        if(!OpenNodesModel.__INSTANCE){
            OpenNodesModel.__INSTANCE = new OpenNodesModel();
        }
        return OpenNodesModel.__INSTANCE;
    }

    openEditorForNode(selectedNode, editorData){
        this.pushNode(selectedNode, editorData);
    }

    updateNodeTitle(object, newTitle){
        this._updatedTitles.set(object, newTitle);
        this.notify('titlesUpdated');
    }

    getObjectLabel(object){
        if(this._updatedTitles.has(object)){
            return this._updatedTitles.get(object);
        }else{
            return object.node.getLabel();
        }
    }

    pushNode(node, editorData){
        let found = false;
        let editorClass = editorData ? editorData.editorClass : null;
        let object = {node:node, editorData:editorData};
        this.notify('willPushNode', object);
        this._openNodes.map(function(o){
            if(o.node === node && (o.editorData && o.editorData.editorClass == editorClass) || (!o.editorData && !editorClass)){
                found = true;
                object = o;
            }
        });
        if(!found){
            this._openNodes.push(object);
        }
        this.notify('nodePushed', object);
        this.notify('update', this._openNodes);
    }

    removeNode(object){
        this.notify('willRemoveNode', object);
        let index = this._openNodes.indexOf(object);
        if(this._updatedTitles.has(object)){
            this._updatedTitles.delete(object);
        }
        this._openNodes = LangUtils.arrayWithout(this._openNodes, index);
        this.notify('nodeRemovedAtIndex', index);
        this.notify('update', this._openNodes);
    }

    getNodes(){
        return this._openNodes;
    }

}

export {OpenNodesModel as default}