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
        INFO_PANEL_WIDTH: 270
    },

    componentDidMount: function(){
        this.props.pydio.getController().updateGuiActions(this.getPydioActions());
    },

    componentWillUnmount: function(){
        this.getPydioActions(true).map(function(key){
            this.props.pydio.getController().deleteFromGuiActions(key);
        }.bind(this));
    },

    getPydioActions: function(keysOnly = false){
        if(keysOnly){
            return ['toggle_info_panel'];
        }
        var multiAction = new Action({
            name:'toggle_info_panel',
            icon_class:'mdi mdi-information',
            text_id:341,
            title_id:341,
            text:this.props.pydio.MessageHash[341],
            title:this.props.pydio.MessageHash[341],
            hasAccessKey:false,
            subMenu:false,
            subMenuUpdateImage:false,
            callback: () => {this.setState({infoPanelToggle: !this.state.infoPanelToggle});}
        }, {
            selection:true,
            dir:true,
            file:true,
            actionBar:true,
            actionBarGroup:'display_toolbar',
            contextMenu:true,
            infoPanel:false
        }, {dir:true,file:true}, {}, {});
        let buttons = new Map();
        buttons.set('toggle_info_panel', multiAction);
        return buttons;
    },


    getInitialState: function(){
        return {
            infoPanelOpen: false,
            infoPanelToggle: true,
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
        if(!this.state.drawerOpen){
            return;
        }
        this.setState({drawerOpen: false});
    },

    render: function () {

        var connectDropTarget = this.props.connectDropTarget || function(c){return c;};
        var isOver = this.props.isOver;
        var canDrop = this.props.canDrop;

        const Color = MaterialUI.Color;

        const styles = {
            appBarStyle : {
                zIndex: 1,
                backgroundColor: this.props.muiTheme.appBar.color
            },
            buttonsStyle : {
                color: this.props.muiTheme.appBar.textColor
            },
            iconButtonsStyle :{
                color: Color(this.props.muiTheme.appBar.color).darken(0.4)
            },
            raisedButtonStyle : {
                height: 30,
                minWidth: 0
            },
            raisedButtonLabelStyle : {
                height: 30,
                lineHeight: '30px'
            }
        }

        let classes = ['vertical_layout', 'vertical_fit', 'react-fs-template'];
        if(this.state.infoPanelOpen && this.state.infoPanelToggle) classes.push('info-panel-open');
        if(this.state.drawerOpen) classes.push('drawer-open');

        let mainToolbars = ["info_panel", "info_panel_share"];
        let mainToolbarsOthers = ["change_main", "more", "change", "remote"];
        if(this.state.infoPanelOpen && this.state.infoPanelToggle){
            mainToolbars = ["change_main"];
            mainToolbarsOthers = ["more", "change", "remote"];
        }

        return connectDropTarget(
            <div className={classes.join(' ')} onTouchTap={this.closeDrawer}>
                <PydioReactUI.AsyncComponent
                    className="left-panel"
                    namespace="PydioWorkspaces"
                    componentName="LeftPanel"
                    pydio={this.props.pydio}
                />
                <div className="desktop-container vertical_layout vertical_fit">
                    <MaterialUI.Paper zDepth={1} style={styles.appBarStyle} rounded={false}>
                        <div id="workspace_toolbar">
                            <span className="drawer-button"><MaterialUI.IconButton style={{color: 'white'}} iconClassName="mdi mdi-menu" onTouchTap={this.openDrawer}/></span>
                            <Breadcrumb {...this.props}/>
                            <SearchForm {...this.props}/>
                        </div>
                        <div id="main_toolbar">
                            <PydioMenus.ButtonMenu
                                {...this.props}
                                buttonStyle={styles.raisedButtonStyle}
                                buttonLabelStyle={styles.raisedButtonLabelStyle}
                                id="create-button-menu"
                                toolbars={["upload", "create"]}
                                buttonTitle="New..."
                                raised={true}
                                secondary={true}
                                controller={this.props.pydio.Controller}
                            />
                            <PydioMenus.Toolbar
                                {...this.props}
                                id="main-toolbar"
                                toolbars={mainToolbars}
                                groupOtherList={mainToolbarsOthers}
                                renderingType="button"
                                buttonStyle={styles.buttonsStyle}
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
                                buttonStyle={styles.iconButtonsStyle}
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

const fileTarget = {
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
