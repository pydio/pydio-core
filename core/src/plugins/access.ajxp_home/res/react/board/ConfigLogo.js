class ConfigLogo extends React.Component{

    render(){
        let logo = this.props.pydio.Registry.getPluginConfigs(this.props.pluginName).get(this.props.pluginParameter);
        let url;
        if(!logo){
            logo = this.props.pydio.Registry.getDefaultImageFromParameters(this.props.pluginName, this.props.pluginParameter);
        }
        if(logo){
            if(logo.indexOf('plugins/') === 0){
                url = logo;
            }else{
                url = this.props.pydio.Parameters.get('ajxpServerAccess') + "&get_action=get_global_binary_param&binary_id=" + logo;
            }
        }
        return <img src={url} style={this.props.style}/>
    }
}

export {ConfigLogo as default}