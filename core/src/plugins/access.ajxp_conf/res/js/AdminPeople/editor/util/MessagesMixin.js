const RoleMessagesConsumerMixin = {
    contextTypes: {
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func,
        getPydioRoleMessage:React.PropTypes.func,
        getRootMessage:React.PropTypes.func
    }
};

const RoleMessagesProviderMixin = {

    childContextTypes: {
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func,
        getPydioRoleMessage:React.PropTypes.func,
        getRootMessage:React.PropTypes.func
    },

    getChildContext: function() {
        var messages = this.context.pydio.MessageHash;
        return {
            messages: messages,
            getMessage: function(messageId, namespace='pydio_role'){
                return messages[namespace + (namespace?".":"") + messageId] || messageId;
            },
            getPydioRoleMessage: function(messageId){
                return messages['role_editor.' + messageId] || messageId;
            },
            getRootMessage: function(messageId){
                return messages[messageId] || messageId;
            }
        };
    }

};

export {RoleMessagesConsumerMixin, RoleMessagesProviderMixin}