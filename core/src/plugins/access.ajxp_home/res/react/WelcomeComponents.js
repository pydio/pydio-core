(function(global){

    var HomeWorkspaceLegendPanel = React.createClass({
        setWorkspace: function(ws){
            this.refs.legend.setWorkspace(ws);
        },
        render:function() {
            return (
                <div id="home_center_panel">
                    <div id="logo_div"><ConfigLogo pydio={this.props.pydio} pluginName="gui.ajax" pluginParameter="CUSTOM_DASH_LOGO"/></div>
                    <HomeWorkspaceLegend
                        ref="legend"
                        onShowLegend={this.props.onShowLegend}
                        onHideLegend={this.props.onHideLegend}
                        onOpenLink={this.props.onOpenLink}/>
                </div>
            )
        }
    });

    var ConfigLogo = React.createClass({
        render: function(){
            var logo = this.props.pydio.Registry.getPluginConfigs(this.props.pluginName).get(this.props.pluginParameter);
            var url;
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
            return <img src={url}/>
        }
    });

    var DlAppElement = React.createClass({
        propTypes:{
            id:React.PropTypes.string,
            configs:React.PropTypes.object,
            configHref:React.PropTypes.string,
            containerClassName:React.PropTypes.string,
            iconClassName:React.PropTypes.string,
            messageId:React.PropTypes.string,
            tooltipId:React.PropTypes.string
        },

        render: function(){
            return (
                <ReactPydio.LabelWithTip className="dl_tooltip_container" tooltip={MessageHash[this.props.tooltipId]}>
                    <div id={this.props.id}>
                        <a href={this.props.configs.get(this.props.configHref)} target="_blank" className={this.props.containerClassName}/>
                        <a href={this.props.configs.get(this.props.configHref)} target="_blank"  className={this.props.iconClassName}/>
                        <div style={{color:'white'}}>{MessageHash[this.props.messageId]}</div>
                    </div>
                </ReactPydio.LabelWithTip>
            );
        }
    });

    var VideoCard = React.createClass({

        propTypes:{
            youtubeId:React.PropTypes.string,
            contentMessageId:React.PropTypes.string,
            launchVideo:React.PropTypes.func
        },

        launchVideo: function(){
            this.props.launchVideo("//www.youtube.com/embed/"+this.props.youtubeId+"?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5&autoplay=1");
        },

        render: function(){
            var htmlMessage = function(id){
                return {__html:MessageHash[id]};
            };
            return (
                <div className="video-card">
                    <div className="tutorial_legend">
                        <div className="tutorial_video_thumb" style={{backgroundImage:'url("https://img.youtube.com/vi/'+this.props.youtubeId+'/0.jpg")'}}></div>
                        <div className="tutorial_content"><span dangerouslySetInnerHTML={htmlMessage(this.props.contentMessageId)}/></div>
                        <div className="tutorial_load_button" onClick={this.launchVideo}><i className="icon-youtube-play"/> Play Video</div>
                    </div>
                </div>
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
                    <div className="overlay" style={{position:'absolute', top:0, left:0, right:0, bottom:0, backgroundColor:'black', opacity:0.4}}></div>
                    <iframe src={this.props.videoSrc} style={{position:'absolute', top:'10%', left:'10%', width:'80%', height:'80%', border:'0'}}></iframe>
                    <a className="mdi mdi-close" style={{position:'absolute', right:'8%', top:'7%', color:'white', textDecoration:'none'}} onClick={this.props.closePlayer}/>
                </div>
            );
        }
    });

    var TutorialPane = React.createClass({

        propTypes:{
            closePane:React.PropTypes.func,
            open:React.PropTypes.bool
        },

        closePane: function(){
            this.props.closePane();
        },

        closePlayer:function(){
            this.setState({player:null});
        },

        launchVideo: function(videoSrc){
            this.setState({player:videoSrc});
        },

        render: function(){
            var htmlMessage = function(id){
                return {__html:MessageHash[id]};
            };

            var videoPlayer;
            if(this.state && this.state.player){
                videoPlayer = <VideoPlayer videoSrc={this.state.player} closePlayer={this.closePlayer}/>
            }

            return (
                <span>
                {videoPlayer}
                <div id="videos_pane" className={this.props.open?"open":"closed"}>
                    <div onClick={this.closePane} className="mdi mdi-close"></div>
                    <div className="tutorial_title">{MessageHash['user_home.56']}</div>
                    <div className="videoCards">
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="C6L_9QT0lDE"
                            contentMessageId="user_home.62"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="ZuVKsIa4XdU"
                            contentMessageId="user_home.63"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="MEHCN64RoTY"
                            contentMessageId="user_home.64"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="ot2Nq-RAnYE"
                            contentMessageId="user_home.66"
                        />
                    </div>
                    <div className="tutorial_more_videos_cont">
                        <a  className="tutorial_more_videos_button" href="https://www.youtube.com/channel/UCNEMnabbk64csjA_qolXvPA" target="_blank">
                            <i className="icon-youtube-play"/>
                            <span dangerouslySetInnerHTML={htmlMessage('user_home.65')}/>
                        </a>
                    </div>
                </div>
                </span>
            );
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
            //this.props.controller.fireAction("open_tutorial_pane");
            this.setState({showGettingStarted:true});
        },

        getInitialState:function(){
            return {showGettingStarted:false};
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

            var gettingStartedBlock = '';
            var gettingStartedPanel;
            if(this.props.enableGettingStarted){
                var dgs = function(){
                    return {__html:MessageHash["user_home.55"]};
                };
                gettingStartedBlock = (
                    <small> <span onClick={this.showGettingStarted} dangerouslySetInnerHTML={dgs()}/></small>
                );
                var close = function(){
                    this.setState({showGettingStarted:false});
                }.bind(this);
                gettingStartedPanel = <TutorialPane closePane={close} open={this.state.showGettingStarted}/>;
            }


            return (
                <div id="welcome">
                    {gettingStartedPanel}
                    {MessageHash['user_home.40'].replace('%s', userLabel)}
                    <p>
                        {loginLink}
                        {gettingStartedBlock}
                    </p>
                </div>
            )
        }

    });

    var DlAppsPanel = React.createClass({

        render: function(){
            let configs = pydio.getPluginConfigs('access.ajxp_home');
            let mobileBlocks = [], syncBlocks = [];
            if(configs.get('URL_APP_IOSAPPSTORE')){
                mobileBlocks.push(
                    <DlAppElement
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
                    <DlAppElement
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
                    <DlAppElement
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
                    <DlAppElement
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
            let blocksSep;
            if(mobileBlocks.length && syncBlocks.length){
                blocksSep = <div className="dl_blocks_sep"></div>;
            }

            return (
                <div id="tutorial_dl_apps_pane">
                    <div id="dl_pydio_cont" className="react-mui-context">
                        {syncBlocks}{blocksSep}{mobileBlocks}
                    </div>
                </div>
            );
        }

    });

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
                bufferCallback('homeWorkspaceTimer', 7000, function(){
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
                                <div className='text-right'><small>{roundSize(data['meta.quota']['total'], MessageHash["byte_unit_symbol"])}</small></div>
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

    var HomeWorkspaceLegendInfoBlock = React.createClass({
        render:function(){
            return (
                <div className="repoInfoBadge">
                    <div className="repoInfoBox flexbox">
                        <div className="repoInfoBody row content">
                            {this.props.children}
                        </div>
                        <div className="repoInfoHeader row header">
                            <span className="repoInfoTitle">
                                {this.props.badgeTitle}
                            </span>
                            <span className={this.props.iconClass}/>
                        </div>
                    </div>
                </div>
            );
        }
    });

    var UserDashboard = React.createClass({

        getInitialState:function(){
            return {
                workspaces: this.props.pydio.user.getRepositoriesList()
            };
        },

        componentDidMount:function(){
            if(this._timer) global.clearTimeout(this._timer);
            this._timer = global.setTimeout(this.closeNavigation, 3000);

            this._reloadObserver = function(){

                if(this.isMounted()){
                    this.setState({
                        workspaces:this.props.pydio.user.getRepositoriesList()
                    });
                }
            }.bind(this);

            this.props.pydio.observe('repository_list_refreshed', this._reloadObserver);
        },

        componentWillUnmount:function(){
            if(this._reloadObserver){
                this.props.pydio.stopObserving('repository_list_refreshed', this._reloadObserver);
            }
        },
        switchToWorkspace:function(repoId, save){
            if(!repoId) return;
            if(save){
                PydioApi.getClient().request({
                    'PREFERENCES_DEFAULT_START_REPOSITORY':repoId,
                    'get_action':'custom_data_edit'
                }, function(){
                    this.props.pydio.user.setPreference('DEFAULT_START_REPOSITORY', repoId, false);
                }.bind(this));
            }
            this.props.pydio.triggerRepositoryChange(repoId);
        },
        onShowLegend: function(){
            // PROTO STUFF!
            $('home_center_panel').addClassName('legend_visible');
        },
        onHideLegend: function(){
            // PROTO STUFF!
            $('home_center_panel').removeClassName('legend_visible');
        },
        onHoverLink:function(event, ws){
            bufferCallback('hoverWorkspaceTimer', 400, function(){
                this.refs.legend.setWorkspace(ws);
            }.bind(this));
        },
        onOutLink:function(event, ws){
            this.refs.legend.setWorkspace(null);
        },
        onOpenLink:function(event, ws, save){
            this.switchToWorkspace(ws.getId(), save);
        },
        render:function(){
            var simpleClickOpen = this.props.pydio.getPluginConfigs("access.ajxp_home").get("SIMPLE_CLICK_WS_OPEN");
            var enableGettingStarted = this.props.pydio.getPluginConfigs('access.ajxp_home').get("ENABLE_GETTING_STARTED");
            return (
                <div className="horizontal_layout vertical_fit">
                    <div id="home_left_bar" className="vertical_layout">
                        <HomeWorkspaceUserCartridge style={{minHeight:'94px'}}
                            controller={this.props.pydio.getController()}
                            user={this.props.pydio.user}
                            enableGettingStarted={enableGettingStarted}
                        />
                        <div id="workspaces_center" className="vertical_layout vertical_fit">
                            <LeftNavigation.UserWorkspacesList
                                pydio={this.props.pydio}
                                workspaces={this.state.workspaces}
                                onHoverLink={this.onHoverLink}
                                onOutLink={this.onOutLink}
                            />

                        </div>
                    </div>
                    <HomeWorkspaceLegendPanel ref="legend"
                        pydio={this.props.pydio}
                        onShowLegend={this.onShowLegend}
                        onHideLegend={this.onHideLegend}
                        onOpenLink={this.onOpenLink}
                    />
                    {this.props.children}
                </div>
                )
            }

    });

    var QRCodeDialogLoader = React.createClass({

        closeClicked: function(){
            this.props.closeAjxpDialog();
        },

        render: function(){

            let jsonData = {
                "server"    : global.location.href.split('welcome').shift(),
                "user"      : global.pydio.user ? global.pydio.user.id : null
            }

            return (
                <div>
                    <div className="home-qrCode-desc">
                        <h4>{global.pydio.MessageHash['user_home.72']}</h4>
                        <p>{global.pydio.MessageHash['user_home.74']}</p>
                    </div>
                    <div className="home-qrCode">
                        <ReactQRCode value={JSON.stringify(jsonData)} size={256}/>
                        <div className="button-panel">
                            <ReactMUI.FlatButton label="Close" onClick={this.closeClicked}/>
                        </div>
                    </div>
                </div>
            );

        }

    });

    var WelcomeComponents = global.WelcomeComponents || {};
    WelcomeComponents.UserDashboard = UserDashboard;
    WelcomeComponents.TutorialPane = TutorialPane;
    WelcomeComponents.QRCodeDialogLoader = QRCodeDialogLoader;
    global.WelcomeComponents = WelcomeComponents;

})(window);
