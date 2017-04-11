import {Component, PropTypes} from 'react';
const Pydio = require('pydio');
import {colors, getMuiTheme} from 'material-ui/styles';
import {MuiThemeProvider} from 'material-ui';

let MainProvider = MuiThemeProvider
let DND;
try{
    DND = require('react-dnd');
    const Backend = require('react-dnd-html5-backend');
    MainProvider = DND.DragDropContext(Backend)(MainProvider);
}catch(e){}

export default function(PydioComponent, pydio){

    class Wrapped extends Component{

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
                <MainProvider muiTheme={theme}>
                    <PydioComponent {...this.props}/>
                </MainProvider>
            );
        }

    }

    Wrapped.displayName = 'PydioContextProvider';
    Wrapped.propTypes={
        pydio       : PropTypes.instanceOf(Pydio).isRequired
    };
    Wrapped.childContextTypes={
        /* Current Instance of Pydio */
        pydio       :PropTypes.instanceOf(Pydio),
        /* Accessor for pydio */
        getPydio    :PropTypes.func,

        /* Associative array of i18n messages */
        messages    :PropTypes.object,
        /* Accessor for messages */
        getMessage  :PropTypes.func
    };

    return Wrapped;
}
