import PluginsList from '../core/PluginsList'
import PluginEditor from '../core/PluginEditor'

const AuthenticationPluginsDashboard = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    openSelection: function(node){
        this.props.openRightPane({
            COMPONENT:PluginEditor,
            PROPS:{
                rootNode:node,
                docAsAdditionalPane:true,
                className:"vertical edit-plugin-inpane",
                closeEditor:this.props.closeRightPane
            },
            CHILDREN:null
        });
    },

    render:function(){
        var fakeNode = new AjxpNode('/plugins/manager/authfront');
        var pluginsList = <PluginsList
            title={this.context.getMessage('plugtype.title.authfront', '')}
            dataModel={this.props.dataModel}
            node={fakeNode}
            rootNode={fakeNode}
            openSelection={this.openSelection}
        />;
        return (
            <PluginEditor
                {...this.props}
                additionalPanes={{top:[pluginsList], bottom:[]}}
                tabs={[
                    {label:this.context.getMessage('plugins.1'), groups:[0,1,2,6]}, // general
                    {label:this.context.getMessage('plugins.2'), groups:[3]}, // master driver
                    {label:this.context.getMessage('plugins.3'), groups:[4,5]} // secondary driver
                ]}
            />
        );
    }

});

export {AuthenticationPluginsDashboard as default}