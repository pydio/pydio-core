/**
 * Simple MuiPaper with a figure and a legend
 */
export default React.createClass({

    propTypes:{
        colorIndicator:React.PropTypes.string,
        figure:React.PropTypes.number.isRequired,
        legend:React.PropTypes.string
    },

    getDefaultProps:function(){
        return {
            colorIndicator: ''
        }
    },

    render: function(){
        return (
            <ReactMUI.Paper style={{display:'inline-block', marginLeft:16}}>
                <div className="figure-badge" style={(this.props.colorIndicator?{borderLeftColor:this.props.colorIndicator}:{})}>
                    <div className="figure">{this.props.figure}</div>
                    <div className="legend">{this.props.legend}</div>
                </div>
            </ReactMUI.Paper>
        );
    }
});

