class UserAvatar extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {
            label : props.userLabel || props.userId,
            avatar: null
        };
    }

    componentDidMount(){
        if(this.props.pydio.user && this.props.pydio.user.id === this.props.userId){
            this.loadLocalData();
            if(!this._userLoggedObs){
                this._userLoggedObs = this.loadLocalData.bind(this);
                this.props.pydio.observe('user_logged', this._userLoggedObs);
            }
        }else{
            this.cache = MetaCacheService.getInstance();
            this.cache.registerMetaStream('users_public_data', 'EXPIRATION_MANUAL_TRIGGER');
            this.loadPublicData();
        }
    }

    componentWillReceiveProps(nextProps){
        if(!this.props.userId || this.props.userId !== nextProps.userId){
            this.setState({label: nextProps.userId});
        }
        if(this.props.pydio && this.props.pydio.user && this.props.pydio.user.id === nextProps.userId){
            this.loadLocalData();
            if(!this._userLoggedObs){
                this._userLoggedObs = this.loadLocalData.bind(this);
                this.props.pydio.observe('user_logged', this._userLoggedObs);
            }
        }else{
            if(this._userLoggedObs){
                this.props.pydio.stopObserving('user_logged', this._userLoggedObs);
            }
            this.cache = MetaCacheService.getInstance();
            this.cache.registerMetaStream('users_public_data', 'EXPIRATION_MANUAL_TRIGGER');
            this.loadPublicData();
        }
    }

    componentWillUnmount(){
        if(this._userLoggedObs){
            this.props.pydio.stopObserving('user_logged', this._userLoggedObs);
        }
    }

    loadLocalData(){
        const {pydio} = this.props;
        if(!pydio.user){
            this.setState({label: '', avatar: null});
            return;
        }
        const userName = pydio.user.getPreference('USER_DISPLAY_NAME') || pydio.user.id;
        const avatarId = pydio.user.getPreference('avatar');
        const avatarUrl = PydioApi.getClient().buildUserAvatarUrl(pydio.user.id, avatarId);
        this.setState({
            label: userName,
            avatar:avatarUrl
        });
        if(!avatarUrl){
            this.loadFromExternalProvider();
        }
    }

    loadPublicData() {

        if(this.cache.hasKey('users_public_data', this.props.userId)){
            this.setState(this.cache.getByKey('users_public_data', this.props.userId));
            return;
        }
        PydioApi.getClient().request({
            get_action:'user_public_data',
            user_id:this.props.userId
        }, function(transport){
            const data = transport.responseJSON;
            if(data.error) return;
            let avatarUrl;
            const avatarId = data.avatar || null;
            const label = data.label || this.props.userId;
            if (!data.avatar) {
                this.loadFromExternalProvider();
            }else{
                avatarUrl = PydioApi.getClient().buildUserAvatarUrl(this.props.userId, avatarId);
            }
            this.cache.setKey('users_public_data', this.props.userId, {label: label, avatar:avatarUrl});
            this.setState({label: label, avatar:avatarUrl});
        }.bind(this));

    }

    loadFromExternalProvider(){
        if(!this.props.pydio.getPluginConfigs("ajxp_plugin[@id='action.avatar']").get("AVATAR_PROVIDER")) {
            return;
        }
        PydioApi.getClient().request({
            get_action: 'get_avatar_url',
            userid: this.props.userId
        }, function (transport) {
            this.cache.setKey('users_public_data', this.props.userId, {label: this.state.label, avatar:transport.responseText});
            this.setState({avatar: transport.responseText});
        }.bind(this));
    }

    render(){

        const {avatar, label} = this.state;
        const {style, labelStyle, avatarStyle, avatarSize, className, avatarClassName, labelClassName, displayLabel, displayAvatar, useDefaultAvatar} = this.props;
        let avatarContent, avatarColor;
        if(displayAvatar && !avatar && label && (!displayLabel || useDefaultAvatar) ){
            avatarContent = label.toUpperCase().substring(0,2);
            avatarColor = this.props.muiTheme.palette.primary1Color;
        }
        return (
            <div className={className} style={style}>
                {displayAvatar && (avatar || avatarContent) && <MaterialUI.Avatar
                    src={avatar}
                    style={avatarStyle}
                    className={avatarClassName}
                    size={avatarSize}
                    backgroundColor={avatarColor}
                >{avatarContent}</MaterialUI.Avatar>}
                {displayLabel && <div
                    className={labelClassName}
                    style={labelStyle}>{label}</div>}
                {this.props.children}
            </div>
        );

    }

}

UserAvatar.propTypes = {
    userId: React.PropTypes.string.isRequired,
    pydio : React.PropTypes.instanceOf(Pydio),
    userLabel:React.PropTypes.string,

    displayLabel: React.PropTypes.bool,
    displayAvatar: React.PropTypes.bool,
    useDefaultAvatar: React.PropTypes.bool,
    avatarSize:React.PropTypes.number,

    className: React.PropTypes.string,
    labelClassName: React.PropTypes.string,
    avatarClassName: React.PropTypes.string,
    style: React.PropTypes.object,
    labelStyle: React.PropTypes.object,
    avatarStyle: React.PropTypes.object
};

UserAvatar.defaultProps = {
    displayLabel: true,
    displayAvatar: true,
    avatarSize: 40,
    className: 'user-avatar-widget',
    avatarClassName:'user-avatar',
    labelClassName:'user-label'
};

UserAvatar = MaterialUI.Style.muiThemeable()(UserAvatar);

export {UserAvatar as default}