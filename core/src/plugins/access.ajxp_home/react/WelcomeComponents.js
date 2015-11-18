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

    var TutorialPane = React.createClass({

        componentDidMount: function(){
            $('videos_pane').select('div.tutorial_load_button').invoke("observe", "click", function(e){
                var t = Event.findElement(e, 'div.tutorial_load_button');
                try{
                    var main = t.up('div.tutorial_legend');
                    if(main.next('img')){
                        main.insert({after:'<iframe className="tutorial_video" width="640" height="360" frameborder="0" allowfullscreen src="'+main.readAttribute('data-videosrc')+'"></iframe>'});
                        main.next('img').remove();
                    }
                }catch(e){}
            });
        },

        closePane: function(){
            React.unmountComponentAtNode(document.getElementById('tutorial_panel'));
        },

        render: function(){
            var configs = pydio.getPluginConfigs('access.ajxp_home');
            var htmlMessage = function(id){
                return {__html:MessageHash[id]};
            };
            return (
                <div id="videos_pane" className="skipSibling">
                    <div onClick={this.closePane} className="icon-remove-sign"></div>
                    <div className="tutorial_title">{MessageHash['user_home.56']}</div>
                    <div id="tutorial_dl_apps_pane">
                        <div id="dl_pydio_cont">
                            <div id="dl_pydio_for">{MessageHash['user_home.57']}</div>
                            <div id="dl_pydio_android">
                                <a href={configs.get('URL_APP_ANDROID')} target="_blank" className="icon-mobile-phone"></a><a href={configs.get('URL_APP_ANDROID')} target="_blank"  className="icon-android"></a><div>{MessageHash['user_home.58']}</div>
                            </div>
                            <div id="dl_pydio_ios">
                                <a href={configs.get('URL_APP_IOSAPPSTORE')} target="_blank" className="icon-tablet"></a><a href={configs.get('URL_APP_IOSAPPSTORE')} target="_blank" className="icon-apple"></a><div>{MessageHash['user_home.59']}</div>
                            </div>
                            <div id="dl_pydio_mac" >
                                <a href={configs.get('URL_APP_SYNC_MAC')} target="_blank" className="icon-desktop" ></a><a href={configs.get('URL_APP_SYNC_MAC')} target="_blank" className="icon-apple" ></a><div >{MessageHash['user_home.60']}</div>
                            </div>
                            <div id="dl_pydio_win" >
                                <a href={configs.get('URL_APP_SYNC_WIN')} target="_blank" className="icon-laptop" ></a><a href={configs.get('URL_APP_SYNC_WIN')} target="_blank" className="icon-windows" ></a><div >{MessageHash['user_home.61']}</div>
                            </div>
                        </div>
                    </div>
                    <div className="tutorial_legend" data-videosrc="//www.youtube.com/embed/80kq-T6bQO4?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5">
                        <span dangerouslySetInnerHTML={htmlMessage('user_home.62')}></span>
                        <div className="tutorial_load_button"><i className="icon-youtube-play"></i> Play Video</div>
                    </div>
                    <img className="tutorial_video" src="https://img.youtube.com/vi/80kq-T6bQO4/0.jpg"/>

                    <div className="tutorial_legend" data-videosrc="//www.youtube.com/embed/ZuVKsIa4XdU?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5">
                        <div dangerouslySetInnerHTML={htmlMessage('user_home.63')}></div>
                        <div className="tutorial_load_button"><i className="icon-youtube-play"></i> Play Video</div>
                    </div>
                    <img className="tutorial_video" src="https://img.youtube.com/vi/ZuVKsIa4XdU/0.jpg"/>

                    <div className="tutorial_legend" data-videosrc="//www.youtube.com/embed/MEHCN64RoTY?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5">
                        <div dangerouslySetInnerHTML={htmlMessage('user_home.64')}></div>
                        <div className="tutorial_load_button"><i className="icon-youtube-play"></i> Play Video</div>
                    </div>
                    <img className="tutorial_video" src="https://img.youtube.com/vi/MEHCN64RoTY/0.jpg"/>

                    <div className="tutorial_legend" data-videosrc="//www.youtube.com/embed/ot2Nq-RAnYE?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5">
                        <div dangerouslySetInnerHTML={htmlMessage('user_home.66')}></div>
                        <div className="tutorial_load_button"><i className="icon-youtube-play"></i> Play Video</div>
                    </div>
                    <img className="tutorial_video" src="https://img.youtube.com/vi/ot2Nq-RAnYE/0.jpg"/>

                    <div className="tutorial_more_videos_cont">
                        <a  className="tutorial_more_videos_button" href="http://pyd.io/end-user-tutorials/" target="_blank"><i className="icon-youtube-play"></i>
                            <span dangerouslySetInnerHTML={htmlMessage('user_home.65')}></span></a>
                    </div>
                </div>
            );

            //<div dangerouslySetInnerHTML={content()}></div>
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
            this.props.controller.fireAction("open_tutorial_pane");
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
            if(this.props.enableGettingStarted){
                gettingStartedBlock = (
                    <small><span onClick={this.showGettingStarted}>{MessageHash["user_home.55"].replace('<a>', '').replace('</a>','')}</span></small>
                )
            }


            return (
                <div id="welcome">
                    {MessageHash['user_home.40'].replace('%s', userLabel)}
                    {loginLink}
                    {gettingStartedBlock}
                </div>
            )
        }

    });

    var HomeWorkspacesList = React.createClass({

        render: function(){
            var workspacesNodes = [];
            var sharedNodes = [];
            this.props.workspaces.forEach(function(v){
                if (v.getAccessType().startsWith('ajxp_')) return;
                var node = <HomeWorkspaceItem ws={v}
                    key={v.getId()}
                    onHoverLink={this.props.onHoverLink}
                    onOutLink={this.props.onOutLink}
                    onOpenLink={this.props.onOpenLink}
                    openOnDoubleClick={this.props.openOnDoubleClick}
                />;
                if (v.owner !== ''){
                    sharedNodes.push(node);
                }else{
                    workspacesNodes.push(node);
                }
            }.bind(this));
            var titleNode = workspacesNodes.length? <li className="ws_selector_title"><h3>{MessageHash[468]}</h3></li> : '';
            var titleSharedNode = sharedNodes.length? <li className="ws_selector_title"><h3>{MessageHash[469]}</h3></li> : '';
            return (
                <ul id="workspaces_list">
                    {titleNode}
                    {workspacesNodes}
                    {titleSharedNode}
                    {sharedNodes}
                </ul>
            );
        }
    });

    var HomeWorkspaceItem = React.createClass({
        onHoverLink: function(event) {
            this.props.onHoverLink(event, this.props.ws);
        },
        onClickLink: function(event) {
            if(!this.props.openOnDoubleClick){
                this.props.onOpenLink(event, this.props.ws);
            }
        },
        onDoubleClickLink: function(event) {
            if(this.props.openOnDoubleClick){
                this.props.onOpenLink(event, this.props.ws);
            }
        },
        render: function(){
            var letters = this.props.ws.getLabel().split(" ").map(function(word){return word.substr(0,1)}).join("");
            return (
                <li onMouseOver={this.onHoverLink} onMouseOut={this.props.onOutLink} onTouchTap={this.onClickLink} onClick={this.onClickLink} onDoubleClick={this.onDoubleClickLink}>
                    <span className="letter_badge">{letters}</span>
                    <h3>{this.props.ws.getLabel()}</h3>
                    <h4>{this.props.ws.getDescription()}</h4>
                </li>
            )
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
                return <div id="ws_legend" className="empty_ws_legend"></div>;
            }
            var blocks = [];
            var data = this.state.data;
            if(data['core.users'] && data['core.users']['internal'] != undefined && data['core.users']['external'] != undefined){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="core.users" badgeTitle={MessageHash[527]} iconClass="icon-group">
                    {MessageHash[531]} {data['core.users']['internal']}
                    <br/>{MessageHash[532]} {data['core.users']['external']}
                    </HomeWorkspaceLegendInfoBlock>
                );
            }
            if(data['meta.quota']){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="meta.quota" badgeTitle={MessageHash['meta.quota.4']} iconClass="icon-dashboard">
                    {parseInt(100*data['meta.quota']['usage']/data['meta.quota']['total'])}%<br/>
                        <small>{roundSize(data['meta.quota']['total'], MessageHash["byte_unit_symbol"])}</small>
                    </HomeWorkspaceLegendInfoBlock>
                );
            }
            if(data['core.notifications'] && data['core.notifications'][0]){
                blocks.push(
                    <HomeWorkspaceLegendInfoBlock key="notifications" badgeTitle={MessageHash[4]} iconClass="icon-calendar">
                    {data['core.notifications'][0]['short_date']}
                    </HomeWorkspaceLegendInfoBlock>
                );
            }

            return (
                <div id="ws_legend">
                    {this.state.workspace.getLabel()}
                    <small>{this.state.workspace.getDescription()}</small>
                    <div className="repoInfo">
                    {blocks}
                    </div>
                    <div style={{lineHeight: '0.5em'}}>
                        <input type="checkbox" ref="save_ws_choice" id="save_ws_choice"/>
                        <label htmlFor="save_ws_choice">{MessageHash['user_home.41']}</label>
                        <a onClick={this.enterWorkspace}>{MessageHash['user_home.42']}</a>
                    </div>
                </div>
            )
        }
    });

    var HomeWorkspaceLegendInfoBlock = React.createClass({
        render:function(){
            return <div className="repoInfoBadge">
                <div className="repoInfoTitle">
                    {this.props.badgeTitle}
                </div>
                <span className={this.props.iconClass}></span>
                {this.props.children}
            </div>
        }
    });

    var UserDashboard = React.createClass({

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
            this.refs.legend.setWorkspace(ws);
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
                            <HomeWorkspacesList className="vertical_layout vertical_fit"
                                workspaces={this.props.pydio.user.repositories}
                                active={this.props.pydio.user.active}
                                openOnDoubleClick={!simpleClickOpen}
                                onHoverLink={this.onHoverLink}
                                onOutLink={this.onOutLink}
                                onOpenLink={this.onOpenLink}
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

    var WelcomeComponents = global.WelcomeComponents || {};
    WelcomeComponents.UserDashboard = UserDashboard;
    WelcomeComponents.TutorialPane = TutorialPane;
    global.WelcomeComponents = WelcomeComponents;

})(window);