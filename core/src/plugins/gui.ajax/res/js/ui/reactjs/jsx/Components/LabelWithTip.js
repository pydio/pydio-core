export default React.createClass({

    propTypes: {
        label:React.PropTypes.string,
        labelElement:React.PropTypes.object,
        tooltip:React.PropTypes.string,
        tooltipClassName:React.PropTypes.string,
        className:React.PropTypes.string,
        style:React.PropTypes.object
    },

    getInitialState:function(){
        return {show:false};
    },

    show:function(){this.setState({show:true});},
    hide:function(){this.setState({show:false});},

    render:function(){
        if(this.props.tooltip){
            let tooltipStyle={};
            if(this.props.label || this.props.labelElement){
                if(this.state.show){
                    tooltipStyle = {bottom: -10, top: 'inherit'};
                }
            }else{
                tooltipStyle = {position:'relative'};
            }
            let label;
            if(this.props.label){
                label = <span className="ellipsis-label">{this.props.label}</span>;
            }else if(this.props.labelElement){
                label = this.props.labelElement;
            }
            let style = this.props.style || {position:'relative'};

            return (
                <span onMouseEnter={this.show} onMouseLeave={this.hide} style={style} className={this.props.className}>
                        {label}
                    {this.props.children}
                    <ReactMUI.Tooltip label={this.props.tooltip} style={tooltipStyle} className={this.props.tooltipClassName} show={this.state.show}/>
                    </span>
            );
        }else{
            if(this.props.label) {
                return <span>{this.props.label}</span>;
            } else if(this.props.labelElement) {
                return this.props.labelElement;
            } else {
                return <span>{this.props.children}</span>;
            }
        }
    }

});
