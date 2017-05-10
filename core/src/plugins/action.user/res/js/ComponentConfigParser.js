const XMLUtils = require('pydio/util/xml')

export default {

    getAccountTabs: function(pydio){

        return XMLUtils.XPathSelectNodes(pydio.getXmlRegistry(), 'client_configs/component_config[@component="UserAccountTabs"]/additional_tab').map(function(node){
            return {
                id: node.getAttribute("id"),
                tabInfo: JSON.parse(node.getAttribute('tabInfo')),
                paneInfo: JSON.parse(node.getAttribute('paneInfo'))
            };

        });

    }

}
