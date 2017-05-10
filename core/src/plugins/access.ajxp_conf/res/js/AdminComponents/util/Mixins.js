const MessagesConsumerMixin = {
    contextTypes: {
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func
    }
};

const MessagesProviderMixin = {

    childContextTypes: {
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func
    },

    getChildContext: function() {
        var messages = this.props.pydio.MessageHash;
        return {
            messages: messages,
            getMessage: function(messageId, namespace='ajxp_admin'){
                try{
                    return messages[namespace + (namespace?".":"") + messageId] || messageId;
                }catch(e){
                    return messageId;
                }
            }
        };
    }

};

const PydioConsumerMixin = {
    contextTypes:{
        pydio:React.PropTypes.instanceOf(Pydio)
    }
};

const PydioProviderMixin = {
    childContextTypes:{
        pydio:React.PropTypes.instanceOf(Pydio)
    },

    getChildContext: function(){
        return {
            pydio: this.props.pydio
        };
    }
};

export {MessagesConsumerMixin, MessagesProviderMixin, PydioConsumerMixin, PydioProviderMixin};