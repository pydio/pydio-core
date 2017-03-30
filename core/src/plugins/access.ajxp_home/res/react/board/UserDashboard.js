import ConfigLogo from './ConfigLogo'
import WorkspacesListCard from '../workspaces/WorkspacesListCard'

let UserDashboard = React.createClass({

    getDefaultCards: function(){

        const retroPalette = [
            '#AA1735', '#C2523E', '#DF6E41', '#F57E5E', '#FA8535'
        ];
        const materialPalette = [
            '#E53935', '#D81B60', '#7b1fa2', '#3f51b5', '#2196f3'
        ];
        const palette = materialPalette;

        const baseCards = [
            {
                id:'quick_upload',
                componentClass:'WelcomeComponents.QuickSendCard',
                props:{
                    tint: palette[0]
                },
                defaultPosition:{
                    x: 0, y: 10
                }
            },
            {
                id:'downloads',
                componentClass:'WelcomeComponents.DlAppsCard',
                props:{
                    tint: palette[1]
                },
                defaultPosition:{
                    x:0, y:20
                },
                defaultLayouts: {
                    md: {x: 6, y: 36},
                    sm: {x: 0, y: 60}
                }
            },
            {
                id:'qr_code',
                componentClass:'WelcomeComponents.QRCodeCard',
                props:{
                    tint: palette[2]
                },
                defaultPosition:{
                    x: 0, y: 30
                }
            },
            {
                id:'videos',
                componentClass:'WelcomeComponents.VideoCard',
                props:{
                    tint: palette[3]
                },
                defaultPosition:{
                    x:0, y:40
                },
                defaultLayouts: {
                    sm: {x: 0, y: 30}
                }
            },
            {
                id:'recently_accessed',
                componentClass:'WelcomeComponents.RecentAccessCard',
                props:{
                    tint: palette[4]
                },
                defaultPosition:{
                    x: 0, y: 50
                }
            },

        ];

        return baseCards;
    },

    render:function() {

        const enableSearch = this.props.pydio.getPluginConfigs('access.ajxp_home').get("ENABLE_GLOBAL_SEARCH");
        const palette = this.props.muiTheme.palette;
        const Color = MaterialUI.Color;
        const widgetStyle = {
            backgroundColor: Color(palette.primary1Color).darken(0.2),
            width:'100%',
            position: 'fixed'
        };
        const lightColor = '#eceff1'; // TO DO: TO BE COMPUTED FROM MAIN COLOR
        const uWidgetProps = this.props.userWidgetProps || {};
        const wsListProps = this.props.workspacesListProps || {};

        return (
            <div className="left-panel expanded vertical_fit vertical_layout" style={{backgroundColor: lightColor}}>
                <PydioWorkspaces.UserWidget
                    pydio={this.props.pydio}
                    style={widgetStyle}
                    {...uWidgetProps}
                >
                    {enableSearch &&
                    <div style={{flex:10, display:'flex', justifyContent:'center'}}>
                        <PydioWorkspaces.SearchForm
                            crossWorkspace={true}
                            pydio={this.props.pydio}
                            groupByField="repository_id"
                        />
                    </div>
                    }
                    <ConfigLogo style={{height:'100%'}} pydio={this.props.pydio} pluginName="gui.ajax" pluginParameter="CUSTOM_DASH_LOGO"/>
                </PydioWorkspaces.UserWidget>
                <div style={{position:'absolute', top: 110, bottom: 0, right: 250, left: 0, display:'flex', padding: 5}}>
                    <WorkspacesListCard filterByType="entries" pydio={this.props.pydio} style={{margin:5, flex:1}}/>
                    <WorkspacesListCard filterByType="shared" pydio={this.props.pydio} style={{margin:5, flex:1}}/>
                </div>
                <PydioComponents.DynamicGrid
                    storeNamespace="WelcomePanel.Dashboard"
                    defaultCards={this.getDefaultCards()}
                    builderNamespaces={["WelcomeComponents"]}
                    pydio={this.props.pydio}
                    cols={{lg: 12, md: 9, sm: 6, xs: 6, xxs: 2}}
                    rglStyle={{position:'absolute', top: 110, bottom: 0, right: 0, width: 260}}
                />
            </div>
        );
    }

});

UserDashboard = MaterialUI.Style.muiThemeable()(UserDashboard);
if(window.ReactDND){
    UserDashboard = window.ReactDND.DragDropContext(ReactDND.HTML5Backend)(UserDashboard);
}else{
    UserDashboard = UserDashboard;
}

export {UserDashboard as default};