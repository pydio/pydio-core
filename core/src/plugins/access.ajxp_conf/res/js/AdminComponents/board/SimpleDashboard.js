const React = require('react')
const {muiThemeable} = require('material-ui/styles')
const {Paper, Card, CardTitle, CardMedia, CardActions, CardHeader, CardText,
    FlatButton, List, ListItem, Divider, IconButton, FontIcon} = require('material-ui')
import {MessagesConsumerMixin} from '../util/Mixins'

let Dashboard = React.createClass({

    mixins: [MessagesConsumerMixin],

    render: function(){

        const horizontalFlex = {display:'flex', width:'100%'};
        const verticalFlex = {display:'flex', flexDirection:'column', height: '100%'};
        const flexFill = {flex:1};
        const flexFillNo = {width:120};

        const paperStyle = {width:500, marginLeft:12, marginTop: 12};
        const flexContainerStyle = {...verticalFlex};
        const {primary1Color, accent1Color, accent2Color} = this.props.muiTheme.palette;
        const textLinkStyle = {cursor: 'pointer', color: accent1Color};

        const MEDIA_TEST_CARD = (
            <Card style={paperStyle}>
                <CardMedia
                    overlay={<CardTitle title="Want to contribute?" subtitle="Pydio is Open Source and will always be" />}
                ><div>
                    <div style={{backgroundColor: '#b0bec5', display:'flex', alignItems:'center', justifyContent:'center', height:400}}>
                        <div className="mdi mdi-github-circle" style={{fontSize: 200, paddingBottom:60}}></div>
                    </div>
                </div>
                </CardMedia>
                <CardActions>
                    <FlatButton label="Get Started"/>
                </CardActions>

            </Card>
        );

        const OPEN_IN_NEW_ICON = <IconButton iconClassName="mdi mdi-arrow-right" iconStyle={{color:'rgba(0,0,0,.33)'}} tooltip="Open in new window"/>;

        return (
            <div style={{height:'100%', overflow: 'auto', backgroundColor:'#ECEFF1'}}>
                <div style={{display:'flex', alignItems:'top', flexWrap:'wrap'}}>
                    <Card style={paperStyle}>
                        <CardTitle
                            title="Welcome on Pydio Community Dashboard"
                            subtitle="The place to administrate and monitor the activity of your platform"
                        />
                        <CardText>
                            This is your dashboard for managing Pydio. Using the left menu you can create <a style={textLinkStyle}>workspaces</a> and <a style={textLinkStyle}>users</a>,
                            check all <a style={textLinkStyle}>events</a> that happened on the platform, and manage <a style={textLinkStyle}>configurations</a> and <a style={textLinkStyle}>plugins</a>.
                        </CardText>
                        <CardText>
                            If you don't know where to start, the <u>Administrator Guide</u> is definitely a good read:
                            <div style={{...horizontalFlex, flexWrap:'wrap',justifyContent:'center', padding:'0 20px'}}>
                                <div style={flexFillNo}>
                                    <FlatButton primary={true} style={{height:110,lineHeight:'20px'}} label={<div><div style={{fontSize:36}} className="mdi mdi-clock-start"/><div>Getting Started</div></div>} fullWidth={true}/>
                                </div>
                                <div style={flexFillNo}>
                                    <FlatButton primary={true} style={{height:110,lineHeight:'20px'}} label={<div><div style={{fontSize:36}} className="mdi mdi-network"/><div>About Workspaces</div></div>} fullWidth={true}/>
                                </div>
                                <div style={flexFillNo}>
                                    <FlatButton primary={true} style={{height:110,lineHeight:'20px'}} label={<div><div style={{fontSize:36}} className="mdi mdi-account-multiple"/><div>About Users Management</div></div>} fullWidth={true}/>
                                </div>
                                <div style={flexFillNo}>
                                    <FlatButton primary={true} style={{height:110,lineHeight:'20px'}} label={<div><div style={{fontSize:36}} className="mdi mdi-settings"/><div>About Parameters</div></div>} fullWidth={true}/>
                                </div>
                                <div style={flexFillNo}>
                                    <FlatButton primary={true} style={{height:110,lineHeight:'20px'}} label={<div><div style={{fontSize:36}} className="mdi mdi-professional-hexagon"/><div>Advanced Topics</div></div>} fullWidth={true}/>
                                </div>
                            </div>
                        </CardText>
                    </Card>
                    <Card style={paperStyle} containerStyle={flexContainerStyle}>
                        <CardTitle
                            title="Get yourself some help"
                            subtitle="Our most common F.A.Q and tutorials, a.k.a RTFM"
                        />
                        <CardText style={{...flexFill, ...verticalFlex, maxHeight: 370}}>
                            <div>Beside the forum, our website provides plenty of docs to help you. Make sure to look throught them before posting your questions ;-)</div>
                            <List style={{overflow: 'auto', flex:1}}>
                                <ListItem primaryText="How to connect my LDAP directory?" secondaryText="Map your Pydio workspaces to folders and shares on your Windows file server, and let Pydio retrieve user accounts and groups from your Active Directory. Wat you get is that all those files are now instantly available to users with" rightIconButton={OPEN_IN_NEW_ICON} secondaryTextLines={2} />
                                <Divider/>
                                <ListItem primaryText="How to raise upload limit for files?" secondaryText="Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed tempus venenatis justo, a interdum massa bibendum sed. Vivamus justo ligula, tincidunt eu iaculis tempor, feugiat sit amet nibh." rightIconButton={OPEN_IN_NEW_ICON} secondaryTextLines={2}/>
                                <Divider/>
                                <ListItem primaryText="How to upgrade from Community to Enterprise version?" secondaryText="In dui risus, placerat ac nibh nec, cursus dictum urna. Morbi mollis nunc lacus, quis ultricies ipsum finibus quis. Mauris a odio iaculis, sagittis nisi id, semper nisi. Mauris id dictum metus, eget pellentesque nulla. Lore" secondaryTextLines={2} rightIconButton={OPEN_IN_NEW_ICON}/>
                                <Divider/>
                                <ListItem primaryText="How to upgrade from Community to Enterprise version?" secondaryText="In dui risus, placerat ac nibh nec, cursus dictum urna. Morbi mollis nunc lacus, quis ultricies ipsum finibus quis. Mauris a odio iaculis, sagittis nisi id, semper nisi. Mauris id dictum metus, eget pellentesque nulla. Lore" secondaryTextLines={2} rightIconButton={OPEN_IN_NEW_ICON}/>
                                <Divider/>
                                <ListItem primaryText="How to upgrade from Community to Enterprise version?" secondaryText="In dui risus, placerat ac nibh nec, cursus dictum urna. Morbi mollis nunc lacus, quis ultricies ipsum finibus quis. Mauris a odio iaculis, sagittis nisi id, semper nisi. Mauris id dictum metus, eget pellentesque nulla. Lore" secondaryTextLines={2} rightIconButton={OPEN_IN_NEW_ICON}/>
                            </List>
                        </CardText>
                        <Divider/>
                        <CardActions style={{textAlign:'right'}}>
                            <FlatButton label="Read all docs" primary={true}/>
                            <FlatButton label="Go to Forums" primary={true}/>
                        </CardActions>
                    </Card>
                    <Card style={paperStyle} containerStyle={flexContainerStyle}>
                        <CardTitle title="Pay it forward!" subtitle="Pydio is free open source software. Contribute back!" />
                        <CardText style={flexFill}>
                            <div className="mdi mdi-github-circle" style={{fontSize: 60, display:'inline-block', float:'left', marginRight:10, marginBottom:10}}/>
                            Pydio code is available publicly on Github. Do you want to fix a bug, help others in the forum or push the next killer feature? Get involved!
                            Or show your love on social networks!
                            <List>
                                <ListItem primaryText="How-to create or complete a language translation ?" rightIconButton={OPEN_IN_NEW_ICON} />
                                <Divider/>
                                <ListItem primaryText="Report a bug" rightIconButton={OPEN_IN_NEW_ICON}/>
                                <Divider/>
                                <ListItem primaryText="Request integration of a new feature" rightIconButton={OPEN_IN_NEW_ICON}/>
                            </List>
                        </CardText>
                        <Divider/>
                        <CardActions style={{textAlign:'center'}}>
                            <FlatButton label="Github Star" primary={true} icon={<FontIcon className="mdi mdi-github-box" />}/>
                            <FlatButton label="Follow us" primary={true} icon={<FontIcon className="mdi mdi-facebook-box" />}/>
                            <FlatButton label="Send Tweet" primary={true} icon={<FontIcon className="mdi mdi-twitter-box" />}/>
                        </CardActions>
                    </Card>
                    <Card style={paperStyle}>
                        <CardMedia
                            overlay={<CardTitle title="Discover Pydio Enterprise Distribution" subtitle="Save time - Get more feature - Extended branding"/>}
                        >
                            <div style={{height:230, backgroundImage:'url(plugins/access.ajxp_conf/res/images/dashboard.png)', backgroundSize:'cover',borderRadius:3}}/>
                        </CardMedia>
                        <List>
                            <ListItem leftIcon={<FontIcon style={{color:accent2Color}} className="mdi mdi-certificate"/>} primaryText="Enterprise-oriented features" secondaryText="Enterprise-ready features : EasyTransfer, CAS/SAML integration, Multi-domain LDAP and many more..." />
                            <Divider/>
                            <ListItem leftIcon={<FontIcon style={{color:accent2Color}} className="mdi mdi-chart-areaspline"/>} primaryText="Advanced Administrator Dashboard" secondaryText="Unique admin dashboard for real-time monitoring of platform usage." />
                            <Divider/>
                            <ListItem leftIcon={<FontIcon style={{color:accent2Color}} className="mdi mdi-message-alert"/>} primaryText="Support and Maintenance" secondaryText="Get help with your installation directly from the core team" />
                        </List>
                        <Divider/>
                        <CardActions style={{textAlign:'right'}}>
                            <FlatButton label="Learn More" primary={true}/>
                            <FlatButton label="Contact Professional Services" primary={true}/>
                        </CardActions>
                    </Card>
                </div>
            </div>
        )
    }

});

Dashboard = muiThemeable()(Dashboard);
export {Dashboard as default}