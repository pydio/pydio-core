(function(global){

    let SharesList = React.createClass({

        render: function(){
            // User Shares List
            let getMessage = function(id) {
                return this.props.pydio.MessageHash[id];
            }.bind(this)

            return (
                <PydioComponents.NodeListCustomProvider
                    nodeProviderProperties={{
                        get_action:"sharelist-load",user_context:"current"
                    }}
                    tableKeys={{
                        shared_element_parent_repository_label:{label:getMessage('ws.39', 'ajxp_admin'), width:'20%'},
                        original_path:{label:getMessage('ws.41', 'ajxp_admin'), width:'80%'},
                        share_type_readable:{label:getMessage('ws.40', 'ajxp_admin'), width:'15%'}
                    }}
                    actionBarGroups={['share_list_toolbar-selection', 'share_list_toolbar']}
                    groupByFields={['share_type_readable','shared_element_parent_repository_label']}
                    defaultGroupBy="shared_element_parent_repository_label"
                    elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                    style={{maxWidth:720}}
                />
            );
        }

    });

    const FakeDndBackend = function(){
        return{
            setup:function(){},
            teardown:function(){},
            connectDragSource:function(){},
            connectDragPreview:function(){},
            connectDropTarget:function(){}
        };
    };

    const selectionStyle = {
        color: '#1E88E5'
    };
    const cardStyle = {
        display:'flex',
        flexDirection:'column',
        backgroundColor: '#fafafa',
        borderRight: '1px solid #f0f0f0',
        minWidth:250,
        maxWidth:320
    };
    const listStyle = {
        flex: 1,
        overflowY:'auto'
    };
    const listItemStyle = {
        whiteSpace:'nowrap',
        overflow:'hidden',
        textOverflow:'ellipsis'
    };
    const selectorContainerStyle={
        backgroundColor: '#eceff1',
        paddingLeft: 10
    }


    class ShareNode {
        constructor(id, data, parentNode = null){
            this._id = id;
            this._label = data['label'];
            this._count = data['count'];
            this._child_parameters = data['child_parameters'];
            this._children = [];
            if(parentNode){
                this._parentNode = parentNode;
            }
        }
        load(groupBy = null){
            return new Promise(function(resolve){
                let user_context;
                if(ShareNode.CURRENT_USER_CONTEXT){
                    user_context = 'current'
                }else{
                    user_context = this._child_parameters['user_id'] || groupBy === 'user_id' ? 'user' : 'global';
                }
                let params = {...this._child_parameters, get_action:'sharelist-load', format:'json', user_context:user_context};
                if(groupBy){
                    params[groupBy] = '__GROUP__';
                }
                PydioApi.getClient().request(params, (transp)=>{
                    const children = transp.responseJSON.data;
                    this._children = [];
                    Object.keys(children).map((k) => {
                        if(children[k]['child_parameters']){
                            this._children.push(new ShareNode(k, children[k], this));
                        }else{
                            this._children.push(new ShareLeaf(k, children[k], this));
                        }
                    });
                    resolve(this);
                });
            }.bind(this));
        }
        getChildren(){
            return this._children;
        }
        getId(){
            return this._id;
        }
        getLabel(){
            return this._label + ' (' + this._count + ')';
        }
        isLeaf(){
            return false;
        }
    }

    class ShareLeaf extends ShareNode{
        constructor(id, data, parentNode){
            super(id, data, parentNode);
            const metadata = data['metadata'];
            this._internalNode = new AjxpNode('/' + id, true, metadata['text']);
            let metaMap = new Map();
            Object.keys(metadata).forEach((k) => {
                metaMap.set(k, metadata[k]);
            });
            this._internalNode.setMetadata(metaMap);
        }
        load(){
            return new Promise((resolve)=>{resolve(this)});
        }
        getLabel(){
            return this._internalNode.getLabel();
        }
        getInternalNode(){
            return this._internalNode;
        }
        isLeaf(){
            return true;
        }
    }

    class ShareCard extends React.Component{

        render(){
            const selection = new PydioDataModel();
            selection.setSelectedNodes([this.props.node.getInternalNode()]);
            const style = {...cardStyle, zIndex:100 - this.props.nestedLevel - 1, maxWidth: 420, marginRight: 10};
            return (
                <MaterialUI.Paper zDepth={1} style={style}>
                    <PydioReactUI.AsyncComponent
                        namespace="ShareDialog"
                        componentName="MainPanel"
                        pydio={this.props.pydio}
                        selection={selection}
                        readonly={true}
                        noModal={true}
                    />
                </MaterialUI.Paper>);
        }

    }

    class ShareView extends React.Component{

        constructor(props){
            super(props);
            let {node, filters, currentUser} = props;
            ShareNode.CURRENT_USER_CONTEXT = currentUser || false;
            if(!node){
                node = new ShareNode('root', {label:'root', count:0, child_parameters:{}});
            }
            if(!filters){
                filters = {parent_repository_id:'Workspaces', share_type:'Share Types', user_id:'Users'};
            }
            this.state = {
                node: node,
                children: node.getChildren(),
                filters: filters,
                filter: Object.keys(filters).shift(),
                selectedChild: null
            };
        }

        load(){
            this.state.node.load(this.state.filter).then((node)=>{
                this.setState({children: node.getChildren(), selectedChild:null})
            });
        }

        componentDidMount(){
            this.load()
        }

        componentWillReceiveProps(nextProps){
            if(nextProps.node && nextProps.node !== this.state.node){
                this.setState({node: nextProps.node}, ()=>{this.load()});
            }
        }

        selectChild(node){
            this.setState({selectedChild: node});
        }

        selectorChange(event, index, value){
            this.setState({filter: value}, () => {this.load()});
        }

        renderSelector(){
            const {filters, filter} = this.state;
            if(!Object.keys(filters).length){
                return <div style={{color: 'rgba(0,0,0,0.93)', height: 75, lineHeight: '91px'}}>Shares List</div>;
            }
            return (
                <MaterialUI.SelectField
                    floatingLabelText="Group by ..."
                    fullWidth={true}
                    value={filter}
                    onChange={this.selectorChange.bind(this)}
                    underlineStyle={{display:'none'}}
                >
                    {Object.keys(filters).map(function(f){
                        return <MaterialUI.MenuItem key={f} value={f} primaryText={filters[f]} />;
                    })}
                </MaterialUI.SelectField>
            );
        }

        render(){
            const nestedLevel = (this.props.nestedLevel || 0) + 1;
            const filters = {...this.state.filters};
            delete filters[this.state.filter];

            const {selectedChild} = this.state;
            return (
                <div style={{display:'flex', height: 600}}>
                    <MaterialUI.Paper zDepth={1} style={{...cardStyle, zIndex:100-nestedLevel}} rounded={false}>
                        <div style={selectorContainerStyle}>{this.renderSelector()}</div>
                        <MaterialUI.Divider style={{height:1}}/>
                        <MaterialUI.List style={listStyle}>
                            {this.state.children.map((c)=>{
                                let itemStyle = {...listItemStyle};
                                if(selectedChild === c){
                                    itemStyle = {...itemStyle, ...selectionStyle};
                                }
                                return (
                                    <MaterialUI.ListItem
                                        style={itemStyle}
                                        primaryText={c.getLabel()}
                                        onTouchTap={() => { this.selectChild(c); } }
                                    />
                                );
                            })}
                        </MaterialUI.List>
                    </MaterialUI.Paper>
                    {this.state.selectedChild && !this.state.selectedChild.isLeaf()
                        && <ShareView pydio={this.props.pydio} filters={filters} nestedLevel={nestedLevel} node={this.state.selectedChild}/>
                    }
                    {this.state.selectedChild && this.state.selectedChild.isLeaf()
                        && <ShareCard pydio={this.props.pydio} node={this.state.selectedChild} nestedLevel={nestedLevel}/>
                    }
                </div>
            );
        }

    }



    global.UserShares = {
        ShareView : ShareView,
        SharesList: ReactDND.DragDropContext(FakeDndBackend)(SharesList)
    };

})(window);