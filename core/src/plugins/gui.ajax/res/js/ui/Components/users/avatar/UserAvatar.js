import GraphPanel from './GraphPanel'
import ActionsPanel from './ActionsPanel'
const debounce = require('lodash.debounce')
const React = require('react')
const Color = require('color')
const {FontIcon, Popover, Paper, Avatar, CardTitle} = require('material-ui')
const {muiThemeable} = require('material-ui/styles')
const MetaCacheService = require('pydio/http/meta-cache-service')
const PydioApi = require('pydio/http/api')

/**
 * Generic component for display a user and her avatar (first letters or photo)
 */
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
            if(!data || data.error){
                this.cache.setKey(namespace, userId, {});
                return;
            }
            const {user, graph} = data;

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
            this.setState({avatar: transport.responseText});
        }.bind(this));
    }

    render(){

        const {user, avatar, graph} = this.state;
        let {pydio, userId, style, labelStyle, avatarStyle, avatarSize, className, avatarClassName,
            labelClassName, displayLabel, displayAvatar, useDefaultAvatar, richCard, cardSize} = this.props;

        let {label} = this.state;
        let userType;
        if(user) {
            label = user.label;
            userType = pydio.MessageHash[user.external ? '589' : '590'];
        }
        if(!label){
            label = this.props.userLabel || this.props.userId;
        }

        let avatarContent, avatarColor, avatarIcon;
        if(richCard){
            displayAvatar = useDefaultAvatar = displayLabel = true;
        }
        if(displayAvatar && !avatar && label && (!displayLabel || useDefaultAvatar) ){
            let avatarsColor = this.props.muiTheme.palette.avatarsColor;
            if(this.props.userType === 'group' || userId.indexOf('AJXP_GRP_/') === 0 || userId.indexOf('/AJXP_TEAM/') === 0){
                avatarsColor = Color(avatarsColor).darken(0.2).toString();
            }
            if(richCard){
                avatarIcon  = <FontIcon className="mdi mdi-account" style={{color:avatarsColor}} />;
                avatarColor = '#f5f5f5';
            }else{
                avatarColor     = avatarsColor;
                if(this.props.icon){
                    avatarIcon = <FontIcon className={this.props.icon}/>;
                }else{
                    avatarContent = label.split(' ').map((word)=>word[0]).join('').substring(0,2);
                    if(avatarContent.length < 2) avatarContent =  label.substring(0,2);
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

            onMouseOut = () => {
                this.setState({showPopover: false});
            };
            onMouseOut = debounce(onMouseOut, 350);
            onMouseOver = (e) => {
                this.setState({showPopover: true, popoverAnchor: e.currentTarget});
                onMouseOut.cancel();
            };
            const onMouseOverInner = (e) =>{
                this.setState({showPopover: true});
                onMouseOut.cancel();
            }

            popover = (
                <Popover
                    open={this.state.showPopover}
                    anchorEl={this.state.popoverAnchor}
                    onRequestClose={() => {this.setState({showPopover: false})}}
                    anchorOrigin={{horizontal:"left",vertical:"center"}}
                    targetOrigin={{horizontal:"right",vertical:"center"}}
                    useLayerForClickAway={false}
                >
                    <Paper zDepth={2} style={{width: 220, height: 320, overflowY: 'auto'}} onMouseOver={onMouseOverInner}  onMouseOut={onMouseOut}>
                        <UserAvatar {...this.props} richCard={true} richOnHover={false} cardSize={220}/>
                    </Paper>
                </Popover>
            );

        }

        const avatarComponent = (
            <Avatar
                src={avatar}
                icon={avatarIcon}
                size={avatarSize}
                style={this.props.avatarOnly ? this.props.style : avatarStyle}
                backgroundColor={avatarColor}
            >{avatarContent}</Avatar>
        );

        if(this.props.avatarOnly){
            return avatarComponent;
        }

        return (
            <div className={className} style={style} onMouseOver={onMouseOver} onMouseOut={onMouseOut}>
                {displayAvatar && (avatar || avatarContent || avatarIcon) && avatarComponent}
                {displayLabel && !richCard && <div
                    className={labelClassName}
                    style={labelStyle}>{label}</div>}
                {displayLabel && richCard && <CardTitle title={label} subtitle={userType}/>}
                {richCard && user && <ActionsPanel {...this.state} {...this.props} reloadAction={reloadAction} onEditAction={onEditAction}/>}
                {graph && <GraphPanel graph={graph} {...this.props} userLabel={label} reloadAction={reloadAction} onEditAction={onEditAction}/>}
                {this.props.children}
                {popover}
            </div>
        );

    }

}

UserAvatar.propTypes = {
    /**
     * Id of the user to be loaded
     */
    userId: React.PropTypes.string.isRequired,
    /**
     * Pydio instance
     */
    pydio : React.PropTypes.instanceOf(Pydio),
    /**
     * Label of the user, if we already have it (otherwise will be loaded)
     */
    userLabel:React.PropTypes.string,
    /**
     * Icon to be displayed in avatar
     */
    icon:React.PropTypes.string,
    /**
     * Display a rich card or a simple avatar+label chip
     */
    richCard: React.PropTypes.bool,
    /**
     * If not rich, display a rich card as popover on mouseover
     */
    richOnHover: React.PropTypes.bool,

    /**
     * Add edit action to the card
     */
    userEditable: React.PropTypes.bool,
    /**
     * Triggered after successful edition
     */
    onEditAction: React.PropTypes.func,
    /**
     * Triggered after deletion
     */
    onDeleteAction: React.PropTypes.func,
    /**
     * Triggered if a reload is required
     */
    reloadAction: React.PropTypes.func,

    /**
     * Display label element or not
     */
    displayLabel: React.PropTypes.bool,
    /**
     * Display avatar element or not
     */
    displayAvatar: React.PropTypes.bool,
    /**
     * Display only avatar
     */
    avatarOnly: React.PropTypes.bool,
    /**
     * Use default avatar
     */
    useDefaultAvatar: React.PropTypes.bool,
    /**
     * Avatar size, 40px by default
     */
    avatarSize:React.PropTypes.number,

    /**
     * Add class name to root element
     */
    className: React.PropTypes.string,
    /**
     * Add class name to label element
     */
    labelClassName: React.PropTypes.string,
    /**
     * Add class name to avatar element
     */
    avatarClassName: React.PropTypes.string,
    /**
     * Add style to root element
     */
    style: React.PropTypes.object,
    /**
     * Add style to label element
     */
    labelStyle: React.PropTypes.object,
    /**
     * Add style to avatar element
     */
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

UserAvatar = muiThemeable()(UserAvatar);

export {UserAvatar as default}