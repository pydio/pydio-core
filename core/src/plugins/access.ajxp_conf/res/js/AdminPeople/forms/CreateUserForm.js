const CreateUserForm = React.createClass({

    propTypes:{
        dataModel: React.PropTypes.instanceOf(PydioDataModel)
    },

    mixins:[
        AdminComponents.MessagesConsumerMixin,
        PydioReactUI.ActionDialogMixin,
        PydioReactUI.CancelButtonProviderMixin,
        PydioReactUI.SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogSize:'sm',
            dialogTitleId: 'ajxp_admin.user.19'
        }
    },

    checkPassword:function(){
        var value1 = this.refs.pass.getValue();
        var value2 = this.refs.passconf.getValue();
        var minLength = parseInt(global.pydio.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"));
        if(value1 && value1.length < minLength){
            this.refs.pass.setErrorText(this.context.getMessage('378'));
            return;
        }
        if(value1 && value2 && value2 != value1){
            this.refs.passconf.setErrorText(this.context.getMessage('238'));
            return;
        }
        this.refs.pass.setErrorText(null);
        this.refs.passconf.setErrorText(null);
    },

    getInitialState: function(){
        return {
            step:1
        }
    },

    submit: function(dialog){
        var parameters = {};
        var ctx = this.props.dataModel.getUniqueNode() || this.props.dataModel.getContextNode();
        parameters['get_action'] = 'create_user';
        parameters['new_user_login'] = this.refs.user_id.getValue();
        parameters['new_user_pwd'] = this.refs.pass.getValue();
        var currentPath = ctx.getPath();
        if(currentPath.startsWith("/data/users")){
            parameters['group_path'] = currentPath.substr("/data/users".length);
        }
        PydioApi.getClient().request(parameters, function(transport){
            var xml = transport.responseXML;
            var message = XMLUtils.XPathSelectSingleNode(xml, "//reload_instruction");
            if(message){
                var node = new AjxpNode(currentPath + "/"+ parameters['new_user_login'], true);
                node.getMetadata().set("ajxp_mime", "user");
                global.pydio.UI.openCurrentSelectionInEditor(node);
                var currentNode = global.pydio.getContextNode();
                if(global.pydio.getContextHolder().getSelectedNodes().length){
                    currentNode = global.pydio.getContextHolder().getSelectedNodes()[0];
                }
                currentNode.reload();
            }
        }.bind(this));
        this.dismiss();
    },

    render: function(){
        var ctx = this.props.dataModel.getUniqueNode() || this.props.dataModel.getContextNode();
        var currentPath = ctx.getPath();
        var path;
        if(currentPath.startsWith("/data/users")){
            path = currentPath.substr("/data/users".length);
            if(path){
                path = <div>{this.context.getMessage('ajxp_admin.user.20').replace('%s', path)}</div>;
            }
        }
        return (
            <div>
                {path}
                <div>
                    <ReactMUI.TextField
                        ref="user_id"
                        floatingLabelText={this.context.getMessage('ajxp_admin.user.21')}
                    />
                </div>
                <div>
                    <ReactMUI.TextField
                        ref="pass"
                        type="password"
                        floatingLabelText={this.context.getMessage('ajxp_admin.user.22')}
                        onChange={this.checkPassword}
                    />
                </div>
                <div>
                    <ReactMUI.TextField
                        ref="passconf"
                        type="password"
                        floatingLabelText={this.context.getMessage('ajxp_admin.user.23')}
                        onChange={this.checkPassword}
                    />
                </div>
            </div>
        );
    }
});

export {CreateUserForm as default}