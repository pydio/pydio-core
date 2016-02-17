(function(global){

    var MessagesConsumerMixin = AdminComponents.MessagesConsumerMixin;

    /******************************/
    /* REACT DND GENERIC COMPONENTS
    /******************************/
    var Types = {
        NODE_PROVIDER: 'node',
        SORTABLE_LIST_ITEM:'sortable-list-item'
    };

    /**
     * Specifies which props to inject into your component.
     */
    function collect(connect, monitor) {
        return {
            connectDragSource: connect.dragSource(),
            isDragging: monitor.isDragging()
        };
    }

    function collectDrop(connect, monitor){
        return {
            connectDropTarget: connect.dropTarget(),
            canDrop: monitor.canDrop(),
            isOver:monitor.isOver(),
            isOverCurrent:monitor.isOver({shallow:true})
        };
    }


    /***********************/
    /* REACT DND SORTABLE LIST
     /***********************/
    /**
     * Specifies the drag source contract.
     * Only `beginDrag` function is required.
     */
    var sortableItemSource = {
        beginDrag: function (props) {
            // Return the data describing the dragged item
            return { id: props.id };
        },
        endDrag: function(props){
            props.endSwitching();
        }
    };

    var sortableItemTarget = {

        hover: function(props, monitor){
            const draggedId = monitor.getItem().id;
            if (draggedId !== props.id) {
                props.switchItems(draggedId, props.id);
            }
        }

    };

    var sortableItem = React.createClass({

        propTypes:{
            connectDragSource: React.PropTypes.func.isRequired,
            connectDropTarget: React.PropTypes.func.isRequired,
            isDragging: React.PropTypes.bool.isRequired,
            id: React.PropTypes.any.isRequired,
            label: React.PropTypes.string.isRequired,
            switchItems: React.PropTypes.func.isRequired,
            removable: React.PropTypes.bool,
            onRemove:React.PropTypes.func
        },

        removeClicked:function(){
            this.props.onRemove(this.props.id);
        },

        render: function () {
            // Your component receives its own props as usual
            var id = this.props.id;

            // These two props are injected by React DnD,
            // as defined by your `collect` function above:
            var isDragging = this.props.isDragging;
            var connectDragSource = this.props.connectDragSource;
            var connectDropTarget = this.props.connectDropTarget;

            var remove;
            if(this.props.removable){
                remove = <span className="button icon-remove-sign" onClick={this.removeClicked}></span>
            }
            return connectDragSource(connectDropTarget(
                <ReactMUI.Paper zDepth={1} style={{opacity:isDragging?0:1}}>
                    <div className={this.props.className}>
                        {this.props.label}
                        {remove}
                    </div>
                </ReactMUI.Paper>
            ));
        }
    });

    var NonDraggableListItem = React.createClass({
        render: function(){
            var remove;
            if(this.props.removable){
                remove = <span className="button icon-remove-sign" onClick={this.removeClicked}></span>
            }
            return (
                <ReactMUI.Paper zDepth={1}>
                    <div className={this.props.className}>
                        {this.props.label}
                        {remove}
                    </div>
                </ReactMUI.Paper>
            );
        }
    });

    var DraggableListItem;
    if(global.ReactDND){
        DraggableListItem = ReactDND.flow(
            ReactDND.DragSource(Types.SORTABLE_LIST_ITEM, sortableItemSource, collect),
            ReactDND.DropTarget(Types.SORTABLE_LIST_ITEM, sortableItemTarget, collectDrop)
        )(sortableItem);
    }else{
        DraggableListItem = NonDraggableListItem;
    }


    var SortableList = React.createClass({

        propTypes: {
            values: React.PropTypes.array.isRequired,
            onOrderUpdated: React.PropTypes.func,
            removable: React.PropTypes.bool,
            onRemove:React.PropTypes.func,
            className:React.PropTypes.string,
            itemClassName:React.PropTypes.string
        },

        getInitialState: function(){
            return {values: this.props.values};
        },
        componentWillReceiveProps: function(props){
            this.setState({values: props.values, switchData:null});
        },

        findItemIndex: function(itemId, data){
            for(var i=0; i<data.length; i++){
                if(data[i]['payload'] == itemId){
                    return i;
                }
            }
        },

        switchItems:function(oldId, newId){
            var oldIndex = this.findItemIndex(oldId, this.state.values);
            var oldItem = this.state.values[oldIndex];
            var newIndex = this.findItemIndex(newId, this.state.values);
            var newItem = this.state.values[newIndex];

            var currentValues = this.state.values.slice();
            currentValues[oldIndex] = newItem;
            currentValues[newIndex] = oldItem;

            // Check that it did not come back to original state
            var oldPrevious = this.findItemIndex(oldId, this.props.values);
            var newPrevious = this.findItemIndex(newId, this.props.values);
            if(oldPrevious == newIndex && newPrevious == oldIndex){
                this.setState({values:currentValues, switchData:null})
                //console.log("no more moves");
            }else{
                this.setState({values:currentValues, switchData:{oldId:oldId, newId:newId}});
                //console.log({oldId:oldIndex, newId:newIndex});
            }

        },

        endSwitching:function(){
            if(this.state.switchData){
                // Check that it did not come back to original state
                if(this.props.onOrderUpdated){
                    this.props.onOrderUpdated(this.state.switchData.oldId, this.state.switchData.newId, this.state.values);
                }
            }
            this.setState({switchData:null});
        },

        render: function(){
            var switchItems = this.switchItems;
            return (
                <div className={this.props.className}>
                    {this.state.values.map(function(item){
                        return <DraggableListItem
                            id={item.payload}
                            key={item.payload}
                            label={item.text}
                            switchItems={switchItems}
                            endSwitching={this.endSwitching}
                            removable={this.props.removable}
                            onRemove={this.props.onRemove}
                            className={this.props.itemClassName}
                        />
                    }.bind(this))}
                </div>
            )
        }
    });

    /****************************/
    /* REACT DND DRAG/DROP NODES
     /***************************/

    var nodeDragSource = {
        beginDrag: function (props) {
            // Return the data describing the dragged item
            return { node: props.node };
        },

        endDrag: function (props, monitor, component) {
            if (!monitor.didDrop()) {
                return;
            }
            var item = monitor.getItem();
            var dropResult = monitor.getDropResult();
            var dnd = pydio.Controller.defaultActions.get("dragndrop");
            if(dnd){
                var dndAction = pydio.Controller.getActionByName(dnd);
                // Make sure to enable
                dndAction.enable();
                dndAction.apply([item.node, dropResult.node]);
            }

        }
    };

    var nodeDropTarget = {

        hover: function(props, monitor){
        },

        canDrop: function(props, monitor){

            var source = monitor.getItem().node;
            var target = props.node;

            var dnd = pydio.Controller.defaultActions.get("dragndrop");
            if(dnd){
                var dndAction = pydio.Controller.getActionByName(dnd);
                // Make sure to enable
                dndAction.enable();
                // Manually apply, do not use action.apply(), as it will
                // catch the exception we are trying to detect.
                global.actionArguments = [source, target, "canDrop"];
                try {
                    eval(dndAction.options.callbackCode);
                } catch (e) {
                    return false;
                }
                return true;
            }
            return false;
        },

        drop: function(props, monitor){
            var hasDroppedOnChild = monitor.didDrop();
            if (hasDroppedOnChild) {
                return;
            }
            return { node: props.node }
        }

    };


    /*******************/
    /* MISC COMPONENTS */
    /*******************/

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
                if(!c.isLeaf()) children.push(c);
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
        render: function () {
            var hasFolderChildrens = this.state.children.length?true:false;
            var hasChildren = hasFolderChildrens ? (
                <span onClick={this.onChildDisplayToggle}>
                {this.state.showChildren || this.props.forceExpand?
                    <span className="tree-icon icon-angle-down"></span>:
                    <span className="tree-icon icon-angle-right"></span>
                    }
                </span>
            ):<span className="tree-icon icon-angle-right"></span>;
            var isSelected = (this.props.dataModel.getSelectedNodes().indexOf(this.props.node) !== -1 ? 'mui-menu-item mui-is-selected' : 'mui-menu-item');
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
                        {hasChildren} <span className="tree-icon icon-folder-close"></span> {this.props.forceLabel?this.props.forceLabel:this.props.node.getLabel()}
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
                            collapse={this.props.collapse}
                            depth={this.props.depth+1}
                            checkboxes={this.props.checkboxes}
                            checkboxesValues={this.props.checkboxesValues}
                            checkboxesComputeStatus={this.props.checkboxesComputeStatus}
                            onCheckboxCheck={this.props.onCheckboxCheck}
                        />);
                }.bind(this));
            }
            return (
                <li ref="node" className={"treenode" + this.props.node.getPath().replace(/\//g, '_')}>
                    {selfLabel}
                    <ul>
                        {children}
                    </ul>
                </li>
            );
        }
    });

    var WrappedTreeNode = React.createClass({
        propTypes:{
            connectDragSource: React.PropTypes.func.isRequired,
            connectDropTarget: React.PropTypes.func.isRequired,
            isDragging: React.PropTypes.bool.isRequired,
            isOver: React.PropTypes.bool.isRequired,
            canDrop: React.PropTypes.bool.isRequired
        },

        render: function () {
            var connectDragSource = this.props.connectDragSource;
            var connectDropTarget = this.props.connectDropTarget;

            return connectDragSource(connectDropTarget(
                <SimpleTreeNode {...this.props}/>
            ));
        }
    });

    var DragDropTreeNode;
    if(global.ReactDND){
        DragDropTreeNode = ReactDND.flow(
            ReactDND.DragSource(Types.NODE_PROVIDER, nodeDragSource, collect),
            ReactDND.DropTarget(Types.NODE_PROVIDER, nodeDropTarget, collectDrop)
        )(WrappedTreeNode);
    }else{
        DragDropTreeNode = SimpleTreeNode;
    }




    /**
     * Simple openable / loadable tree taking AjxpNode as inputs
     */
    var SimpleTree = React.createClass({

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
            this.props.dataModel.setSelectedNodes([node]);
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

    /**
     * Simple MuiPaper with a figure and a legend
     */
    var SimpleFigureBadge = React.createClass({

        propTypes:{
            colorIndicator:React.PropTypes.string,
            figure:React.PropTypes.number.isRequired,
            legend:React.PropTypes.string
        },

        getDefaultProps:function(){
            return {
                colorIndicator: ''
            }
        },

        render: function(){
            return (
                <ReactMUI.Paper style={{display:'inline-block', marginLeft:16}}>
                    <div className="figure-badge" style={(this.props.colorIndicator?{borderLeftColor:this.props.colorIndicator}:{})}>
                        <div className="figure">{this.props.figure}</div>
                        <div className="legend">{this.props.legend}</div>
                    </div>
                </ReactMUI.Paper>
            );
        }
    });

    /**
     * Search input building a set of query parameters and calling
     * the callbacks to display / hide results
     */
    var SearchBox = React.createClass({

        propTypes:{
            // Required
            parameters:React.PropTypes.object.isRequired,
            queryParameterName:React.PropTypes.string.isRequired,
            // Other
            textLabel:React.PropTypes.string,
            displayResults:React.PropTypes.func,
            hideResults:React.PropTypes.func,
            displayResultsState:React.PropTypes.bool,
            limit:React.PropTypes.number
        },

        getInitialState: function(){
            return {
                displayResult:this.props.displayResultsState?true:false
            };
        },

        getDefaultProps: function(){
            var dm = new PydioDataModel();
            dm.setRootNode(new AjxpNode());
            return {dataModel: dm};
        },

        displayResultsState: function(){
            this.setState({
                displayResult:true
            });
        },

        hideResultsState: function(){
            this.setState({
                displayResult:false
            });
            this.props.hideResults();
        },

        onClickSearch: function(){
            var value = this.refs.query.getValue();
            var dm = this.props.dataModel;
            var params = this.props.parameters;
            params[this.props.queryParameterName] = value;
            params['limit'] = this.props.limit || 100;
            dm.getRootNode().setChildren([]);
            PydioApi.getClient().request(params, function(transport){
                var remoteNodeProvider = new RemoteNodeProvider({});
                remoteNodeProvider.parseNodes(dm.getRootNode(), transport);
                dm.getRootNode().setLoaded(true);
                this.displayResultsState();
                this.props.displayResults(value, dm);
            }.bind(this));
        },

        keyDown: function(event){
            if(event.key == 'Enter'){
                this.onClickSearch();
            }
        },

        render: function(){
            return (
                <div className={(this.props.className?this.props.className:'')}>
                    <div style={{paddingTop:22, float:'right', opacity:0.3}}>
                        <ReactMUI.IconButton
                            ref="button"
                            onClick={this.onClickSearch}
                            iconClassName="icon-search"
                            tooltip="Search"
                            />
                    </div>
                    <div className="searchbox-input-fill" style={{width: 220, float:'right'}}>
                        <ReactMUI.TextField ref="query" onKeyDown={this.keyDown} floatingLabelText={this.props.textLabel}/>
                    </div>
                </div>
            );
        }

    });

    var LabelWithTip = React.createClass({

        propTypes: {
            label:React.PropTypes.string,
            tooltip:React.PropTypes.string,
            tooltipClassName:React.PropTypes.string
        },

        getInitialState:function(){
            return {show:false};
        },

        show:function(){this.setState({show:true});},
        hide:function(){this.setState({show:false});},

        render:function(){
            if(this.props.tooltip){
                var style={};
                if(this.state.show){
                    style = {bottom: -10, top: 'inherit'};
                }
                return (
                    <span onMouseEnter={this.show} onMouseLeave={this.hide} style={{position:'relative'}}>
                        <span className="ellipsis-label">{this.props.label}</span>
                        <ReactMUI.Tooltip label={this.props.tooltip} style={style} className={this.props.tooltipClassName} show={this.state.show}/>
                    </span>
                );
            }else{
                return <span>{this.props.label}</span>
            }
        }

    });

    /**
     * Get info from Pydio controller an build an
     * action bar with active actions.
     * TBC
     */
    var SimpleReactActionBar = React.createClass({

        propTypes:{
            dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
            node:React.PropTypes.instanceOf(AjxpNode).isRequired,
            actions:React.PropTypes.array
        },

        clickAction: function(event){
            var actionName = event.currentTarget.getAttribute("data-action");
            this.props.dataModel.setSelectedNodes([this.props.node]);
            var a = global.pydio.Controller.getActionByName(actionName);
            a.fireContextChange(true, global.pydio.user, this.props.dataModel.getContextNode());
            a.fireSelectionChange(this.props.dataModel);
            a.apply([this.props.dataModel]);
            event.stopPropagation();
            event.preventDefault();
        },

        render: function(){
            var actions = this.props.actions.map(function(a){
                return(
                    <div
                        key={a.options.name}
                        className={a.options.icon_class+' material-list-action-inline' || ''}
                        title={a.options.title}
                        data-action={a.options.name}
                        onClick={this.clickAction}></div>
                );
            }.bind(this));
            return(
                <span>
                    {actions}
                </span>
            );

        }
    });


    /*******************/
    /* GENERIC EDITORS */
    /*******************/

    var LegacyUIWrapper = React.createClass({
        propTypes:{
            componentName:React.PropTypes.string.isRequired,
            componentOptions:React.PropTypes.object,
            onLoadCallback:React.PropTypes.func
        },

        componentDidMount(){
            if(window[this.props.componentName]){
                var element = this.refs.wrapper.getDOMNode();
                var options = this.props.componentOptions;
                this.legacyComponent = new window[this.props.componentName](element, options);
                if(this.props.onLoadCallback){
                    this.props.onLoadCallback(this.legacyComponent);
                }
            }
        },

        componentWillUnmount(){
            if(this.legacyComponent){
                this.legacyComponent.destroy();
            }
        },

        shouldComponentUpdate: function() {
            // Let's just never update this component again.
            return false;
        },

        render: function(){
            return <div id={this.props.id} className={this.props.className} style={this.props.style} ref="wrapper"></div>;
        }
    });
    /**
     * Opens an oldschool Pydio editor in React context, based on node mime type.
     * @type {*|Function}
     */
    var ReactEditorOpener = React.createClass({

        propTypes:{
            node:React.PropTypes.instanceOf(AjxpNode),
            registry:React.PropTypes.instanceOf(Registry).isRequired,
            closeEditorContainer:React.PropTypes.func,
            registerCloseCallback:React.PropTypes.func
        },

        getInitialState: function(){
            return {editorData: null};
        },

        _getEditorData: function(node){
            var selectedMime = getAjxpMimeType(node);
            var editors = this.props.registry.findEditorsForMime(selectedMime, false);
            if(editors.length && editors[0].openable){
                return editors[0];
            }
        },

        closeEditor: function(){
            if(this.editor){
                var el = this.editor.element;
                this.editor.destroy();
                el.remove();
                this.editor = null;
            }
            if(this.props.closeEditorContainer() !== false){
                this.setState({editorData: null, node:null});
            }
        },

        loadEditor: function(node){
            if(this.editor){
                this.closeEditor();
            }
            var editorData = this._getEditorData(node);
            if(editorData) {
                this.props.registry.loadEditorResources(editorData.resourcesManager);
                this.setState({editorData: editorData, node:node});
            }else{
                this.setState({editorData: null, node:null});
            }
        },

        componentDidMount:function(){
            if(this.props.node) this.loadEditor(this.props.node);
        },

        componentWillReceiveProps:function(newProps){
            if(newProps.node) this.loadEditor(newProps.node);
        },

        componentDidUpdate: function(){
            if(this.editor){
                this.editor.destroy();
                this.editor = null;
            }
            if(this.state.editorData && this.state.editorData.formId && this.props.node){
                var editorElement = $(this.refs.editor.getDOMNode()).down('#'+this.state.editorData.formId);
                if(editorElement){
                    var editorOptions = {
                        closable: false,
                        context: this,
                        editorData: this.state.editorData
                    };
                    this.editor = new global[editorOptions.editorData['editorClass']](editorElement, editorOptions);
                    this.editor.open(this.props.node);
                    fitHeightToBottom(editorElement);
                }
            }
        },

        componentWillUnmount:function(){
            if(this.editor){
                this.editor.destroy();
                this.editor = null;
            }
        },

        render: function(){
            var editor;
            if(this.state.editorData){
                if(this.state.editorData.formId){
                    var content = function(){
                        if(this.state && this.state.editorData && $(this.state.editorData.formId)){
                            return {__html:$(this.state.editorData.formId).outerHTML};
                        }else{
                            return {__html:''};
                        }
                    }.bind(this);
                    editor = <div ref="editor" style={{height:"100vh"}} id="editor" key={this.state && this.props.node?this.props.node.getPath():null} dangerouslySetInnerHTML={content()}></div>;
                }else if(global[this.state.editorData.editorClass]){
                    editor = React.createElement(global[this.state.editorData.editorClass], {
                        node:this.props.node,
                        closeEditor:this.closeEditor,
                        registerCloseCallback:this.props.registerCloseCallback
                    });
                }
            }
            return editor || null;
        }
    });

    /**
     * Two columns layout used for Workspaces and Plugins editors
     */
    var PaperEditorLayout = React.createClass({

        propTypes:{
            title:React.PropTypes.any,
            titleActionBar:React.PropTypes.any,
            leftNav:React.PropTypes.any,
            contentFill:React.PropTypes.bool,
            className:React.PropTypes.string
        },


        toggleMenu:function(){
            var crtLeftOpen = (this.state && this.state.forceLeftOpen);
            this.setState({forceLeftOpen:!crtLeftOpen});
        },

        render:function(){
            return (
                <div className={"paper-editor-content layout-fill vertical-layout" + (this.props.className?' '+ this.props.className:'')}>
                    <div className="paper-editor-title">
                        <h2>{this.props.title} <div className="left-picker-toggle"><ReactMUI.IconButton iconClassName="icon-caret-down" onClick={this.toggleMenu} /></div></h2>
                        <div className="title-bar">{this.props.titleActionBar}</div>
                    </div>
                    <div className="layout-fill main-layout-nav-to-stack">
                        <div className={"paper-editor-left" + (this.state && this.state.forceLeftOpen? ' picker-open':'')} onClick={this.toggleMenu} >
                            {this.props.leftNav}
                        </div>
                        <div className={"layout-fill paper-editor-right" + (this.props.contentFill?' vertical-layout':'')} style={this.props.contentFill?{}:{overflowY: 'auto'}}>
                            {this.props.children}
                        </div>
                    </div>
                </div>
            );
        }
    });
    /**
     * Navigation subheader used by PaperEditorLayout
     */
    var PaperEditorNavHeader = React.createClass({

        propTypes:{
            label:React.PropTypes.string
        },

        render:function(){

            return (
                <div className="mui-subheader">
                    {this.props.children}
                    {this.props.label}
                </div>
            );

        }

    });
    /**
     * Navigation entry used by PaperEditorLayout.
     */
    var PaperEditorNavEntry = React.createClass({

        propTypes:{
            keyName:React.PropTypes.string.isRequired,
            onClick:React.PropTypes.func.isRequired,
            label:React.PropTypes.string,
            selectedKey:React.PropTypes.string,
            isLast:React.PropTypes.bool,
            // Drop Down Data
            dropDown:React.PropTypes.bool,
            dropDownData:React.PropTypes.object,
            dropDownChange:React.PropTypes.func,
            dropDownDefaultItems:React.PropTypes.array
        },

        onClick:function(){
            this.props.onClick(this.props.keyName);
        },

        captureDropDownClick: function(){
            if(this.preventClick){
                this.preventClick = false;
                return;
            }
            this.props.onClick(this.props.keyName);
        },

        dropDownChange: function(event, index, item){
            this.preventClick = true;
            this.props.dropDownChange(item);
        },

        render:function(){

            if(!this.props.dropDown || !this.props.dropDownData){
                return (
                    <div
                        className={'menu-entry' + (this.props.keyName==this.props.selectedKey?' menu-entry-selected':'') + (this.props.isLast?' last':'')}
                        onClick={this.onClick}>
                        {this.props.children}
                        {this.props.label}
                    </div>
                );
            }

            // dropDown & dropDownData are loaded
            var menuItemsTpl = [{text:this.props.label, payload:'-1'}];
            if(this.props.dropDownDefaultItems){
                menuItemsTpl = menuItemsTpl.concat(this.props.dropDownDefaultItems);
            }
            this.props.dropDownData.forEach(function(v, k){
                menuItemsTpl.push({text:v.label, payload:v});
            });
            return (
                <div onClick={this.captureDropDownClick} className={'menu-entry-dropdown' + (this.props.keyName==this.props.selectedKey?' menu-entry-selected':'') + (this.props.isLast?' last':'')}>
                    <ReactMUI.DropDownMenu
                        menuItems={menuItemsTpl}
                        className="dropdown-full-width"
                        style={{width:256}}
                        autoWidth={false}
                        onChange={this.dropDownChange}
                        />
                </div>
            );

        }
    });

    /**************************/
    /* GENERIC LIST COMPONENT */
    /**************************/

    /**
     * Pagination component reading metadata "paginationData" from current node.
     */
    var ListPaginator = React.createClass({

        mixins:[MessagesConsumerMixin],

        propTypes:{
            dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
            node:React.PropTypes.instanceOf(AjxpNode).isRequired
        },

        changePage: function(event){
            this.props.node.getMetadata().get("paginationData").set("new_page", event.currentTarget.getAttribute('data-page'));
            this.props.dataModel.requireContextChange(this.props.node);
        },

        onMenuChange:function(event, index, item){
            this.props.node.getMetadata().get("paginationData").set("new_page", item.payload);
            this.props.dataModel.requireContextChange(this.props.node);
        },

        render: function(){
            var pData = this.props.node.getMetadata().get("paginationData");
            var current = parseInt(pData.get("current"));
            var total = parseInt(pData.get("total"));
            var pages = [], next, last, previous, first;
            var pageWord = this.context.getMessage('331', '');
            for(var i=1; i <= total; i++){
                pages.push({payload:i, text:pageWord + ' ' +i + (i == current?(' / ' + total ): '')});
            }
            if(!pages.length){
                return null;
            }
            if(current > 1) previous = <ReactMUI.FontIcon onClick={this.changePage} data-page={current-1} className="icon-angle-left" />;
            if(current < total) next = <ReactMUI.FontIcon onClick={this.changePage} data-page={current+1} className="icon-angle-right" />;
            return (
                <span>
                    {first}
                    {previous}
                    <ReactMUI.DropDownMenu onChange={this.onMenuChange} menuItems={pages} selectedIndex={current-1} />
                    {next}
                    {last}
                    <span className="mui-toolbar-separator">&nbsp;</span>
                </span>
            );
        }

    });

    /**
     * Material List Entry
     */
    var ListEntry = React.createClass({

        propTypes:{
            showSelector:React.PropTypes.bool,
            selected:React.PropTypes.bool,
            selectorDisabled:React.PropTypes.bool,
            onSelect:React.PropTypes.func,
            onClick:React.PropTypes.func,
            iconCell:React.PropTypes.element,
            mainIcon:React.PropTypes.string,
            firstLine:React.PropTypes.node,
            secondLine:React.PropTypes.node,
            thirdLine:React.PropTypes.node,
            actions:React.PropTypes.element,
            activeDroppable:React.PropTypes.bool,
            className:React.PropTypes.string
        },

        onClick: function(event){
            if(this.props.showSelector) {
                if(this.props.selectorDisabled) return;
                this.props.onSelect(this.props.node);
                event.stopPropagation();
                event.preventDefault();
            }else if(this.props.onClick){
                this.props.onClick(this.props.node);
            }
        },

        render: function(){
            var selector;
            if(this.props.showSelector){
                selector = (
                    <div className="material-list-selector">
                        <ReactMUI.Checkbox checked={this.props.selected} ref="selector" disabled={this.props.selectorDisabled}/>
                    </div>
                );
            }
            var iconCell;
            if(this.props.iconCell){
                iconCell = this.props.iconCell;
            }else if(this.props.mainIcon){
                iconCell = <ReactMUI.FontIcon className={this.props.mainIcon}/>;
            }
            var additionalClassName = this.props.className ? this.props.className + ' ' : '';
            if(this.props.canDrop && this.props.isOver){
                additionalClassName += ' droppable-active ';
            }
            if(this.props.node){
                additionalClassName += ' listentry' + this.props.node.getPath().replace(/\//g, '_') + ' ' + ' ajxp_node_' + (this.props.node.isLeaf()?'leaf':'collection') + ' ';
            }
            return (
                <div onClick={this.onClick} className={additionalClassName + "material-list-entry material-list-entry-" + (this.props.thirdLine?3:this.props.secondLine?2:1) + "-lines"+ (this.props.selected? " selected":"")}>
                    {selector}
                    <div className={"material-list-icon" + ((this.props.mainIcon || iconCell)?"":" material-list-icon-none")}>
                        {iconCell}
                    </div>
                    <div className="material-list-text">
                        <div className="material-list-line-1">{this.props.firstLine}</div>
                        <div className="material-list-line-2">{this.props.secondLine}</div>
                        <div className="material-list-line-3">{this.props.thirdLine}</div>
                    </div>
                    <div className="material-list-actions">
                        {this.props.actions}
                    </div>
                </div>
            );

        }
    });

    var WrappedListEntry = React.createClass({

        propTypes:{
            connectDragSource: React.PropTypes.func.isRequired,
            connectDropTarget: React.PropTypes.func.isRequired,
            isDragging: React.PropTypes.bool.isRequired,
            isOver: React.PropTypes.bool.isRequired,
            canDrop: React.PropTypes.bool.isRequired
        },

        render: function () {
            // These two props are injected by React DnD,
            // as defined by your `collect` function above:
            var isDragging = this.props.isDragging;
            var connectDragSource = this.props.connectDragSource;
            var connectDropTarget = this.props.connectDropTarget;

            return connectDragSource(connectDropTarget(
                <ListEntry {...this.props}/>
            ));
        }
    });

    var DragDropListEntry;
    if(global.ReactDND){
        var DragDropListEntry = ReactDND.flow(
            ReactDND.DragSource(Types.NODE_PROVIDER, nodeDragSource, collect),
            ReactDND.DropTarget(Types.NODE_PROVIDER, nodeDropTarget, collectDrop)
        )(WrappedListEntry);
    }else{
        DragDropListEntry = ListEntry;
    }


    /**
     * Specific header for Table layout, reading metadata from node and using keys
     */
    var TableListHeader = React.createClass({

        mixins:[MessagesConsumerMixin],

        propTypes:{
            tableKeys:React.PropTypes.object.isRequired,
            loading:React.PropTypes.bool,
            reload:React.PropTypes.func
        },

        render: function(){
            var cells = [];
            for(var key in this.props.tableKeys){
                if(!this.props.tableKeys.hasOwnProperty(key)) continue;
                var data = this.props.tableKeys[key];
                var style = data['width']?{width:data['width']}:null;
                cells.push(<span key={key} className={'cell header_cell cell-' + key} style={style}>{data['label']}</span>);
            }
            return (
                <ReactMUI.Toolbar className="toolbarTableHeader">
                    <ReactMUI.ToolbarGroup float="left">{cells}</ReactMUI.ToolbarGroup>
                    <ReactMUI.ToolbarGroup float="right">
                        <ReactMUI.FontIcon
                            key={1}
                            tooltip={this.context.getMessage('149', '')}
                            className={"icon-refresh" + (this.props.loading?" rotating":"")}
                            onClick={this.props.reload}
                        />
                    </ReactMUI.ToolbarGroup>
                </ReactMUI.Toolbar>
            );

        }
    });

    /**
     * Specific list entry rendered as a table row. Not a real table, CSS used.
     */
    var TableListEntry = React.createClass({

        propTypes:{
            tableKeys:React.PropTypes.object.isRequired,
            renderActions:React.PropTypes.func
        },

        render: function(){

            var actions = this.props.actions;
            if(this.props.renderActions) {
                actions = this.props.renderActions(this.props.node);
            }

            var cells = [];
            for(var key in this.props.tableKeys){
                if(!this.props.tableKeys.hasOwnProperty(key)) continue;

                var data = this.props.tableKeys[key];
                var style = data['width']?{width:data['width']}:null;
                var value = this.props.node.getMetadata().get(key);
                cells.push(<span key={key} className={'cell cell-' + key} title={value} style={style} data-label={data['label']}>{value}</span>);
            }

            return (
                <ListEntry
                    {...this.props}
                    iconCell={null}
                    firstLine={cells}
                    actions={actions}
                    key={'list-' + this.props.key}
                />
            );


        }

    });

    /**
     * Callback based material list entry with custom icon render, firstLine, secondLine, etc.
     */
    var ConfigurableListEntry = React.createClass({

        propTypes: {
            // SEE ALSO ListEntry PROPS
            renderIcon: React.PropTypes.func,
            renderFirstLine:React.PropTypes.func,
            renderSecondLine:React.PropTypes.func,
            renderThirdLine:React.PropTypes.func,
            renderActions:React.PropTypes.func
        },

        render: function(){
            var icon, firstLine, secondLine, thirdLine;
            if(this.props.renderIcon) {
                icon = this.props.renderIcon(this.props.node);
            } else {
                var node = this.props.node;
                var iconClass = node.getMetadata().get("icon_class")? node.getMetadata().get("icon_class") : (node.isLeaf()?"icon-file-alt":"icon-folder-close");
                icon = <ReactMUI.FontIcon className={iconClass}/>;
            }

            if(this.props.renderFirstLine) {
                firstLine = this.props.renderFirstLine(this.props.node);
            } else {
                firstLine = this.props.node.getLabel();
            }

            if(this.props.renderSecondLine) {
                secondLine = this.props.renderSecondLine(this.props.node);
            }
            if(this.props.renderThirdLine) {
                thirdLine = this.props.renderThirdLine(this.props.node);
            }
            var actions = this.props.actions;
            if(this.props.renderActions) {
                actions = this.props.renderActions(this.props.node);
            }

            return (
                <DragDropListEntry
                    {...this.props}
                    iconCell={icon}
                    firstLine={firstLine}
                    secondLine={secondLine}
                    thirdLine={thirdLine}
                    actions={actions}
                    key={'list-' + this.props.key}
                    />
            );

        }

    });

    /**
     * Main List component
     */
    var SimpleFlatList = React.createClass({

        mixins:[MessagesConsumerMixin],

        propTypes:{
            infiniteSliceCount:React.PropTypes.number,
            filterNodes:React.PropTypes.func,
            customToolbar:React.PropTypes.object,
            tableKeys:React.PropTypes.object,
            autoRefresh:React.PropTypes.number,
            reloadAtCursor:React.PropTypes.bool,
            heightAutoWithMax:React.PropTypes.number,
            observeNodeReload:React.PropTypes.bool,
            groupByFields:React.PropTypes.array,
            defaultGroupBy:React.PropTypes.string,

            entryEnableSelector:React.PropTypes.func,
            entryRenderIcon:React.PropTypes.func,
            entryRenderActions:React.PropTypes.func,
            entryRenderFirstLine:React.PropTypes.func,
            entryRenderSecondLine:React.PropTypes.func,
            entryRenderThirdLine:React.PropTypes.func,

            elementHeight:React.PropTypes.oneOfType([
                React.PropTypes.number,
                React.PropTypes.object
            ]).isRequired

        },

        statics:{
            HEIGHT_ONE_LINE:50,
            HEIGHT_TWO_LINES:73
        },

        getDefaultProps:function(){
            return {infiniteSliceCount:30}
        },

        clickRow: function(gridRow){
            var node;
            if(gridRow.props){
                node = gridRow.props.data.node;
            }else{
                node = gridRow;
            }
            if(node.isLeaf() && this.props.openEditor) {
                var res = this.props.openEditor(node);
                if( res === false){
                    return;
                }
                var uniqueSelection = new Map();
                uniqueSelection.set(node, true);
                this.setState({selection:uniqueSelection}, this.rebuildLoadedElements);
            } else if(!node.isLeaf()) {
                this.props.dataModel.setSelectedNodes([node]);
            }
        },

        getInitialState: function(){
            this.actionsCache = {multiple:new Map()};
            this.dm = new PydioDataModel();
            this.dm.setContextNode(this.props.dataModel.getContextNode());
            var state = {
                loaded: this.props.node.isLoaded(),
                loading: !this.props.node.isLoaded(),
                showSelector:false,
                elements: this.props.node.isLoaded()?this.buildElements(0, this.props.infiniteSliceCount):[],
                containerHeight:this.props.heightAutoWithMax?0:500
            };
            if(this.props.defaultGroupBy){
                state.groupBy = this.props.defaultGroupBy;
            }
            if(this.props.elementHeight instanceof Object){
                state.elementHeight = this.computeElementHeightResponsive();
            }
            return state;
        },

        componentWillReceiveProps: function(nextProps) {
            this.indexedElements = null;
            if(nextProps.filterNodes) this.props.filterNodes = nextProps.filterNodes;
            var currentLength = Math.max(this.state.elements.length, this.props.infiniteSliceCount);
            this.setState({
                loaded: nextProps.node.isLoaded(),
                loading:!nextProps.node.isLoaded(),
                showSelector:false,
                elements:nextProps.node.isLoaded()?this.buildElements(0, currentLength, nextProps.node):[]
            });
            if(!nextProps.autoRefresh&& this.refreshInterval){
                global.clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }else if(nextProps.autoRefresh && !this.refreshInterval){
                this.refreshInterval = global.setInterval(this.reload, nextProps.autoRefresh);
            }
            /*
            if(this.props.node === nextProps.node && this.indexedElements && nextProps.node.isLoaded()){
                nextProps.node.getChildren().forEach(function (child) {
                    var nodeActions = this.getActionsForNode(this.dm, child);
                    this.indexedElements.push({node: child, parent: false, actions: nodeActions});
                }.bind(this));
            }
            */
        },

        _loadNodeIfNotLoaded: function(){
            var node = this.props.node;
            if(!node.isLoaded()){
                node.observeOnce("loaded", function(){
                    if(!this.isMounted()) return;
                    if(this.props.node === node){
                        this.setState({
                            loaded:true,
                            loading: false,
                            elements:this.buildElements(0, this.props.infiniteSliceCount)
                        });
                    }
                    if(this.props.heightAutoWithMax){
                        this.updateInfiniteContainerHeight();
                    }
                }.bind(this));
                node.load();
            }
        },

        _loadingListener: function(){
            this.setState({loaded:false, loading:true});
            this.indexedElements = null;
        },
        _loadedListener: function(){
            var currentLength = Math.max(this.state.elements.length, this.props.infiniteSliceCount);
            this.setState({
                loading:false,
                elements:this.buildElements(0, currentLength, this.props.node)
            });
            if(this.props.heightAutoWithMax){
                this.updateInfiniteContainerHeight();
            }
        },

        reload: function(){
            if(this.props.reloadAtCursor && this._currentCursor){
                this.loadStartingAtCursor();
                return;
            }
            this._loadingListener();
            this.props.node.observeOnce("loaded", this._loadedListener);
            this.props.node.reload();
        },

        loadStartingAtCursor: function(){
            this._loadingListener();
            var node = this.props.node;
            var cachedChildren = node.getChildren();
            var newChildren = [];
            node.observeOnce("loaded", function(){
                var reorderedChildren = new Map();
                newChildren.map(function(c){reorderedChildren.set(c.getPath(), c);});
                cachedChildren.forEach(function(c){reorderedChildren.set(c.getPath(), c);});
                node._children = reorderedChildren;
                this._loadedListener();
            }.bind(this));
            node.setLoaded(false);
            node.observe("child_added", function(newChild){
                newChildren.push(node._children.get(newChild));
            });
            this.props.node.load(null, {cursor:this._currentCursor});
        },

        wireReloadListeners: function(){
            this.wrappedLoading = this._loadingListener;
            this.wrappedLoaded = this._loadedListener;
            this.props.node.observe("loading", this.wrappedLoading);
            this.props.node.observe("loaded", this.wrappedLoaded);
        },
        stopReloadListeners:function(){
            this.props.node.stopObserving("loading", this.wrappedLoading);
            this.props.node.stopObserving("loaded", this.wrappedLoaded);
        },

        toggleSelector:function(){
            // Force rebuild elements
            this.setState({
                showSelector:!this.state.showSelector,
                selection:new Map()
            }, this.rebuildLoadedElements);
        },

        toggleSelection:function(node){
            var selection = this.state.selection || new Map();
            if(selection.get(node)) selection.delete(node);
            else selection.set(node, true);
            this.refs.all_selector.setChecked(false);
            this.setState({
                selection:selection
            }, this.rebuildLoadedElements);
        },

        selectAll:function(){
            if(!this.refs.all_selector.isChecked()){
                this.setState({selection:new Map()}, this.rebuildLoadedElements);
            }else{
                var selection = new Map();
                this.props.node.getChildren().forEach(function(child){
                    if(this.props.filterNodes && !this.props.filterNodes(child)){
                        return;
                    }
                    if(child.isLeaf()){
                        selection.set(child, true);
                    }
                }.bind(this));
                this.refs.all_selector.setChecked(true);
                this.setState({selection:selection}, this.rebuildLoadedElements);
            }
        },

        applyMultipleAction: function(ev){
            if(!this.state.selection || !this.state.selection.size){
                return;
            }
            var actionName = ev.currentTarget.getAttribute('data-action');
            var dm = this.dm || new PydioDataModel();
            dm.setContextNode(this.props.node);
            var selNodes = [];
            this.state.selection.forEach(function(v, node){
                selNodes.push(node);
            });
            dm.setSelectedNodes(selNodes);
            var a = global.pydio.Controller.getActionByName(actionName);
            a.fireContextChange(true, global.pydio.user, dm.getContextNode());
            a.fireSelectionChange(dm);
            a.apply([dm]);

            ev.stopPropagation();
            ev.preventDefault();
        },

        getActionsForNode: function(dm, node){
            var cacheKey = node.isLeaf() ? 'file':'dir';

            var nodeActions = [];
            if(this.actionsCache[cacheKey]) {
                nodeActions = this.actionsCache[cacheKey];
            }else{
                dm.setSelectedNodes([node]);
                global.pydio.Controller.actions.forEach(function(a){
                    a.fireContextChange(true, global.pydio.user, dm.getContextNode());
                    a.fireSelectionChange(dm);
                    if(a.context.selection && a.context.actionBar && a.selectionContext[cacheKey] && !a.deny && a.options.icon_class
                        && (!this.props.actionBarGroups || this.props.actionBarGroups.indexOf(a.context.actionBarGroup) !== -1)
                    ) {
                        nodeActions.push(a);
                        if(node.isLeaf() &&  a.selectionContext.unique === false) {
                            this.actionsCache.multiple.set(a.options.name, a);
                        }
                    }
                }.bind(this));
                this.actionsCache[cacheKey] = nodeActions;
            }

            return nodeActions;
        },

        updateInfiniteContainerHeight: function(){
            var containerHeight = this.refs.infiniteParent.getDOMNode().clientHeight;
            if(this.props.heightAutoWithMax){
                var elementHeight = this.state.elementHeight?this.state.elementHeight:this.props.elementHeight;
                containerHeight = Math.min(this.props.node.getChildren().size * elementHeight ,this.props.heightAutoWithMax);
            }
            this.setState({containerHeight:containerHeight});
        },

        computeElementHeightResponsive:function(){
            var breaks = this.props.elementHeight;
            if(! (breaks instanceof Object) ){
                breaks = {
                    "min-width:480px":this.props.elementHeight,
                    "max-width:480px":(Object.keys(this.props.tableKeys).length * 24) + 33
                };
            }
            if(global.matchMedia){
                for(var k in breaks){
                    if(breaks.hasOwnProperty(k) && global.matchMedia('('+k+')').matches){
                        return breaks[k];
                    }
                }
            }else{
                var width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
                if(width < 480) return breaks["max-width:480px"];
                else return breaks["max-width:480px"];
            }
            return 50;
        },

        updateElementHeightResponsive: function(){
            var newH = this.computeElementHeightResponsive();
            if(!this.state || !this.state.elementHeight || this.state.elementHeight != newH){
                this.setState({elementHeight:newH}, function(){
                    if(this.props.heightAutoWithMax){
                        this.updateInfiniteContainerHeight();
                    }
                }.bind(this));
            }
        },

        componentDidMount: function(){
            this._loadNodeIfNotLoaded();
            if(this.refs.infiniteParent){
                this.updateInfiniteContainerHeight();
                if(!this.props.heightAutoWithMax) {
                    if(global.addEventListener){
                        global.addEventListener('resize', this.updateInfiniteContainerHeight);
                    }else{
                        global.attachEvent('onresize', this.updateInfiniteContainerHeight);
                    }
                }
            }
            if(this.props.autoRefresh){
                this.refreshInterval = global.setInterval(this.reload, this.props.autoRefresh);
            }
            if(this.props.observeNodeReload){
                this.wireReloadListeners();
            }
            if(this.props.elementHeight instanceof Object || this.props.tableKeys){
                if(global.addEventListener){
                    global.addEventListener('resize', this.updateElementHeightResponsive);
                }else{
                    global.attachEvent('onresize', this.updateElementHeightResponsive);
                }
                this.updateElementHeightResponsive();
            }
        },

        componentWillUnmount: function(){
            if(!this.props.heightAutoWithMax) {
                if(global.removeEventListener){
                    global.removeEventListener('resize', this.updateInfiniteContainerHeight);
                }else{
                    global.detachEvent('onresize', this.updateInfiniteContainerHeight);
                }
            }
            if(this.props.elementHeight instanceof Object || this.props.tableKeys){
                if(global.removeEventListener){
                    global.removeEventListener('resize', this.updateElementHeightResponsive);
                }else{
                    global.detachEvent('resize', this.updateElementHeightResponsive);
                }
            }
            if(this.refreshInterval){
                global.clearInterval(this.refreshInterval);
            }
            if(this.props.observeNodeReload){
                this.stopReloadListeners();
            }
        },

        componentDidUpdate: function(){
            this._loadNodeIfNotLoaded();
        },

        onScroll:function(ev){
            var scrollTop = ev.target.scrollTop;
            bufferCallback('infiniteScroller', 50, function(){
                this.setState({scrollerDelta:scrollTop});
            }.bind(this));
        },

        buildElementsFromNodeEntries: function(nodeEntries, showSelector){

            var components = [];
            nodeEntries.forEach(function(entry){
                var data;
                if(entry.parent) {
                    data = {
                        node: entry.node,
                        key: entry.node.getPath(),
                        id: entry.node.getPath(),
                        mainIcon: "icon-level-up",
                        firstLine: "..",
                        secondLine:this.context.getMessage('react.1'),
                        onClick: this.clickRow,
                        showSelector: false,
                        selectorDisabled: true
                    };
                    components.push(React.createElement(ListEntry, data));
                }else if(entry.groupHeader){
                    data = {
                        node: null,
                        key: entry.groupHeader,
                        id: entry.groupHeader,
                        mainIcon: null,
                        firstLine: entry.groupHeader,
                        className:'list-group-header',
                        onClick: null,
                        showSelector: false,
                        selectorDisabled: true
                    };
                    components.push(React.createElement(ListEntry, data));
                }else{
                    data = {
                        node:entry.node,
                        onClick:this.clickRow,
                        onSelect:this.toggleSelection,
                        key:entry.node.getPath(),
                        id:entry.node.getPath(),
                        renderIcon:this.props.entryRenderIcon,
                        renderFirstLine:this.props.entryRenderFirstLine,
                        renderSecondLine:this.props.entryRenderSecondLine,
                        renderThirdLine:this.props.entryRenderThirdLine,
                        renderActions:this.props.entryRenderActions,
                        showSelector:showSelector,
                        selected:(this.state && this.state.selection)?this.state.selection.get(entry.node):false,
                        actions:<SimpleReactActionBar node={entry.node} actions={entry.actions} dataModel={this.dm}/>,
                        selectorDisabled:!(this.props.entryEnableSelector?this.props.entryEnableSelector(entry.node):entry.node.isLeaf())
                    };
                    if(this.props.tableKeys){
                        if(this.state && this.state.groupBy){
                            data['tableKeys'] = LangUtils.deepCopy(this.props.tableKeys);
                            delete data['tableKeys'][this.state.groupBy];
                        }else{
                            data['tableKeys'] = this.props.tableKeys;
                        }
                        components.push(React.createElement(TableListEntry, data));
                    }else{
                        components.push(React.createElement(ConfigurableListEntry, data));
                    }
                }
            }.bind(this));
            return components;

        },

        buildElements: function(start, end, node, showSelector){
            var theNode = this.props.node;
            if (node) theNode = node;
            var theShowSelector = this.state && this.state.showSelector;
            if(showSelector !== undefined) theShowSelector = showSelector;

            if(!this.indexedElements) {
                this.indexedElements = [];
                if(this.state && this.state.groupBy){
                    var groupBy = this.state.groupBy;
                    var groups = {};
                    var groupKeys = [];
                }


                if (!this.props.skipParentNavigation && theNode.getParent() && this.props.dataModel.getContextNode() !== theNode) {
                    this.indexedElements.push({node: theNode.getParent(), parent: true, actions: null});
                }

                theNode.getChildren().forEach(function (child) {
                    if(child.getMetadata().has('cursor')){
                        var childCursor = parseInt(child.getMetadata().get('cursor'));
                        this._currentCursor = Math.max((this._currentCursor ? this._currentCursor : 0), childCursor);
                    }
                    if(this.props.filterNodes && !this.props.filterNodes(child)){
                        return;
                    }
                    var nodeActions = this.getActionsForNode(this.dm, child);
                    if(groupBy){
                        var groupValue = child.getMetadata().get(groupBy) || 'N/A';
                        if(!groups[groupValue]) {
                            groups[groupValue] = [];
                            groupKeys.push(groupValue);
                        }
                        groups[groupValue].push({node: child, parent: false, actions: nodeActions});
                    }else{
                        this.indexedElements.push({node: child, parent: false, actions: nodeActions});
                    }
                }.bind(this));

                if(groupBy){
                    groupKeys = groupKeys.sort();
                    groupKeys.map(function(k){
                        this.indexedElements.push({node: null, groupHeader:k, parent: false, actions: null});
                        this.indexedElements = this.indexedElements.concat(groups[k]);
                    }.bind(this));
                }

            }



            var nodes = this.indexedElements.slice(start, end);
            if(!nodes.length && theNode.getMetadata().get('paginationData')){
                /*
                //INFINITE SCROLLING ACCROSS PAGE. NOT SURE IT'S REALLY UX FRIENDLY FOR BIG LISTS OF USERS.
                //BUT COULD BE FOR E.G. LOGS
                var pData = theNode.getMetadata().get('paginationData');
                var total = parseInt(pData.get("total"));
                var current = parseInt(pData.get("current"));
                if(current < total){
                    pData.set("new_page", current+1);
                }
                this.dm.requireContextChange(theNode);
                */
                return [];
            }else{
                return nodes; //this.buildElementsFromNodeEntries(nodes, theShowSelector);
            }
        },

        rebuildLoadedElements: function(){
            var elemLength = this.state.elements.length;
            var newElements = this.buildElements(0, elemLength);
            this.setState({elements:newElements});
            this.updateInfiniteContainerHeight();
        },

        handleInfiniteLoad: function() {
            var that = this;
            this.setState({
                isInfiniteLoading: true
            });
            var elemLength = that.state.elements.length;
            var newElements = that.buildElements(elemLength, elemLength + this.props.infiniteSliceCount);
            that.setState({
                isInfiniteLoading: false,
                elements: that.state.elements.concat(newElements)
            });
        },


        renderToolbar: function(){

            var rightButtons = <ReactMUI.FontIcon
                key={1}
                        tooltip="Reload"
                        className={"icon-refresh" + (this.state.loading?" rotating":"")}
                        onClick={this.reload}
            />;
            var leftToolbar;
            var paginator;

            if(this.props.node.getMetadata().get("paginationData")){
                paginator = (
                    <ListPaginator dataModel={this.dm} node={this.props.node}/>
                );
            }

            if(this.props.listTitle){
                leftToolbar =(
                    <ReactMUI.ToolbarGroup key={0} float="left">
                        <div className="list-title">{this.props.listTitle}</div>
                    </ReactMUI.ToolbarGroup>
                );
            }

            if(this.props.searchResultData){

                leftToolbar =(
                    <ReactMUI.ToolbarGroup key={0} float="left">
                        <h2 className="search-results-title">{this.context.getMessage('react.3').replace('%s', this.props.searchResultData.term)}</h2>
                    </ReactMUI.ToolbarGroup>
                );
                rightButtons = <ReactMUI.RaisedButton key={1} label={this.context.getMessage('react.4')} primary={true} onClick={this.props.searchResultData.toggleState} />;

            }else if(this.actionsCache.multiple.size){
                var bulkLabel = this.context.getMessage('react.2');
                if(this.state.selection && this.state.showSelector){
                    bulkLabel +=" (" + this.state.selection.size + ")";
                }
                leftToolbar = (
                    <ReactMUI.ToolbarGroup key={0} float="left" className="hide-on-vertical-layout">
                        <ReactMUI.Checkbox ref="all_selector" onClick={this.selectAll}/>
                        <ReactMUI.FlatButton label={bulkLabel} onClick={this.toggleSelector} />
                    </ReactMUI.ToolbarGroup>
                );

                if(this.state.showSelector) {
                    rightButtons = [];
                    var index = 0;
                    this.actionsCache.multiple.forEach(function(a){
                        rightButtons.push(<ReactMUI.RaisedButton
                                key={index}
                                label={a.options.text}
                                data-action={a.options.name}
                                onClick={this.applyMultipleAction}
                                primary={true}/>
                        );
                    }.bind(this));
                    rightButtons = (<span>{rightButtons}</span>);

                }

            }

            return (
                <ReactMUI.Toolbar>
                    {leftToolbar}
                    <ReactMUI.ToolbarGroup key={1} float="right">
                        {paginator}
                        {rightButtons}
                    </ReactMUI.ToolbarGroup>
                </ReactMUI.Toolbar>
            );

        },

        render: function(){

            var containerClasses = "material-list vertical-layout layout-fill";
            if(this.props.className){
                containerClasses += " " + this.props.className;
            }
            if(this.state.showSelector) {
                containerClasses += " list-show-selectors";
            }
            if(this.props.tableKeys){
                containerClasses += " table-mode";
            }
            var toolbar;
            if(this.props.tableKeys){
                var tableKeys;
                if(this.state && this.state.groupBy){
                    tableKeys = LangUtils.deepCopy(this.props.tableKeys);
                    delete tableKeys[this.state.groupBy];
                }else{
                    tableKeys = this.props.tableKeys;
                }
                toolbar = <TableListHeader
                    tableKeys={tableKeys}
                    loading={this.state.loading}
                    reload={this.reload}
                    ref="loading_indicator"
                />
            }else{
                toolbar = this.props.customToolbar ? this.props.customToolbar : this.renderToolbar();
            }

            var elements = this.buildElementsFromNodeEntries(this.state.elements, this.state.showSelector);
            return (
                <div className={containerClasses}>
                    {toolbar}
                    <div className={this.props.heightAutoWithMax?"infinite-parent-smooth-height":"layout-fill"} ref="infiniteParent">
                        <Infinite
                            elementHeight={this.state.elementHeight?this.state.elementHeight:this.props.elementHeight}
                            containerHeight={this.state.containerHeight}
                            infiniteLoadBeginBottomOffset={200}
                            onInfiniteLoad={this.handleInfiniteLoad}
                        >
                            {elements}
                        </Infinite>
                    </div>
                </div>
            );
        }

    });

    /**
     * Simple to use list component encapsulated with its own query mechanism
     * using a set of properties for the remote node provider.
     */
    var NodeListCustomProvider = React.createClass({

        propTypes:{
            nodeProviderProperties:React.PropTypes.object.isRequired,
            autoRefresh:React.PropTypes.number,
            actionBarGroups:React.PropTypes.array,
            heightAutoWithMax:React.PropTypes.number,
            elementHeight:React.PropTypes.number.isRequired
        },

        reload: function(){
            this.refs.list.reload();
        },

        getInitialState:function(){
            var dataModel = new PydioDataModel(true);
            var rNodeProvider = new RemoteNodeProvider();
            dataModel.setAjxpNodeProvider(rNodeProvider);
            rNodeProvider.initProvider(this.props.nodeProviderProperties);
            var rootNode = new AjxpNode("/", false, "loading", "folder.png", rNodeProvider);
            dataModel.setRootNode(rootNode);
            return {node:rootNode, dataModel:dataModel};
        },

        render:function(){
            var legend;
            if(this.props.legend){
                legend = <div className="subtitle">{this.props.legend}</div>;
            }
            return (
                <div className={this.props.heightAutoWithMax?"":"layout-fill vertical-layout"}>
                    <ReactPydio.SimpleList
                        {...this.props}
                        ref="list"
                        style={{height:'100%'}}
                        node={this.state.node}
                        dataModel={this.state.dataModel}
                        actionBarGroups={this.props.actionBarGroups}
                        skipParentNavigation={true}
                    />
                </div>
            );
        }

    });


    /********************/
    /* ASYNC COMPONENTS */
    /********************/
    /**
     * Load a component from server (if not already loaded) based on its namespace.
     */
    var AsyncComponent = React.createClass({

        propTypes: {
            namespace:React.PropTypes.string.isRequired,
            componentName:React.PropTypes.string.isRequired
        },

        _asyncLoad:function(){
            ResourcesManager.loadClassesAndApply([this.props.namespace], function(){
                this.setState({loaded:true});
                if(this.refs['component'] && this.props.onLoad && !this.loadFired){
                    this.props.onLoad(this.refs['component']);
                    this.loadFired = true;
                }
            }.bind(this));
        },

        componentDidMount: function(){
            this._asyncLoad();
        },

        componentWillReceiveProps:function(newProps){
            if(this.props.namespace != newProps.namespace){
                this.loadFired = false;
                this.setState({loaded:false});
            }
        },

        componentDidUpdate:function(){
            if(!this.state.loaded){
                this._asyncLoad();
            }else{
                if(this.refs['component'] && this.props.onLoad && !this.loadFired){
                    this.props.onLoad(this.refs['component']);
                    this.loadFired = true;
                }
            }
        },

        getInitialState: function(){
            return {loaded: false};
        },

        getComponent:function(){
            return (this.refs.component ? this.refs.component : null);
        },

        render: function(){
            if(this.state && this.state.loaded){
                var nsObject = global[this.props.namespace];
                if(nsObject && nsObject[this.props.componentName]){
                    var props = LangUtils.simpleCopy(this.props);
                    props['ref'] = 'component';
                    return React.createElement(nsObject[this.props.componentName], props, null);
                }else{
                    return <div>Component {this.props.namespace}.{this.props.componentName} not found!</div>;
                }
            }else{
                return <div>Loading ...</div>;
            }
        }

    });
    /**
     * Specific AsyncComponent for Modal Dialog
     */
    var AsyncModal = React.createClass({

        getInitialState:function(){
            return {
                async:true,
                componentData:null,
                actions:[
                    { text: 'Cancel', ref: 'cancel' },
                    { text: 'Submit', ref: 'submit' }
                ],
                title:null
            }
        },

        componentWillReceiveProps: function(nextProps){
            var componentData = nextProps.componentData;
            var state = {componentData:componentData, async:true};
            if(componentData && (!componentData instanceof Object || !componentData['namespace'])){
                state['async'] = false;
                this.initModalFromComponent(componentData);
            }
            this.setState(state);
        },

        show: function(){
            if(this.refs.dialog) this.refs.dialog.show();
        },

        hide:function(){
            this.refs.dialog.dismiss();
        },

        onActionsUpdate:function(component){
            if(component.getButtons){
                this.setState({actions:component.getButtons()});
            }
        },

        onTitleUpdate:function(component){
            if(component.getTitle){
                this.setState({title:component.getTitle()});
            }
        },

        onDialogClassNameUpdate:function(component){
            if(component.getDialogClassName){
                this.setState({className:component.getDialogClassName()});
            }
        },

        initModalFromComponent:function(component){
            if(component.getButtons){
                this.setState({actions:component.getButtons()});
            }
            if(component.getTitle){
                this.setState({title:component.getTitle()});
            }
            if(component.getDialogClassName){
                this.setState({className:component.getDialogClassName()});
            }
            if(component.setModal){
                component.setModal(this);
            }
        },

        render: function(){
            var modalContent;
            if(this.state.componentData){
                if(this.state.async){
                    modalContent = (
                        <ReactPydio.AsyncComponent
                            {...this.props}
                            namespace={this.state.componentData.namespace}
                            componentName={this.state.componentData.compName}
                            ref="modalAsync"
                            onLoad={this.initModalFromComponent}
                            dismiss={this.hide}
                            actionsUpdated={this.onActionsUpdate}
                            titleUpdated={this.onTitleUpdate}
                            classNameUpdated={this.onDialogClassNameUpdate}
                            modalData={{modal:this, payload: this.state.componentData['payload']}}
                        />
                    );
                }else{
                    modalContent = this.state.componentData;
                }
            }
            return (
                <ReactMUI.Dialog
                    ref="dialog"
                    title={this.state.title}
                    actions={this.state.actions}
                    actionFocus="submit"
                    modal={false}
                    className={this.state.className}
                    dismissOnClickAway={true}
                    onShow={this.props.onShow}
                    onDismiss={this.props.onDismiss}
                    contentClassName={this.state.className}
                >{modalContent}</ReactMUI.Dialog>
            );
        }

    });

    var ReactPydio = global.ReactPydio || {};
    ReactPydio.SortableList = SortableList;
    ReactPydio.SimpleList = SimpleFlatList;
    ReactPydio.NodeListCustomProvider = NodeListCustomProvider;
    ReactPydio.ListEntry = ListEntry;

    ReactPydio.SimpleFigureBadge = SimpleFigureBadge;
    ReactPydio.SimpleTree = SimpleTree;
    ReactPydio.SearchBox = SearchBox;

    ReactPydio.ReactEditorOpener = ReactEditorOpener;
    ReactPydio.LegacyUIWrapper = LegacyUIWrapper;
    ReactPydio.PaperEditorLayout = PaperEditorLayout;
    ReactPydio.PaperEditorNavEntry = PaperEditorNavEntry;
    ReactPydio.PaperEditorNavHeader = PaperEditorNavHeader;

    ReactPydio.AsyncComponent = AsyncComponent;
    ReactPydio.AsyncModal = AsyncModal;

    ReactPydio.LabelWithTip = LabelWithTip;

    global.ReactPydio = ReactPydio;

})(window);
