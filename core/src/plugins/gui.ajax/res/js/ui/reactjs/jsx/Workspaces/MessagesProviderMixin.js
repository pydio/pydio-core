export default {

    childContextTypes: {
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func
    },

    getChildContext: function() {
        var messages = this.props.pydio.MessageHash;
        return {
            messages: messages,
            getMessage: function(messageId){
                try{
                    return messages[messageId] || messageId;
                }catch(e){
                    return messageId;
                }
            }
        };
    }

};

