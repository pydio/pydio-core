import PluginsList from './PluginsList'
import PluginEditor from './PluginEditor'

const CoreAndPluginsDashboard = React.createClass({

    render:function(){
        var coreId = PathUtils.getBasename(this.props.rootNode.getPath());
        if(coreId.indexOf("core.") !== 0) coreId = "core." + coreId ;
        var fakeNode = new AjxpNode('/' + coreId);
        var pluginsList = <PluginsList {...this.props} title={this.props.rootNode.getLabel()}/>;
        return (
            <PluginEditor
                rootNode={fakeNode}
                additionalPanes={{top:[], bottom:[pluginsList]}}
            />
        );
    }

});

export {CoreAndPluginsDashboard as default}