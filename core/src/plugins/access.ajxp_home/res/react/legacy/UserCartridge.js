export default React.createClass({

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
