(function(global){

    let ActivityPanel = React.createClass({

        componentWillReceiveProps: function(nextProps){
            if(nextProps.node !== this.props.node){
                this.forceUpdate(function(){
                    this.refs.provider.reload();
                }.bind(this));
            }
        },

        getProviderProperties: function(node){

            return {
                "get_action":"get_my_feed",
                "connexion_discrete":true,
                "format":"xml", "current_repository":"true",
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

        render: function(){

            const {node, pydio} = this.props;

            let dataModel = new PydioDataModel(true);
            const rNodeProvider = new RemoteNodeProvider(this.getProviderProperties(node));
            const rootNode = new AjxpNode(node.getPath(), false, "Activity", "", rNodeProvider);
            dataModel.setAjxpNodeProvider(rNodeProvider);
            dataModel.setRootNode(rootNode);


            return (

                <PydioDetailPanes.InfoPanelCard title="Activity">
                    <PydioComponents.NodeListCustomProvider
                        pydio={pydio}
                        className="small"
                        elementHeight={53}
                        heightAutoWithMax={500}
                        presetDataModel={dataModel}
                        actionBarGroups={[]}
                        ref="provider"
                    />
                </PydioDetailPanes.InfoPanelCard>

            );

        }

    });

    let NotificationsPanel = React.createClass({

        getInitialState: function(){
            return {open: false};
        },

        handleTouchTap:function(event){
            // This prevents ghost click.
            event.preventDefault();

            this.setState({
                open: true,
                anchorEl: event.currentTarget,
            });
        },

        handleRequestClose : function(){
            this.setState({
                open: false,
            });
        },

        render: function() {

            let providerProperties = {
                get_action:"get_my_feed",
                connexion_discrete:true,
                format:"xml",
                current_repository:"true",
                feed_type:"alert",
                merge_description:"false"
            };

            return (
                <span>
                    <MaterialUI.IconButton
                        onTouchTap={this.handleTouchTap}
                        iconClassName={this.props.iconClassName || "icon-bell"}
                        tooltip={this.props.pydio.MessageHash['notification_center.4']}
                        className="userActionButton"
                    />
                    <MaterialUI.Popover
                        open={this.state.open}
                        anchorEl={this.state.anchorEl}
                        anchorOrigin={{horizontal: 'left', vertical: 'bottom'}}
                        targetOrigin={{horizontal: 'left', vertical: 'top'}}
                        onRequestClose={this.handleRequestClose}
                        style={{width:400}}

                    >
                        <PydioComponents.NodeListCustomProvider
                            pydio={this.props.pydio}
                            elementHeight={53}
                            heightAutoWithMax={500}
                            nodeProviderProperties={providerProperties}
                            actionBarGroups={[]}
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

