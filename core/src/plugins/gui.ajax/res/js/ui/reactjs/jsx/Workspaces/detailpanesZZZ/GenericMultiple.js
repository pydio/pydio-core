import InfoPanelCard from './InfoPanelCard'

export default React.createClass({

    render: function(){
        let nodes = this.props.nodes;
        let more;
        if(nodes.length > 10){
            const moreNumber = nodes.length - 10;
            nodes = nodes.slice(0, 10);
            more = <div>... and {moreNumber} more.</div>
        }
        return (
            <InfoPanelCard>
                <div style={{padding:'0'}}>
                    {nodes.map(function(node){
                        return (
                            <div style={{display:'flex', alignItems:'center', borderBottom:'1px solid #eeeeee'}}>
                                <PydioWorkspaces.FilePreview
                                    key={node.getPath()}
                                    style={{height:50, width:50, fontSize: 25}}
                                    node={node}
                                    loadThumbnail={true}
                                    richPreview={true}
                                />
                                <div style={{flex:1, fontSize:14, marginLeft:6}}>{node.getLabel()}</div>
                            </div>
                        );
                    })}
                    {more}
                </div>
                <PydioMenus.Toolbar className="primaryToolbar" renderingType="button-icon" toolbars={["info_panel", "info_panel_share"]} controller={this.props.pydio.getController()}/>
            </InfoPanelCard>
        );
    }

});
