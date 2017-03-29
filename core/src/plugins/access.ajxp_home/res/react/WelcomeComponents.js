(function(global){

    /***********************************************
     * LEGACY COMPONENTS, COULD PROBABlY BE REMOVED
     ***********************************************/
    var HomeWorkspaceLegend = React.createClass({

        getInitialState: function() {
            return {workspace: null};
        },
        enterWorkspace:function(event){
            this.props.onOpenLink(event, this.state.workspace, this.refs.save_ws_choice.getDOMNode().checked);
        },
        componentWillUnmount: function(){
            if(window['homeWorkspaceTimer']){
                window.clearTimeout(window['homeWorkspaceTimer']);
            }
        },
        setWorkspace:function(ws){
            if(!this._internalCache){
                this._internalCache = new Map();
                this._repoInfosLoading = new Map();
            }
            this._internalState = ws;
            if(!ws){
                FuncUtils.bufferCallback('homeWorkspaceTimer', 7000, function(){
                    this.setState({workspace:null});
                    this.props.onHideLegend();
                }.bind(this));
                return;
            }
            // check the cache and re-render?
            var repoId = ws.getId();
            if(!this._repoInfosLoading.get(repoId) && !this._internalCache.get(repoId)){
                this.props.onShowLegend(ws);
                this._repoInfosLoading.set(repoId, 'loading');
                PydioApi.getClient().request({
                    get_action:'load_repository_info',
                    tmp_repository_id:repoId,
                    collect:'true'
                }, function(transport){
                    this._repoInfosLoading.delete(repoId);
                    if(transport.responseJSON){
                        var data = transport.responseJSON;
                        this._internalCache.set(repoId, data);
                        if(this._internalState == ws){
                            this.setState({workspace:ws, data:data});
                        }
                    }
                }.bind(this));
            }else if(this._internalCache.get(repoId)){
                this.props.onShowLegend(ws);
                this.setState({workspace:ws, data:this._internalCache.get(repoId)});
            }
        },
        render: function(){
            if(!this.state.workspace){
                //return <div id="ws_legend" className="empty_ws_legend"></div>;
                return <DlAppsPanel/>
            }
            var blocks = [];
            var data = this.state.data;
            var usersData = data['core.users'];

            if(usersData && usersData['users'] != undefined && usersData['groups'] != undefined){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="core.users" badgeTitle={MessageHash[527]} iconClass="mdi mdi-account-network">
                        <div className="table">
                            {(() => {
                                if (usersData['users'] > 0) {
                                    return <div>
                                        <div>{MessageHash[531]}</div>
                                        <div className="text-center">{usersData['users']}</div>
                                    </div>;
                                }
                            })()}
                            {(() => {
                                if (usersData['groups'] > 0) {
                                    return <div>
                                        <div>{MessageHash[532]}</div>
                                        <div className="text-center">{usersData['groups']}</div>
                                    </div>;
                                }
                            })()}
                        </div>
                    </HomeWorkspaceLegendInfoBlock>
                );
            }
            if(data['access.inbox']){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="core.users" badgeTitle={MessageHash['inbox_driver.2p']} iconClass="mdi mdi-file-multiple">
                        <div className="table">
                            <div>
                                <div>{MessageHash['inbox_driver.16']}</div>
                                <div className="text-center">{data['access.inbox']['files']}</div>
                            </div>
                            {(() => {
                                if (this.state.workspace.getAccessStatus() > 0) {
                                    return <div>
                                        <div>{MessageHash['inbox_driver.17']}</div>
                                        <div className="text-center">{this.state.workspace.getAccessStatus()}</div>
                                    </div>;
                                }
                            })()}
                        </div>
                    </HomeWorkspaceLegendInfoBlock>
                );
            }
            if(data['meta.quota']){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="meta.quota" badgeTitle={MessageHash['meta.quota.4']} iconClass="icon-dashboard">
                        <div className="table">
                            <div>
                                <div>{parseInt(100*data['meta.quota']['usage']/data['meta.quota']['total'])}%</div>
                                <div className='text-right'><small>{PathUtils.roundFileSize(data['meta.quota']['total'], MessageHash["byte_unit_symbol"])}</small></div>
                            </div>
                        </div>
                    </HomeWorkspaceLegendInfoBlock>
                );
            }
            if(data['core.notifications'] && data['core.notifications'][0]){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="notifications" badgeTitle={MessageHash[4]} iconClass="mdi mdi-calendar">
                        <div className="text-center">{data['core.notifications'][0]['short_date']}</div>
                    </HomeWorkspaceLegendInfoBlock>
                );
            }

            if(blocks.length == 1){
                blocks.push(<div className="repoInfoBadge" style={{visibility:'hidden'}}></div>);
            }

            return (
                <div id="ws_legend">
                    <div className={"repoInfoBadge main size-"+(blocks.length)}>
                        <div className="repoInfoBox flexbox">
                            <div className="repoInfoBody content">
                                <h4>{this.state.workspace.getLabel()}</h4>
                                {this.state.workspace.getDescription()}
                            </div>
                            <div className="repoInfoHeader row header">
                            <span className="repoInfoTitle">
                                <span className="enter_save_choice" style={{lineHeight: '0.5em'}}>
                                    <input type="checkbox" ref="save_ws_choice" id="save_ws_choice"/>
                                    <label htmlFor="save_ws_choice">{MessageHash['user_home.41']}</label>
                                </span>
                                <a onClick={this.enterWorkspace}>{MessageHash['user_home.42']}</a>
                            </span>
                            </div>
                        </div>
                    </div>
                    <div className="repoInfo">
                        {blocks}
                    </div>
                </div>
            )
        }
    });

    var HomeWorkspaceUserCartridge = React.createClass({

        clickDisconnect: function(){
            this.props.controller.fireAction("logout");
        },

        clickConnect: function(){
            this.props.controller.fireAction("login");
        },

        showGettingStarted: function(){
            if(!this.isMounted()) return;
            this.setState({showGettingStarted:true});
        },

        closeGettingStarted: function(){
            this.setState({showGettingStarted:false});
            if(this.state.initiallyOpened){
                let guiPrefs = this.props.user.getPreference("gui_preferences", true);
                guiPrefs['WelcomeComponent.HomePanel.TutorialShown'] = true;
                this.props.user.setPreference('gui_preferences', guiPrefs, true);
                this.props.user.savePreference('gui_preferences');
            }
        },

        getInitialState:function(){
            let guiPrefs = this.props.user.getPreference('gui_preferences', true);
            return {showGettingStarted:false, initiallyOpened:!guiPrefs['WelcomeComponent.HomePanel.TutorialShown']};
        },

        componentDidMount: function(){
            if(this.state.initiallyOpened){
                window.setTimeout(this.showGettingStarted, 1000);
            }
        },

        render: function(){
            var userLabel = this.props.user.getPreference("USER_DISPLAY_NAME") || this.props.user.id;
            var loginLink = '';
            if(this.props.controller.getActionByName("logout") && this.props.user.id != "guest"){
                var parts = MessageHash["user_home.67"].replace('%s', userLabel).split("%logout");
                loginLink = (
                    <small>{parts[0]}
                        <span id='disconnect_link' onClick={this.clickDisconnect}>
                        <a>{this.props.controller.getActionByName("logout").options.text.toLowerCase()}</a>
                    </span>{parts[1]}
                    </small>
                )
            }else if(this.props.user.id == "guest" && this.props.controller.getActionByName("login")){
                loginLink = (
                    <small>
                        You can <a id='disconnect_link' onClick={this.clickConnect}>login</a> if you are not guest.
                    </small>
                )
            }

            let gettingStartedBlock = null;
            let adminAccessBlock = null;
            var gettingStartedPanel;
            if(this.props.enableGettingStarted){
                var dgs = function(){
                    return {__html:MessageHash["user_home.55"]};
                };
                gettingStartedBlock = (
                    <small> <span onClick={this.showGettingStarted} dangerouslySetInnerHTML={dgs()}/></small>
                );
                gettingStartedPanel = <TutorialPane closePane={this.closeGettingStarted} open={this.state.showGettingStarted}/>;
            }
            let a = this.props.controller.getActionByName('switch_to_settings');
            if(a && !a.deny){
                let func = function(){
                    this.props.controller.fireAction('switch_to_settings');
                }.bind(this);
                let sentenceParts = MessageHash['user_home.76'].split("%1");
                let dashName = MessageHash['user_home.77'];
                adminAccessBlock = <small> {sentenceParts[0]} <a onClick={func}>{dashName}</a> {sentenceParts.length > 1 ? sentenceParts[1] : null}</small>;
            }

            return (
                <div id="welcome">
                    {gettingStartedPanel}
                    {MessageHash['user_home.40'].replace('%s', userLabel)}
                    <p>
                        {loginLink}
                        {gettingStartedBlock}
                        {adminAccessBlock}
                    </p>
                </div>
            )
        }

    });

    /***********************************************
     * DOWNLOAD NATIVE APPS
     ***********************************************/

    class DownloadApp extends React.Component{

        render(){

            const styles = {
                smallIcon: {
                    fontSize: 40,
                    width: 40,
                    height: 40,
                },
                small: {
                    width: 80,
                    height: 80,
                    padding: 20,
                }
            };

            const {pydio, iconClassName, tooltipId, configs, configHref} = this.props;

            return (
                <MaterialUI.IconButton
                    iconClassName={iconClassName}
                    tooltip={pydio.MessageHash[tooltipId]}
                    tooltipStyles={{marginTop: 40}}
                    style={styles.small}
                    iconStyle={{...styles.smallIcon, color: this.props.iconColor}}
                    onTouchTap={() => { window.open(configs.get(configHref)) }}
                />);

        }

    }

    DownloadApp.propTypes = {
        pydio: React.PropTypes.instanceOf(Pydio),
        id:React.PropTypes.string,
        configs:React.PropTypes.object,
        configHref:React.PropTypes.string,
        iconClassName:React.PropTypes.string,
        iconColor:React.PropTypes.string,
        messageId:React.PropTypes.string,
        tooltipId:React.PropTypes.string
    };

    const DlAppsPanel = React.createClass({

        render: function(){
            let configs = this.props.pydio.getPluginConfigs('access.ajxp_home');
            let mobileBlocks = [], syncBlocks = [];
            if(configs.get('URL_APP_IOSAPPSTORE')){
                mobileBlocks.push(
                    <DownloadApp
                        {...this.props}
                        id="dl_pydio_ios"
                        key="dl_pydio_ios"
                        configs={configs}
                        configHref="URL_APP_IOSAPPSTORE"
                        containerClassName="icon-tablet"
                        iconClassName="icon-apple"
                        messageId="user_home.59"
                        tooltipId="user_home.70"
                    />

                );
            }
            if(configs.get('URL_APP_ANDROID')){
                mobileBlocks.push(
                    <DownloadApp
                        {...this.props}
                        id="dl_pydio_android"
                        key="dl_pydio_android"
                        configs={configs}
                        configHref="URL_APP_ANDROID"
                        containerClassName="icon-mobile-phone"
                        iconClassName="icon-android"
                        messageId="user_home.58"
                        tooltipId="user_home.71"
                    />
                );
            }
            if(configs.get('URL_APP_SYNC_WIN')){
                syncBlocks.push(
                    <DownloadApp
                        {...this.props}
                        id="dl_pydio_win"
                        key="dl_pydio_win"
                        configs={configs}
                        configHref="URL_APP_SYNC_WIN"
                        containerClassName="icon-laptop"
                        iconClassName="icon-windows"
                        messageId="user_home.61"
                        tooltipId="user_home.68"
                    />
                );
            }
            if(configs.get('URL_APP_SYNC_MAC')){
                syncBlocks.push(
                    <DownloadApp
                        {...this.props}
                        id="dl_pydio_mac"
                        key="dl_pydio_mac"
                        configs={configs}
                        configHref="URL_APP_SYNC_MAC"
                        containerClassName="icon-desktop"
                        iconClassName="icon-apple"
                        messageId="user_home.60"
                        tooltipId="user_home.69"
                    />
                );
            }

            return (
                <div style={{textAlign: 'center', paddingTop: 5}}>{this.props.type === 'sync' ? syncBlocks : mobileBlocks}</div>
            );
        }

    });


    /***********************************************
     * VIDEO TUTORIALS
     ***********************************************/
    const VideoCard = React.createClass({

        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:3,
            gridHeight:16,
            builderDisplayName:'Video Tutorial',
            builderFields:[]
        },

        propTypes:{
            youtubeId           : React.PropTypes.string,
            contentMessageId    : React.PropTypes.string,
            launchVideo         : React.PropTypes.func
        },

        getInitialState: function(){
            this._videos = [
                ['qvsSeLXr-T4', 'user_home.63'],
                ['HViCWPpyZ6k', 'user_home.79'],
                ['jBRNqwannJM', 'user_home.80'],
                ['2jl1EsML5v8', 'user_home.81'],
                ['28-t4dvhE6c', 'user_home.82'],
                ['fP0MVejnVZE', 'user_home.83'],
                ['TXFz4w4trlQ', 'user_home.84'],
                ['OjHtgnL_L7Y', 'user_home.85'],
                ['ot2Nq-RAnYE', 'user_home.66']
            ];
            const k = Math.floor(Math.random() * this._videos.length);
            const value = this._videos[k];
            return {
                videoIndex      : k,
                youtubeId       : value[0],
                contentMessageId: value[1]
            };
        },

        launchVideo: function(){
            const url = "//www.youtube.com/embed/"+this.state.youtubeId+"?list=PLxzQJCqzktEbYm3U_O1EqFru0LsEFBca5&autoplay=1";
            this._videoDiv = document.createElement('div');
            document.body.appendChild(this._videoDiv);
            ReactDOM.render(<VideoPlayer videoSrc={url} closePlayer={this.closePlayer}/>, this._videoDiv);
        },

        closePlayer: function(){
            ReactDOM.unmountComponentAtNode(this._videoDiv);
            document.body.removeChild(this._videoDiv);
        },

        getTitle: function(messId){
            const text = this.props.pydio.MessageHash[messId];
            return text.split('\n').shift().replace('<h2>', '').replace('</h2>', '');
        },

        browse: function(direction = 'next', event){
            let nextIndex;
            const {videoIndex} = this.state;
            if(direction === 'next'){
                nextIndex = videoIndex < this._videos.length -1  ? videoIndex + 1 : 0;
            }else{
                nextIndex = videoIndex > 0  ? videoIndex - 1 : this._videos.length - 1;
            }
            const value = this._videos[nextIndex];
            this.setState({
                videoIndex      : nextIndex,
                youtubeId       : value[0],
                contentMessageId: value[1]
            });
        },

        render: function(){
            const MessageHash = this.props.pydio.MessageHash;
            var htmlMessage = function(id){
                return {__html:MessageHash[id]};
            };
            const menus = this._videos.map(function(item, index){
                return <MaterialUI.MenuItem primaryText={this.getTitle(item[1])} onTouchTap={() => {this.setState({youtubeId:item[0], contentMessageId:item[1], videoIndex: index})} }/>;
            }.bind(this));
            let props = {...this.props};
            const {youtubeId, contentMessageId} = this.state;
            props.className += ' video-card';
            props['zDepth'] = 1;
            const TMP_VIEW_MORE = (
                <a className="tutorial_more_videos_button" href="https://www.youtube.com/channel/UCNEMnabbk64csjA_qolXvPA" target="_blank" dangerouslySetInnerHTML={htmlMessage('user_home.65')}/>
            );
            return (
                <MaterialUI.Paper {...props} transitionEnabled={false}>
                    {this.getCloseButton()}
                    <div className="tutorial_legend">
                        <div className="tutorial_video_thumb" style={{backgroundImage:'url("https://img.youtube.com/vi/'+youtubeId+'/0.jpg")'}}>
                            <div className="tutorial_prev mdi mdi-arrow-left" onClick={this.browse.bind(this, 'previous')}/>
                            <div className="tutorial_play mdi mdi-play" onClick={this.launchVideo}/>
                            <div className="tutorial_next mdi mdi-arrow-right" onClick={this.browse.bind(this, 'next')}/>
                            <div className="tutorial_title">
                                <span dangerouslySetInnerHTML={htmlMessage(contentMessageId)}/>
                                <MaterialUI.IconMenu
                                    style={{position: 'absolute', bottom: 0, right: 0, backgroundColor: 'rgba(0,0,0,0.43)', padding: 6, borderRadius: '0 0 2px 0'}}
                                    iconStyle={{color:'white'}}
                                    iconButtonElement={<MaterialUI.IconButton iconClassName="mdi mdi-dots-vertical"/>}
                                    anchorOrigin={{horizontal: 'left', vertical: 'top'}}
                                    targetOrigin={{horizontal: 'left', vertical: 'top'}}
                                >{menus}</MaterialUI.IconMenu>
                            </div>
                        </div>
                    </div>
                </MaterialUI.Paper>
            );
        }
    });

    var VideoPlayer = React.createClass({

        propTypes:{
            videoSrc:React.PropTypes.string,
            closePlayer:React.PropTypes.func
        },

        render: function(){
            return (
                <div className="video-player" style={{position:'absolute', top:0, left:0, right:0, bottom:0, zIndex:200000}}>
                    <div className="overlay" style={{position:'absolute', top:0, left:0, right:0, bottom:0, backgroundColor:'black', opacity:0.4}} onClick={this.props.closePlayer}></div>
                    <div style={{position:'absolute', top:'10%', left:'10%', width:'80%', height:'80%', minWidth:420, minHeight: 600, boxShadow:'rgba(0, 0, 0, 0.156863) 0px 3px 10px, rgba(0, 0, 0, 0.227451) 0px 3px 10px'}}>
                        <iframe src={this.props.videoSrc} style={{width:'100%', height:'100%', border:0}}/>
                    </div>
                    <a className="mdi mdi-close" style={{position:'absolute', right:'8%', top:'7%', color:'white', textDecoration:'none', fontSize:24}} onClick={this.props.closePlayer}/>
                </div>
            );
        }
    });

    /***********************************************
     * OTHERS
     ***********************************************/
    const ConfigLogo = React.createClass({
        render: function(){
            let logo = this.props.pydio.Registry.getPluginConfigs(this.props.pluginName).get(this.props.pluginParameter);
            let url;
            if(!logo){
                logo = this.props.pydio.Registry.getDefaultImageFromParameters(this.props.pluginName, this.props.pluginParameter);
            }
            if(logo){
                if(logo.indexOf('plugins/') === 0){
                    url = logo;
                }else{
                    url = this.props.pydio.Parameters.get('ajxpServerAccess') + "&get_action=get_global_binary_param&binary_id=" + logo;
                }
            }
            return <img src={url} style={this.props.style}/>
        }
    });

    const QRCodeCard = React.createClass({

        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:2,
            gridHeight:20,
            builderDisplayName:'Qr Code',
            builderFields:[]
        },


        render: function(){

            let jsonData = {
                "server"    : window.location.href.split('welcome').shift(),
                "user"      : this.props.pydio.user ? this.props.pydio.user.id : null
            }

            const style = {
                ...this.props.style,
                backgroundColor: MaterialUI.Style.colors.blue600,
                color: 'white'
            };

            return (
                <MaterialUI.Paper zDepth={1} {...this.props} transitionEnabled={false} style={style}>
                    <div style={{padding: 16, fontSize: 16}}>{this.props.pydio.MessageHash['user_home.74']}</div>
                    <div className="home-qrCode" style={{display:'flex', justifyContent:'center'}}>
                        <ReactQRCode bgColor={style.backgroundColor} fgColor={style.color} value={JSON.stringify(jsonData)} size={150}/>
                    </div>
                </MaterialUI.Paper>
            );

        }

    });


    /***********************************************
     * DYNAMIC GRID DASHBOARD
     ***********************************************/

    /*
    Simple component for customizing colors
     */
    class ThemeableTitle extends React.Component{

        render(){
            const {pydio, filterByType, muiTheme} = this.props;
            const messages = pydio.MessageHash;
            const bgColor = filterByType === 'entries' ? muiTheme.palette.primary1Color : MaterialUI.Style.colors.teal500;
            const title = messages[filterByType==='entries'?468:469];
            const cardTitleStyle = {backgroundColor:bgColor, color: 'white', padding: 16, fontSize: 24, lineHeight:'36px'};

            return <MaterialUI.Paper zDepth={0} rounded={false} style={cardTitleStyle}>{title}</MaterialUI.Paper>;
        }

    }

    ThemeableTitle = MaterialUI.Style.muiThemeable()(ThemeableTitle);

    const WorkspacesListCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:3,
            gridHeight:40,
            builderDisplayName:'My Workspaces',
            builderFields:[]
        },

        render: function(){
            const {pydio, filterByType} = this.props;
            let props = {...this.props};
            if(props.style){
                props.style = {...props.style, overflowY:'auto'};
            }
            return (
                <MaterialUI.Paper zDepth={1} {...props} transitionEnabled={false}>
                    {this.getCloseButton()}
                    <div  style={{height: '100%', display:'flex', flexDirection:'column'}}>
                        <ThemeableTitle {...this.props}/>
                        <PydioWorkspaces.WorkspacesListMaterial
                            className={"vertical_fit filter-" + filterByType}
                            pydio={this.props.pydio}
                            workspaces={this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []}
                            showTreeForWorkspace={false}
                            filterByType={this.props.filterByType}
                            sectionTitleStyle={{display:'none'}}
                            style={{flex:1, overflowY: 'auto'}}
                        />
                    </div>
                </MaterialUI.Paper>
            );
        }
    });

    const DlAppsCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:2,
            gridHeight:10,
            builderDisplayName:'Download Applications',
            builderFields:[]
        },

        render: function(){
            let props = {...this.props};
            const style = {
                ...props.style,
                overflow:'visible',
                backgroundColor: MaterialUI.Style.colors.cyan500,
                color: 'white'
            };
            return (
                <MaterialUI.Paper zDepth={1} {...props}  transitionEnabled={false} style={style}>
                    {this.getCloseButton()}
                    <DlAppsPanel pydio={this.props.pydio} type="sync" iconColor={style.color}/>
                    <div style={{fontSize: 16, padding: 16, paddingTop: 0, textAlign:'center'}}>Keep your files offline with Pydio Desktop Client</div>
                </MaterialUI.Paper>
            );
        }
    });

    const RecentAccessCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:5,
            gridHeight:16,
            builderDisplayName:'Recently Accessed',
            builderFields:[]
        },

        renderIcon: function(node){
            console.log(node);
            if(node.isLeaf()){
                return <PydioWorkspaces.FilePreview node={node} loadThumbnail={true} style={{borderRadius: '50%'}}/>
            }else{
                if(node.getPath() === '/' || !node.getPath()){
                    return <MaterialUI.FontIcon className="mdi mdi-folder-plus"/>
                }else{
                    return <MaterialUI.FontIcon className="mdi mdi-folder"/>
                }
            }
        },

        renderEntry: function(entry){
            const {node} = entry;
            let primaryText, secondaryText;
            const path = node.getPath();
            const meta = node.getMetadata();
            if(!path || path === '/'){
                primaryText = meta.get('repository_label');
                secondaryText = 'Workspace opened on ' + meta.get('recent_access_time');
            }else{
                primaryText = node.getLabel();
                secondaryText = node.getPath();
            }

            return (
                <MaterialUI.ListItem
                    leftIcon={this.renderIcon(node)}
                    primaryText={primaryText}
                    secondaryText={secondaryText}
                    onTouchTap={() => {this.props.pydio.goTo(node);}}
                />
            );
        },

        renderLabel: function(node, data){
            const path = node.getPath();
            const meta = node.getMetadata();
            if(!path || path === '/'){
                return <span style={{fontSize: 14}}>{meta.get('repository_label')} <span style={{opacity: 0.33}}> (Workspace)</span></span>;
            }else{
                const dir = PathUtils.getDirname(node.getPath());
                let dirSegment;
                if(dir){
                    dirSegment = <span style={{opacity: 0.33}}> ({node.getPath()})</span>
                }
                if(node.isLeaf()){
                    return <span><span style={{fontSize: 14}}>{node.getLabel()}</span>{dirSegment}</span>;
                }else{
                    return <span><span style={{fontSize: 14}}>{'/' + node.getLabel()}</span>{dirSegment}</span>;
                }
            }
        },

        renderAction: function(node, data){
            return <span style={{position:'relative'}}><MaterialUI.IconButton
                iconClassName="mdi mdi-chevron-right"
                tooltip="Open ... "
                onTouchTap={() => {this.props.pydio.goTo(node)}}
                style={{position:'absolute', right:0}}
            /></span>
        },

        render: function(){
            const title = <MaterialUI.CardTitle title="Recently Accessed"/>;

            return (
                <MaterialUI.Paper zDepth={1} {...this.props} className="vertical-layout" transitionEnabled={false}>
                    <PydioComponents.NodeListCustomProvider
                        className="recently-accessed-list"
                        nodeProviderProperties={{get_action:"load_user_recent_items"}}
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                        nodeClicked={(node) => {this.props.pydio.goTo(node);}}
                        hideToolbar={true}
                        tableKeys={{
                            label:{renderCell:this.renderLabel, label:'Recently Accessed Files', width:'60%'},
                            recent_access_readable:{label:'Accessed', width:'20%'},
                            repository_label:{label:'Workspace', width:'20%'},
                        }}
                        entryRenderActions={this.renderAction}
                    />
                </MaterialUI.Paper>
            );
        }

    });

    const SearchFormCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:6,
            gridHeight:10,
            builderDisplayName:'Search Form',
            builderFields:[]
        },

        render: function(){
            const title = <MaterialUI.CardTitle title="Search your files"/>;

            return (
                <MaterialUI.Paper zDepth={1} {...this.props} className="vertical-layout" transitionEnabled={false}>
                    {title}
                    <PydioReactUI.AsyncComponent
                        namespace="PydioWorkspaces"
                        componentName="SearchForm"
                        pydio={this.props.pydio}
                        crossWorkspace={true}
                        groupByField="repository_id"
                    />
                </MaterialUI.Paper>
            );
        }

    });


    const QuickSendCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:2,
            gridHeight:10,
            builderDisplayName:'Quick Upload',
            builderFields:[]
        },

        render: function(){
            const title = <MaterialUI.CardTitle title="Quick Upload"/>;

            const style = {
                ...this.props.style,
                backgroundColor: MaterialUI.Style.colors.lightBlue500,
                color: 'white'
            };

            return (
                <MaterialUI.Paper zDepth={1} {...this.props} className="vertical-layout" transitionEnabled={false} style={style}>
                    <div style={{display:'flex'}}>
                        <div style={{padding: 16, fontSize: 16}}>Drop a file here from your desktop</div>
                        <div style={{textAlign:'center', padding:18}}><span style={{borderRadius:'50%', border: '4px solid white', fontSize:56, padding: 20}} className="mdi mdi-cloud-upload"></span></div>
                    </div>
                </MaterialUI.Paper>
            );
        }

    });



    let UserDashboard = React.createClass({

        closePlayer:function(){
            this.setState({player:null});
        },

        launchVideo: function(videoSrc){
            this.setState({player:videoSrc});
        },

        getDefaultCards: function(){

            let baseCards = [
                {
                    id:'my_workspaces',
                    componentClass:'WelcomeComponents.WorkspacesListCard',
                    props:{
                        filterByType:"entries",
                    },
                    defaultPosition:{
                        x:0, y:0
                    },
                },
                {
                    id:'shared_with_me',
                    componentClass:'WelcomeComponents.WorkspacesListCard',
                    props:{
                        filterByType:"shared",
                    },
                    defaultPosition:{
                        x:3, y:0
                    }
                },
                {
                    id:'videos',
                    componentClass:'WelcomeComponents.VideoCard',
                    props:{
                        launchVideo : this.launchVideo.bind(this)
                    },
                    defaultPosition:{
                        x:5, y:40
                    },
                    defaultLayouts: {
                        sm: {x: 0, y: 30}
                    }
                },
                {
                    id:'downloads',
                    componentClass:'WelcomeComponents.DlAppsCard',
                    defaultPosition:{
                        x:6, y:20
                    },
                    defaultLayouts: {
                        md: {x: 6, y: 36},
                        sm: {x: 0, y: 60}
                    }
                },
                {
                    id:'recently_accessed',
                    componentClass:'WelcomeComponents.RecentAccessCard',
                    defaultPosition:{
                        x: 0, y: 40
                    }
                },
                {
                    id:'qr_code',
                    componentClass:'WelcomeComponents.QRCodeCard',
                    defaultPosition:{
                        x: 6, y: 0
                    }
                },
                {
                    id:'quick_upload',
                    componentClass:'WelcomeComponents.QuickSendCard',
                    defaultPosition:{
                        x: 6, y: 30
                    }
                }

            ];

            return baseCards;
        },

        getInitialState:function(){
            return {player: null};
        },

        render:function() {

            let videoPlayer;
            if(this.state && this.state.player){
                videoPlayer = <VideoPlayer videoSrc={this.state.player} closePlayer={this.closePlayer}/>
            }
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
                    {videoPlayer}
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
                    <PydioComponents.DynamicGrid
                        storeNamespace="WelcomePanel.Dashboard"
                        defaultCards={this.getDefaultCards()}
                        builderNamespaces={["WelcomeComponents"]}
                        pydio={this.props.pydio}
                        cols={{lg: 12, md: 9, sm: 6, xs: 6, xxs: 2}}
                        rglStyle={{position:'absolute', top: 110, bottom: 0, left: 0, right: 0}}
                    />
                </div>
            );
        }

    });

    UserDashboard = MaterialUI.Style.muiThemeable()(UserDashboard);
    if(global.ReactDND){
        UserDashboard = global.ReactDND.DragDropContext(ReactDND.HTML5Backend)(UserDashboard);
    }else{
        UserDashboard = UserDashboard;
    }

    global.WelcomeComponents = {
        VideoCard,
        DlAppsCard,
        RecentAccessCard,
        QRCodeCard,
        WorkspacesListCard,
        UserDashboard,
        SearchFormCard,
        QuickSendCard
    };

})(window);
