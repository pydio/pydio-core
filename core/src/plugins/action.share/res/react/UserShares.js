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
            this._childrenCursor = {};
            if(parentNode){
                this._parentNode = parentNode;
            }
        }
        load(groupBy = null, reload=false){
            return new Promise(function(resolve){
                let user_context, append = false;
                if(ShareNode.CURRENT_USER_CONTEXT){
                    user_context = 'current'
                }else{
                    user_context = this._child_parameters['user_id'] || groupBy === 'user_id' ? 'user' : 'global';
                }
                let params = {...this._child_parameters, get_action:'sharelist-load', format:'json', user_context:user_context};
                if(groupBy){
                    params[groupBy] = '__GROUP__';
                }
                if(!reload && this.hasMore()){
                    params['page'] = (Math.ceil( (this._childrenCursor[0] + 1) / this._childrenCursor[1]) + 1);
                    append = true;
                }
                PydioApi.getClient().request(params, (transp)=>{
                    const children = transp.responseJSON.data;
                    this._childrenCursor = transp.responseJSON.cursor;
                    if(!append) {
                        this._children = [];
                    }
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
        hasMore(){
            return (this._childrenCursor && this._childrenCursor.total &&
                    this._childrenCursor.total > (this._childrenCursor[0] + this._childrenCursor[1]));
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

        componentDidMount(){
            if(this.props.scrollXMax){
                this.props.scrollXMax();
            }
        }

        placeButtons(component){
            const updater = (buttons) => {this.setState({buttons})};
            this.setState({buttons: component.getButtons(updater)});
        }

        render(){
            const selection = new PydioDataModel();
            selection.setSelectedNodes([this.props.node.getInternalNode()]);
            const style = {...cardStyle, zIndex:100 - this.props.nestedLevel - 1, maxWidth: 420, minWidth: 420, marginRight: 10, overflowY:'scroll'};
            return (
                <MaterialUI.Paper zDepth={1} style={style}>
                    <PydioReactUI.AsyncComponent
                        namespace="ShareDialog"
                        componentName="MainPanel"
                        pydio={this.props.pydio}
                        selection={selection}
                        readonly={true}
                        noModal={true}
                        onLoad={this.placeButtons.bind(this)}
                        onDismiss={this.props.close}
                    />
                    <div style={{textAlign:'right'}}>{this.state && this.state.buttons}</div>
                </MaterialUI.Paper>);
        }

    }

    class ShareView extends React.Component{

        constructor(props){
            super(props);
            let {node, filters, currentUser} = props;
            if(!node){
                ShareNode.CURRENT_USER_CONTEXT = currentUser || false;
                node = new ShareNode('root', {label:'root', count:0, child_parameters:{}});
            }
            if(!filters){
                filters = {parent_repository_id:'250', share_type:'share_center.238', user_id:'249'};
            }
            this.state = {
                node: node,
                children: node.getChildren(),
                filters: filters,
                filter: Object.keys(filters).shift(),
                selectedChild: null
            };
        }

        load(reload = false){
            this.setState({loading: true});
            this.state.node.load(this.state.filter, reload).then((node)=>{
                this.setState({children: node.getChildren(), selectedChild:null, loading: false})
            });
        }

        componentDidMount(){
            this.load();
            if(this.props.scrollXMax){
                this.props.scrollXMax();
            }
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
            const {filters, filter, loading} = this.state;
            const {pydio:{MessageHash}} = this.props;
            let buttonStyle = {marginTop: 22};
            if(loading) buttonStyle['animation'] = 'spin 3.5s infinite linear';
            const reloadButton = <MaterialUI.IconButton style={buttonStyle} iconStyle={{color:'rgba(0,0,0,0.23)'}} iconClassName="mdi mdi-reload" onTouchTap={()=>{this.load(true)}}/>;
            if(!Object.keys(filters).length){
                return (
                    <div style={{display:'flex'}}>
                        <div style={{flex:1, color: 'rgba(0,0,0,0.93)', height: 72, lineHeight: '91px', fontSize:16}}>{MessageHash['share_center.241']}</div>
                        {reloadButton}
                    </div>
                );
            }

            return (
                <div style={{display:'flex'}}>
                    <MaterialUI.SelectField
                        floatingLabelText={MessageHash['share_center.240']}
                        fullWidth={true}
                        value={filter}
                        onChange={this.selectorChange.bind(this)}
                        underlineStyle={{display:'none'}}
                    >
                        {Object.keys(filters).map(function(f){
                            return <MaterialUI.MenuItem key={f} value={f} primaryText={MessageHash[filters[f]]} />;
                        })}
                    </MaterialUI.SelectField>
                    {reloadButton}
                </div>
            );
        }

        scrollXMax(){
            if(this.refs.root){
                this.refs.root.scrollLeft = 1000000;
            }
        }

        render(){
            const nestedLevel = (this.props.nestedLevel || 0) + 1;
            const filters = {...this.state.filters};
            delete filters[this.state.filter];

            const {selectedChild} = this.state;
            return (
                <div style={{...this.props.style, display:'flex', overflowX: nestedLevel === 1 ? 'scroll' : 'initial'}} ref="root">
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
                            {this.state.node.hasMore() &&
                                <div style={{textAlign:'center'}}><MaterialUI.FlatButton primary={true} label={this.props.pydio.MessageHash['share_center.242']} onTouchTap={() => {this.load()}}/></div>
                            }
                        </MaterialUI.List>
                    </MaterialUI.Paper>
                    {this.state.selectedChild && !this.state.selectedChild.isLeaf() &&
                        <ShareView
                            pydio={this.props.pydio}
                            filters={filters}
                            nestedLevel={nestedLevel}
                            node={this.state.selectedChild}
                            scrollXMax={this.props.scrollXMax || this.scrollXMax.bind(this)}
                        />
                    }
                    {this.state.selectedChild && this.state.selectedChild.isLeaf() &&
                        <ShareCard
                            pydio={this.props.pydio}
                            node={this.state.selectedChild}
                            nestedLevel={nestedLevel}
                            scrollXMax={this.props.scrollXMax || this.scrollXMax.bind(this)}
                            close={() => {this.setState({selectedChild: null})}}
                        />
                    }
                </div>
            );
        }

    }


    const ShareViewModal = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogSize: 'xl',
                dialogPadding: false,
                dialogIsModal: false,
                dialogScrollBody: false
            };
        },

        submit: function(){
            this.dismiss();
        },

        render: function(){

            return (
                <div style={{width:'100%', display:'flex', flexDirection:'column'}}>
                    <PydioComponents.ModalAppBar
                        title={this.props.pydio.MessageHash['share_center.98']}
                        showMenuIconButton={false}
                        iconClassNameRight="mdi mdi-close"
                        onRightIconButtonTouchTap={()=>{this.dismiss()}}
                    />
                    <ShareView {...this.props} style={{width:'100%', flex: 1}}/>
                </div>
            );

        }

    });


    global.UserShares = {
        ShareView       : ShareView,
        ShareViewModal  : ShareViewModal,
        SharesList      : ReactDND.DragDropContext(FakeDndBackend)(SharesList)
    };

})(window);