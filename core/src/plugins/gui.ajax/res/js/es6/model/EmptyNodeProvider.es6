import Observable from '../lang/Observable'

export default class EmptyNodeProvider extends Observable{

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

    loadLeafNodeSync(node, callback){

    }

}