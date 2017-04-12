export default React.createClass({

    propTypes: {
        closeDialog: React.PropTypes.func,
        userId: React.PropTypes.string.isRequired
    },

    getInitialState: function () {
        return {okEnabled: false};
    },

    onChange: function (event, value) {
        var minLength = parseInt(global.pydio.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"));

        var enabled = (this.refs.pass.getValue()
            && this.refs.pass.getValue().length >= minLength
            && this.refs.pass.getValue() == this.refs.confirm.getValue()
        );

        this.setState({okEnabled: enabled});
    },

    submit: function () {
        var value = this.refs.pass.getValue();
        PydioApi.getClient().request({
                get_action: "edit",
                sub_action: "update_user_pwd",
                user_id: this.props.userId,
                user_pwd: value
            }, function () {
                this.dismiss()
            }.bind(this)
        );
    },

    dismiss: function () {
        this.props.closeDialog();
    },

    render: function () {

        // This is passed via state, context is not working,
        // so we have to get the messages from the global.
        var getMessage = function (id, namespace='') {
            return global.pydio.MessageHash[namespace + (namespace ? '.' : '') + id] || id;
        };

        var actions = [
            <ReactMUI.FlatButton key="can" label={getMessage('54')} onClick={this.dismiss}/>,
            <ReactMUI.FlatButton key="next" label={getMessage('25','role_editor')}
                                 onClick={this.submit} disabled={!this.state.okEnabled}/>
        ];
        return (
            <ReactMUI.Dialog
                modal={true}
                actions={actions}
                title={getMessage('25', 'role_editor')}
                dismissOnClickAway={true}
                openImmediately={true}
                contentClassName="dialog-max-480"
            >
                <ReactMUI.TextField ref="pass" type="password"
                                    onChange={this.onChange}
                                    floatingLabelText={getMessage('523')}/><br/>
                <ReactMUI.TextField ref="confirm" type="password"
                                    onChange={this.onChange}
                                    floatingLabelText={getMessage('199')}/>
            </ReactMUI.Dialog>
        );

    }

});
