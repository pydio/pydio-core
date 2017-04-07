import NestedListItem from './NestedListItem'
import UsersList from './UsersList'

import RightPanelCard from './RightPanelCard'
import SearchForm from './SearchForm'

import Loaders from './Loaders'

import TeamCreationForm from '../TeamCreationForm'

const React = require('react');
const Pydio = require('pydio');
const {AsyncComponent} = Pydio.requireLib('boot')
const {Popover, IconButton} = require('material-ui')
const {muiThemeable} = require('material-ui/styles')
const Color = require('color')

let AddressBook = React.createClass({

    propTypes: {
        pydio           : React.PropTypes.instanceOf(Pydio),
        mode            : React.PropTypes.oneOf(['book', 'selector', 'popover']).isRequired,
        onItemSelected  : React.PropTypes.func,
        usersOnly       : React.PropTypes.bool,
        usersFrom       : React.PropTypes.oneOf(['local', 'remote', 'any']),
        disableSearch   : React.PropTypes.bool,
        muiTheme        : React.PropTypes.object,
        popoverStyle                : React.PropTypes.object,
        popoverButton               : React.PropTypes.object,
        popoverContainerStyle       : React.PropTypes.object,
        popoverIconButtonStyle      : React.PropTypes.object
    },

    getDefaultProps: function(){
        return {
            mode            : 'book',
            usersOnly       : false,
            usersFrom       : 'any',
            teamsOnly       : false,
            disableSearch   : false
        };
    },

    getInitialState: function(){

        const {pydio, mode, usersOnly, usersFrom, teamsOnly, disableSearch} = this.props;

        let root;
        if(teamsOnly){
            root = {
                id: 'teams',
                label: 'Your Teams',
                childrenLoader: Loaders.loadTeams,
                _parent: null,
                _notSelectable: true,
                actions: {
                    type: 'teams',
                    create: '+ Create Team',
                    remove: 'Delete Team',
                    multiple: true
                }
            };
            return {
                root: root,
                selectedItem:root,
                loading: false,
                rightPaneItem: null
            };
        }

        root = {
            id:'root',
            label:'Address Book',
            type:'root',
            collections: []
        };
        if(usersFrom !== 'remote'){
            root.collections.push({
                id:'ext',
                label:'Your Users',
                //icon:'mdi mdi-account-network',
                itemsLoader: Loaders.loadExternalUsers,
                _parent:root,
                _notSelectable:true,
                actions:{
                    type    : 'users',
                    create  : '+ Create User',
                    remove  : 'Delete User',
                    multiple: true
                }
            });

            if(!usersOnly) {
                root.collections.push({
                    id: 'teams',
                    label: 'Your Teams',
                    //icon: 'mdi mdi-account-multiple',
                    childrenLoader: Loaders.loadTeams,
                    _parent: root,
                    _notSelectable: true,
                    actions: {
                        type: 'teams',
                        create: '+ Create Team',
                        remove: 'Delete Team',
                        multiple: true
                    }
                });
            }
            root.collections.push({
                id:'AJXP_GRP_/',
                label:'All Users',
                //icon:'mdi mdi-account-box',
                childrenLoader:Loaders.loadGroups,
                itemsLoader: Loaders.loadGroupUsers,
                _parent:root,
                _notSelectable:true
            });
        }

        const ocsRemotes = pydio.getPluginConfigs('core.ocs').get('TRUSTED_SERVERS');
        if(ocsRemotes && !usersOnly && usersFrom !== 'local'){
            let remotes = JSON.parse(ocsRemotes);
            let remotesNodes = {
                id:'remotes',
                label:'Remote Servers',
                //icon:'mdi mdi-server',
                collections:[],
                _parent:root,
                _notSelectable:true
            };
            for(let k in remotes){
                if(!remotes.hasOwnProperty(k)) continue;
                remotesNodes.collections.push({
                    id:k,
                    label:remotes[k],
                    icon:'mdi mdi-server-network',
                    type:'remote',
                    _parent:remotesNodes,
                    _notSelectable:true
                });
            }
            if(remotesNodes.collections.length){
                root.collections.push(remotesNodes);
            }
        }

        if(!disableSearch){
            root.collections.push({
                id:'search',
                label:'Search Local Users',
                //icon:'mdi mdi-account-search',
                type:'search',
                _parent:root,
                _notSelectable: true
            });
        }

        return {
            root: root,
            selectedItem:mode === 'selector' ? root : root.collections[0],
            loading: false,
            rightPaneItem: null
        };
    },

    componentDidMount: function(){
        this.onFolderClicked(this.state.selectedItem);
    },

    onFolderClicked: function(item, callback = undefined){
        // Special case for teams
        if(item.type === 'group' && item.id.indexOf('/AJXP_TEAM/') === 0){
            this.onUserListItemClicked(item);
            return;
        }
        this.setState({loading: true});
        Loaders.childrenAsPromise(item, false).then((children) => {
            Loaders.childrenAsPromise(item, true).then((children) => {
                this.setState({selectedItem:item, loading: false}, callback);
            });
        });
    },

    onUserListItemClicked: function(item){
        if(this.props.onItemSelected){
            const uObject = new PydioUsers.User(
                item.id,
                item.label,
                item.type,
                item.group,
                item.avatar,
                item.temporary,
                item.external
            );
            if(item.trusted_server_id) {
                uObject.trustedServerId = item.trusted_server_id;
                uObject.trustedServerLabel = item.trusted_server_label;
            }
            this.props.onItemSelected(uObject);
        }else{
            this.setState({rightPaneItem:item});
        }
    },

    onCreateAction: function(item){
        this.setState({createDialogItem:item});
    },

    closeCreateDialogAndReload: function(){
        this.setState({createDialogItem:null});
        this.reloadCurrentNode();
    },

    onCardUpdateAction: function(item){
        if(item._parent && item._parent === this.state.selectedItem){
            this.reloadCurrentNode();
        }
    },

    onDeleteAction: function(parentItem, selection){
        if(!confirm('Are you sure you want to delete these items?')){
            return;
        }
        switch(parentItem.actions.type){
            case 'users':
                selection.forEach(function(user){
                    if(this.state.rightPaneItem === user) this.setState({rightPaneItem: null});
                    PydioUsers.Client.deleteUser(user.id, this.reloadCurrentNode.bind(this));
                }.bind(this));
                break;
            case 'teams':
                selection.forEach(function(team){
                    if(this.state.rightPaneItem === team) this.setState({rightPaneItem: null});
                    PydioUsers.Client.deleteTeam(team.id.replace('/AJXP_TEAM/', ''), this.reloadCurrentNode.bind(this));
                }.bind(this));
                break;
            case 'team':
                TeamCreationForm.updateTeamUsers(parentItem, 'delete', selection, this.reloadCurrentNode.bind(this));
                break;
            default:
                break;
        }
    },

    openPopover:function(event){
        this.setState({
            popoverOpen: true,
            popoverAnchor: event.currentTarget
        });
    },

    closePopover: function(){
        this.setState({popoverOpen: false});
    },

    reloadCurrentNode: function(){
        this.state.selectedItem.leafLoaded = false;
        this.state.selectedItem.collectionsLoaded = false;
        this.onFolderClicked(this.state.selectedItem, () => {
            if(this.state.rightPaneItem){
                const rPaneId = this.state.rightPaneItem.id;
                let foundItem = null;
                const leafs = this.state.selectedItem.leafs || [];
                const collections = this.state.selectedItem.collections || [];
                [...leafs, ...collections].forEach((leaf) => {
                    if(leaf.id === rPaneId) foundItem = leaf;
                });
                this.setState({rightPaneItem: foundItem});
            }
        });
    },

    render: function(){

        const {mode, muiTheme} = this.props;

        if(mode === 'popover'){

            const popoverStyle = this.props.popoverStyle || {}
            const popoverContainerStyle = this.props.popoverContainerStyle || {}
            const iconButtonStyle = this.props.popoverIconButtonStyle || {}
            let iconButton = (
                <IconButton
                    style={{position:'absolute', padding:15, zIndex:100, right:0, top: 25, display:this.state.loading?'none':'initial', ...iconButtonStyle}}
                    iconStyle={{fontSize:19, color:'rgba(0,0,0,0.6)'}}
                    iconClassName={'mdi mdi-book-open-variant'}
                    onTouchTap={this.openPopover}
                />
            );
            if(this.props.popoverButton){
                iconButton = <this.props.popoverButton.type {...this.props.popoverButton.props} onTouchTap={this.openPopover}/>
            }
            return (
                <span>
                    {iconButton}
                    <Popover
                        open={this.state.popoverOpen}
                        anchorEl={this.state.popoverAnchor}
                        anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                        targetOrigin={{horizontal: 'left', vertical: 'top'}}
                        onRequestClose={this.closePopover}
                        style={{marginLeft: 20, ...popoverStyle}}
                    >
                        <div style={{width: 256, height: 320, ...popoverContainerStyle}}>
                            <AddressBook {...this.props} mode="selector" />
                        </div>
                    </Popover>
                </span>

            );

        }

        const {selectedItem, root, rightPaneItem, createDialogItem} = this.state;

        const leftColumnStyle = {
            backgroundColor: Color(muiTheme.palette.primary1Color).lighten(1.3).toString(),
            width: 256,
            overflowY:'auto',
            overflowX: 'hidden'
        };
        let centerComponent, rightPanel, leftPanel;

        if(selectedItem.id === 'search'){

            centerComponent = (
                <SearchForm
                    item={selectedItem}
                    title={"All users"}
                    searchLabel={"Search user(s)"}
                    onItemClicked={this.onUserListItemClicked}
                    onFolderClicked={this.onFolderClicked}
                    mode={mode}
                />);

        }else if(selectedItem.type === 'remote'){

            centerComponent = (
                <SearchForm
                    item={selectedItem}
                    params={{trusted_server_id:selectedItem.id}}
                    searchLabel={"Search user(s)"}
                    title={"Server '" + selectedItem.label + "'"}
                    onItemClicked={this.onUserListItemClicked}
                    onFolderClicked={this.onFolderClicked}
                    mode={mode}
                />);

        }else{

            centerComponent = (
                <UsersList
                    item={selectedItem}
                    onItemClicked={this.onUserListItemClicked}
                    onFolderClicked={this.onFolderClicked}
                    onCreateAction={this.onCreateAction}
                    onDeleteAction={this.onDeleteAction}
                    loading={this.state.loading}
                    mode={mode}
                    onTouchTap={this.state.rightPaneItem ? () => { this.setState({rightPaneItem:null}) } : null}
                />);

        }
        if(rightPaneItem){
            rightPanel = (
                <RightPanelCard
                    pydio={this.props.pydio}
                    onRequestClose={() => {this.setState({rightPaneItem:null})}}
                    style={{...leftColumnStyle, backgroundColor: 'white'}}
                    onCreateAction={this.onCreateAction}
                    onDeleteAction={this.onDeleteAction}
                    onUpdateAction={this.onCardUpdateAction}
                    item={rightPaneItem}/>
            );
        }
        if(mode === 'book'){
            leftPanel = (
                <MaterialUI.Paper zDepth={0} style={{...leftColumnStyle, zIndex:2}}>
                    <MaterialUI.List>
                        {root.collections.map(function(e){
                            return (
                                <NestedListItem
                                    key={e.id}
                                    selected={selectedItem.id}
                                    nestedLevel={0}
                                    entry={e}
                                    onTouchTap={this.onFolderClicked}
                                />
                            );
                        }.bind(this))}
                    </MaterialUI.List>
                </MaterialUI.Paper>
            );
        }

        let dialogTitle, dialogContent;
        if(createDialogItem){
            if(createDialogItem.actions.type === 'users'){
                dialogTitle = 'Create New User';
                dialogContent = <AsyncComponent
                    namespace="PydioForm"
                    componentName="UserCreationForm"
                    zDepth={0}
                    style={{height:500}}
                    newUserName={""}
                    onUserCreated={this.closeCreateDialogAndReload}
                    onCancel={() => {this.setState({createDialogItem:null})}}
                    pydio={this.props.pydio}
                />;
            }else if(createDialogItem.actions.type === 'teams'){
                dialogTitle = 'Create New Team';
                dialogContent = <TeamCreationForm
                    onTeamCreated={this.closeCreateDialogAndReload}
                    onCancel={() => {this.setState({createDialogItem:null})}}
                />;
            }else if(createDialogItem.actions.type === 'team'){
                const selectUser = (item) => {
                    TeamCreationForm.updateTeamUsers(createDialogItem, 'add', [item], this.reloadCurrentNode.bind(this));
                };
                dialogTitle = null;
                dialogContent = <AddressBook
                    pydio={this.props.pydio}
                    mode="selector"
                    usersOnly={true}
                    disableSearch={false}
                    onItemSelected={selectUser}
                />;
            }
        }

        let style = this.props.style || {}
        return (
            <div style={{display:'flex', height: mode === 'selector' ? 320 : 450 , ...style}}>
                {leftPanel}
                {centerComponent}
                {rightPanel}
                <MaterialUI.Dialog
                    contentStyle={{width:380,minWidth:380,maxWidth:380, padding:0}}
                    bodyStyle={{padding:0}}
                    title={<div style={{padding: 20}}>{dialogTitle}</div>}
                    actions={null}
                    modal={false}
                    open={createDialogItem?true:false}
                    onRequestClose={() => {this.setState({createDialogItem:null})}}
                >
                    {dialogContent}
                </MaterialUI.Dialog>
            </div>
        );
    }

});

AddressBook = muiThemeable()(AddressBook);
export {AddressBook as default}