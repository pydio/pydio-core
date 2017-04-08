const ResourcesManager = require('pydio/http/resources-manager')

export default function(pydio){

    return {

        openDashboard: function(){
            ResourcesManager.loadClassesAndApply(['PydioForm'], function(){
                pydio.UI.openComponentInModal('UserAccount', 'ModalDashboard');
            });
        },

        openAddressBook: function(){
            ResourcesManager.loadClassesAndApply(['PydioForm', 'PydioComponents'], function() {
                pydio.UI.openComponentInModal('UserAccount', 'ModalAddressBook');
            });
        }


    }

}

