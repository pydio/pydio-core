(function(global){

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
            return (
                <span>
                    <MaterialUI.IconButton
                        onTouchTap={this.handleTouchTap}
                        iconClassName="icon-bell"
                        tooltip="Notifications"
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
                            nodeProviderProperties={{get_action:"get_my_feed", connexion_discrete:true, format:"xml", current_repository:"true", feed_type:"alert", merge_description:"false"}}
                            actionBarGroups={[]}
                        />
                    </MaterialUI.Popover>
                </span>
            );
        }

    });

    global.PydioNotifications = {
        Panel: NotificationsPanel
    };

})(window);

