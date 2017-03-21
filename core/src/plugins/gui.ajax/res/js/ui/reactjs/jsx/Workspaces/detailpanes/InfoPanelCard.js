/**
 * Default InfoPanel Card
 */

const styles = {
    card: {
        backgroundColor: 'white'
    }
};

let InfoPanelCard = React.createClass({

    propTypes: {
        title:React.PropTypes.string,
        actions:React.PropTypes.array
    },

    render: function(){
        let iconStyle = this.props.iconStyle || {};
        iconStyle = {...iconStyle, color:this.props.iconColor, float:'right'};
        let icon = this.props.icon ? <div style={iconStyle} className={"panelIcon mdi mdi-" + this.props.icon}/> : null;
        let title = this.props.title ? <div className="panelHeader">{icon}{this.props.title}</div> : null;
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