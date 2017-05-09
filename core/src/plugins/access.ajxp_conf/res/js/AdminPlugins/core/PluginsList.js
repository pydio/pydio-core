import PluginEditor from './PluginEditor'
import {Toggle, IconButton} from 'material-ui'

const PluginsList = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    togglePluginEnable:function(node, toggled) {
        var nodeId = PathUtils.getBasename(node.getPath());
        var params = {
            get_action: "edit",
            sub_action: "edit_plugin_options",
            plugin_id: nodeId,
            DRIVER_OPTION_AJXP_PLUGIN_ENABLED: toggled ? "true" : "false",
            DRIVER_OPTION_AJXP_PLUGIN_ENABLED_ajxptype: "boolean"
        };
        PydioApi.getClient().request(params, function (transport) {
            node.getMetadata().set("enabled", this.context.getMessage(toggled?'440':'441', ''));
            this.forceUpdate();
            pydio.fire("admin_clear_plugins_cache");
        }.bind(this));
        return true;
    },

    renderListIcon:function(node){
        if(!node.isLeaf()){
            return (
                <div>
                    <div className="icon-folder-open" style={{fontSize: 24,color: 'rgba(0,0,0,0.63)', padding: '20px 25px', display: 'block'}}></div>
                </div>
            );
        }
        var onToggle = function(e, toggled){
            e.stopPropagation();
            var res = this.togglePluginEnable(node, toggled);
            if(!res){

            }
        }.bind(this);

        return (
            <div style={{margin:'24px 8px'}} onClick={(e) => {e.stopPropagation()}}>
                <Toggle
                    ref="toggle"
                    className="plugin-enable-toggle"
                    name="plugin_toggle"
                    value="plugin_enabled"
                    defaultToggled={node.getMetadata().get("enabled") == this.context.getMessage('440', '')}
                    toggled={node.getMetadata().get("enabled") == this.context.getMessage('440', '')}
                    onToggle={onToggle}
                />
            </div>
        );
    },

    renderSecondLine:function(node){
        return node.getMetadata().get('plugin_description');
    },

    renderActions:function(node){
        if(!node.isLeaf()){
            return null;
        }
        var edit = function(){
            if(this.props.openRightPane){
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
            }
        }.bind(this);
        return (
            <div className="plugins-list-actions">
                <IconButton iconStyle={{color: 'rgba(0,0,0,0.33)', fontSize:21}} style={{padding:6}} iconClassName="mdi mdi-pencil" onClick={edit}/>
            </div>
        );
    },

    reload: function(){
        this.refs.list.reload();
    },

    render:function(){

        return (
            <PydioComponents.SimpleList
                ref="list"
                node={this.props.currentNode || this.props.rootNode}
                dataModel={this.props.dataModel}
                className="plugins-list"
                actionBarGroups={[]}
                entryRenderIcon={this.renderListIcon}
                entryRenderActions={this.renderActions}
                entryRenderSecondLine={this.renderSecondLine}
                openEditor={this.props.openSelection}
                infineSliceCount={1000}
                filterNodes={null}
                listTitle={this.props.title}
                elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES}
            />
        );
    }

});

export {PluginsList as default}