import {Types, collect, collectDrop, nodeDragSource, nodeDropTarget} from './DND'

/**
 * Tree Node
 */
var SimpleTreeNode = React.createClass({

    propTypes:{
        collapse:React.PropTypes.bool,
        forceExpand:React.PropTypes.bool,
        childrenOnly:React.PropTypes.bool,
        depth:React.PropTypes.number,
        onNodeSelect:React.PropTypes.func,
        node:React.PropTypes.instanceOf(AjxpNode),
        dataModel:React.PropTypes.instanceOf(PydioDataModel),
        forceLabel:React.PropTypes.string,
        // Optional currently selected detection
        nodeIsSelected: React.PropTypes.func,
        // Optional checkboxes
        checkboxes:React.PropTypes.array,
        checkboxesValues:React.PropTypes.object,
        checkboxesComputeStatus:React.PropTypes.func,
        onCheckboxCheck:React.PropTypes.func
    },

    getDefaultProps:function(){
        return {
            collapse: false,
            childrenOnly: false,
            depth:0,
            onNodeSelect: function(node){}
        }
    },

    listenToNode: function(node){
        this._childrenListener = function(){
            if(!this.isMounted()) return;
            this.setState({children:this._nodeToChildren(node)});
        }.bind(this);
        this._nodeListener = function(){
            if(!this.isMounted()) return;
            this.forceUpdate();
        }.bind(this);
        node.observe("child_added", this._childrenListener);
        node.observe("child_removed", this._childrenListener);
        node.observe("node_replaced", this._nodeListener);
    },

    stopListening: function(node){
        node.stopObserving("child_added", this._childrenListener);
        node.stopObserving("child_removed", this._childrenListener);
        node.stopObserving("node_replaced", this._nodeListener);
    },

    componentDidMount: function(){
        this.listenToNode(this.props.node);
    },

    componentWillUnmount:function(){
        this.stopListening(this.props.node);
    },

    componentWillReceiveProps: function(nextProps){
        var oldNode = this.props.node;
        var newNode = nextProps.node;
        if(newNode == oldNode && newNode.getMetadata().get("paginationData")){
            var remapedChildren = this.state.children.map(function(c){c.setParent(newNode);return c;});
            var remapedPathes = this.state.children.map(function(c){return c.getPath()});
            var newChildren = this._nodeToChildren(newNode);
            newChildren.forEach(function(nc){
                if(remapedPathes.indexOf(nc.getPath()) === -1){
                    remapedChildren.push(nc);
                }
            });
            this.setState({children:remapedChildren});
        }else{
            this.setState({children:this._nodeToChildren(newNode)});
        }
        if(newNode !== oldNode){
            this.stopListening(oldNode);
            this.listenToNode(newNode);
        }
    },

    getInitialState: function(){
        return {
            showChildren: !this.props.collapse || this.props.forceExpand,
            children:this._nodeToChildren(this.props.node)
        };
    },

    _nodeToChildren:function(){
        var children = [];
        this.props.node.getChildren().forEach(function(c){
            if(!c.isLeaf() || c.getAjxpMime() === 'ajxp_browsable_archive') children.push(c);
        });
        return children;
    },

    onNodeSelect: function (ev) {
        if (this.props.onNodeSelect) {
            this.props.onNodeSelect(this.props.node);
        }
        ev.preventDefault();
        ev.stopPropagation();
    },
    onChildDisplayToggle: function (ev) {
        if (this.props.node.getChildren().size) {
            this.setState({showChildren: !this.state.showChildren});
        }
        ev.preventDefault();
        ev.stopPropagation();
    },
    nodeIsSelected: function(n){
        if(this.props.nodeIsSelected) return this.props.nodeIsSelected(n);
        else return (this.props.dataModel.getSelectedNodes().indexOf(n) !== -1);
    },
    render: function () {
        var hasFolderChildrens = this.state.children.length?true:false;
        var hasChildren;
        if(hasFolderChildrens){
            hasChildren = (
                <span onClick={this.onChildDisplayToggle}>
                {this.state.showChildren || this.props.forceExpand?
                    <span className="tree-icon icon-angle-down"></span>:
                    <span className="tree-icon icon-angle-right"></span>
                }
                </span>);
        }else{
            let cname = "tree-icon icon-angle-right";
            if(this.props.node.isLoaded()){
                cname += " no-folder-children";
            }
            hasChildren = <span className={cname}></span>;
        }
        var isSelected = (this.nodeIsSelected(this.props.node) ? 'mui-menu-item mui-is-selected' : 'mui-menu-item');
        var selfLabel;
        if(!this.props.childrenOnly){
            if(this.props.canDrop && this.props.isOverCurrent){
                isSelected += ' droppable-active';
            }
            var boxes;
            if(this.props.checkboxes){
                var values = {}, inherited = false, disabled = {}, additionalClassName = '';
                if(this.props.checkboxesComputeStatus){
                    var status = this.props.checkboxesComputeStatus(this.props.node);
                    values = status.VALUES;
                    inherited = status.INHERITED;
                    disabled = status.DISABLED;
                    if(status.CLASSNAME) additionalClassName = ' ' + status.CLASSNAME;
                }else if(this.props.checkboxesValues && this.props.checkboxesValues[this.props.node.getPath()]){
                    values = this.props.checkboxesValues[this.props.node.getPath()];
                }
                var valueClasses = [];
                boxes = this.props.checkboxes.map(function(c){
                    var selected = values[c] !== undefined ? values[c] : false;
                    var click = function(event, value){
                        this.props.onCheckboxCheck(this.props.node, c, value);
                    }.bind(this);
                    if(selected) valueClasses.push(c);
                    return (
                        <ReactMUI.Checkbox
                            name={c}
                            key={c+"-"+(selected?"true":"false")}
                            checked={selected}
                            onCheck={click}
                            disabled={disabled[c]}
                            className={"cbox-" + c}
                        />
                    );
                }.bind(this));
                isSelected += inherited?" inherited ":"";
                isSelected += valueClasses.length ? (" checkbox-values-" + valueClasses.join('-')) : " checkbox-values-empty";
                boxes = <div className={"tree-checkboxes" + additionalClassName}>{boxes}</div>;
            }
            selfLabel = (
                <div className={'tree-item ' + isSelected + (boxes?' has-checkboxes':'')} style={{paddingLeft:this.props.depth*20}}>
                    <div className="tree-item-label" onClick={this.onNodeSelect} title={this.props.node.getLabel()}
                         data-id={this.props.node.getPath()}>
                        {hasChildren}<span className="tree-icon icon-folder-close"></span>{this.props.forceLabel?this.props.forceLabel:this.props.node.getLabel()}
                    </div>
                    {boxes}
                </div>
            );
        }

        var children = [];
        if(this.state.showChildren || this.props.forceExpand){
            children = this.state.children.map(function(child) {
                return (<DragDropTreeNode
                    childrenOnly={false}
                    forceExpand={this.props.forceExpand}
                    key={child.getPath()}
                    dataModel={this.props.dataModel}
                    node={child}
                    onNodeSelect={this.props.onNodeSelect}
                    nodeIsSelected={this.props.nodeIsSelected}
                    collapse={this.props.collapse}
                    depth={this.props.depth+1}
                    checkboxes={this.props.checkboxes}
                    checkboxesValues={this.props.checkboxesValues}
                    checkboxesComputeStatus={this.props.checkboxesComputeStatus}
                    onCheckboxCheck={this.props.onCheckboxCheck}
                />);
            }.bind(this));
        }
        let connector = (instance) => instance;
        if(window.ReactDND && this.props.connectDropTarget && this.props.connectDragSource){
            let connectDragSource = this.props.connectDragSource;
            let connectDropTarget = this.props.connectDropTarget;
            connector = (instance) => {
                connectDragSource(ReactDOM.findDOMNode(instance));
                connectDropTarget(ReactDOM.findDOMNode(instance));
            };
        }
        return (
            <li ref={connector} className={"treenode" + this.props.node.getPath().replace(/\//g, '_')}>
                {selfLabel}
                <ul>
                    {children}
                </ul>
            </li>
        );
    }
});

var DragDropTreeNode;
if(window.ReactDND){
    DragDropTreeNode = ReactDND.flow(
        ReactDND.DragSource(Types.NODE_PROVIDER, nodeDragSource, collect),
        ReactDND.DropTarget(Types.NODE_PROVIDER, nodeDropTarget, collectDrop)
    )(SimpleTreeNode);
}else{
    DragDropTreeNode = SimpleTreeNode;
}




/**
 * Simple openable / loadable tree taking AjxpNode as inputs
 */
export default React.createClass({

    propTypes:{
        showRoot:React.PropTypes.bool,
        rootLabel:React.PropTypes.string,
        onNodeSelect:React.PropTypes.func,
        node:React.PropTypes.instanceOf(AjxpNode).isRequired,
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        selectable:React.PropTypes.bool,
        selectableMultiple:React.PropTypes.bool,
        initialSelectionModel:React.PropTypes.array,
        onSelectionChange:React.PropTypes.func,
        forceExpand:React.PropTypes.bool,
        // Optional currently selected detection
        nodeIsSelected: React.PropTypes.func,
        // Optional checkboxes
        checkboxes:React.PropTypes.array,
        checkboxesValues:React.PropTypes.object,
        checkboxesComputeStatus:React.PropTypes.func,
        onCheckboxCheck:React.PropTypes.func
    },

    getDefaultProps:function(){
        return {
            showRoot:true,
            onNodeSelect: this.onNodeSelect
        }
    },

    onNodeSelect: function(node){
        if(this.props.onNodeSelect){
            this.props.onNodeSelect(node);
        }else{
            this.props.dataModel.setSelectedNodes([node]);
        }
    },

    render: function(){
        return(
            <ul className={this.props.className}>
                <DragDropTreeNode
                    childrenOnly={!this.props.showRoot}
                    forceExpand={this.props.forceExpand}
                    node={this.props.node?this.props.node:this.props.dataModel.getRootNode()}
                    dataModel={this.props.dataModel}
                    onNodeSelect={this.onNodeSelect}
                    nodeIsSelected={this.props.nodeIsSelected}
                    forceLabel={this.props.rootLabel}
                    checkboxes={this.props.checkboxes}
                    checkboxesValues={this.props.checkboxesValues}
                    checkboxesComputeStatus={this.props.checkboxesComputeStatus}
                    onCheckboxCheck={this.props.onCheckboxCheck}
                />
            </ul>
        )
    }
});

