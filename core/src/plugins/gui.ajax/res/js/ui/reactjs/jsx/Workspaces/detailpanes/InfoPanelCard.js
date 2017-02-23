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
        let rows;
        if(this.props.standardData){
            rows = this.props.standardData.map(function(object){
                return (
                    <div className="infoPanelRow" key={object.key}>
                        <div className="infoPanelLabel">{object.label}</div>
                        <div className="infoPanelValue">{object.value}</div>
                    </div>
                );
            });
        }

        return (
            <ReactMUI.Paper zDepth={1} className="panelCard">
                {title}
                <div className="panelContent">{this.props.children}{rows}</div>
                {actions}
            </ReactMUI.Paper>
        );
    }

});