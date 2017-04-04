import {pydio} from '../globals'

export default function () {

    if(!pydio.repositoryId || pydio.repositoryId != "ajxp_user"){
        pydio.triggerRepositoryChange('ajxp_user');
    }

}