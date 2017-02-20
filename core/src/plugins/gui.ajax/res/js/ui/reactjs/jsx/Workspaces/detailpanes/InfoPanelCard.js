/**
 * Default InfoPanel Card
 */
export default React.createClass({

    propTypes: {
        title:React.PropTypes.string,
        actions:React.PropTypes.array
    },

    render: function(){
        let title = this.props.title ? <div className="panelHeader">{this.props.title}</div> : null;
        let actions = this.props.actions ? <div className="panelActions">{this.props.actions}</div> : null;

        return (
            <ReactMUI.Paper zDepth={1} className="panelCard">
                {title}
                <div className="panelContent">{this.props.children}</div>
                {actions}
            </ReactMUI.Paper>
        );
    }

});