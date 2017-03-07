import MessagesProviderMixin from './MessagesProviderMixin'
import Breadcrumb from './Breadcrumb'
import SearchForm from './SearchForm'
import MainFilesList from './MainFilesList'
import EditionPanel from './EditionPanel'
import InfoPanel from './detailpanes/InfoPanel'

let FSTemplate = React.createClass({

    mixins: [MessagesProviderMixin],

    propTypes: {
        pydio:React.PropTypes.instanceOf(Pydio)
    },

    statics: {
        INFO_PANEL_WIDTH: 250
    },

    getInitialState: function(){
        return {
            infoPanelOpen: false,
            drawerOpen: false
        };
    },

    infoPanelContentChange(numberOfCards){
        this.setState({infoPanelOpen: (numberOfCards > 0)})
    },

    openDrawer: function(event){
        event.stopPropagation();
        this.setState({drawerOpen: true});
    },

    closeDrawer: function(){
        this.setState({drawerOpen: false});
    },

    render: function () {

        var connectDropTarget = this.props.connectDropTarget;
        var isOver = this.props.isOver;
        var canDrop = this.props.canDrop;

        const appBarStyle = {
            zIndex: 1,
            backgroundColor: this.props.muiTheme.appBar.color
        };
        const raisedButtonStyle = {
            height: 30
        };
        const raisedButtonLabelStyle = {
            height: 30,
            lineHeight: '30px'
        };

        let classes = ['vertical_layout', 'vertical_fit', 'react-fs-template'];
        if(this.state.infoPanelOpen) classes.push('info-panel-open');
        if(this.state.drawerOpen) classes.push('drawer-open');

        return connectDropTarget(
            <div className={classes.join(' ')} onTouchTap={this.closeDrawer}>
                <PydioReactUI.AsyncComponent
                    className="left-panel"
                    namespace="LeftNavigation"
                    componentName="PinnedLeftPanel"
                    pydio={this.props.pydio}
                />
                <div className="desktop-container vertical_layout vertical_fit">
                    <MaterialUI.Paper zDepth={1} style={appBarStyle} rounded={false}>
                        <div id="workspace_toolbar">
                            <span className="drawer-button"><MaterialUI.IconButton iconClassName="mdi mdi-menu" onTouchTap={this.openDrawer}/></span>
                            <Breadcrumb {...this.props}/>
                            <SearchForm {...this.props}/>
                        </div>
                        <div id="main_toolbar">
                            <PydioMenus.ButtonMenu
                                {...this.props}
                                buttonStyle={raisedButtonStyle}
                                buttonLabelStyle={raisedButtonLabelStyle}
                                id="create-button-menu"
                                toolbars={["upload", "create"]}
                                buttonTitle="New..."
                                raised={true}
                                secondary={true}
                            />
                            <PydioMenus.Toolbar
                                {...this.props}
                                id="main-toolbar"
                                toolbars={["change_main"]}
                                groupOtherList={["more", "change", "remote"]}
                                renderingType="button"
                            />
                            <PydioComponents.ListPaginator
                                id="paginator-toolbar"
                                dataModel={this.props.pydio.getContextHolder()}
                                toolbarDisplay={true}
                            />
                            <PydioMenus.Toolbar
                                {...this.props}
                                id="display-toolbar"
                                toolbars={["display_toolbar"]}
                                renderingType="icon-font"
                            />
                        </div>
                    </MaterialUI.Paper>
                    <MainFilesList ref="list" {...this.props}/>
                </div>
                <InfoPanel
                    {...this.props}
                    dataModel={this.props.pydio.getContextHolder()}
                    onContentChange={this.infoPanelContentChange}
                />
                <EditionPanel {...this.props}/>
                <span className="context-menu"><PydioMenus.ContextMenu/></span>
            </div>
        );

    }

});

var fileTarget = {
    drop: function (props, monitor) {
        let dataTransfer = monitor.getItem().dataTransfer;
        let passItems;
        if (dataTransfer.items.length && dataTransfer.items[0] && (dataTransfer.items[0].getAsEntry || dataTransfer.items[0].webkitGetAsEntry)) {
            passItems = dataTransfer.items;
        }
        if(window['UploaderModel'] && pydio.getController().getActionByName('upload')){
            UploaderModel.Store.getInstance().handleDropEventResults(passItems, dataTransfer.files, window.pydio.getContextHolder().getContextNode());
            if(!UploaderModel.Store.getInstance().getAutoStart()){
                pydio.getController().fireAction('upload');
            }
        }
    }
};

if(window.ReactDND){
    let DropTemplate = ReactDND.DropTarget(ReactDND.HTML5Backend.NativeTypes.FILE, fileTarget, function (connect, monitor) {
        return {
            connectDropTarget: connect.dropTarget(),
            isOver: monitor.isOver(),
            canDrop: monitor.canDrop()
        };
    })(FSTemplate);
    FSTemplate = ReactDND.DragDropContext(ReactDND.HTML5Backend)(DropTemplate);
}

FSTemplate = MaterialUI.Style.muiThemeable()(FSTemplate);

export {FSTemplate as default}
