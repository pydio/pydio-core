import SimpleList from './SimpleList'

/**
 * Simple to use list component encapsulated with its own query mechanism
 * using a set of properties for the remote node provider.
 */
export default React.createClass({

    propTypes:{
        nodeProviderProperties:React.PropTypes.object,
        presetDataModel:React.PropTypes.instanceOf(PydioDataModel),
        autoRefresh:React.PropTypes.number,
        actionBarGroups:React.PropTypes.array,
        heightAutoWithMax:React.PropTypes.number,
        elementHeight:React.PropTypes.number.isRequired,
        nodeClicked:React.PropTypes.func,
        reloadOnServerMessage:React.PropTypes.string,
        entryRenderAsCard:React.PropTypes.func
    },

    reload: function(){
        if(this.refs.list && this.isMounted()){
            this.refs.list.reload();
        }
    },

    componentWillUnmount:function(){
        if(this._smObs){
            this.props.pydio.stopObserving("server_message", this._smObs);
            this.props.pydio.stopObserving("server_message:" + this.props.reloadOnServerMessage, this.reload);
        }
    },

    componentWillReceiveProps: function(nextProps){
        if(this.props.nodeProviderProperties && this.props.nodeProviderProperties !== nextProps.nodeProviderProperties){
            let {dataModel, node} = this.state;
            const provider = new RemoteNodeProvider(nextProps.nodeProviderProperties);
            dataModel.setAjxpNodeProvider(provider);
            node.updateProvider(provider);
            this.setState({dataModel: dataModel, node: node});
        }else if(this.props.presetDataModel && this.props.presetDataModel !== nextProps.presetDataModel){
            this.setState({
                dataModel: nextProps.presetDataModel,
                node: nextProps.presetDataModel.getRootNode()
            });
        }
    },

    getInitialState:function(){

        var dataModel, rootNode;
        if(this.props.presetDataModel){
            dataModel = this.props.presetDataModel;
            rootNode = dataModel.getRootNode();
        }else{
            dataModel = new PydioDataModel(true);
            var rNodeProvider = new RemoteNodeProvider();
            dataModel.setAjxpNodeProvider(rNodeProvider);
            rNodeProvider.initProvider(this.props.nodeProviderProperties);
            rootNode = new AjxpNode("/", false, "loading", "folder.png", rNodeProvider);
            dataModel.setRootNode(rootNode);
        }
        if(this.props.nodeClicked){
            // leaf
            this.openEditor = function(node){
                this.props.nodeClicked(node);
                return false;
            }.bind(this);
            // dir
            dataModel.observe("selection_changed", function(event){
                var selectedNodes = event.memo.getSelectedNodes();
                if(selectedNodes.length) {
                    this.props.nodeClicked(selectedNodes[0]);
                    event.memo.setSelectedNodes([]);
                }
            }.bind(this));
        }
        if(this.props.reloadOnServerMessage && this.props.pydio){
            this._smObs = function(event){ if(XMLUtils.XPathSelectSingleNode(event, this.props.reloadOnServerMessage)) this.reload(); }.bind(this);
            this.props.pydio.observe("server_message", this._smObs);
            this.props.pydio.observe("server_message:" + this.props.reloadOnServerMessage, this.reload);
        }
        return {node:rootNode, dataModel:dataModel};
    },

    render:function(){
        var legend;
        if(this.props.legend){
            legend = <div className="subtitle">{this.props.legend}</div>;
        }
        return (
            <div className={this.props.heightAutoWithMax?"":"layout-fill vertical-layout"}>
                <SimpleList
                    {...this.props}
                    openEditor={this.openEditor}
                    ref="list"
                    style={Object.assign({height:'100%'}, this.props.style || {})}
                    node={this.state.node}
                    dataModel={this.state.dataModel}
                    actionBarGroups={this.props.actionBarGroups}
                    skipParentNavigation={true}
                    observeNodeReload={true}
                />
            </div>
        );
    }

});

