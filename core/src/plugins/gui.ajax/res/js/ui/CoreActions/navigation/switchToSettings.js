import {pydio} from '../globals'

export default function () {

    if(!pydio.repositoryId || pydio.repositoryId != "ajxp_conf"){
        pydio.triggerRepositoryChange('ajxp_conf');
    }

}