export default function(PydioComponent, pydio){

    return React.createClass({

        propTypes:{
            pydio: React.PropTypes.instanceOf(Pydio).isRequired
        },

        childContextTypes: {

            /* Current Instance of Pydio */
            pydio:React.PropTypes.instanceOf(Pydio),
            /* Accessor for pydio */
            getPydio:React.PropTypes.func,

            /* Associative array of i18n messages */
            messages:React.PropTypes.object,
            /* Accessor for messages */
            getMessage:React.PropTypes.func

        },

        getChildContext: function() {

            const messages = pydio.MessageHash;
            return {
                pydio       : pydio,
                messages    : messages,
                getMessage  : function(messageId){
                    try{
                        return messages[messageId] || messageId;
                    }catch(e){
                        return messageId;
                    }
                },
                getPydio    : function(){
                    return pydio;
                }
            };

        },

        render: function(){
            return <PydioComponent {...this.props}/>
        }

    });

}