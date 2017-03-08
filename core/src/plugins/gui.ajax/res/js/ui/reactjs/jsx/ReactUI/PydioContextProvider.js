export default function(PydioComponent, pydio){

    return React.createClass({

        displayName: 'PydioContextProvider',

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
                getMessage  : function(messageId, namespace = ''){
                    if(namespace){
                        messageId = namespace + '.' + messageId ;
                    }
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

            const themeCusto = {
                palette: {
                    primary1Color       : MaterialUI.Style.colors.blueGrey500,
                    primary2Color       : MaterialUI.Style.colors.deepOrange500,
                    accent1Color        : MaterialUI.Style.colors.deepOrange500
                }
            };

            const theme = MaterialUI.Style.getMuiTheme(themeCusto);

            return (
                <MaterialUI.MuiThemeProvider muiTheme={theme}>
                    <PydioComponent {...this.props}/>
                </MaterialUI.MuiThemeProvider>
            );
        }

    });

}