const React = require('react')
const {Paper} = require('material-ui')
import Palette from './Palette'

/**
 * Generic paper with a background color picked from palette
 */
class ColorPaper extends React.Component{

    render(){

        const tint = Palette[this.props.paletteIndex];

        const style = {
            ...this.props.style,
            backgroundColor: tint,
            color: 'white'
        };


        return (
            <Paper zDepth={1} {...this.props} transitionEnabled={false} style={style} className={this.props.className}>
                {this.props.getCloseButton ? this.props.getCloseButton() : this.props.closeButton}
                {this.props.children}
            </Paper>
        );
    }

}

ColorPaper.propTypes = {
    /**
     * Pass the proper style for grid layout
     */
    style: React.PropTypes.object.isRequired,
    /**
     * Legacy way of passing the close button, use closeButton prop instead
     */
    getCloseButton: React.PropTypes.func,
    /**
     * Passed by parent, through the asGridItem HOC
     */
    closeButton: React.PropTypes.object,
    /**
     * An integer to choose which color to pick
     */
    paletteIndex: React.PropTypes.number.isRequired
};

export {ColorPaper as default}