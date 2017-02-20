import InfoPanelCard from './InfoPanelCard'

export default React.createClass({

    render: function(){
        return (
            <InfoPanelCard>
                <div className="mimefont-container"><div className={"mimefont mdi mdi-" + this.props.node.getMetadata().get('fonticon')}></div></div>
                <PydioMenus.Toolbar className="primaryToolbar" renderingType="button-icon" toolbars={["info_panel", "info_panel_share"]} controller={this.props.pydio.getController()}/>
            </InfoPanelCard>
        );
    }

});