const React = require('react')
import Color from 'color'
import ConfigLogo from './ConfigLogo'
import {Paper, IconButton, Badge} from 'material-ui'
import WorkspacesListCard from '../workspaces/WorkspacesListCard'
import RecentAccessCard from '../recent/RecentAccessCard'
import Pydio from 'pydio'
const {LeftPanel, SearchForm} = Pydio.requireLib('workspaces');
const {AsyncComponent} = Pydio.requireLib('boot');

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
                backgroundColor: appBarColor.toString(),
                height: 110
            },
            buttonsStyle : {
                color: this.props.muiTheme.appBar.textColor
            },
            iconButtonsStyle :{
                color: appBarColor.darken(0.4).toString()
            }
        }


        let wsListsContainerStyle = {
            position:'absolute',
            top: 110,
            bottom: 0,
            right: 250,
            left: 250,
            display:'flex',
            marginRight: 10
        };
        let rglStyle = {
            position:'absolute',
            top: 110,
            bottom: 0,
            right: 0,
            width: 260,
            overflowY:'auto',
            backgroundColor: '#ECEFF1'
        }
        if(this.props.pydio.UI.MOBILE_EXTENSIONS){
            wsListsContainerStyle = {...wsListsContainerStyle, display:'bloc', marginRight:0, right: 0};
            rglStyle = {...rglStyle, transform:'translateX(260px)'};
        }

        return (

            <div className={['vertical_layout', 'vertical_fit', 'react-fs-template'].join(' ')} style={{backgroundColor:'white'}}>
                <LeftPanel className="left-panel" pydio={pydio} userWidgetProps={{hideNotifications:true}}/>
                <div className="desktop-container vertical_layout vertical_fit">
                    <Paper zDepth={1} style={styles.appBarStyle} rounded={false}>
                        <div id="workspace_toolbar" style={{display: "flex", justifyContent: "space-between"}}>
                            <span className="drawer-button"><IconButton style={{color: 'white'}} iconClassName="mdi mdi-menu" onTouchTap={this.openDrawer}/></span>
                            <div style={{flex:2}}>
                                <SearchForm {...this.props} style={{margin: '30px auto', position:'relative', width:420}} crossWorkspace={true} groupByField="repository_id"/>
                            </div>
                            <div style={{textAlign:'center', width: 260}}>
                                <ConfigLogo style={{height:110}} pydio={this.props.pydio} pluginName="gui.ajax" pluginParameter="CUSTOM_DASH_LOGO"/>
                            </div>
                        </div>
                    </Paper>
                    <div>
                        <div style={wsListsContainerStyle}>
                            <div style={{flex:1, width:'50%', display:'flex', flexDirection:'column'}}>
                                <div style={{padding:16, fontSize:20}}>{"My History"}</div>
                                <RecentAccessCard
                                    {...this.props}
                                    listClassName="recent-access-centered files-list"
                                    style={{flex:1}}
                                    zDepth={0}
                                    colored={false}
                                    noTitle={true}
                                    emptyStateProps={{style:{backgroundColor:'white'}}}
                                />
                            </div>
                            <div style={{flex:1, width:'50%', display:'flex', flexDirection:'column', borderLeft:'1px solid #e0e0e0'}}>
                                <div style={{padding:16, fontSize:20}}>
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
                        </div>

                        <PydioComponents.DynamicGrid
                            storeNamespace="WelcomePanel.Dashboard"
                            defaultCards={this.getDefaultCards()}
                            builderNamespaces={["WelcomeComponents"]}
                            pydio={this.props.pydio}
                            cols={{lg: 12, md: 9, sm: 6, xs: 6, xxs: 2}}
                            rglStyle={rglStyle}
                        />
                    </div>
                </div>
            </div>

        );

    }
});

AltDashboard = MaterialUI.Style.muiThemeable()(AltDashboard);

export {AltDashboard as default};
