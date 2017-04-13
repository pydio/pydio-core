const React = require('react')
import {MessagesProviderMixin, PydioProviderMixin} from '../util/Mixins'
import AdminLeftNav from './AdminLeftNav'
const {AppBar, Paper} = require('material-ui')
const PydioDataModel = require('pydio/model/data-model')
const {UserWidget} = require('pydio').requireLib('workspaces')

const AdminDashboard = React.createClass({

    mixins:[MessagesProviderMixin, PydioProviderMixin],

    propTypes:{
        pydio: React.PropTypes.instanceOf(Pydio).isRequired
    },

    getInitialState: function(){
        const dm = this.props.pydio.getContextHolder();
        return {
            contextNode:dm.getContextNode(),
            selectedNodes:dm.getSelectedNodes(),
            contextStatus:dm.getContextNode().isLoaded(),
            openLeftNav: false
        };
    },

    dmChangesToState: function(){
        const dm = this.props.pydio.getContextHolder();
        this.setState({
            contextNode:dm.getContextNode(),
            selectedNodes:dm.getSelectedNodes(),
            contextStatus:dm.getContextNode().isLoaded()
        });
        dm.getContextNode().observe("loaded", this.dmChangesToState);
        if(dm.getUniqueNode()){
            dm.getUniqueNode().observe("loaded", this.dmChangesToState);
        }
    },

    openEditor: function(node){
        this.openRightPane({
            COMPONENT:PydioComponents.ReactEditorOpener,
            PROPS:{
                node:node,
                registry:this.props.pydio.Registry,
                onRequestTabClose: this.closeRightPane,
                registerCloseCallback:this.registerRightPaneCloseCallback
            },
            CHILDREN:null
        });
    },

    openRightPane: function(serializedComponent){
        serializedComponent['PROPS']['registerCloseCallback'] = this.registerRightPaneCloseCallback;
        serializedComponent['PROPS']['closeEditorContainer'] = this.closeRightPane;
        // Do not open on another already opened
        if(this.state && this.state.rightPanel && this.state.rightPanelCloseCallback){
            if(this.state.rightPanelCloseCallback() === false){
                return;
            }
        }
        if(typeof serializedComponent.COMPONENT === 'string' || serializedComponent.COMPONENT instanceof String ){

            const [namespace, componentName] = serializedComponent.COMPONENT.split('.');
            ResourcesManager.loadClassesAndApply([namespace], function(){
                if(window[namespace] && window[namespace][componentName]){
                    const comp = window[namespace][componentName];
                    serializedComponent.COMPONENT = comp;
                    this.openRightPane(serializedComponent);
                }
            }.bind(this));

        }else{
            this.setState({ rightPanel:serializedComponent });
        }
    },

    registerRightPaneCloseCallback: function(callback){
        this.setState({rightPanelCloseCallback:callback});
    },

    closeRightPane:function(){
        if(this.state.rightPanelCloseCallback && this.state.rightPanelCloseCallback() === false){
            return false;
        }
        this.setState({rightPanel:null, rightPanelCloseCallback:null});
        return true;
    },

    openLeftNav:function(){
        this.setState({openLeftNav: true});
    },

    componentWillReceiveProps: function(){
        this.setState({openLeftNav: false});
    },

    componentDidMount: function(){
        const dm = this.props.pydio.getContextHolder();
        dm.observe("context_changed", this.dmChangesToState);
        dm.observe("selection_changed", this.dmChangesToState);
        // Monkey Patch Open Current Selection In Editor
        let monkeyObject = this.props.pydio.UI;
        if(this.props.pydio.UI.__proto__){
            monkeyObject = this.props.pydio.UI.__proto__;
        }
        monkeyObject.__originalOpenCurrentSelectionInEditor = monkeyObject.openCurrentSelectionInEditor;
        monkeyObject.openCurrentSelectionInEditor = function(dataModelOrNode){
            if(dataModelOrNode instanceof PydioDataModel){
                this.openEditor(dataModelOrNode.getUniqueNode());
            }else{
                this.openEditor(dataModelOrNode);
            }
        }.bind(this);
        this._bmObserver = function(){
            this.props.pydio.Controller.actions.delete("bookmark");
        }.bind(this);
        this.props.pydio.observe("actions_loaded", this._bmObserver);
    },

    componentWillUnmount: function(){
        const dm = this.props.pydio.getContextHolder();
        dm.stopObserving("context_changed", this.dmChangesToState);
        dm.stopObserving("selection_changed", this.dmChangesToState);
        // Restore Monkey Patch
        let monkeyObject = this.props.pydio.UI;
        if(this.props.pydio.UI.__proto__){
            monkeyObject = this.props.pydio.UI.__proto__;
        }
        monkeyObject.openCurrentSelectionInEditor = monkeyObject.__originalOpenCurrentSelectionInEditor;
        if(this._bmObserver){
            this.props.pydio.stopObserving("actions_loaded", this._bmObserver);
        }
    },

    routeMasterPanel: function(node, selectedNode){
        const path = node.getPath();
        if(!selectedNode) selectedNode = node;

        let dynamicComponent;
        if(node.getMetadata().get('component')){
            dynamicComponent = node.getMetadata().get('component');
        }else{
            return <div>No Component Found</div>;
        }
        const parts = dynamicComponent.split('.');
        const additionalProps = node.getMetadata().has('props') ? JSON.parse(node.getMetadata().get('props')) : {};
        return (
            <PydioReactUI.AsyncComponent
                pydio={this.props.pydio}
                namespace={parts[0]}
                componentName={parts[1]}
                dataModel={this.props.pydio.getContextHolder()}
                rootNode={node}
                currentNode={selectedNode}
                openEditor={this.openEditor}
                openRightPane={this.openRightPane}
                closeRightPane={this.closeRightPane}
                {...additionalProps}
            />);
    },

    backToHome: function(){
        this.props.pydio.triggerRepositoryChange("ajxp_home");
    },

    render: function(){
        const dm = this.props.pydio.getContextHolder();
        let params = this.props.pydio.Parameters;
        let img = ResourcesManager.resolveImageSource('white_logo.png');
        const logo = (
            <img
                className="custom_logo_image linked"
                src={img}
                title="Back to Home"
                width=""
                height=""
                style={{height: 40, width: 'auto'}}
                onClick={this.backToHome}
            />
        );
        let rPanelContent;
        if(this.state.rightPanel){
            rPanelContent = React.createElement(this.state.rightPanel.COMPONENT, this.state.rightPanel.PROPS, this.state.rightPanel.CHILDREN);
        }
        const rightPanel = (
            <Paper zDepth={2} className={"paper-editor layout-fill vertical-layout" + (this.state.rightPanel?' visible':'')}>
                {rPanelContent}
            </Paper>
        );

        let appBarRight;
        if(this.props.iconElementRight){
            appBarRight = this.props.iconElementRight;
        }else{
            const style = {
                color: 'white',
                fontSize: 20,
                display:'flex',
                alignItems:'center',
                height: 50
            };
            appBarRight = (
                <div style={style}>Pydio Community Distribution{logo}</div>
            );
        }
        const userWidgetStyle = {
            height: 64,
            lineHeight: '16px',
            backgroundColor: 'transparent',
            boxShadow: 'none',
            display:'flex',
            alignItems:'center'
        };
        const title = <UserWidget pydio={this.props.pydio} style={userWidgetStyle} hideActionBar={true} userTouchBackHome={true}/>

        return (
            <div className="app-canvas">
                <AdminLeftNav
                    pydio={this.props.pydio}
                    dataModel={dm}
                    rootNode={dm.getRootNode()}
                    contextNode={dm.getContextNode()}
                    open={this.state.openLeftNav}
                />
                <AppBar
                    title={title}
                    zDepth={1}
                    showMenuIconButton={true}
                    onLeftIconButtonTouchTap={this.openLeftNav.bind(this)}
                    iconElementRight={appBarRight}
                />
                <div className="main-panel">
                    {this.routeMasterPanel(dm.getContextNode(), dm.getUniqueNode())}
                </div>
                {rightPanel}
            </div>
        )
    }
});

export {AdminDashboard as default}