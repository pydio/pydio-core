const CreateRoleOrGroupForm = React.createClass({

    mixins:[
        AdminComponents.MessagesConsumerMixin,
        PydioReactUI.CancelButtonProviderMixin,
        PydioReactUI.SubmitButtonProviderMixin
    ],

    propTypes:{
        type: React.PropTypes.oneOf(['group', 'user', 'role']),
        roleNode: React.PropTypes.instanceOf(AjxpNode),
        openRoleEditor: React.PropTypes.func
    },

    getTitle:function(){
        if(this.props.type == 'group'){
            return this.context.getMessage('ajxp_admin.user.15');
        }else{
            return this.context.getMessage('ajxp_admin.user.14');
        }
    },

    getPadding: function(){
        return true;
    },

    getSize: function() {
        return 'sm'
    },

    dismiss: function() {
        return this.props.onDismiss()
    },

    submit: function() {
        var type = this.props.type;
        var parameters;
        var currentNode;
        if( type == "group"){
            var gId = this.refs.group_id.getValue();
            var gLabel = this.refs.group_label.getValue();
            if(!gId || !gLabel){
                return;
            }
            if(pydio.getContextHolder().getSelectedNodes().length){
                currentNode = pydio.getContextHolder().getSelectedNodes()[0];
            }else{
                currentNode = pydio.getContextNode();
            }
            parameters = {get_action:'create_group', dir:currentNode.getPath(), group_name:gId, group_label:gLabel};

        }else if (type == "role"){
            currentNode = this.props.roleNode;
            parameters = {get_action:'create_role', role_id:this.refs.role_id.getValue()};
        }

        PydioApi.getClient().request(parameters, function(){
            this.dismiss();
            if(currentNode) currentNode.reload();
        }.bind(this));
    },

    render: function(){
        if(this.props.type == 'group'){
            return (
                <div>
                    <ReactMUI.TextField
                        ref="group_id"
                        floatingLabelText={this.context.getMessage('ajxp_admin.user.16')}
                    /><br/>
                    <ReactMUI.TextField
                        ref="group_label"
                        floatingLabelText={this.context.getMessage('ajxp_admin.user.17')}
                    />
                </div>
            );
        }else{
            return (
                <div>
                    <ReactMUI.TextField
                        ref="role_id"
                        floatingLabelText={this.context.getMessage('ajxp_admin.user.18')}
                    /><br/>
                </div>
            );
        }
    }

});

export {CreateRoleOrGroupForm as default}