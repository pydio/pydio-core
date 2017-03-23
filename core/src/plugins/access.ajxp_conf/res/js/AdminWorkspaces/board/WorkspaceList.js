export default React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        rootNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        currentNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        openSelection:React.PropTypes.func,
        filter:React.PropTypes.string
    },

    reload:function(){
        this.refs.list.reload();
    },

    renderListIcon:function(node){
        var letters = node.getLabel().split(" ").map(function(word){return word.substr(0,1)}).join("");
        return <span className="letter_badge">{letters}</span>;
    },

    renderSecondLine: function(node){
        if(!node.getMetadata().get("template_name")){
            return this.context.getMessage('ws.5') + ": " + node.getMetadata().get("slug") + " / " + node.getMetadata().get("accessLabel");
        }else{
            return this.context.getMessage('ws.5') + ": " + node.getMetadata().get("slug") + " / Template " + node.getMetadata().get("template_name");
        }
    },

    filterNodes:function(node){
        if(! this.props.filter ) return true;
        if(['ajxp_conf','ajxp_home','admin','ajxp_user'].indexOf(node.getMetadata().get('accessType')) !== -1){
            return false;
        }
        if( this.props.filter == 'workspaces'){
            return !(node.getMetadata().get('is_template') == 'true');
        }else if(this.props.filter == 'templates'){
            return node.getMetadata().get('is_template') == 'true';
        }
        return true;
    },

    render:function(){
        return (
            <PydioComponents.SimpleList
                ref="list"
                node={this.props.currentNode}
                dataModel={this.props.dataModel}
                className="workspaces-list"
                actionBarGroups={[]}
                entryRenderIcon={this.renderListIcon}
                entryRenderSecondLine={this.renderSecondLine}
                openEditor={this.props.openSelection}
                infineSliceCount={1000}
                filterNodes={this.filterNodes}
                elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES}
            />
        );
    }

});
