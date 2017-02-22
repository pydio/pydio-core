import AsyncComponent from './AsyncComponent'

class BackgroundImage{

    static getImageBackgroundFromConfig(configName, forceConfigs){

        var bgrounds,paramPrefix,bStyles,index, i;
        if(forceConfigs){
            bgrounds = forceConfigs;
            paramPrefix = configName;
            bStyles = [];
            index = 1;
            while(bgrounds[paramPrefix+index]){
                bStyles.push({
                    backgroundImage:"url('"+bgrounds[paramPrefix+index]+"')",
                    backgroundSize:"cover",
                    backgroundPosition:"center center"
                });
                index++;
            }
            if (bStyles.length) {
                i = Math.floor( Math.random() * bStyles.length);
                return bStyles[i];
            }
            return {};
        }
        return BackgroundImage.computeBackgroundFromConfigs(configName);

    }

    static computeBackgroundFromConfigs(configName, important){

        var bgrounds,paramPrefix,bStyles,index, i;

        var exp = configName.split("/");
        var plugin = exp[0];
        paramPrefix = exp[1];
        var registry = ajaxplorer.getXmlRegistry();
        var configs = XPathSelectNodes(registry, "plugins/*[@id='"+plugin+"']/plugin_configs/property[contains(@name, '"+paramPrefix+"')]");
        var defaults = XPathSelectNodes(registry, "plugins/*[@id='"+plugin+"']/server_settings/global_param[contains(@name, '"+paramPrefix+"')]");


        bgrounds = {};
        configs.map(function(c){
            bgrounds[c.getAttribute("name")] = c.firstChild.nodeValue.replace(/"/g, '');
        });
        defaults.map(function(d){
            if(!d.getAttribute('defaultImage')) return;
            var n = d.getAttribute("name");
            if(!bgrounds[n]){
                bgrounds[n] = d.getAttribute("defaultImage");
            }else{
                if(getBaseName(bgrounds[n]) == bgrounds[n]){
                    bgrounds[n] = window.ajxpServerAccessPath+"&get_action=get_global_binary_param&binary_id="+bgrounds[n];
                }
            }
        });
        bStyles = [];
        index = 1;
        while(bgrounds[paramPrefix+index]){
            bStyles.push({
                backgroundImage:"url('"+bgrounds[paramPrefix+index]+"')" + (important?' !important':''),
                backgroundSize:"cover",
                backgroundPosition:"center center"
            });
            index++;
        }
        let windowWith = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        if(windowWith < 600 && bgrounds[paramPrefix+'LOWRES']){
            // This is probably a mobile, let's force switching to low res.
            bStyles = [{
                backgroundImage:"url('"+bgrounds[paramPrefix+'LOWRES']+"')" + (important?' !important':''),
                backgroundSize:"cover",
                backgroundPosition:"center center"
            }];
        }
        if (bStyles.length) {
            i = Math.floor( Math.random() * bStyles.length);
            var bg = bStyles[i];
            return bStyles[i];
        }
        return {};

    }


}

export default React.createClass({

    propTypes: {
        pydio: React.PropTypes.instanceOf(Pydio),
        containerId:React.PropTypes.string
    },

    render: function(){

        let pydio = this.props.pydio;
        let containerId = this.props.containerId;

        let components = [];
        let style = {};
        if(this.props.imageBackgroundFromConfigs){
            if(BackgroundImage.SESSION_IMAGE){
                style = BackgroundImage.SESSION_IMAGE;
            }else{
                style = BackgroundImage.getImageBackgroundFromConfig(this.props.imageBackgroundFromConfigs);
                BackgroundImage.SESSION_IMAGE = style;
            }
        }

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
                    style={style}
                />
            );

        }.bind(this));

        if(components.length === 1) return components[0];
        else {
            return <div style={style} id={this.props.containerId}>{components}</div>
        }

    }


});