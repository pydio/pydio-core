(function(global){

    const EventsIcons = {
        'add'       : 'folder-plus',
        'add-file'  : 'folder-upload',
        'delete'    : 'delete',
        'change'    : 'pencil',
        'rename'    : 'rename-box',
        'view'      : 'eye',
        'copy'      : 'content-copy',
        'move'      : 'folder-move',
        'copy_to'   : 'folder-move',
        'copy_from' : 'folder-move',
        'move_from' : 'folder-move',
        'move_to'   : 'folder-move'
    };

    const timelineStyle = {
        position: 'absolute',
        top: 0,
        left: 33,
        bottom: 0,
        width: 4,
        backgroundColor: '#eceff1'
    };

    const originStyles = {opacity: 0.3}
    const targetStyles = {opacity: 1}

    let Template = (props) => {
        return <div {...props} style={{padding: 0}} />
    }
    Template = PydioHOCs.Animations.makeTransition(originStyles, targetStyles)(Template)

    let ActivityPanel = React.createClass({

        getInitialState: function(){
            return {
                empty: true,
                dataModel: this.initDataModel(this.props.node)
            };
        },

        initDataModel: function(node){
            const dataModel = PydioDataModel.RemoteDataModelFactory(this.getProviderProperties(node), "Activity");
            dataModel.getRootNode().observe('loaded', () => {
                this.setState({empty: !dataModel.getRootNode().getChildren().size});
            });
            dataModel.getRootNode().load();
            return dataModel;
        },

        componentWillReceiveProps: function(nextProps){
            if(nextProps.node !== this.props.node){
                this.setState({
                    dataModel: this.initDataModel(nextProps.node)
                }, () => {
                    if(this.refs.provider) this.refs.provider.reload();
                });
            }
        },

        getProviderProperties: function(node){

            return {
                "get_action":"get_my_feed",
                "connexion_discrete":true,
                "format":"xml",
                "current_repository":"true",
                "feed_type":"notif",
                "limit":(node.isLeaf() || node.isRoot() ? 18 : 4),
                "path":(node.isLeaf() || node.isRoot()?node.getPath():node.getPath()+'/'),
                "merge_description":"true",
                "description_as_label":node.isLeaf()?"true":"false",
                "cache_service":{
                    "metaStreamName":"files.activity" + node.getPath(),
                    "expirationPolicy":MetaCacheService.EXPIRATION_MANUAL_TRIGGER
                }
            };

        },

        renderIconFile: function(node){
            let fileNode = new AjxpNode(node.getMetadata().get('real_path'), node.isLeaf(), node.getLabel());
            fileNode.setMetadata(node.getMetadata());
            return (
                <div style={{position:'relative'}}>
                    <div style={{...timelineStyle, bottom: -1}}/>
                    <PydioWorkspaces.FilePreview rounded={true} loadThumbnail={true} node={fileNode}/>
                </div>
            );
        },

        renderTimelineEntry: function(props){

            const {node, isFirst} = props;
            let action = node.getMetadata().get('event_action');
            if(action === 'add' && node.isLeaf()){
                action = 'add-file';
            }
            const timeline = {...timelineStyle};
            if(isFirst){
                timeline['top'] = 34;
            }

            return (

                <div className="ajxp_node_leaf material-list-entry material-list-entry-2-lines" style={{borderBottom: 0}}>
                    <div style={{position:'relative'}} className="material-list-icon">
                        <div style={timeline}/>
                        <div className="mimefont-container" style={{position:'absolute'}}>
                            <div className={"mimefont mdi mdi-" + EventsIcons[action]}/>
                        </div>
                    </div>
                    <div className="material-list-text">
                        <div className="material-list-line-1" style={{whiteSpace:'normal', lineHeight: '24px'}}>{node.getMetadata().get('event_description')}</div>
                        <div className="material-list-line-2">{node.getMetadata().get('short_date')}</div>
                    </div>
                </div>

            );
        },

        renderFirstLineLeaf: function(node){
            return <div style={{whiteSpace:'normal', lineHeight: '24px'}}>{node.getMetadata().get('event_description')}</div>
        },

        renderSecondLine: function(node){
            return <div style={{whiteSpace:'normal'}}>{node.getMetadata().get('event_description')}</div>;
        },

        renderActions: function(node){
            const {pydio} = this.props;
            const open = function(){
                pydio.goTo(node.getMetadata().get('real_path'));
            };
            return <MaterialUI.IconButton
                iconClassName="mdi mdi-arrow-right"
                onTouchTap={open}
                iconStyle={{color: 'rgba(0,0,0,0.23)',iconHoverColor: 'rgba(0,0,0,0.63)'}}/>
        },

        render: function(){

            if(this.state.empty){
                return null;
            }
            const {pydio, node} = this.props;

            let renderIcon = this.renderIconFile;
            let renderFirstLine = null;
            let renderCustomEntry = null;
            let renderSecondLine = this.renderSecondLine;
            let nodeClicked = (node) => {
                pydio.goTo(node.getMetadata().get('real_path'));
            };
            if(node.isLeaf()){
                renderCustomEntry = this.renderTimelineEntry;
                renderFirstLine = null;
                renderSecondLine = null;
                renderIcon = null;
                nodeClicked = () => {};
            }

            let label = node.isLeaf() ? "File Activity" : "Folder Activity";
            let root = false;
            if(node === pydio.getContextHolder().getRootNode()){
                label = "Workspace Activity";
                root = true;
            }

            return (

                <PydioWorkspaces.InfoPanelCard title={label} icon="pulse" iconColor="#F57C00" style={this.props.style}>
                    <Template>
                        <PydioComponents.NodeListCustomProvider
                            pydio={pydio}
                            className="files-list"
                            elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES + 20}
                            heightAutoWithMax={root ? 420 : 320}
                            presetDataModel={this.state.dataModel}
                            actionBarGroups={[]}
                            ref="provider"
                            hideToolbar={true}
                            renderCustomEntry={renderCustomEntry}
                            entryRenderIcon={renderIcon}
                            entryRenderFirstLine={renderFirstLine}
                            entryRenderSecondLine={renderSecondLine}
                            nodeClicked={nodeClicked}
                            defaultSortingInfo={{attribute : 'event_time',sortType:'number',direction : 'desc'}}
                        />
                    </Template>
                </PydioWorkspaces.InfoPanelCard>

            );

        }

    });

    let NotificationsPanel = React.createClass({

        getInitialState: function(){

            let providerProperties = {
                get_action:"get_my_feed",
                connexion_discrete:true,
                format:"xml",
                feed_type:"alert",
                merge_description:"false"
            };
            let repositoryScope = 'all';
            if(!(pydio && pydio.user && pydio.user.activeRepository === 'ajxp_home')){
                providerProperties['current_repository'] = 'true';
                repositoryScope = pydio.user.activeRepository;
            }
            const dataModel = PydioDataModel.RemoteDataModelFactory(providerProperties, 'Notifications');
            const rNode = dataModel.getRootNode();
            rNode.observe("loaded", function(){
                const unread = parseInt(rNode.getMetadata().get('unread_notifications_count')) || 0;
                if(unread) {
                    this.setState({unreadStatus: unread});
                }
            }.bind(this));
            rNode.load();

            if(repositoryScope === 'all'){
                this._pe = new PeriodicalExecuter(() => {rNode.reload(null, true)}, 8);
            }else{
                this._smObs = function(event){
                    if(XMLUtils.XPathSelectSingleNode(event, 'tree/reload_user_feed')) {
                        rNode.reload(null, true);
                    }
                }.bind(this);
            }
            this.props.pydio.observe("server_message", this._smObs);

            return {
                open: false,
                dataModel:dataModel,
                repositoryScope: repositoryScope,
                unreadStatus: 0
            };
        },

        componentWillUnmount: function(){
            if(this._smObs){
                this.props.pydio.stopObserving("server_message", this._smObs);
            }else if(this._pe){
                this._pe.stop();
            }
        },

        handleTouchTap:function(event){
            // This prevents ghost click.
            event.preventDefault();
            if(this.state.unreadStatus){
                this.updateAlertsLastRead();
            }
            this.setState({
                open: true,
                anchorEl: event.currentTarget,
                unreadStatus: 0
            });
        },

        handleRequestClose : function(){
            this.setState({
                open: false,
            });
        },

        renderIcon: function(node){
            return (
                <PydioWorkspaces.FilePreview
                    loadThumbnail={true}
                    node={node}
                    pydio={this.props.pydio}
                    rounded={true}
                />
            );
        },

        renderSecondLine: function(node){
            return node.getMetadata().get('event_description');
        },

        renderActions: function(node){
            const touchTap = function(event){
                event.stopPropagation();
                this.dismissAlert(node);
            }.bind(this);
            return <MaterialUI.IconButton
                iconClassName="mdi mdi-close"
                onClick={touchTap}
                style={{width: 36, height: 36, padding: 6}}
                iconStyle={{color: 'rgba(0,0,0,.23)', hoverColor:'rgba(0,0,0,.73)'}}
            />;
        },

        entryClicked: function(node){
            this.handleRequestClose();
            this.props.pydio.goTo(node);
        },

        dismissAlert: function(node){
            const alertId = node.getMetadata().get('alert_id');
            const occurences = node.getMetadata().get('event_occurence');
            PydioApi.getClient().request({
                get_action:'dismiss_user_alert',
                alert_id:alertId,
                // Warning, occurrences parameter expects 2 'r'
                occurrences:occurences
            }, function(t){
                this.refs.list.reload();
            }.bind(this));
        },

        updateAlertsLastRead: function(){
            PydioApi.getClient().request({
                get_action          : 'update_alerts_last_read',
                repository_scope    : this.state.repositoryScope
            });
        },

        render: function() {

            let button;
            const buttonIcon = (
                <MaterialUI.IconButton
                    onTouchTap={this.handleTouchTap}
                    iconClassName={this.props.iconClassName || "icon-bell"}
                    tooltip={this.props.pydio.MessageHash['notification_center.4']}
                    className="userActionButton"
                />
            );

            return (
                <span>
                    <MaterialUI.Badge
                        badgeContent={this.state.unreadStatus}
                        secondary={true}
                        style={this.state.unreadStatus  ? {padding: '0 24px 0 0'} : {padding: 0}}
                        badgeStyle={!this.state.unreadStatus ? {display:'none'} : null}
                    >{buttonIcon}</MaterialUI.Badge>
                    <MaterialUI.Popover
                        open={this.state.open}
                        anchorEl={this.state.anchorEl}
                        anchorOrigin={{horizontal: 'left', vertical: 'bottom'}}
                        targetOrigin={{horizontal: 'left', vertical: 'top'}}
                        onRequestClose={this.handleRequestClose}
                        style={{width:400}}
                        zDepth={2}

                    >
                        <PydioComponents.NodeListCustomProvider
                            ref="list"
                            className={'files-list'}
                            hideToolbar={true}
                            pydio={this.props.pydio}
                            elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES + 2}
                            heightAutoWithMax={500}
                            presetDataModel={this.state.dataModel}
                            reloadAtCursor={true}
                            actionBarGroups={[]}
                            entryRenderIcon={this.renderIcon}
                            entryRenderSecondLine={this.renderSecondLine}
                            entryRenderActions={this.renderActions}
                            nodeClicked={this.entryClicked}
                        />
                    </MaterialUI.Popover>
                </span>
            );
        }

    });

    global.PydioNotifications = {
        Panel: NotificationsPanel,
        ActivityPanel: ActivityPanel
    };

})(window);
