(function(global){

    class OpenNodesModel extends Observable{

        constructor(){
            super();
            this._openNodes = [];
            global.pydio.UI.registerEditorOpener(this);
            global.pydio.observe("repository_list_refreshed", function(){
                this._openNodes = [];
            }.bind(this));
        }

        static getInstance(){
            if(!OpenNodesModel.__INSTANCE){
                OpenNodesModel.__INSTANCE = new OpenNodesModel();
            }
            return OpenNodesModel.__INSTANCE;
        }

        openEditorForNode(selectedNode, editorData){
            this.pushNode(selectedNode, editorData);
        }

        pushNode(node, editorData){
            let found = false;
            let editorClass = editorData ? editorData.editorClass : null;
            let object = {node:node, editorData:editorData};
            this.notify('willPushNode', object);
            this._openNodes.map(function(o){
                if(o.node === node && (o.editorData && o.editorData.editorClass == editorClass) || (!o.editorData && !editorClass)){
                    found = true;
                    object = o;
                }
            });
            if(!found){
                this._openNodes.push(object);
            }
            this.notify('nodePushed', object);
            this.notify('update', this._openNodes);
        }

        removeNode(object){
            this.notify('willRemoveNode', object);
            let index = this._openNodes.indexOf(object);
            this._openNodes = LangUtils.arrayWithout(this._openNodes, index);
            this.notify('nodeRemovedAtIndex', index);
            this.notify('update', this._openNodes);
        }

        getNodes(){
            return this._openNodes;
        }

    }

    class ComponentConfigsParser{
        
        constructor(){
            
        }

        getDefaultListColumns(){
            return {
                text:{
                    label:'File Name',
                    message:'1',
                    width: '50%',
                    renderCell:MainFilesList.tableEntryRenderCell,
                    sortType:'string',
                    remoteSortAttribute:'ajxp_label'
                },
                filesize:{
                    label:'File Size',
                    message:'2',
                    sortType:'number',
                    sortAttribute:'bytesize',
                    remoteSortAttribute:'filesize'
                },
                mimestring:{
                    label:'File Type',
                    message:'3',
                    sortType:'string'
                },
                ajxp_modiftime:{
                    label:'Mofidied on',
                    message:'4',
                    sortType:'number'
                }
            };
        }

        loadConfigs(componentName){
            let configs = new Map();
            let columnsNodes = XMLUtils.XPathSelectNodes(global.pydio.getXmlRegistry(), 'client_configs/component_config[@className="FilesList"]/columns/column|client_configs/component_config[@className="FilesList"]/columns/additional_column');
            let columns = {};
            let messages = global.pydio.MessageHash;
            columnsNodes.forEach(function(colNode){
                let name = colNode.getAttribute('attributeName');
                columns[name] = {
                    message : colNode.getAttribute('messageId'),
                    label   : colNode.getAttribute('messageString') ? colNode.getAttribute('messageString') : messages[colNode.getAttribute('messageId')],
                    sortType: messages[colNode.getAttribute('sortType')]
                };
                if(name === 'ajxp_label') {
                    columns[name].renderCell = MainFilesList.tableEntryRenderCell;
                }
                if(colNode.getAttribute('reactModifier')){
                    let reactModifier = colNode.getAttribute('reactModifier');
                    columns[name].renderComponent = columns[name].renderCell = function(){
                        var args = Array.from(arguments);
                        return ResourcesManager.detectModuleToLoadAndApply(reactModifier, function(){
                            let modifierFuncWrapped = eval(reactModifier);
                            return modifierFuncWrapped.apply(null, args);
                        }, false);
                    };
                }
                columns[name]['sortType'] = 'string';
            });
            configs.set('columns', columns);
            return configs;
        }
        
    }
    
    let MessagesProviderMixin = {

        childContextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func
        },

        getChildContext: function() {
            var messages = this.props.pydio.MessageHash;
            return {
                messages: messages,
                getMessage: function(messageId){
                    try{
                        return messages[messageId] || messageId;
                    }catch(e){
                        return messageId;
                    }
                }
            };
        }

    };

    let FilePreview = React.createClass({

        propTypes: {
            node: React.PropTypes.instanceOf(AjxpNode),
            noRichPreview: React.PropTypes.bool
        },

        getInitialState: function(){
            return {loading: false, element: null}
        },

        componentDidMount: function(){
            this.loadCoveringImage();
        },

        componentWillReceiveProps: function(nextProps){
            if(nextProps.node.getPath() !== this.props.node.getPath()){
                this.loadCoveringImage();
                return;
            }
            if(nextProps.noRichPreview !== this.props.noRichPreview && !nextProps.noRichPreview){
                this.loadCoveringImage(true);
            }
        },

        loadCoveringImage: function(force = false){
            if(this.props.noRichPreview && !force){
                return;
            }
            let pydio = global.pydio, node = this.props.node;
            let editors = global.pydio.Registry.findEditorsForMime((node.isLeaf()?node.getAjxpMime():"mime_folder"), true);
            if(!editors || !editors.length) {
                return;
            }
            let editor = editors[0];
            pydio.Registry.loadEditorResources(editors[0].resourcesManager);
            var editorClass = Class.getByName(editors[0].editorClass);
            if(editorClass.prototype.getCoveringBackgroundSource){
                let image = new Image();
                let bgUrl = editorClass.prototype.getCoveringBackgroundSource(node);

                let loader = function(){
                    if(!this.isMounted) return;
                    bgUrl = bgUrl.replace('(', '\\(').replace(')', '\\)').replace('\'', '\\\'');
                    let element = (<div className="covering-bg-preview" style={{
                        backgroundImage:'url(' + bgUrl + ')',
                        backgroundSize : 'cover'
                    }}></div>);
                    this.setState({loading: false, element: element});
                }.bind(this);
                this.setState({loading: true});
                image.src = bgUrl;
                if(image.readyState && image.readyState === 'complete'){
                    loader();
                }else{
                    image.onload = loader();
                }
            }

        },

        render: function(){

            if(this.state.element){
                return this.state.element;
            }

            let node  = this.props.node;
            let svg = AbstractEditor.prototype.getSvgSource(node);
            let object;
            if(svg){
                object = <div className="mimefont-container"><div className={"mimefont mdi mdi-" + svg}></div></div>;
            }else{
                var src = ResourcesManager.resolveImageSource(node.getIcon(), "mimes/ICON_SIZE", 64);
                if(!src){
                    if(!node.isLeaf()) src = ResourcesManager.resolveImageSource('folder.png', "mimes/ICON_SIZE", 64);
                    else src = ResourcesManager.resolveImageSource('mime_empty.png', "mimes/ICON_SIZE", 64);
                }
                object = <img  src={src}/>
            }

            return object;

        }

    });

    let EditionPanel = React.createClass({

        propTypes: {
            pydio: React.PropTypes.instanceOf(Pydio)
        },

        updateNodesFromModel: function(pushed, removedAtIndex){
            
            let nodes = OpenNodesModel.getInstance().getNodes();
            let active = this.state.activeTab;
            if(pushed){
                active = nodes.indexOf(pushed);
            }else if(removedAtIndex !== undefined){
                active = Math.max(0, removedAtIndex - 1);
            }
            if(pushed){
                this.setState({
                    nodes:nodes,
                    visible: false,
                    activeTab:active
                }, this.toggle.bind(this));
            }else{
                if(!nodes.length && this.state.visible){
                    this.setState({visible:!this.state.visible}, function(){
                        global.setTimeout(function(){
                            this.setState({closed:true, opened:false, nodes:[]});
                        }.bind(this), 800);
                    }.bind(this));
                }else{
                    this.setState({
                        nodes:nodes,
                        activeTab:active
                    });
                }
            }

        },

        componentDidMount: function(){
            this._nodesModelObserver = this.updateNodesFromModel.bind(this);
            this._nodesRemoveObserver = function(index){
                this.updateNodesFromModel(null, index);
            }.bind(this);
            OpenNodesModel.getInstance().observe("nodePushed", this._nodesModelObserver);
            OpenNodesModel.getInstance().observe("nodeRemovedAtIndex", this._nodesRemoveObserver);
        },

        componentWillUnmount: function(){
            OpenNodesModel.getInstance().stopObserving("nodePushed", this._nodesModelObserver);
            OpenNodesModel.getInstance().stopObserving("nodeRemovedAtIndex", this._nodesRemoveObserver);
        },

        getInitialState: function(){
            return {nodes:[], visible: false, activeTab:0};
        },

        toggle: function(){
            let visible = this.state.visible;
            this.setState({visible:!this.state.visible}, function(){
                global.setTimeout(function(){
                    if(visible) this.setState({closed:true, opened:false});
                    else this.setState({closed:false, opened:true});
                }.bind(this), 800);
            }.bind(this));
        },

        onChange: function(index, tab){
            this.setState({activeTab: index});
        },

        render: function(){
            if(this.state.nodes.length){
                let style = {};
                let overlay;
                let className = 'editor-window react-mui-context vertical_layout', iconClassName='mdi mdi-pencil';
                if(this.state.closed) className += ' closed';
                else if(this.state.opened) className += ' opened';
                
                let tabs = [], title, nodeTitle, mfbMenus = [];
                let index = 0;
                let editors = this.state.nodes.map(function(object){
                    if(this.state.visible && this.state.opened){
                        let closeTab = function(e){
                            OpenNodesModel.getInstance().removeNode(object);
                        };
                        let label = <span className="closeable-tab"><span className="label">{object.node.getLabel()}</span><ReactMUI.FontIcon className="mdi mdi-close" onClick={closeTab}/></span>;
                        tabs.push(<ReactMUI.Tab label={label} selected={index === this.state.activeTab}></ReactMUI.Tab>);
                    }else{
                        mfbMenus.push(<ReactMFB.ChildButton icon="mdi mdi-file" label={object.node.getLabel()} onClick={this.toggle}/>);
                    }
                    if(index === this.state.activeTab){
                        nodeTitle = object.node.getLabel();
                    }
                    let style={display:(index === this.state.activeTab ? 'flex' : 'none')};
                    index ++;
                    return (
                        <div className="editor_container vertical_layout vertical_fit" style={style}>
                            <ReactPydio.ReactEditorOpener
                                node={object.node}
                                editorData={object.editorData}
                                registry={this.props.pydio.Registry}
                                closeEditorContainer={function(){return true;}}
                            />
                        </div>
                    );
                }.bind(this));

                let mainIcon;
                if(this.state.visible) {
                    className += ' open';
                    title = <div>{nodeTitle}</div>;
                    let overlayClass = "editor-overlay";
                    if(this.state.opened) {
                        iconClassName = 'mdi mdi-window-minimize';
                        overlayClass += ' opened';
                    }
                    overlay = <div key="overlay" onClick={this.toggle} className={overlayClass}></div>;
                    mainIcon = <ReactMUI.IconButton iconClassName={iconClassName} onClick={this.toggle}/>;
                }else{
                    if(this.state.nodes && this.state.nodes.length > 1){
                        mainIcon = (
                            <ReactMFB.Menu effect="slidein" position="br" icon={iconClassName} ref="menu">
                                <ReactMFB.MainButton iconResting={iconClassName} iconActive="mdi mdi-close"/>
                                {mfbMenus}
                            </ReactMFB.Menu>
                        );
                    }else{
                        mainIcon = <ReactMUI.IconButton iconClassName={iconClassName} onClick={this.toggle}/>;
                    }
                }
                

                return (
                    <div className="react-editor">
                        {overlay}
                        <div className={className} style={style}>
                            <div className="editor-title">
                                {mainIcon}
                                {title}
                            </div>
                            <ReactMUI.Tabs onChange={this.onChange} initialSelectedIndex={this.state.activeTab} tabWidth={200}>
                                {tabs}
                            </ReactMUI.Tabs>
                            {editors}
                        </div>
                    </div>
                );
            }else{
                return <span></span>
            }
        }

    });

    let MainFilesList = React.createClass({

        mixins: [MessagesProviderMixin],

        propTypes: {
            pydio: React.PropTypes.instanceOf(Pydio)
        },

        statics: {
            tableEntryRenderCell: function(node){
                return <span><FilePreview noRichPreview={true} node={node}/> {node.getLabel()}</span>;
            }
        },

        getInitialState: function(){
            let configParser = new ComponentConfigsParser();
            let columns = configParser.loadConfigs('FilesList').get('columns');
            return {
                contextNode : this.props.pydio.getContextHolder().getContextNode(),
                displayMode : 'list',
                thumbNearest: 200,
                thumbSize   : 200,
                elementsPerLine: 5,
                columns     : columns ? columns : configParser.getDefaultListColumns(),
                parentIsScrolling: this.props.parentIsScrolling
            }
        },

        pydioResize: function(){
            if(this.refs['list']){
                this.refs['list'].updateInfiniteContainerHeight();
            }
            this.recomputeThumbnailsDimension();
        },

        recomputeThumbnailsDimension: function(nearest){

            if(!nearest){
                nearest = this.state.thumbNearest;
            }
            let containerWidth = this.refs['list'].getDOMNode().clientWidth;

            // Find nearest dim
            let blockNumber = Math.floor(containerWidth / nearest);
            let width = Math.floor(containerWidth / blockNumber);


            // Recompute columns widths
            let columns = this.state.columns;
            let columnKeys = Object.keys(columns);
            let defaultFirstWidthPercent = 10;
            let defaultFirstMinWidthPx = 250;
            let firstColWidth = Math.max(250, containerWidth * defaultFirstWidthPercent / 100);
            let otherColWidth = (containerWidth - firstColWidth) / (Object.keys(this.state.columns).length - 1);
            columnKeys.map(function(columnKey){
                columns[columnKey]['width'] = otherColWidth;
            });

            this.setState({
                columns: columns,
                elementsPerLine: blockNumber,
                thumbSize: width,
                thumbNearest:nearest
            });


        },

        componentDidMount: function(){
            // Hook to the central datamodel
            this._contextObserver = function(){
                this.setState({contextNode: this.props.pydio.getContextHolder().getContextNode()});
            }.bind(this);
            this.props.pydio.getContextHolder().observe("context_changed", this._contextObserver);
            this.recomputeThumbnailsDimension();
            this.props.pydio.getController().updateGuiActions(this.getPydioActions());
        },

        componentWillUnmount: function(){
            this.props.pydio.getContextHolder().stopObserving("context_changed", this._contextObserver);
            this.getPydioActions(true).map(function(key){
                this.props.pydio.getController().deleteFromGuiActions(key);
            }.bind(this));
        },

        selectNode: function(node){
            if(node.isLeaf()){
                this.props.pydio.getContextHolder().setSelectedNodes([node]);
            }else{
                this.props.pydio.getContextHolder().requireContextChange(node);
            }
        },

        entryRenderIcon: function(node, entryProps = {}){
            return <FilePreview noRichPreview={!!entryProps['parentIsScrolling']} node={node}/>;
        },

        entryRenderActions: function(node){
            // This would be for mobile actions
            /*
            if(node.isLeaf()){
                return null;
                let pushNodeToEditor = function(){
                    OpenNodesModel.getInstance().pushNode(node);
                };
                return <ReactMUI.FontIcon className="icon-ellipsis-vertical" tooltip="Info" onClick={pushNodeToEditor}/>;
            }else{
                let selectFolder = function(e){
                    e.stopPropagation();
                    this.props.pydio.getContextHolder().setSelectedNodes([node]);
                }.bind(this);
                return <ReactMUI.FontIcon className="icon-ellipsis-vertical" tooltip="Info" onClick={selectFolder}/>;
            }*/
            let content = null;
            if(node.getMetadata().get('overlay_class')){
                let elements = node.getMetadata().get('overlay_class').split(',').map(function(c){
                    return <span className={c + ' overlay-class-span'}></span>;
                });
                content = <div className="overlay_icon_div">{elements}</div>;
            }
            return content;

        },

        entryHandleClicks: function(node, clickType){
            let dm = this.props.pydio.getContextHolder();
            if(!clickType || clickType == ReactPydio.SimpleList.CLICK_TYPE_SIMPLE){
                dm.setSelectedNodes([node]);
            }else if(clickType == ReactPydio.SimpleList.CLICK_TYPE_DOUBLE){
                if(node.isLeaf()){
                    dm.setSelectedNodes([node]);
                    this.props.pydio.Controller.fireAction("open_with_unique");
                }else{
                    dm.requireContextChange(node);
                }
            }
        },

        entryRenderSecondLine: function(node){
            let metaData = node.getMetadata();
            let pieces = [];
            if(metaData.get("ajxp_description")){
                pieces.push(<span className="metadata_chunk metadata_chunk_description">{metaData.get('ajxp_description')}</span>);
            }

            var first = false;
            var attKeys = Object.keys(this.state.columns);
            for(var i = 0; i<attKeys.length;i++ ){
                let s = attKeys[i];
                let columnDef = this.state.columns[s];
                let label;
                if(s === 'ajxp_label' || s === 'text'){
                    continue;
                }else if(s=="ajxp_modiftime"){
                    var date = new Date();
                    date.setTime(parseInt(metaData.get(s))*1000);
                    label = PathUtils.formatModifDate(date);
                }else if(s == "ajxp_dirname" && metaData.get("filename")){
                    var dirName = getRepName(metaData.get("filename"));
                    label =  dirName?dirName:"/" ;
                }else if(s == "filesize" && metaData.get(s) == "-") {
                    continue;
                }else if(columnDef.renderComponent){
                    columnDef['name'] = s;
                    label = columnDef.renderComponent(node, columnDef);
                }else{
                    var metaValue = metaData.get(s) || "";
                    if(!metaValue) continue;
                    label = metaValue;
                }
                let sep;
                if(!first){
                    sep = <span className="icon-angle-right"></span>;
                }
                let cellClass = 'metadata_chunk metadata_chunk_standard metadata_chunk_' + s;
                pieces.push(<span className={cellClass}>{sep}<span className="text_label">{label}</span></span>);
            }
            return pieces;

        },

        switchDisplayMode: function(displayMode){
            this.setState({displayMode: displayMode});
            if(displayMode.indexOf('grid-') === 0){
                let near = parseInt(displayMode.split('-')[1]);
                this.recomputeThumbnailsDimension(near);
            }else if(displayMode === 'detail'){
                this.recomputeThumbnailsDimension();
            }
        },

        getPydioActions: function(keysOnly = false){
            if(keysOnly){
                return ['switch_display_mode'];
            }
            var multiAction = new Action({
                name:'switch_display_mode',
                icon_class:'icon-th-large',
                text_id:150,
                title_id:151,
                text:MessageHash[150],
                title:MessageHash[151],
                hasAccessKey:false,
                subMenu:true,
                subMenuUpdateImage:true
            }, {
                selection:false,
                dir:true,
                actionBar:true,
                actionBarGroup:'display_toolbar',
                contextMenu:false,
                infoPanel:false
            }, {}, {}, {
                staticItems:[
                    {name:'List',title:227,icon_class:'icon-table',callback:function(){this.switchDisplayMode('list')}.bind(this),hasAccessKey:true,accessKey:'list_access_key'},
                    {name:'Detail',title:461,src:'view_list_details.png',icon_class:'icon-th-list',callback:function(){this.switchDisplayMode('detail')}.bind(this),hasAccessKey:true,accessKey:'detail_access_key'},
                    {name:'Thumbs',title:229,src:'view_icon.png',icon_class:'icon-th',callback:function(){this.switchDisplayMode('grid-160')}.bind(this),hasAccessKey:true,accessKey:'thumbs_access_key'},
                    {name:'Thumbs large',title:229,src:'view_icon.png',icon_class:'icon-th',callback:function(){this.switchDisplayMode('grid-320')}.bind(this),hasAccessKey:false},
                    {name:'Thumbs small',title:229,src:'view_icon.png',icon_class:'icon-th',callback:function(){this.switchDisplayMode('grid-80')}.bind(this),hasAccessKey:false}
                ]
            });
            let buttons = new Map();
            buttons.set('switch_display_mode', multiAction);
            return buttons;
        },

        render: function(){

            let tableKeys, sortKeys, elementStyle, className = 'main-file-list layout-fill';
            let elementHeight, entryRenderSecondLine, elementsPerLine = 1, near;
            let dMode = this.state.displayMode;
            if(dMode.indexOf('grid-') === 0){
                near = parseInt(dMode.split('-')[1]);
                dMode = 'grid';
            }
            let infiniteSliceCount = 50;

            if(dMode === 'detail'){

                elementHeight = ReactPydio.SimpleList.HEIGHT_ONE_LINE;
                tableKeys = this.state.columns;

            } else if(dMode === 'grid'){

                sortKeys = this.state.columns;
                className += ' material-list-grid grid-size-' + near;
                elementHeight =  Math.ceil(this.state.thumbSize / this.state.elementsPerLine);
                elementsPerLine = this.state.elementsPerLine;
                elementStyle={
                    width: this.state.thumbSize,
                    height: this.state.thumbSize
                };
                // Todo: compute a more real number of elements visible per page.
                if(near === 320) infiniteSliceCount = 25;
                else if(near === 160) infiniteSliceCount = 80;
                else if(near === 80) infiniteSliceCount = 200;

            } else if(dMode === 'list'){

                sortKeys = this.state.columns;
                elementHeight = ReactPydio.SimpleList.HEIGHT_TWO_LINES;
                entryRenderSecondLine = this.entryRenderSecondLine.bind(this);

            }

            return (
                <ReactPydio.SimpleList
                    ref="list"
                    tableKeys={tableKeys}
                    sortKeys={sortKeys}
                    node={this.state.contextNode}
                    dataModel={this.props.pydio.getContextHolder()}
                    externalResize={true}
                    className={className}
                    actionBarGroups={["change_main"]}
                    infiniteSliceCount={infiniteSliceCount}
                    skipInternalDataModel={true}
                    elementsPerLine={elementsPerLine}
                    elementHeight={elementHeight}
                    elementStyle={elementStyle}
                    passScrollingStateToChildren={true}
                    entryRenderIcon={this.entryRenderIcon}
                    entryRenderSecondLine={entryRenderSecondLine}
                    entryRenderActions={this.entryRenderActions}
                    entryHandleClicks={this.entryHandleClicks}
                />
            );
        }

    });

    let SearchForm = React.createClass({

        focused: function(){
            this.setState({focused: true});
            this.props.pydio.UI.disableAllKeyBindings();
        },

        blurred: function(){
            this.setState({focused: false});
            this.props.pydio.UI.enableAllKeyBindings();
        },

        render: function(){
            let focused = this.state && this.state.focused ? ' focused' : '';
            return (
                <div className={"top_search_form" + focused}>
                    <ReactMUI.TextField onFocus={this.focused} onBlur={this.blurred} hintText="Search..."/>
                </div>
            );
        }

    });

    let Breadcrumb = React.createClass({

        getInitialState: function(){
            return {node: null};
        },

        componentDidMount: function(){
            let n = this.props.pydio.getContextHolder().getContextNode();
            if(n){
                this.setState({node: n});
            }
            this._observer = function(event){
                this.setState({node: event.memo});
            }.bind(this);
            this.props.pydio.getContextHolder().observe("context_changed", this._observer);
        },

        componentWillUnmount: function(){
            this.props.pydio.getContextHolder().stopObserving("context_changed", this._observer);
        },

        goTo: function(target, event){
            this.props.pydio.getContextHolder().requireContextChange(new AjxpNode(target));
        },

        render: function(){
            var pydio = this.props.pydio;
            let repoLabel = pydio.user.repositories.get(pydio.user.activeRepository).getLabel();
            let segments = [];
            let path = this.state.node ? LangUtils.trimLeft(this.state.node.getPath(), '/') : '';
            let rebuilt = '';
            path.split('/').map(function(seg){
                rebuilt += '/' + seg;
                segments.push(<span className="separator"> / </span>);
                segments.push(<span className="segment" onClick={this.goTo.bind(this, rebuilt)}>{seg}</span>);
            }.bind(this));
            return <span className="react_breadcrumb"><span className="segment first" onClick={this.goTo.bind(this, '/')}>{repoLabel}</span> {segments}</span>
        }

    });

    let FSTemplate = React.createClass({

        mixins: [MessagesProviderMixin],

        propTypes: {
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        pydioResize: function(){
            if(this.refs['list']){
                this.refs['list'].pydioResize();
            }
        },

        render: function () {
            
            return (
                <div className="react-mui-context vertical_layout vertical_fit react-fs-template">
                    <LeftNavigation.PinnedLeftPanel {...this.props}/>
                    <div style={{marginLeft:250}} className="vertical_layout vertical_fit">
                        <div id="workspace_toolbar">
                            <Breadcrumb {...this.props}/>
                            <SearchForm {...this.props}/>
                        </div>
                        <div id="files_list_header">
                            <Toolbars.ButtonMenu {...this.props} id="create-button-menu" toolbars={["mfb"]} buttonTitle="New..." raised={true} primary={true}/>
                            <Toolbars.Toolbar {...this.props} id="main-toolbar" toolbars={["change_main"]} groupOtherList={["more", "change", "remote"]} renderingType="button"/>
                            <Toolbars.Toolbar {...this.props} id="display-toolbar" toolbars={["display_toolbar"]} renderingType="icon-font"/>
                        </div>
                        <MainFilesList ref="list" {...this.props}/>
                    </div>
                    <ReactPydio.LegacyUIWrapper {...this.props} id="info_panel" componentName="InfoPanel" componentOptions={{replaceScroller:true, skipActions:true, previewUseCoveringBg: true}}/>
                    <EditionPanel {...this.props}/>
                    <span className="context-menu"><Toolbars.ContextMenu/></span>
                </div>
            );
            
        }
        
    });
    

    var FakeDndBackend = function(){
        return{
            setup:function(){},
            teardown:function(){},
            connectDragSource:function(){},
            connectDragPreview:function(){},
            connectDropTarget:function(){}
        };
    };

    let ns = global.WSComponents || {};
    if(global.ReactDND){
        ns.FSTemplate = ReactDND.DragDropContext(ReactDND.HTML5Backend)(FSTemplate);
    }else{
        ns.FSTemplate = FSTemplate;
    }
    ns.OpenNodesModel = OpenNodesModel;
    ns.MainFilesList = MainFilesList;
    ns.EditionPanel = EditionPanel;
    ns.Breadcrumb = Breadcrumb;
    ns.SearchForm = SearchForm;
    global.WSComponents = ns;

})(window);