import InfoPanelCard from './InfoPanelCard'

export default React.createClass({

    render: function(){
        return (
            <InfoPanelCard>
                <PydioWorkspaces.FilePreview
                    key={this.props.node.getPath()}
                    style={{height:200}}
                    node={this.props.node}
                    loadThumbnail={true}
                    richPreview={true}
                />
                <PydioMenus.Toolbar className="primaryToolbar" renderingType="button-icon" toolbars={["info_panel", "info_panel_share"]} controller={this.props.pydio.getController()}/>
            </InfoPanelCard>
        );
    }

});
