const React = require('react')
import Palette from '../board/Palette'
const Color = require('color')
const {asGridItem, NodeListCustomProvider} = require('pydio').requireLib('components')
const {FilePreview} = require('pydio').requireLib('workspaces')
const {Paper, IconButton} = require('material-ui')

const PALETTE_INDEX = 3;

/**
 * Show a list of recently accessed files or folders.
 * This list is stored by the server in the user preferences, and served by the feed plugin
 */
let RecentAccessCard = React.createClass({

    getDefaultProps: function(){
        return {colored: true};
    },

    renderIcon: function(node){
        if(node.getPath() === '/' || !node.getPath()){
            return <div className="mimefont-container"><div className="mimefont" style={{fontSize: 14}}>WS</div></div>
        }else{
            return <FilePreview node={node} loadThumbnail={true}/>
        }
    },

    renderLabel: function(node, data){
        const path = node.getPath();
        const meta = node.getMetadata();
        if(!path || path === '/'){
            return <span style={{fontSize: 14}}>{meta.get('repository_label')} <span style={{opacity: 0.33}}> (Workspace)</span></span>;
        }else{
            const dir = PathUtils.getDirname(node.getPath());
            let dirSegment;
            if(dir){
                dirSegment = <span style={{opacity: 0.33}}> ({node.getPath()})</span>
            }
            if(node.isLeaf()){
                return <span><span style={{fontSize: 14}}>{node.getLabel()}</span>{dirSegment}</span>;
            }else{
                return <span><span style={{fontSize: 14}}>{'/' + node.getLabel()}</span>{dirSegment}</span>;
            }
        }
    },

    renderAction: function(node, data){
        return <span style={{position:'relative'}}><IconButton
            iconClassName="mdi mdi-chevron-right"
            tooltip="Open ... "
            onTouchTap={() => {this.props.pydio.goTo(node)}}
            style={{position:'absolute', right:0}}
        /></span>
    },

    renderFirstLine: function(node){
        if(!node.getPath() || node.getPath() === '/'){
            return node.getMetadata().get('repository_label');
        }else{
            return node.getLabel();
        }
    },

    renderSecondLine: function(node){
        return node.getMetadata().get('recent_access_readable');
    },

    render: function(){
        const {colored} = this.props;
        const c = new Color(Palette[PALETTE_INDEX]);
        let title;
        if(!this.props.noTitle){
            if(colored){
                title = <div style={{backgroundColor:c.darken(0.1).toString(),color:'white', padding:'16px 0 16px 12px', fontSize:20}}>{this.props.pydio.MessageHash['user_home.87']}</div>;
            }else{
                title = <div style={{padding:'16px 0 16px 12px', fontSize:20}}>{this.props.pydio.MessageHash['user_home.87']}</div>;
            }
        }

        const displayMode = this.props.displayMode || 'list';

        if(displayMode === 'table'){
            return (
                <Paper zDepth={this.props.zDepth !== undefined ? this.props.zDepth : 1} {...this.props} className="vertical-layout" transitionEnabled={false}>
                    {this.getCloseButton()}
                    <NodeListCustomProvider
                        className="recently-accessed-list table-mode"
                        nodeProviderProperties={{get_action:"load_user_recent_items"}}
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                        nodeClicked={(node) => {this.props.pydio.goTo(node);}}
                        hideToolbar={true}
                        tableKeys={{
                            label:{renderCell:this.renderLabel, label:'Recently Accessed Files', width:'60%'},
                            recent_access_readable:{label:'Accessed', width:'20%'},
                            repository_label:{label:'Workspace', width:'20%'},
                        }}
                        entryRenderActions={this.renderAction}
                    />
                </Paper>
            );
        }else{
            return (
                <Paper zDepth={this.props.zDepth !== undefined ? this.props.zDepth :1} {...this.props} className={"vertical-layout " + (this.props.className || '')} transitionEnabled={false}>
                    {this.props.closeButton}
                    {title}
                    <NodeListCustomProvider
                        className={this.props.listClassName?this.props.listClassName:"recently-accessed-list files-list"}
                        style={{backgroundColor:colored?Palette[PALETTE_INDEX]:'transparent'}}
                        nodeProviderProperties={{get_action:"load_user_recent_items"}}
                        elementHeight={63}
                        nodeClicked={(node) => {this.props.pydio.goTo(node);}}
                        hideToolbar={true}
                        delayInitialLoad={700}
                        entryRenderFirstLine={this.renderFirstLine}
                        entryRenderSecondLine={this.renderSecondLine}
                        entryRenderIcon={this.renderIcon}
                        emptyStateProps={this.props.emptyStateProps}
                    />
                </Paper>
            );
        }
    }

});

RecentAccessCard = asGridItem(RecentAccessCard,global.pydio.MessageHash['user_home.87'],{gridWidth:5,gridHeight:20},[]);
export {RecentAccessCard as default}