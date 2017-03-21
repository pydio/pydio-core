/**
 * Default InfoPanel Card
 */

const styles = {
    card: {
        backgroundColor: 'rgba(250,250,250,0.8)'
    }
};

let InfoPanelCard = React.createClass({

    propTypes: {
        title:React.PropTypes.string,
        actions:React.PropTypes.array
    },

    render: function(){
        let title = this.props.title ? <div className="panelHeader">{this.props.title}</div> : null;
        let actions = this.props.actions ? <div className="panelActions">{this.props.actions}</div> : null;
        let rows, toolBar;
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
        if(this.props.primaryToolbars){
            const themePalette = this.props.muiTheme.palette;
            const tBarStyle = {
                backgroundColor: themePalette.accent2Color
            };
            toolBar = (
                <PydioMenus.Toolbar
                    toolbarStyle={tBarStyle}
                    className="primaryToolbar"
                    renderingType="button-icon"
                    toolbars={this.props.primaryToolbars}
                    controller={this.props.pydio.getController()}
                />
            );
        }

        return (
            <MaterialUI.Paper zDepth={1} className="panelCard" style={styles.card}>
                {title}
                <div className="panelContent" style={this.props.contentStyle}>
                    {this.props.children}
                    {rows}
                    {toolBar}
                </div>
                {actions}
            </MaterialUI.Paper>
        );
    }

});

InfoPanelCard = MaterialUI.Style.muiThemeable()(InfoPanelCard);
export {InfoPanelCard as default}