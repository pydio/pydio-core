const PydioApi = require('pydio/http/api')

export default function (pydio) {

    return function(){

        PydioApi.getClient().postSelectionWithAction('restore');

    }

}