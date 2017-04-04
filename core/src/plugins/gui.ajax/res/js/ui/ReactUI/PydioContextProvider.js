const React = require('react')
const Pydio = require('pydio')

export default function(PydioComponent, pydio){

    class Wrapped extends React.Component{

        getChildContext() {

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

        }

        render(){

            const customPalette = pydio.Parameters.get('palette') || {};

            const themeCusto = {
                palette: {
                    primary1Color       : MaterialUI.Style.colors.blueGrey600,
                    primary2Color       : MaterialUI.Style.colors.deepOrange500,
                    accent1Color        : MaterialUI.Style.colors.deepOrange500,
                    accent2Color        : MaterialUI.Style.colors.lightBlue500,
                    ...customPalette
                }
            };

            const theme = MaterialUI.Style.getMuiTheme(themeCusto);

            return (
                <MaterialUI.MuiThemeProvider muiTheme={theme}>
                    <PydioComponent {...this.props}/>
                </MaterialUI.MuiThemeProvider>
            );
        }

    }

    Wrapped.displayName = 'PydioContextProvider';
    Wrapped.propTypes={
        pydio: React.PropTypes.instanceOf(Pydio).isRequired
    };
    Wrapped.childContextTypes={
        /* Current Instance of Pydio */
        pydio:React.PropTypes.instanceOf(Pydio),
        /* Accessor for pydio */
        getPydio:React.PropTypes.func,

        /* Associative array of i18n messages */
        messages:React.PropTypes.object,
        /* Accessor for messages */
        getMessage:React.PropTypes.func
    };

    return Wrapped

}