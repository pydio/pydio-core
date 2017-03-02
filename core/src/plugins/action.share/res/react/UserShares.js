(function(global){

    let SharesList = React.createClass({

        render: function(){
            // User Shares List
            let getMessage = function(id) {
                return this.props.pydio.MessageHash[id];
            }.bind(this)

            return (
                <PydioComponents.NodeListCustomProvider
                    nodeProviderProperties={{
                        get_action:"sharelist-load",user_context:"current"
                    }}
                    tableKeys={{
                        shared_element_parent_repository_label:{label:getMessage('ws.39', 'ajxp_admin'), width:'20%'},
                        original_path:{label:getMessage('ws.41', 'ajxp_admin'), width:'80%'},
                        share_type_readable:{label:getMessage('ws.40', 'ajxp_admin'), width:'15%'}
                    }}
                    actionBarGroups={['share_list_toolbar-selection', 'share_list_toolbar']}
                    groupByFields={['share_type_readable','shared_element_parent_repository_label']}
                    defaultGroupBy="shared_element_parent_repository_label"
                    elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                    style={{maxWidth:720}}
                />
            );
        }

    });

    const FakeDndBackend = function(){
        return{
            setup:function(){},
            teardown:function(){},
            connectDragSource:function(){},
            connectDragPreview:function(){},
            connectDropTarget:function(){}
        };
    };

    global.UserShares = {
        SharesList: ReactDND.DragDropContext(FakeDndBackend)(SharesList)
    };

})(window);