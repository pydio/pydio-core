import Palette from './Palette'

class ColorPaper extends React.Component{

    render(){

        const tint = Palette[this.props.paletteIndex];

        const style = {
            ...this.props.style,
            backgroundColor: tint,
            color: 'white'
        };


        return (
            <MaterialUI.Paper zDepth={1} {...this.props} transitionEnabled={false} style={style} className={this.props.className}>
                {this.props.getCloseButton()}
                {this.props.children}
            </MaterialUI.Paper>
        );
    }

}

ColorPaper.propTypes = {
    style: React.PropTypes.object.isRequired,
    getCloseButton: React.PropTypes.func.isRequired,
    paletteIndex: React.PropTypes.number.isRequired
};

export {ColorPaper as default}