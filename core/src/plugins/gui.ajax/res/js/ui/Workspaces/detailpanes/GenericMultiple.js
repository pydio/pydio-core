import InfoPanelCard from './InfoPanelCard'
import FilePreview from '../FilePreview'

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
            <InfoPanelCard {...this.props} primaryToolbars={["info_panel", "info_panel_share"]}>
                <div style={{padding:'0'}}>
                    {nodes.map(function(node){
                        return (
                            <div style={{display:'flex', alignItems:'center', borderBottom:'1px solid #eeeeee'}}>
                                <FilePreview
                                    key={node.getPath()}
                                    style={{height:50, width:50, fontSize: 25}}
                                    node={node}
                                    loadThumbnail={true}
                                    richPreview={false}
                                />
                                <div style={{flex:1, fontSize:14, marginLeft:6}}>{node.getLabel()}</div>
                            </div>
                        );
                    })}
                    {more}
                </div>
            </InfoPanelCard>
        );
    }

});
