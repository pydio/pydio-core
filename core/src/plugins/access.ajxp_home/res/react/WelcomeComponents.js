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

    var UserDashboardOrig = React.createClass({

        getInitialState:function(){
            return {
                workspaces: this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []
            };
        },

        componentDidMount:function(){
            if(this._timer) global.clearTimeout(this._timer);
            this._timer = global.setTimeout(this.closeNavigation, 3000);

            this._reloadObserver = function(){

                if(this.isMounted()){
                    this.setState({
                        workspaces:this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []
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
            //$('home_center_panel').addClassName('legend_visible');
        },
        onHideLegend: function(){
            // PROTO STUFF!
            //$('home_center_panel').removeClassName('legend_visible');
        },
        onHoverLink:function(event, ws){
            FuncUtils.bufferCallback('hoverWorkspaceTimer', 400, function(){
                if(this.refs && this.refs.legend){
                    this.refs.legend.setWorkspace(ws);
                }
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
            let UserCartridge;
            if(this.props.pydio.user){
                UserCartridge = (
                    <HomeWorkspaceUserCartridge style={{minHeight:'94px'}}
                                                controller={this.props.pydio.getController()}
                                                user={this.props.pydio.user}
                                                enableGettingStarted={enableGettingStarted}
                    />
                );
            }
            return (
                <div className="horizontal_layout vertical_fit" id={this.props.rootId} style={this.props.style}>
                    <div id="home_left_bar" className="vertical_layout">
                        {UserCartridge}
                        <div id="workspaces_center" className="vertical_layout vertical_fit">
                            <PydioWorkspaces.WorkspacesList
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
                <PydioComponents.LabelWithTip className="dl_tooltip_container" tooltip={MessageHash[this.props.tooltipId]}>
                    <div id={this.props.id}>
                        <a href={this.props.configs.get(this.props.configHref)} target="_blank" className={this.props.containerClassName}/>
                        <a href={this.props.configs.get(this.props.configHref)} target="_blank"  className={this.props.iconClassName}/>
                        <div>{MessageHash[this.props.messageId]}</div>
                    </div>
                </PydioComponents.LabelWithTip>
            );
        }
    });

    var DlAppsPanel = React.createClass({

        render: function(){
            let configs = this.props.pydio.getPluginConfigs('access.ajxp_home');
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
            let searchBlock;
            if(pydio.getPluginConfigs('access.ajxp_home').get("ENABLE_GLOBAL_SEARCH")){
                //searchBlock = <HomeSearchEngine className="react-mui-context"/>;
            }
            return (
                <div id="tutorial_dl_apps_pane">
                    <div id="dl_pydio_cont" className="react-mui-context">
                        {syncBlocks}{blocksSep}{mobileBlocks}
                    </div>
                    {searchBlock}
                </div>
            );
        }

    });


    /***********************************************
     * VIDEO TUTORIALS
     ***********************************************/
    var VideoCard = React.createClass({

        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:3,
            gridHeight:36,
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
                            <div className="tutorial_next mdi mdi-arrow-right" onClick={this.browse.bind(this, 'next')}/>
                            <div className="tutorial_title"><span dangerouslySetInnerHTML={htmlMessage(contentMessageId)}/></div>
                        </div>
                        <div className="tutorial_content"><span dangerouslySetInnerHTML={htmlMessage(contentMessageId)}/></div>
                        <MaterialUI.Divider style={{minHeight: 1}}/>
                        <div style={{textAlign:'right', padding: '0 6px'}}>
                            <MaterialUI.IconMenu style={{float:'left'}}
                                iconButtonElement={<MaterialUI.IconButton iconClassName="mdi mdi-dots-vertical"/>}
                                anchorOrigin={{horizontal: 'left', vertical: 'bottom'}}
                                targetOrigin={{horizontal: 'left', vertical: 'bottom'}}
                            >{menus}</MaterialUI.IconMenu>
                            <MaterialUI.FlatButton
                                onTouchTap={this.launchVideo}
                                label={MessageHash['user_home.86']}
                                primary={true}
                                style={{marginTop:5}}
                                icon={<MaterialUI.FontIcon className="icon-youtube-play" />}
                            />
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

    QRCodeDialogLoader.open = function(){
        var dialog = new AjxpReactDialogLoader('WelcomeComponents', 'QRCodeDialogLoader', {});
        dialog.openDialog('qrcode_dialog_form', true);
    };


    /***********************************************
     * DYNAMIC GRID DASHBOARD
     ***********************************************/
    const WorkspacesListCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:3,
            gridHeight:36,
            builderDisplayName:'My Workspaces',
            builderFields:[]
        },

        render: function(){
            const {pydio, filterByType} = this.props;
            let props = {...this.props};
            if(props.style){
                props.style = {...props.style, overflowY:'auto'};
            }
            const messages = this.props.pydio.MessageHash;
            return (
                <MaterialUI.Paper zDepth={1} {...props} transitionEnabled={false}>
                    {this.getCloseButton()}
                    <MaterialUI.CardTitle title={messages[filterByType==='entries'?468:469]} subtitle={filterByType==='entries'?'Generic workspaces I can access to':'Shared with me by other users'}/>
                    <PydioWorkspaces.WorkspacesList
                        className={"vertical_fit filter-" + filterByType}
                        pydio={this.props.pydio}
                        workspaces={this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []}
                        showTreeForWorkspace={false}
                        filterByType={this.props.filterByType}
                        sectionTitleStyle={{display:'none'}}
                    />
                </MaterialUI.Paper>
            );
        }
    });


    const DlAppsCard = React.createClass({
        mixins: [PydioComponents.DynamicGridItemMixin],

        statics:{
            gridWidth:3,
            gridHeight:10,
            builderDisplayName:'Download Applications',
            builderFields:[]
        },

        render: function(){
            let props = {...this.props};
            if(props.style){
                props.style = {...props.style, overflowY:'auto'};
            }
            return (
                <MaterialUI.Paper zDepth={1} {...props}  transitionEnabled={false}>
                    {this.getCloseButton()}
                    <div style={{width: 380, margin:'10px auto', position:'relative'}}>
                        <DlAppsPanel pydio={this.props.pydio} open={true}/>
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
                        x:6, y:0
                    },
                    defaultLayouts: {
                        sm: {x: 0, y: 30}
                    }
                },
                {
                    id:'downloads',
                    componentClass:'WelcomeComponents.DlAppsCard',
                    defaultPosition:{
                        x:0, y:30
                    },
                    defaultLayouts: {
                        md: {x: 0, y: 60},
                        sm: {x: 0, y: 60}
                    }
                }
            ];

            return baseCards;
        },

        getInitialState:function(){
            return {player: null};
        },

        render:function() {

            var videoPlayer;
            if(this.state && this.state.player){
                videoPlayer = <VideoPlayer videoSrc={this.state.player} closePlayer={this.closePlayer}/>
            }

            var simpleClickOpen = this.props.pydio.getPluginConfigs("access.ajxp_home").get("SIMPLE_CLICK_WS_OPEN");
            var enableGettingStarted = this.props.pydio.getPluginConfigs('access.ajxp_home').get("ENABLE_GETTING_STARTED");

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
                <div className="left-panel expanded vertical_fit vertical_layout">
                    {videoPlayer}
                    <PydioWorkspaces.UserWidget
                        pydio={this.props.pydio}
                        style={widgetStyle}
                        {...uWidgetProps}
                    >
                        <div>
                            <PydioWorkspaces.SearchForm
                                crossWorkspace={true}
                                pydio={this.props.pydio}
                                groupByField="repository_id"
                            />
                        </div>
                    </PydioWorkspaces.UserWidget>
                    <PydioComponents.DynamicGrid
                        storeNamespace="WelcomePanel.Dashboard"
                        defaultCards={this.getDefaultCards()}
                        builderNamespaces={["WelcomeComponents"]}
                        pydio={this.props.pydio}
                        cols={{lg: 12, md: 9, sm: 6, xs: 6, xxs: 2}}
                        rglStyle={{position:'absolute', top: 110, bottom: 0, left: 0, right: 0, backgroundColor: lightColor}}
                    />
                </div>
            );
        }

    });

    UserDashboard = MaterialUI.Style.muiThemeable()(UserDashboard);

    var WelcomeComponents = global.WelcomeComponents || {};
    if(global.ReactDND){
        WelcomeComponents.UserDashboard = global.ReactDND.DragDropContext(ReactDND.HTML5Backend)(UserDashboard);
    }else{
        WelcomeComponents.UserDashboard = UserDashboard;
    }
    WelcomeComponents.VideoCard = VideoCard;
    WelcomeComponents.DlAppsCard = DlAppsCard;
    WelcomeComponents.QRCodeDialogLoader = QRCodeDialogLoader;
    WelcomeComponents.WorkspacesListCard = WorkspacesListCard;
    global.WelcomeComponents = WelcomeComponents;

})(window);
