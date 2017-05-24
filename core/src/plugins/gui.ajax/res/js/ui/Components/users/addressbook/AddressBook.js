/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */


import NestedListItem from './NestedListItem'
import UsersList from './UsersList'

import RightPanelCard from './RightPanelCard'
import SearchPanel from './SearchPanel'

import Loaders from './Loaders'

import TeamCreationForm from '../TeamCreationForm'

const React = require('react');
const Pydio = require('pydio');
const {AsyncComponent, PydioContextConsumer} = Pydio.requireLib('boot')
const {Popover, IconButton} = require('material-ui')
const {muiThemeable, colors} = require('material-ui/styles')

/**
 * High level component to browse users, groups and teams, either in a large format (mode='book') or a more compact
 * format (mode='selector'|'popover').
 * Address book allows to create external users, teams, and also to browse trusted server directories if Federated Sharing
 * is active.
 */
let AddressBook = React.createClass({

    propTypes: {
        /**
         * Main instance of pydio
         */
        pydio           : React.PropTypes.instanceOf(Pydio),
        /**
         * Display mode, either large (book) or small picker ('selector', 'popover').
         */
        mode            : React.PropTypes.oneOf(['book', 'selector', 'popover']).isRequired,
        /**
         * Callback triggered in 'selector' mode whenever an item is clicked.
         */
        onItemSelected  : React.PropTypes.func,
        /**
         * Display users only, no teams or groups
         */
        usersOnly       : React.PropTypes.bool,
        /**
         * Choose various user sources, either the local directory or remote ( = trusted ) servers.
         */
        usersFrom       : React.PropTypes.oneOf(['local', 'remote', 'any']),
        /**
         * Disable the search engine
         */
        disableSearch   : React.PropTypes.bool,
        /**
         * Theme object passed by muiThemeable() wrapper
         */
        muiTheme                    : React.PropTypes.object,
        /**
         * Will be passed to the Popover object
         */
        popoverStyle                : React.PropTypes.object,
        /**
         * Used as a button to open the selector in a popover
         */
        popoverButton               : React.PropTypes.object,
        /**
         * Will be passed to the Popover container object
         */
        popoverContainerStyle       : React.PropTypes.object,
        /**
         * Will be passed to the Popover Icon Button.
         */
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
        const getMessage = (id) => {return this.props.getMessage(id, '')};
        const confConfigs = pydio.getPluginConfigs('core.conf');

        let root;
        if(teamsOnly){
            root = {
                id: 'teams',
                label: getMessage(568),
                childrenLoader: Loaders.loadTeams,
                _parent: null,
                _notSelectable: true,
                actions: {
                    type: 'teams',
                    create: '+ ' + getMessage(569),
                    remove: getMessage(570),
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
            label:getMessage(592),
            type:'root',
            collections: []
        };
        if(usersFrom !== 'remote'){
            if(confConfigs.get('USER_CREATE_USERS')){
                root.collections.push({
                    id:'ext',
                    label:getMessage(593),
                    //icon:'mdi mdi-account-network',
                    itemsLoader: Loaders.loadExternalUsers,
                    _parent:root,
                    _notSelectable:true,
                    actions:{
                        type    : 'users',
                        create  : '+ ' + getMessage(484),
                        remove  : getMessage(582),
                        multiple: true
                    }
                });
            }
            if(!usersOnly) {
                root.collections.push({
                    id: 'teams',
                    label: getMessage(568),
                    //icon: 'mdi mdi-account-multiple',
                    childrenLoader: Loaders.loadTeams,
                    _parent: root,
                    _notSelectable: true,
                    actions: {
                        type: 'teams',
                        create: '+ ' + getMessage(569),
                        remove: getMessage(570),
                        multiple: true
                    }
                });
            }
            if(confConfigs.get('ALLOW_CROSSUSERS_SHARING')){
                let groupOrUsers = confConfigs.get('ADDRESSBOOK_GROUP_OR_USERS');
                if(groupOrUsers && groupOrUsers.group_switch_value) groupOrUsers = groupOrUsers.group_switch_value;
                else groupOrUsers = 'both';

                if(groupOrUsers === 'search'){
                    if(!disableSearch){
                        root.collections.push({
                            id:'search',
                            label:getMessage(583),
                            //icon:'mdi mdi-account-search',
                            type:'search',
                            _parent:root,
                            _notSelectable: true
                        });
                    }
                }else{
                    root.collections.push({
                        id:'AJXP_GRP_/',
                        label:getMessage(584),
                        //icon:'mdi mdi-account-box',
                        childrenLoader: (groupOrUsers === 'both' || groupOrUsers === 'groups' ) ? Loaders.loadGroups : null,
                        itemsLoader:  (groupOrUsers === 'both' || groupOrUsers === 'users' ) ? Loaders.loadGroupUsers : null,
                        _parent:root,
                        _notSelectable:true
                    });
                }
            }
        }

        const ocsRemotes = pydio.getPluginConfigs('core.ocs').get('TRUSTED_SERVERS');
        if(ocsRemotes && !usersOnly && usersFrom !== 'local'){
            let remotes = JSON.parse(ocsRemotes);
            let remotesNodes = {
                id:'remotes',
                label:getMessage(594),
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

        return {
            root: root,
            selectedItem:mode === 'selector' ? root : root.collections[0],
            loading: false,
            rightPaneItem: null
        };
    },

    componentDidMount: function(){
        this.state.selectedItem && this.onFolderClicked(this.state.selectedItem);
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
        if(!confirm(this.props.getMessage(278))){
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

    reloadCurrentAtPage: function(letterOrRange){
        this.state.selectedItem.leafLoaded = false;
        this.state.selectedItem.collectionsLoaded = false;
        if(letterOrRange === -1) {
            this.state.selectedItem.currentParams = null;
        }else if(letterOrRange.indexOf('-') !== -1){
            this.state.selectedItem.range = letterOrRange;
        }else{
            this.state.selectedItem.range = null;
            this.state.selectedItem.currentParams = {alpha_pages:'true', value:letterOrRange};
        }
        this.onFolderClicked(this.state.selectedItem);
    },

    reloadCurrentWithSearch: function(value){
        if(!value){
            this.reloadCurrentAtPage(-1);
            return;
        }
        this.state.selectedItem.leafLoaded = false;
        this.state.selectedItem.collectionsLoaded = false;
        this.state.selectedItem.currentParams = {has_search: true, value:value, existing_only:true};
        this.onFolderClicked(this.state.selectedItem);
    },

    render: function(){

        const {mode, muiTheme, getMessage} = this.props;

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
                        zDepth={2}
                    >
                        <div style={{width: 320, height: 420, ...popoverContainerStyle}}>
                            <AddressBook {...this.props} mode="selector" />
                        </div>
                    </Popover>
                </span>

            );

        }

        const {selectedItem, root, rightPaneItem, createDialogItem} = this.state;

        const leftColumnStyle = {
            backgroundColor: colors.grey100,
            width: 256,
            overflowY:'auto',
            overflowX: 'hidden'
        };
        let centerComponent, rightPanel, leftPanel;

        if(selectedItem.id === 'search'){

            centerComponent = (
                <SearchPanel
                    item={selectedItem}
                    title={getMessage(583, '')}
                    searchLabel={getMessage(595, '')}
                    onItemClicked={this.onUserListItemClicked}
                    onFolderClicked={this.onFolderClicked}
                    mode={mode}
                />);

        }else if(selectedItem.type === 'remote'){

            centerComponent = (
                <SearchPanel
                    item={selectedItem}
                    params={{trusted_server_id:selectedItem.id}}
                    searchLabel={getMessage(595, '')}
                    title={getMessage(596, '').replace('%s', selectedItem.label)}
                    onItemClicked={this.onUserListItemClicked}
                    onFolderClicked={this.onFolderClicked}
                    mode={mode}
                />);

        }else{

            let emptyStatePrimary;
            let emptyStateSecondary;
            let otherProps = {};
            if(selectedItem.id === 'teams'){
                emptyStatePrimary = getMessage(571, '');
                emptyStateSecondary = getMessage(572, '');
            }else if(selectedItem.id === 'ext'){
                emptyStatePrimary = getMessage(585, '');
                emptyStateSecondary = getMessage(586, '');
            }else if(selectedItem.id.indexOf('AJXP_GRP_/') === 0){
                otherProps = {
                    showSubheaders: true,
                    paginatorType: !(selectedItem.currentParams && selectedItem.currentParams.has_search) && 'alpha',
                    paginatorCallback: this.reloadCurrentAtPage.bind(this),
                    enableSearch: !this.props.disableSearch,
                    searchLabel: getMessage(595, ''),
                    onSearch: this.reloadCurrentWithSearch.bind(this),
                };
            }


            centerComponent = (
                <UsersList
                    item={selectedItem}
                    onItemClicked={this.onUserListItemClicked}
                    onFolderClicked={this.onFolderClicked}
                    onCreateAction={this.onCreateAction}
                    onDeleteAction={this.onDeleteAction}
                    loading={this.state.loading}
                    mode={mode}
                    emptyStatePrimaryText={emptyStatePrimary}
                    emptyStateSecondaryText={emptyStateSecondary}
                    onTouchTap={this.state.rightPaneItem ? () => { this.setState({rightPaneItem:null}) } : null}
                    {...otherProps}
                />);

        }
        let rightPanelStyle = {...leftColumnStyle, transformOrigin:'right', backgroundColor: 'white'};
        if(!rightPaneItem){
            rightPanelStyle = {...rightPanelStyle, transform: 'translateX(256px)', width: 0};
        }
        rightPanel = (
            <RightPanelCard
                pydio={this.props.pydio}
                onRequestClose={() => {this.setState({rightPaneItem:null})}}
                style={rightPanelStyle}
                onCreateAction={this.onCreateAction}
                onDeleteAction={this.onDeleteAction}
                onUpdateAction={this.onCardUpdateAction}
                item={rightPaneItem}/>
        );
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
                dialogTitle = getMessage(484, '');
                dialogContent = <div style={{height:500}}><AsyncComponent
                    namespace="PydioForm"
                    componentName="UserCreationForm"
                    zDepth={0}
                    style={{height:500}}
                    newUserName={""}
                    onUserCreated={this.closeCreateDialogAndReload}
                    onCancel={() => {this.setState({createDialogItem:null})}}
                    pydio={this.props.pydio}
                /></div>;
            }else if(createDialogItem.actions.type === 'teams'){
                dialogTitle = getMessage(569, '');
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
                    disableSearch={true}
                    onItemSelected={selectUser}
                />;
            }
        }

        let style = this.props.style || {}
        return (
            <div style={{display:'flex', height: mode === 'selector' ? 420 : 450 , ...style}}>
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

AddressBook = PydioContextConsumer(AddressBook);
AddressBook = muiThemeable()(AddressBook);
export {AddressBook as default}
