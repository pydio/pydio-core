import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'

export default React.createClass({

    mixins:[RoleMessagesConsumerMixin],

    propTypes:{
        userId:React.PropTypes.string.isRequired,
        sharedWorkspaces:React.PropTypes.object,
        workspacesDetails:React.PropTypes.object
    },

    render: function(){
        return (
            <div className="vertical-layout" style={{padding:16,height:'100%'}}>
                <h2>{this.context.getMessage('52')}</h2>
                <ReactMUI.Paper zDepth={1} className="workspace-activity-block layout-fill vertical-layout">
                    <PydioComponents.NodeListCustomProvider
                        title={this.context.getMessage('ws.25', 'ajxp_admin')}
                        nodeProviderProperties={{
                            get_action:"sharelist-load",
                            user_id:this.props.userId,
                            user_context:"user"
                        }}
                        tableKeys={{
                            shared_element_parent_repository_label:{label:this.context.getMessage('ws.39', 'ajxp_admin'), width:'20%'},
                            original_path:{label:this.context.getMessage('ws.41', 'ajxp_admin'), width:'80%'},
                            share_type_readable:{label:this.context.getMessage('ws.40', 'ajxp_admin'), width:'15%'}
                        }}
                        actionBarGroups={['share_list_toolbar-selection', 'share_list_toolbar']}
                        groupByFields={['share_type_readable','shared_element_parent_repository_label']}
                        defaultGroupBy="shared_element_parent_repository_label"
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                    />
                </ReactMUI.Paper>
            </div>
        );
    }
});
