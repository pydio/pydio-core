class AbstractEditor{
    
    static getSvgSource(ajxpNode){
        return ajxpNode.getMetadata().get("fonticon");
    }
    
}

export {AbstractEditor as default}