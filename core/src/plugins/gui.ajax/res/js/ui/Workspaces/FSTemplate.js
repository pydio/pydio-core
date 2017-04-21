import MessagesProviderMixin from './MessagesProviderMixin'
import Breadcrumb from './Breadcrumb'
import {SearchForm} from './search'
import MainFilesList from './MainFilesList'
import EditionPanel from './EditionPanel'
import InfoPanel from './detailpanes/InfoPanel'
import LeftPanel from './leftnav/LeftPanel'

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
            callback: () => {this.setState({infoPanelToggle: !this.state.infoPanelToggle}, this.resizeAfterTransition.bind(this));}
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

    resizeAfterTransition: function(){
        setTimeout(() => {
            if(this.refs.list) this.refs.list.resize();
        }, 500);
    },

    infoPanelContentChange(numberOfCards){
        this.setState({infoPanelOpen: (numberOfCards > 0)}, this.resizeAfterTransition.bind(this))
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
        const appBarColor = Color(this.props.muiTheme.appBar.color);

        const styles = {
            appBarStyle : {
                zIndex: 1,
                backgroundColor: this.props.muiTheme.appBar.color
            },
            buttonsStyle : {
                color: this.props.muiTheme.appBar.textColor
            },
            iconButtonsStyle :{
                color: appBarColor.darken(0.4).toString()
            },
            raisedButtonStyle : {
                height: 30,
                minWidth: 0
            },
            raisedButtonLabelStyle : {
                height: 30,
                lineHeight: '30px'
            },
            infoPanelStyle : {
                backgroundColor: appBarColor.lightness(95).rgb().toString()
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

        let homeButton, breadcrumbStyle;
        if(false && this.props.pydio.user && this.props.pydio.user.getRepositoriesList().has('ajxp_home')){
            homeButton = (
                <MaterialUI.IconButton
                    style={{marginTop:7, marginLeft: 10, marginRight: -6}}
                    iconStyle={{color: 'rgba(255,255,255,0.87)'}}
                    onTouchTap={() => {this.props.pydio.triggerRepositoryChange('ajxp_home')}}
                    iconClassName="userActionIcon mdi mdi-home-variant"
                    tooltip={this.props.pydio.MessageHash['305']}
                    tooltipPosition="bottom-right"
                />
            );
            breadcrumbStyle={paddingLeft: 0};
        }

        // Making sure we only pass the style to the parent element
        const {style, ...props} = this.props

        return connectDropTarget(
            <div style={style} className={classes.join(' ')} onTouchTap={this.closeDrawer}>
                <LeftPanel className="left-panel" pydio={props.pydio}/>
                <div className="desktop-container vertical_layout vertical_fit">
                    <MaterialUI.Paper zDepth={1} style={styles.appBarStyle} rounded={false}>
                        <div id="workspace_toolbar" style={{display: "flex", justifyContent: "space-between"}}>
                            <span className="drawer-button"><MaterialUI.IconButton style={{color: 'white'}} iconClassName="mdi mdi-menu" onTouchTap={this.openDrawer}/></span>
                            {homeButton}
                            <Breadcrumb {...props} startWithSeparator={homeButton?true:false} rootStyle={breadcrumbStyle}/>
                            <SearchForm {...props}/>
                        </div>
                        <div id="main_toolbar">
                            <PydioComponents.ButtonMenu
                                {...props}
                                buttonStyle={styles.raisedButtonStyle}
                                buttonLabelStyle={styles.raisedButtonLabelStyle}
                                id="create-button-menu"
                                toolbars={["upload", "create"]}
                                buttonTitle="New"
                                raised={true}
                                secondary={true}
                                controller={props.pydio.Controller}
                            />
                            <PydioComponents.Toolbar
                                {...props}
                                id="main-toolbar"
                                toolbars={mainToolbars}
                                groupOtherList={mainToolbarsOthers}
                                renderingType="button"
                                buttonStyle={styles.buttonsStyle}
                            />
                            <PydioComponents.ListPaginator
                                id="paginator-toolbar"
                                dataModel={props.pydio.getContextHolder()}
                                toolbarDisplay={true}
                            />
                            <PydioComponents.Toolbar
                                {...props}
                                id="display-toolbar"
                                toolbars={["display_toolbar"]}
                                renderingType="icon-font"
                                buttonStyle={styles.iconButtonsStyle}
                            />
                        </div>
                    </MaterialUI.Paper>
                    <MainFilesList ref="list" pydio={this.props.pydio}/>
                </div>

                <InfoPanel
                    {...props}
                    dataModel={props.pydio.getContextHolder()}
                    onContentChange={this.infoPanelContentChange}
                    style={styles.infoPanelStyle}
                />

                <EditionPanel {...props}/>
                <span className="context-menu"><PydioComponents.ContextMenu/></span>
            </div>
        );
    }
});

if(window['UploaderModel']){
    FSTemplate = UploaderModel.DropProvider(FSTemplate);
}
FSTemplate = MaterialUI.Style.muiThemeable()(FSTemplate);

export {FSTemplate as default}
