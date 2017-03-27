import GraphPanel from './GraphPanel'
import ActionsPanel from './ActionsPanel'

class UserAvatar extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {
            user : null,
            avatar: null,
            graph : null
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
            this.cache.registerMetaStream('user_public_data', 'EXPIRATION_MANUAL_TRIGGER');
            this.cache.registerMetaStream('user_public_data-rich', 'EXPIRATION_MANUAL_TRIGGER');
            this.loadPublicData(this.props.userId);
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
            this.cache.registerMetaStream('user_public_data', 'EXPIRATION_MANUAL_TRIGGER');
            this.cache.registerMetaStream('user_public_data-rich', 'EXPIRATION_MANUAL_TRIGGER');
            this.loadPublicData(nextProps.userId);
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

    loadPublicData(userId) {

        const namespace = this.props.richCard ? 'user_public_data-rich' :'user_public_data';
        if(this.cache.hasKey(namespace, userId)){
            this.setState(this.cache.getByKey(namespace, userId));
            return;
        }
        PydioApi.getClient().request({
            get_action:'user_public_data',
            user_id:userId,
            graph: this.props.richCard ? 'true' : 'false'
        }, function(transport){
            const data = transport.responseJSON;
            const {user, graph, error} = data;
            if(error) return;

            let avatarUrl;
            const avatarId = user.avatar || null;
            const label = user.label || userId;
            if (!user.avatar) {
                this.loadFromExternalProvider();
            }else{
                avatarUrl = PydioApi.getClient().buildUserAvatarUrl(userId, avatarId);
            }
            this.cache.setKey(namespace, userId, {
                user: user,
                graph:graph,
                avatar:avatarUrl
            });
            this.setState({
                user: user,
                graph:graph,
                avatar:avatarUrl
            });
        }.bind(this));

    }

    loadFromExternalProvider(){
        if(!this.props.pydio.getPluginConfigs("ajxp_plugin[@id='action.avatar']").get("AVATAR_PROVIDER")) {
            return;
        }
        const namespace = this.props.richCard ? 'user_public_data-rich' :'user_public_data';
        PydioApi.getClient().request({
            get_action: 'get_avatar_url',
            userid: this.props.userId
        }, function (transport) {
//            this.cache.setKey(namespace, this.props.userId, {label: this.state.label, avatar:transport.responseText, graph:this.state.graph});
            this.setState({avatar: transport.responseText});
        }.bind(this));
    }

    render(){

        const {user, avatar, graph} = this.state;
        let label = this.props.userLabel || this.props.userId;
        let userType;
        if(user) {
            label = user.label;
            userType = user.external ? 'External User' : 'Internal User';
        }

        let {style, labelStyle, avatarStyle, avatarSize, className, avatarClassName,
            labelClassName, displayLabel, displayAvatar, useDefaultAvatar, richCard, cardSize} = this.props;
        let avatarContent, avatarColor, avatarIcon;
        if(richCard){
            displayAvatar = useDefaultAvatar = displayLabel = true;
        }
        console.log(this.props.icon);
        if(displayAvatar && !avatar && label && (!displayLabel || useDefaultAvatar) ){
            if(richCard){
                avatarIcon  = <MaterialUI.FontIcon className="mdi mdi-account" style={{color:this.props.muiTheme.palette.primary1Color}} />;
                avatarColor = '#ECEFF1';
            }else{
                avatarColor     = this.props.muiTheme.palette.primary1Color;
                if(this.props.icon){
                    avatarIcon = <MaterialUI.FontIcon className={this.props.icon}/>;
                }else{
                    avatarContent   = label.toUpperCase().substring(0,2);
                }
            }
        }
        let reloadAction, onEditAction, onMouseOver, onMouseOut, popover;
        if(richCard){

            displayAvatar = true;
            style = {...style, flexDirection:'column'};
            avatarSize = cardSize ? cardSize : '100%';
            avatarStyle = {borderRadius: 0};
            const localReload = () => {
                MetaCacheService.getInstance().deleteKey('user_public_data-rich', this.props.userId);
                this.loadPublicData(this.props.userId);
            }
            reloadAction = () => {
                localReload();
                if(this.props.reloadAction) this.props.reloadAction();
            }
            onEditAction = () => {
                localReload();
                if(this.props.onEditAction) this.props.onEditAction();
            }
        }else if(this.props.richOnHover){

            onMouseOver = (e) => {
                this.setState({showPopover: true, popoverAnchor: e.currentTarget});
            };
            onMouseOut = () => {
                this.setState({showPopover: false});
            };

            popover = (
                <MaterialUI.Popover
                    open={this.state.showPopover}
                    anchorEl={this.state.popoverAnchor}
                    onRequestClose={() => {this.setState({showPopover: false})}}
                    anchorOrigin={{horizontal:"left",vertical:"center"}}
                    targetOrigin={{horizontal:"right",vertical:"center"}}
                >
                    <MaterialUI.Paper zDepth={2} style={{width: 220, height: 320, overflowY: 'auto'}}>
                        <UserAvatar {...this.props} richCard={true} richOnHover={false} cardSize={220}/>
                    </MaterialUI.Paper>
                </MaterialUI.Popover>
            );

        }

        const avatarComponent = (
            <MaterialUI.Avatar
                src={avatar}
                icon={avatarIcon}
                size={avatarSize}
                style={this.props.avatarOnly ? this.props.style : avatarStyle}
                backgroundColor={avatarColor}
            >{avatarContent}</MaterialUI.Avatar>
        );

        if(this.props.avatarOnly){
            return avatarComponent;
        }

        return (
            <div className={className} style={style} onMouseOver={onMouseOver}>
                {displayAvatar && (avatar || avatarContent || avatarIcon) && avatarComponent}
                {displayLabel && !richCard && <div
                    className={labelClassName}
                    style={labelStyle}>{label}</div>}
                {displayLabel && richCard && <MaterialUI.CardTitle title={label} subtitle={userType}/>}
                {richCard && user && <ActionsPanel {...this.state} {...this.props} reloadAction={reloadAction} onEditAction={onEditAction}/>}
                {graph && <GraphPanel graph={graph} {...this.props} reloadAction={reloadAction} onEditAction={onEditAction}/>}
                {this.props.children}
                {popover}
            </div>
        );

    }

}

UserAvatar.propTypes = {
    userId: React.PropTypes.string.isRequired,
    pydio : React.PropTypes.instanceOf(Pydio),
    userLabel:React.PropTypes.string,
    icon:React.PropTypes.string,
    richCard: React.PropTypes.bool,

    // Wll add an action panel to the card
    userEditable: React.PropTypes.bool,
    onEditAction: React.PropTypes.func,
    onDeleteAction: React.PropTypes.func,
    reloadAction: React.PropTypes.func,

    displayLabel: React.PropTypes.bool,
    displayAvatar: React.PropTypes.bool,
    avatarOnly: React.PropTypes.bool,
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