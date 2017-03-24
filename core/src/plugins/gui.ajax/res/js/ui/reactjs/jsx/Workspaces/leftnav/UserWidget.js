export default React.createClass({

    applyAction: function(actionName){
        switch (actionName){
            case 'alerts':
                break;
            case 'home':
                this.props.pydio.triggerRepositoryChange('ajxp_home');
                break;
            case 'settings':
                this.props.pydio.triggerRepositoryChange('ajxp_conf');
                break;
            case 'info':
                this.props.pydio.Controller.getActionByName('splash').deny = false;
                this.props.pydio.Controller.fireAction('splash');
                break;
            case 'cog':
                // Open dashboard in modal
                this.props.pydio.Controller.fireAction('open_user_dashboard');
                break;
            case 'address-book':
                // Open dashboard in modal
                this.props.pydio.Controller.fireAction('open_address_book');
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
        let homeButton, infoButton, logoutButton, notificationsButton, settingsButton, addressBookButton;
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
            if(user.getRepositoriesList().has('ajxp_home')){
                homeButton = (
                    <MaterialUI.IconButton
                        onTouchTap={this.applyAction.bind(this, 'home')}
                        iconClassName="userActionIcon mdi mdi-home"
                        className="userActionButton"
                        tooltip={messages['305']}
                        disabled={user.activeRepository === 'ajxp_home'}
                    />
                );
            }
            if(user.getRepositoriesList().has('ajxp_conf') && user.activeRepository === 'ajxp_home'){
                settingsButton = (
                    <MaterialUI.IconButton
                        onTouchTap={this.applyAction.bind(this, 'settings')}
                        iconClassName="userActionIcon mdi mdi-settings"
                        className="userActionButton"
                        tooltip={messages['165']}
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
            if(this.props.pydio.Controller.getActionByName('open_address_book')){
                addressBookButton = (
                    <MaterialUI.IconButton
                        onTouchTap={this.applyAction.bind(this, 'address-book')}
                        iconClassName="userActionIcon mdi mdi-book-open"
                        className="userActionButton"
                        tooltip={messages['166']}
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

        // Do not display Home Button here for the moment
        const actionBar = (
            <div className="action_bar">
                {settingsButton}
                {notificationsButton}
                {addressBookButton}
                {infoButton}
                {logoutButton}
            </div>
        );

        if(this.props.children){
            return (
                <MaterialUI.Paper zDepth={1} rounded={false} style={{...this.props.style, display:'flex'}} className="user-widget primaryColorDarkerPaper">
                    <div style={{flex: 1}}>
                        {avatar}
                        {actionBar}
                    </div>
                    <div>{this.props.children}</div>
                </MaterialUI.Paper>
            );
        }else{
            return (
                <MaterialUI.Paper zDepth={1} rounded={false} style={this.props.style} className="user-widget primaryColorDarkerPaper">
                    {avatar}
                    {actionBar}
                </MaterialUI.Paper>
            );
        }
    }
});
