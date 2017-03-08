export default React.createClass({

    applyAction: function(actionName){
        switch (actionName){
            case 'alerts':
                break;
            case 'home':
                this.props.pydio.triggerRepositoryChange('ajxp_home');
                break;
            case 'info':
                this.props.pydio.Controller.getActionByName('splash').deny = false;
                this.props.pydio.Controller.fireAction('splash');
                break;
            case 'cog':
                // Open dashboard in modal
                this.props.pydio.Controller.fireAction('open_user_dashboard');
                break;
            case 'logout':
                this.props.pydio.Controller.fireAction('logout');
                break;
            default:
                break;
        }
    },

    render: function(){

        const messages = this.props.pydio.MessageHash;

        let avatar;
        let homeButton, infoButton, logoutButton, notificationsButton;
        if(this.props.pydio.user){
            const user = this.props.pydio.user;
            avatar = (
                <PydioComponents.UserAvatar
                    pydio={this.props.pydio}
                    userId={user.id}
                    avatarStyle={{marginRight:20}}
                    className="user-display"
                    labelClassName="userLabel"
                >
                    <MaterialUI.IconButton
                        onTouchTap={this.applyAction.bind(this, 'cog')}
                        iconClassName="mdi mdi-settings"
                        tooltip={messages['165']}
                        style={{width: 38, height: 38}}
                        iconStyle={{fontSize: 16, color: 'rgba(255,255,255,0.87)'}}
                    />
                </PydioComponents.UserAvatar>
            );
            if(user.getRepositoriesList().has('ajxp_home') && user.activeRepository !== 'ajxp_home'){
                homeButton = (
                    <MaterialUI.IconButton
                        onTouchTap={this.applyAction.bind(this, 'home')}
                        iconClassName="userActionIcon mdi mdi-home"
                        className="userActionButton"
                        tooltip={messages['305']}
                    />
                );
            }
            if(this.props.pydio.Controller.getActionByName('get_my_feed')){
                notificationsButton = (
                    <PydioReactUI.AsyncComponent
                        namespace="PydioNotifications"
                        componentName="Panel"
                        noLoader={true}
                        iconClassName="userActionIcon mdi mdi-bell-outline"
                        {...this.props}
                    />
                );
            }
        }
        if(this.props.pydio.Controller.getActionByName('splash')){
            infoButton =(
                <MaterialUI.IconButton
                    onTouchTap={this.applyAction.bind(this, 'info')}
                    iconClassName="userActionIcon mdi mdi-information-outline"
                    className="userActionButton"
                    tooltip={messages['166']}
                />
            ) ;
        }
        if(this.props.pydio.Controller.getActionByName('logout')){
            logoutButton = (
                <MaterialUI.IconButton
                    onTouchTap={this.applyAction.bind(this, 'logout')}
                    iconClassName="userActionIcon mdi mdi-logout"
                    className="userActionButton"
                    tooltip={messages['169']}
                />
            );
        }

        return (
            <MaterialUI.Paper zDepth={1} rounded={false} style={this.props.style} className="user-widget primaryColorDarkerPaper">
                {avatar}
                <div className="action_bar">
                    {homeButton}
                    {notificationsButton}
                    {infoButton}
                    {logoutButton}
                </div>
            </MaterialUI.Paper>
        );
    }
});
