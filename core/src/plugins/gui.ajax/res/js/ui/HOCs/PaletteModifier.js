const {Component} = require('react');
const {MuiThemeProvider} = require('material-ui')
const {muiThemeable, getMuiTheme} = require('material-ui/styles')

export default function (palette){

    return function(PydioComponent){

        class PaletteModifier extends Component{

            render(){

                const currentPalette = this.props.muiTheme.palette;
                const newPalette = {...currentPalette, ...palette};
                const muiTheme = getMuiTheme({palette:newPalette});
                const props = {...this.props, muiTheme};
                return (
                    <MuiThemeProvider muiTheme={muiTheme}>
                        <PydioComponent {...props}/>
                    </MuiThemeProvider>
                )

            }

        }

        PaletteModifier = muiThemeable()(PaletteModifier);


        return PaletteModifier;
    }

}