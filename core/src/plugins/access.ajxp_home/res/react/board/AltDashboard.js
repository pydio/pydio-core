const React = require('react')
import Color from 'color'
import ConfigLogo from './ConfigLogo'
import {Paper, IconButton, Badge} from 'material-ui'
import WorkspacesListCard from '../workspaces/WorkspacesListCard'
import RecentAccessCard from '../recent/RecentAccessCard'
import WelcomeTour from './WelcomeTour'
import Pydio from 'pydio'
const {LeftPanel, SearchForm} = Pydio.requireLib('workspaces');
const {AsyncComponent} = Pydio.requireLib('boot');
import HomeSearchForm from './HomeSearchForm'

let AltDashboard = React.createClass({

    getDefaultCards: function(){

        const baseCards = [
            {
                id:'quick_upload',
                componentClass:'WelcomeComponents.QuickSendCard',
                defaultPosition:{
                    x: 0, y: 10
                }
            },
            {
                id:'downloads',
                componentClass:'WelcomeComponents.DlAppsCard',
                defaultPosition:{
                    x:0, y:20
                }
            },
            {
                id:'qr_code',
                componentClass:'WelcomeComponents.QRCodeCard',
                defaultPosition:{
                    x: 0, y: 30
                }
            },
            {
                id:'videos',
                componentClass:'WelcomeComponents.VideoCard',
                defaultPosition:{
                    x:0, y:50
                }
            },

        ];

        return baseCards;
    },

    getInitialState: function(){
        return {unreadStatus:0};
    },

    render:function() {

        const enableSearch = this.props.pydio.getPluginConfigs('access.ajxp_home').get("ENABLE_GLOBAL_SEARCH");
        const palette = this.props.muiTheme.palette;
        const Color = MaterialUI.Color;
        const widgetStyle = {
            backgroundColor: Color(palette.primary1Color).darken(0.2).toString(),
            width:'100%',
            position: 'fixed'
        };

        const lightColor = '#eceff1'; // TO DO: TO BE COMPUTED FROM MAIN COLOR
        const uWidgetProps = this.props.userWidgetProps || {};
        const wsListProps = this.props.workspacesListProps || {};

        const {pydio} = this.props;
        const appBarColor = new Color(this.props.muiTheme.appBar.color);

        const styles = {
            appBarStyle : {
                zIndex: 1,
                backgroundColor: appBarColor.alpha(.6).toString(),
                height: 110
            },
            buttonsStyle : {
                color: this.props.muiTheme.appBar.textColor
            },
            iconButtonsStyle :{
                color: appBarColor.darken(0.4).toString()
            },
            wsListsContainerStyle: {
                position:'absolute',
                zIndex: 10,
                top: 55,
                bottom: 0,
                right: 260,
                left: 260,
                display:'flex',
                flexDirection:'column'
            },
            rglStyle: {
                position:'absolute',
                top: 110,
                bottom: 0,
                right: 0,
                width: 260,
                overflowY:'auto',
                backgroundColor: '#ECEFF1'
            }
        }


        if(this.props.pydio.UI.MOBILE_EXTENSIONS){
            wsListsContainerStyle = {...wsListsContainerStyle, display:'bloc', marginRight:0, right: 0};
            rglStyle = {...rglStyle, transform:'translateX(260px)'};
        }

        const centerTitleStyle = {
            padding: '20px 16px 10px',
            fontSize: 13,
            color: '#93a8b2',
            fontWeight: 500
        };

        const guiPrefs = this.props.pydio.user ? this.props.pydio.user.getPreference('gui_preferences', true) : [];

        const openAlertPanel = (
            <div style={{flex:2, display:'flex', flexDirection:'column', borderTop:'2px solid #e0e0e0'}}  id="alerts-block">
                <div style={centerTitleStyle}>
                    <Badge
                        badgeContent={this.state.unreadStatus}
                        secondary={true}
                        style={this.state.unreadStatus  ? {padding: '0 24px 0 0'} : {padding: 0}}
                        badgeStyle={!this.state.unreadStatus ? {display:'none'} : {marginTop: -10}}
                    ><span style={{marginRight:10, display:'inline-block'}}>{"My Alerts"}</span></Badge>
                </div>
                <AsyncComponent
                    namespace="PydioNotifications"
                    componentName="Panel"
                    pydio={this.props.pydio}
                    listOnly={true}
                    listClassName="vertical-fill"
                    emptyStateProps={{style:{backgroundColor:'white'}}}
                    onUnreadStatusChange={(s)=>{this.setState({unreadStatus: s})}}
                />
            </div>
        );

        return (

            <div className={['vertical_layout', 'vertical_fit', 'react-fs-template', 'user-dashboard-template'].join(' ')}>
                {!guiPrefs['WelcomeComponent.Pydio8.TourGuide.Welcome'] && <WelcomeTour ref="welcome" pydio={this.props.pydio}/>}
                <LeftPanel
                    className="left-panel"
                    pydio={pydio}
                    style={{backgroundColor:'transparent'}}
                    userWidgetProps={{hideNotifications:false, style:{backgroundColor:appBarColor.darken(.2).alpha(.7).toString()}}}
                />
                <div className="desktop-container vertical_layout vertical_fit">
                    <Paper zDepth={0} style={styles.appBarStyle} rounded={false}>
                        <div id="workspace_toolbar" style={{display: "flex", justifyContent: "space-between"}}>
                            <span className="drawer-button"><IconButton style={{color: 'white'}} iconClassName="mdi mdi-menu" onTouchTap={this.openDrawer}/></span>
                            <span style={{flex:1}}></span>
                            <div style={{textAlign:'center', width: 260}}>
                                <ConfigLogo style={{height:110}} pydio={this.props.pydio} pluginName="gui.ajax" pluginParameter="CUSTOM_DASH_LOGO"/>
                            </div>
                        </div>
                    </Paper>
                    <div style={{backgroundColor:'white'}} className="vertical_fit">

                        <HomeSearchForm zDepth={2} {...this.props} style={styles.wsListsContainerStyle}>
                            <div style={{flex:1, display:'flex', flexDirection:'column'}} id="history-block">
                                <div style={centerTitleStyle}>{pydio.MessageHash['user_home.87']}</div>
                                <RecentAccessCard
                                    {...this.props}
                                    listClassName="recent-access-centered files-list"
                                    style={{flex:1}}
                                    zDepth={0}
                                    colored={false}
                                    noTitle={true}
                                    longLegend={true}
                                    emptyStateProps={{style:{backgroundColor:'white'}}}
                                />
                            </div>
                        </HomeSearchForm>

                        <PydioComponents.DynamicGrid
                            storeNamespace="WelcomePanel.Dashboard"
                            defaultCards={this.getDefaultCards()}
                            builderNamespaces={["WelcomeComponents"]}
                            pydio={this.props.pydio}
                            cols={{lg: 12, md: 9, sm: 6, xs: 6, xxs: 2}}
                            rglStyle={styles.rglStyle}
                        />
                    </div>
                </div>
            </div>

        );

    }
});

AltDashboard = MaterialUI.Style.muiThemeable()(AltDashboard);

export {AltDashboard as default};
