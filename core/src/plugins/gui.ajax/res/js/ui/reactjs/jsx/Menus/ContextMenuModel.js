export default class ContextMenuModel extends Observable{

    super(){
        this._currentNode = null;
        this._position    = null;
    }

    static getInstance(){
        if(!ContextMenuModel.__INSTANCE) {
            ContextMenuModel.__INSTANCE = new ContextMenuModel();
        }
        return ContextMenuModel.__INSTANCE;
    }

    openAtPosition(clientX, clientY){
        this._currentNode = null;
        this._position    = {x: clientX, y: clientY};
        this.notify("open");
    }

    openNodeAtPosition(node, clientX, clientY){
        this._currentNode = node;
        this._position    = {x: clientX, y: clientY};
        this.notify("open", node);
    }

    getNode(){
        return this._currentNode;
    }

    getPosition(){
        return this._position;
    }

    close(){
        this._currentNode = null;
        this.notify("close");
    }

}