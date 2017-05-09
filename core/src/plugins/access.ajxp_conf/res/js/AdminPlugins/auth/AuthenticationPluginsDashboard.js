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

    getInitialState: function(){
        return {authfrontNode: new AjxpNode('/plugins/manager/authfront')};
    },

    render:function(){
        const pluginsList = <PluginsList
            title={this.context.getMessage('plugtype.title.authfront', '')}
            dataModel={this.props.dataModel}
            node={this.state.authfrontNode}
            rootNode={this.state.authfrontNode}
            openSelection={this.openSelection}
        />;
        return (
            <PluginEditor
                {...this.props}
                style={{...this.props.style, backgroundColor:'#f4f4f4'}}
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