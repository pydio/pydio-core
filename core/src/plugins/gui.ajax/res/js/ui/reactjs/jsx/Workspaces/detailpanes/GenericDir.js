import InfoPanelCard from './InfoPanelCard'

let GenericDir = React.createClass({

    render: function(){

        const themePalette = this.props.muiTheme.palette;
        const tBarStyle = {
            backgroundColor: themePalette.primary1Color
        };

        return (
            <InfoPanelCard>
                <div className="mimefont-container"><div className={"mimefont mdi mdi-" + this.props.node.getMetadata().get('fonticon')}></div></div>
                <PydioMenus.Toolbar toolbarStyle={tBarStyle} className="primaryToolbar" renderingType="button-icon" toolbars={["info_panel", "info_panel_share"]} controller={this.props.pydio.getController()}/>
            </InfoPanelCard>
        );
    }

});

GenericDir = MaterialUI.Style.muiThemeable()(GenericDir);

export {GenericDir as default}