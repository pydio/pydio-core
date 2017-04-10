import InfoPanelCard from './InfoPanelCard'
import FilePreview from '../FilePreview'

class GenericInfoCard extends React.Component {

    constructor(props) {
        super(props)

        this.build(props)

        this.state = {
            ready: false
        }
    }

    componentWillReceiveProps(nextProps) {
        this.build(nextProps)
    }

    build(props) {
        let isMultiple, isLeaf, isDir;

        // Determine if we have a multiple selection or a single
        const {node, nodes} = props

        if (nodes) {
            isMultiple = true
        } else if (node) {
            isLeaf = node.isLeaf()
            isDir = !isLeaf;
        } else {
            return
        }

        this.setState({
            isMultiple,
            isLeaf,
            isDir,
            ready: true
        })
    }

    render() {

        if (!this.state.ready) {
            return null
        }

        if (this.state.isMultiple) {
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
                                        richPreview={true}
                                    />
                                    <div style={{flex:1, fontSize:14, marginLeft:6}}>{node.getLabel()}</div>
                                </div>
                            );
                        })}
                        {more}
                    </div>
                </InfoPanelCard>
            );
        } else if (this.state.isDir) {
            return (
                <InfoPanelCard {...this.props} primaryToolbars={["info_panel", "info_panel_share"]}>
                    <div className="mimefont-container" style={{width:'100%', height:200}}>
                        <div className={"mimefont mdi mdi-" + this.props.node.getMetadata().get('fonticon')}></div>
                    </div>
                </InfoPanelCard>
            );
        } else if (this.state.isLeaf) {
            return (
                <InfoPanelCard {...this.props} primaryToolbars={["info_panel", "info_panel_share"]}>
                    <FilePreview
                        key={this.props.node.getPath()}
                        style={{height:200}}
                        node={this.props.node}
                        loadThumbnail={true}
                        richPreview={true}
                    />
                </InfoPanelCard>
            )
        }

        return null
    }
}

export {GenericInfoCard as default}
