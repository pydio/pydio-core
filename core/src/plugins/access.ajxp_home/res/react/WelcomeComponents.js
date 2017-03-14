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
                        <div style={{color:'white'}}>{MessageHash[this.props.messageId]}</div>
                    </div>
                </PydioComponents.LabelWithTip>
            );
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

        propTypes:{
            youtubeId:React.PropTypes.string,
            contentMessageId:React.PropTypes.string,
            launchVideo:React.PropTypes.func
        },

        launchVideo: function(){
            this.props.launchVideo("//www.youtube.com/embed/"+this.props.youtubeId+"?list=PLxzQJCqzktEbYm3U_O1EqFru0LsEFBca5&autoplay=1");
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
                        <div className="tutorial_load_button" onClick={this.launchVideo}><i className="icon-youtube-play"/> {MessageHash['user_home.86']}</div>
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
                            youtubeId="nkfRn-MxezE"
                            contentMessageId="user_home.62"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="qvsSeLXr-T4"
                            contentMessageId="user_home.63"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="HViCWPpyZ6k"
                            contentMessageId="user_home.79"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="jBRNqwannJM"
                            contentMessageId="user_home.80"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="2jl1EsML5v8"
                            contentMessageId="user_home.81"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="28-t4dvhE6c"
                            contentMessageId="user_home.82"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="fP0MVejnVZE"
                            contentMessageId="user_home.83"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="TXFz4w4trlQ"
                            contentMessageId="user_home.84"
                        />
                        <VideoCard
                            launchVideo={this.launchVideo}
                            youtubeId="OjHtgnL_L7Y"
                            contentMessageId="user_home.85"
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

    /***********************************************
     * OTHERS
     ***********************************************/
    var HomeSearchEngine = React.createClass({

        getInitialState:function(){
            return {
                loading : 0,
                value   : ''
            };
        },

        suggestionLoader:function(input, callback) {

            let nodeProvider = new RemoteNodeProvider();
            nodeProvider.initProvider({get_action:'multisearch', query:input});
            let rootNode     = new AjxpNode('/', false);
            this.setState({loading:true, value: input});
            nodeProvider.loadNode(rootNode, function(){
                let results = [];
                let previousRepo = -1;
                rootNode.getChildren().forEach(function(v){
                    let repoId = v.getMetadata().get("repository_id");
                    if(repoId != previousRepo){
                        let node = new AjxpNode('/', false, v.getMetadata().get("repository_display"));
                        node.setRoot();
                        node.getMetadata().set("repository_id", repoId);
                        results.push(node);
                    }
                    results.push(v);
                    previousRepo = repoId;
                });
                // Hack : force suggestions display
                if(this.refs.autosuggest.lastSuggestionsInputValue && this.refs.autosuggest.lastSuggestionsInputValue.indexOf(input) === 0){
                    this.refs.autosuggest.lastSuggestionsInputValue = input;
                }
                callback(null, results);
                this.setState({loading:false});
            }.bind(this));
        },

        getSuggestions(input, callback){
            if(input.length < 3){
                callback(null, []);
                return;
            }
            FuncUtils.bufferCallback('suggestion-loader-search', 350, function(){
                this.suggestionLoader(input, callback);
            }.bind(this));
        },

        suggestionValue: function(suggestion){
            return '';
        },

        onSuggestionSelected: function(resultNode, event){
            if(typeof resultNode === "string"){
                return ;
            }
            pydio.goTo(resultNode);
        },

        renderSuggestion(resultNode){
            if(typeof resultNode === "string"){
                return <span className="groupHeader">{resultNode}</span>;
            }
            if(resultNode.isRoot()){
                return <span className="groupHeader">{resultNode.getLabel()}<span className="openicon icon-long-arrow-right"></span></span>;
            }else{
                let isLeaf = resultNode.isLeaf();
                let label = resultNode.getLabel();
                let value = this.state.value;
                let r = new RegExp(value, 'gi');
                label = label.replace(r, function(m){return '<span class="highlight">'+m+'</span>'});
                let htmlFunc = function(){return {__html:label}};
                return (
                    <span className="nodeSuggestion">
                        <span className={isLeaf ? "icon icon-file-alt" : "icon icon-folder-close-alt"}></span>
                        <span dangerouslySetInnerHTML={htmlFunc()}/>
                    </span>);
            }
        },

        render: function(){

            const inputAttributes = {
                id: 'search-autosuggest',
                name: 'search-autosuggest',
                className: 'react-autosuggest__input',
                placeholder: pydio.MessageHash['user_home.75'],
                onBlur: event => pydio.UI.enableAllKeyBindings(),
                onFocus: event => pydio.UI.disableAllKeyBindings(),
                value: ''   // Initial value
            };
            return (
                <div className={this.props.className + ' home_search'}>
                    <span className={"suggest-search icon-" + (this.state.loading ? 'refresh rotating' : 'search')}/>
                    <ReactAutoSuggest
                        ref="autosuggest"
                        cache={true}
                        showWhen = {input => true }
                        inputProps={inputAttributes}
                        suggestions={this.getSuggestions}
                        suggestionRenderer={this.renderSuggestion}
                        suggestionValue={this.suggestionValue}
                        onSuggestionSelected={this.onSuggestionSelected}
                    />
                </div>

            );
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
            gridWidth:4,
            gridHeight:30,
            builderDisplayName:'My Workspaces',
            builderFields:[]
        },

        render: function(){
            let props = {...this.props};
            if(props.style){
                props.style = {...props.style, overflowY:'auto'};
            }
            return (
                <MaterialUI.Paper zDepth={1} {...props} >
                    <PydioWorkspaces.WorkspacesList
                        className={"vertical_fit"}
                        pydio={this.props.pydio}
                        workspaces={this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []}
                        showTreeForWorkspace={false}
                        filterByType={this.props.filterByType}
                    />
                </MaterialUI.Paper>
            );
        }
    });

    let UserDashboard = React.createClass({

        getDefaultCards: function(){
            return [
                {
                    id:'my_workspaces',
                    componentClass:'WelcomeComponents.WorkspacesListCard',
                    props:{
                        filterByType:"entries",
                    },
                    defaultPosition:{
                        x:0, y:0
                    }
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
                }
            ];
        },

        render:function() {
            var simpleClickOpen = this.props.pydio.getPluginConfigs("access.ajxp_home").get("SIMPLE_CLICK_WS_OPEN");
            var enableGettingStarted = this.props.pydio.getPluginConfigs('access.ajxp_home').get("ENABLE_GETTING_STARTED");

            const palette = this.props.muiTheme.palette;
            const Color = MaterialUI.Color;
            const widgetStyle = {
                backgroundColor: Color(palette.primary1Color).darken(0.2),
                width:'100%'
            };
            const uWidgetProps = this.props.userWidgetProps || {};
            const wsListProps = this.props.workspacesListProps || {};
            return (
                <div className="left-panel vertical_fit vertical_layout" style={{width:'100%'}}>
                    <PydioWorkspaces.UserWidget
                        pydio={this.props.pydio}
                        style={widgetStyle}
                        {...uWidgetProps}
                    />
                    <PydioComponents.DynamicGrid
                        storeNamespace="WelcomePanel.Dashboard"
                        defaultCards={this.getDefaultCards()}
                        pydio={this.props.pydio}
                        disableDrag={true}
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
    WelcomeComponents.TutorialPane = TutorialPane;
    WelcomeComponents.QRCodeDialogLoader = QRCodeDialogLoader;
    WelcomeComponents.WorkspacesListCard = WorkspacesListCard;
    global.WelcomeComponents = WelcomeComponents;

})(window);
