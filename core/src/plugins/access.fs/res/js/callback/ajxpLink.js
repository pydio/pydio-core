const FuncUtils = require('pydio/util/func')

export default function (pydio) {

    return function(){
        let link;
        let url = global.document.location.href;
        if(url.indexOf('#') > 0){
            url = url.substring(0, url.indexOf('#'));
        }
        if(url.indexOf('?') > 0){
            url = url.substring(0, url.indexOf('?'));
        }
        let repoId = pydio.repositoryId || (pydio.user ? pydio.user.activeRepository : null);
        if(pydio.user){
            const slug = pydio.user.repositories.get(repoId).getSlug();
            if(slug) repoId = slug;
        }
        link = LangUtils.trimRight(url, '/') + pydio.getUserSelection().getUniqueNode().getPath();

        pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
            dialogTitleId:369,
            fieldLabelId:296,
            defaultValue:link,
            submitValue:FuncUtils.Empty
        });


    }

}