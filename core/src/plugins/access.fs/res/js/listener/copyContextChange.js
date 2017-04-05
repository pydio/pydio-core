export default function(pydio) {

    return function(){

        this.rightsContext.write = true;
        const pydioUser = pydio.user;
        if(pydioUser && pydioUser.canRead() && pydioUser.canCrossRepositoryCopy() && pydioUser.hasCrossRepositories()){
            this.rightsContext.write = false;
            if(!pydioUser.canWrite()){
                pydio.getController().defaultActions['delete']('ctrldragndrop');
                pydio.getController().defaultActions['delete']('dragndrop');
            }
        }
        if(pydioUser && pydioUser.canWrite() && pydio.getContextNode().hasAjxpMimeInBranch("ajxp_browsable_archive")){
            this.rightsContext.write = false;
        }
        if(pydio.getContextNode().hasAjxpMimeInBranch("ajxp_browsable_archive")){
            this.setLabel(247, 248);
            this.setIconSrc('ark_extract.png');
        }else{
            this.setLabel(66, 159);
            this.setIconSrc('editcopy.png');
        }
    }


}

