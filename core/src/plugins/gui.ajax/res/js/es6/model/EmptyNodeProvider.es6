class EmptyNodeProvider extends Observable{

    constructor(){
        super();
    }

    initProvider(properties){
        this.properties = properties;
    }

    /**
     *
     * @param node AjxpNode
     * @param nodeCallback Function
     * @param childCallback Function
     */
    loadNode(node, nodeCallback, childCallback){

    }

    loadLeafNodeSyncfunction(node, callback){

    }

}