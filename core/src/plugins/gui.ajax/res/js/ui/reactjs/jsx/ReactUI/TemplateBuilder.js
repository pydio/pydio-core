import AsyncComponent from './AsyncComponent'

export default React.createClass({

    propTypes: {
        pydio: React.PropTypes.instanceOf(Pydio),
        containerId:React.PropTypes.string
    },

    render: function(){

        let pydio = this.props.pydio;
        let containerId = this.props.containerId;

        let components = [];

        let parts = XMLUtils.XPathSelectNodes(pydio.getXmlRegistry(), "client_configs/template_part[@component]");
        parts.map(function(node){
            if(node.getAttribute("theme") && node.getAttribute("theme") != pydio.Parameters.get("theme")){
                return;
            }
            if(containerId !== node.getAttribute("ajxpId")){
                return;
            }

            let namespace = node.getAttribute("namespace");
            let componentName = node.getAttribute("component");


            let props = {};
            if(node.getAttribute("props")){
                props = JSON.parse(node.getAttribute("props"));
            }
            props['pydio']      = pydio;

            components.push(
                <AsyncComponent
                    namespace={namespace}
                    componentName={componentName}
                    {...props}
                />
            );

        }.bind(this));

        if(components.length === 1) return components[0];
        else return <div id={this.props.containerId}>{components}</div>

    }


});