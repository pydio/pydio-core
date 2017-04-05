const React = require('react')
const Pydio = require('pydio')
const {MuiThemeProvider} = require('material-ui')
const {colors, getMuiTheme} = require('material-ui/styles')

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
                    primary1Color       : colors.blueGrey600,
                    primary2Color       : colors.deepOrange500,
                    accent1Color        : colors.deepOrange500,
                    accent2Color        : colors.lightBlue500,
                    ...customPalette
                }
            };

            const theme = getMuiTheme(themeCusto);

            return (
                <MuiThemeProvider muiTheme={theme}>
                    <PydioComponent {...this.props}/>
                </MuiThemeProvider>
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

    return Wrapped;

}