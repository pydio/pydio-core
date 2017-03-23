import Workspace from '../model/Workspace'

export default React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes:{
        model:React.PropTypes.instanceOf(Workspace).isRequired
    },

    getInitialState: function(){
        return {clearing: false};
    },

    clearBrokenLinks: function(){
        this.setState({clearing:true});
        PydioApi.getClient().request({
            get_action:"sharelist-load",
            parent_repository_id:this.props.model.wsId,
            user_context:"global",
            clear_broken_links: "true"
        }, function(t){
            let count = t.responseJSON["cleared_count"];
            if(count){
                pydio.displayMessage('SUCCESS', 'Removed ' + count + ' broken links');
                this.refs.list.reload();
            }else{
                pydio.displayMessage('SUCCESS', 'Nothing to do');
            }
            this.setState({clearing:false});
        }.bind(this));
    },

    render: function(){

        return (
            <div className="layout-fill vertical-layout">
                <div style={{position:'absolute',right:20,top:90}}><ReactMUI.RaisedButton label={this.state.clearing?"Processing...":"Clear broken links"} disabled={this.state.clearing} onClick={this.clearBrokenLinks}/></div>
                <h1  className="workspace-general-h1">{this.context.getMessage('ws.38')}</h1>
                <ReactMUI.Paper zDepth={1} className="workspace-activity-block layout-fill vertical-layout">
                    <PydioComponents.NodeListCustomProvider
                        ref="list"
                        title={this.context.getMessage('ws.25')}
                        nodeProviderProperties={{
                            get_action:"sharelist-load",
                            parent_repository_id:this.props.model.wsId,
                            user_context:"global"
                        }}
                        tableKeys={{
                            owner:{label:this.context.getMessage('ws.39'), width:'20%'},
                            share_type_readable:{label:this.context.getMessage('ws.40'), width:'15%'},
                            original_path:{label:this.context.getMessage('ws.41'), width:'80%'}
                        }}
                        actionBarGroups={['share_list_toolbar-selection', 'share_list_toolbar']}
                        groupByFields={['owner','share_type_readable']}
                        defaultGroupBy="share_type_readable"
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                    />
                </ReactMUI.Paper>
            </div>
        );
    }
});