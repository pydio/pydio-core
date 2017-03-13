import OpenNodesModel from './OpenNodesModel'

class EditionPanel extends React.Component {

    constructor(props) {
        super(props)
        
        this.state = {
            nodes:[],
            visible: false,
            activeTab:0
        }
    }

    componentDidMount() {
        this._nodesModelObserver = (node) => this._handleNodePushed(node);
        this._nodesRemoveObserver = (index) => this._handleNodeRemoved(index);
        this._titlesObserver = () => this.forceUpdate()

        OpenNodesModel.getInstance().observe("nodePushed", this._nodesModelObserver);
        OpenNodesModel.getInstance().observe("nodeRemovedAtIndex", this._nodesRemoveObserver);
        OpenNodesModel.getInstance().observe("titlesUpdated", this._titlesObserver);
    }

    componentWillUnmount() {
        OpenNodesModel.getInstance().stopObserving("nodePushed", this._nodesModelObserver);
        OpenNodesModel.getInstance().stopObserving("nodeRemovedAtIndex", this._nodesRemoveObserver);
        OpenNodesModel.getInstance().stopObserving("titlesUpdated", this._titlesObserver);
    }

    _handleNodePushed(node) {
        let nodes = OpenNodesModel.getInstance().getNodes();
        let active = nodes.indexOf(node);

        if (active < 0) return

        this.setState({
            nodes:nodes,
            visible: false,
            activeTab: active
        }, this.toggle.bind(this));
    }

    _handleNodeRemoved(index) {
        let nodes = OpenNodesModel.getInstance().getNodes();
        let active = Math.max(0, index - 1);

        if(!nodes.length && this.state.visible) {
            this.setState({
                visible: false,
                closed:  true,
                opened:  false,
                nodes:   []
            });
        }else{
            this.setState({
                nodes: nodes,
                activeTab: active
            });
        }
    }

    updateNodesFromModel(pushed, removedAtIndex){
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
    }

    toggle() {
        console.log("Toggle visible")
        let visible = this.state.visible;
        this.setState({visible:!this.state.visible}, function(){
            global.setTimeout(function(){
                if(visible) this.setState({closed:true, opened:false});
                else this.setState({closed:false, opened:true});
            }.bind(this), 500);
        }.bind(this));
    }

    onChange(index, tab) {
        this.setState({activeTab: index});
    }

    render() {
        let overlay, editorWindow;

        if(this.state.nodes.length){
            let style = {};
            let className = 'editor-window react-mui-context vertical_layout', iconClassName='mdi mdi-pencil';

            if(this.state.closed) className += ' closed';
            else if(this.state.opened) className += ' opened';

            let tabs = [], title, nodeTitle, mfbMenus = [];
            let index = 0;
            let editors = this.state.nodes.map(function(object){

                let closeTab = function(e){
                    OpenNodesModel.getInstance().removeNode(object);
                };

                let updateTabTitle=function(newTitle){
                    OpenNodesModel.getInstance().updateNodeTitle(object, newTitle);
                };

                if(this.state.visible && this.state.opened){
                    let label = <span className="closeable-tab"><span className="label">{OpenNodesModel.getInstance().getObjectLabel(object)}</span><ReactMUI.FontIcon className="mdi mdi-close" onClick={closeTab}/></span>;
                    tabs.push(<MaterialUI.Tab key={index} label={label} value={index}></MaterialUI.Tab>);
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
                        <PydioComponents.ReactEditorOpener
                            pydio={this.props.pydio}
                            node={object.node}
                            editorData={object.editorData}
                            registry={this.props.pydio.Registry}
                            closeEditorContainer={function(){return true;}}
                            onRequestTabClose={closeTab}
                            onRequestTabTitleUpdate={updateTabTitle}
                        />
                    </div>
                );
            }.bind(this));

            let mainIcon;
            if(this.state.visible) {
                className += ' open';
                title = <div>{nodeTitle}</div>;
                let overlayClass = "editor-overlay opening";
                if(this.state.opened) {
                    iconClassName = 'mdi mdi-window-minimize';
                    //overlayClass += ' opened';
                }
                overlay = <div key="overlay" onClick={this.toggle} className={overlayClass}/>;
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
                /*overlay = <div key="overlay" onClick={this.toggle} className="editor-overlay hidden"/>;*/
            }

            let tabStyle={};
            if(tabs.length == 1){
                tabStyle = {/*display:'none'*/};
            }
            editorWindow = (
                <div className={className} style={style}>
                    <div className="editor-title">
                        {mainIcon}
                        {title}
                    </div>
                    <MaterialUI.Tabs
                        onChange={this.onChange}
                        value={this.state.activeTab}
                        tabItemContainerStyle={tabStyle}
                        inkBarStyle={tabStyle}
                    >
                        {tabs}
                    </MaterialUI.Tabs>
                    {editors}
                </div>
            );

        }
        return (
            <div className="react-editor">
                <ReactCSSTransitionGroup
                    transitionName="fade-in"
                    transitionAppear={true}
                    transitionAppearTimeout={300}
                    transitionEnter={true}
                    transitionEnterTimeout={300}
                    transitionLeave={true}
                    transitionLeaveTimeout={300}
                >
                    {overlay}
                </ReactCSSTransitionGroup>
                {editorWindow}
            </div>
        );
    }
}

EditionPanel.PropTypes = {
    pydio: React.PropTypes.instanceOf(Pydio)
}

export {EditionPanel as default}
