export default React.createClass({

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