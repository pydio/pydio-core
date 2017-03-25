(function(global){


    class Loaders{

        static childrenAsPromise(item, leaf = false){

            const {childrenLoader, itemsLoader, leafLoaded, collectionsLoaded, leafs, collections} = item;
            let loader = leaf ? itemsLoader : childrenLoader;
            let loaded = leaf ? leafLoaded : collectionsLoaded;
            return new Promise((resolve, reject) => {
                if(!loaded && loader){
                    loader(item, (newChildren)=>{
                        if(leaf) {
                            item.leafs = newChildren;
                            item.leafLoaded = true;
                        }else {
                            item.collections = newChildren;
                            item.collectionsLoaded = true;
                        }
                        resolve(newChildren);
                    });
                }else{
                    const res = ( leaf ? leafs : collections ) || [];
                    resolve(res);
                }
            });

        }

        static listUsers(params, callback, parent = null){
            let baseParams = {get_action:'user_list_authorized_users',format:'json'};
            baseParams = {...baseParams, ...params};
            let cb = callback;
            if(parent){
                cb = (children) => {
                    callback(children.map(function(c){ c._parent = parent; return c; }));
                };
            }
            PydioApi.getClient().request(baseParams, function(transport){
                cb(transport.responseJSON);
            });
        }

        static loadTeams(entry, callback){
            const wrapped = (children) => {
                children.map(function(child){
                    child.icon = 'mdi mdi-account-multiple-outline';
                    child.itemsLoader = Loaders.loadTeamUsers;
                    child.actions = {
                        type    :'team',
                        create  :'Add User to Team',
                        remove  :'Remove from Team',
                        multiple: true
                    };
                });
                callback(children);
            };
            Loaders.listUsers({filter_value:8}, wrapped, entry);
        }

        static loadGroups(entry, callback){
            const wrapped = (children) => {
                children.map(function(child){
                    child.icon = 'mdi mdi-account-multiple-outline';
                    child.childrenLoader = Loaders.loadGroups;
                    child.itemsLoader = Loaders.loadGroupUsers;
                });
                callback(children);
            };
            const path = entry.id.replace('AJXP_GRP_', '');
            Loaders.listUsers({filter_value:4, group_path:path}, wrapped, entry);
        }

        static loadExternalUsers(entry, callback){
            Loaders.listUsers({filter_value:2}, callback, entry);
        }

        static loadGroupUsers(entry, callback){
            const path = entry.id.replace('AJXP_GRP_', '');
            Loaders.listUsers({filter_value:1, group_path:path}, callback, entry);
        }

        static loadTeamUsers(entry, callback){
            Loaders.listUsers({filter_value:3, group_path:entry.id}, callback, entry);
        }

    }

    const BoxListItem = React.createClass({

        propTypes:{
            nestedLevel:React.PropTypes.number,
            selected:React.PropTypes.string,
            onTouchTap: React.PropTypes.func
        },

        onTouchTap: function(){
            this.props.onTouchTap(this.props.entry);
        },

        buildNestedItems: function(data){
            return data.map(function(entry){
                return (
                    <BoxListItem
                        nestedLevel={this.props.nestedLevel+1}
                        entry={entry}
                        onTouchTap={this.props.onTouchTap}
                        selected={this.props.selected}
                    />);
            }.bind(this));
        },

        render: function(){
            const {id, label, icon} = this.props.entry;
            const children = this.props.entry.collections || [];
            const nested = this.buildNestedItems(children);
            let fontIcon;
            if(icon){
                fontIcon = <MaterialUI.FontIcon className={icon}/>;
            }
            return (
                <MaterialUI.ListItem
                    nestedLevel={this.props.nestedLevel}
                    key={id}
                    primaryText={label}
                    onTouchTap={this.onTouchTap}
                    nestedItems={nested}
                    initiallyOpen={true}
                    leftIcon={fontIcon}
                    innerDivStyle={{fontWeight:this.props.selected === this.props.entry.id ? 500 : 400}}
                />
            );
        }

    });

    class TeamCreationForm extends React.Component{

        static updateTeamUsers(team, operation, users, callback){
            console.log(team, operation, users);
            const teamId = team.id.replace('/AJXP_TEAM/', '');
            if(operation === 'add'){
                users.forEach((user) => {
                    PydioUsers.Client.addUserToTeam(teamId, user.getId ? user.getId() : user.id, callback);
                });
            }else if(operation === 'delete'){
                users.forEach((user) => {
                    PydioUsers.Client.removeUserFromTeam(teamId, user.getId ? user.getId() : user.id, callback);
                });
            }else if(operation === 'create'){
                PydioUsers.Client.saveSelectionAsTeam(teamId, users, callback);
            }
        }

        constructor(props, context){
            super(props, context);
            this.state = {value : ''};
        }

        onChange(e,value){
            this.setState({value: value});
        }

        submitCreationForm(){
            const value = this.state.value;
            TeamCreationForm.updateTeamUsers({id: value}, 'create', [], this.props.onTeamCreated);
        }

        render(){
            return (
                <div style={{padding: 20}}>
                    <div>Choose a name for your team, and you will add users to it after creation.</div>
                    <MaterialUI.TextField floatingLabelText="Team Label" value={this.state.value} onChange={this.onChange.bind(this)} fullWidth={true}/>
                    <div>
                        <div style={{textAlign:'right', paddingTop:10}}>
                            <MaterialUI.FlatButton label={"Create Team"} secondary={true} onTouchTap={this.submitCreationForm.bind(this)} />
                            <MaterialUI.FlatButton label={pydio.MessageHash[49]} onTouchTap={this.props.onCancel.bind(this)} />
                        </div>
                    </div>
                </div>
            );
        }

    }
    TeamCreationForm.propTypes = {
        onTeamCreated: React.PropTypes.func.isRequired,
        onCancel: React.PropTypes.func.isRequired
    };


    class UsersList extends React.Component{

        constructor(props, context){
            super(props, context);
            this.state = {select: false, selection:[]};
        }

        render(){
            if(this.props.loading){
                return <PydioReactUI.Loader style={{flex:1}}/>;
            }
            const {item} = this.props;
            const folders = item.collections || [];
            const leafs = item.leafs || [];
            const items = [...folders, ...leafs];
            const total = items.length;
            let elements = [];
            const toggleSelect = () => {this.setState({select:!this.state.select, selection:[]})};
            const createAction = () => {this.props.onCreateAction(item)};
            const deleteAction = () => {this.props.onDeleteAction(item, this.state.selection); this.setState({select: false, selection: []})};
            const toolbar = (
                <div style={{padding: 10, height:56, backgroundColor:'#fafafa', display:'flex', alignItems:'center'}}>
                    {item.actions && item.actions.multiple && <MaterialUI.Checkbox style={{width:'initial', marginLeft: 7}} onCheck={toggleSelect}/>}
                    <div style={{flex:1, fontSize:20}}>{item.label}</div>
                    {item.actions && item.actions.create && !this.state.select && <MaterialUI.FlatButton secondary={true} label={item.actions.create} onTouchTap={createAction}/>}
                    {item.actions && item.actions.remove && this.state.select && <MaterialUI.FlatButton secondary={true} label={item.actions.remove} disabled={!this.state.selection.length} onTouchTap={deleteAction}/>}
                </div>
            );
            if(item._parent && this.props.mode !== 'inner'){
                elements.push(
                    <MaterialUI.ListItem
                        key={'__parent__'}
                        primaryText={".."}
                        onTouchTap={() => {this.props.onFolderClicked(item._parent)}}
                        leftIcon={<MaterialUI.FontIcon className={'mdi mdi-arrow-up-bold-circle-outline'}/>}
                    />
                );
                if(total){
                    elements.push(<MaterialUI.Divider key={'parent-divider'}/>);
                }
            }
            items.forEach(function(item, index){
                let fontIcon = <MaterialUI.FontIcon className={item.icon || 'mdi mdi-account-circle'}/>
                let addGroupButton
                let touchTap = ()=>{this.props.onItemClicked(item)};
                if(folders.indexOf(item) > -1 && this.props.onFolderClicked){
                    touchTap = ()=>{ this.props.onFolderClicked(item) };
                    if(!item._notSelectable){
                        addGroupButton = (<MaterialUI.IconButton
                            iconClassName={"mdi " + (this.props.mode === 'book' ? "mdi-dots-vertical":"mdi-account-multiple-plus")}
                            tooltip={this.props.mode === 'book' ? "Open group / team" : "Add this group / team"}
                            tooltipPosition="bottom-left"
                            onTouchTap={()=>{this.props.onItemClicked(item)}}
                        />);
                    }
                }
                const select = (e, checked) => {
                    if(checked) {
                        this.setState({selection: [...this.state.selection, item]});
                    }else {
                        const stateSel = this.state.selection;
                        const selection = [...stateSel.slice(0, stateSel.indexOf(item)), ...stateSel.slice(stateSel.indexOf(item)+1)];
                        this.setState({selection: selection});
                    }
                };
                elements.push(<MaterialUI.ListItem
                    key={item.id}
                    primaryText={item.label}
                    onTouchTap={touchTap}
                    leftIcon={!this.state.select && fontIcon}
                    rightIconButton={addGroupButton}
                    leftCheckbox={this.state.select && <MaterialUI.Checkbox checked={this.state.selection.indexOf(item) > -1} onCheck={select}/>}
                />);
                if(index < total - 1){
                    elements.push(<MaterialUI.Divider key={item.id + '-divider'}/>);
                }
            }.bind(this));
            return (
                <div style={{flex:1, flexDirection:'column', display:'flex'}}>
                    {this.props.mode === 'book' && toolbar}
                    <MaterialUI.List style={{flex:1, overflowY:'auto'}}>
                        {elements}
                    </MaterialUI.List>
                </div>
            );
        }

    }

    UsersList.propTypes ={
        item: React.PropTypes.object,
        onCreateAction:React.PropTypes.func,
        onDeleteAction:React.PropTypes.func,
        onItemClicked:React.PropTypes.func,
        onFolderClicked:React.PropTypes.func,
        mode:React.PropTypes.oneOf(['book', 'selector', 'inner'])
    };


    const SearchForm = React.createClass({

        propTypes: {
            params: React.PropTypes.object,
            searchLabel: React.PropTypes.string,
            onItemClicked:React.PropTypes.func
        },

        getInitialState: function(){
            return {value: '', items: []};
        },

        search: function(){
            if(!this.state.value){
                this.setState({items: []});
                return;
            }
            let params = {value: this.state.value, existing_only:'true'};
            if(this.props.params){
                params = {...params, ...this.props.params};
            }
            Loaders.listUsers(params, (children) => {this.setState({items:children})});
        },

        onChange: function(event, value){
            this.setState({value: value});
            FuncUtils.bufferCallback('search_users_list', 300, this.search );
        },

        render: function(){

            return (
                <div style={{flex: 1, display:'flex', flexDirection:'column'}}>
                    <div>
                        <MaterialUI.Paper zDepth={1} style={{padding: 10, margin: 10, paddingTop: 0}}>
                            <MaterialUI.TextField
                                fullWidth={true}
                                value={this.state.value}
                                onChange={this.onChange}
                                floatingLabelText={this.props.searchLabel}
                            />
                        </MaterialUI.Paper>
                    </div>
                    <UsersList onItemClicked={this.props.onItemClicked} item={{leafs: this.state.items}}/>
                </div>
            );

        }

    });

    class TeamCard extends React.Component{

        constructor(props, context){
            super(props, context);
            this.state = {label: this.props.item.label};
        }

        loadMembers(item){
            this.setState({loading: true});
            Loaders.childrenAsPromise(item, false).then((children) => {
                Loaders.childrenAsPromise(item, true).then((children) => {
                    this.setState({members:item.leafs, loading: false});
                });
            });
        }
        componentWillMount(){
            this.loadMembers(this.props.item);
        }
        componentWillReceiveProps(nextProps){
            this.loadMembers(nextProps.item);
            this.setState({label: nextProps.item.label});
        }
        onLabelChange(e, value){
            this.setState({label: value});
        }
        updateLabel(){
            PydioUsers.Client.updateTeamLabel(this.props.item.id.replace('/AJXP_TEAM/', ''), this.state.label, () => {
                this.props.onUpdateAction(this.props.item);
            });
        }
        render(){
            const {item} = this.props;
            return (
                <div>
                    <MaterialUI.TextField style={{margin:'0 10px'}} fullWidth={true} disabled={false} underlineShow={false} floatingLabelText="Label" value={this.state.label} onChange={this.onLabelChange.bind(this)}/>
                    <MaterialUI.Divider/>
                    <MaterialUI.TextField style={{margin:'0 10px'}} fullWidth={true} disabled={true} underlineShow={false} floatingLabelText="Id" value={item.id.replace('/AJXP_TEAM/', '')}/>
                    <MaterialUI.Divider/>
                    <div style={{margin:'16px 10px 0', transform: 'scale(0.75)', transformOrigin: 'left', color: 'rgba(0,0,0,0.33)'}}>Team Members</div>
                    <div style={{margin:10}}>{item.leafs? "Currently " + (item.leafs.length) + " members. Open the team in the main panel to add or remove users." : ""}</div>
                    <MaterialUI.Divider/>
                    {this.props.onDeleteAction &&
                        <div style={{margin:10, textAlign:'right'}}>
                            <MaterialUI.FlatButton secondary={false} label="Remove Team" onTouchTap={() => {this.props.onDeleteAction(item) }}/>
                            {
                                this.props.item.label !== this.state.label &&
                                <MaterialUI.FlatButton secondary={true} label="Update" onTouchTap={() => {this.updateLabel()}}/>
                            }
                        </div>
                    }
                </div>
            )
        }

    }

    class UserCard extends React.Component{

        render(){
            return (
                <div>
                    <PydioComponents.UserAvatar
                        userId={this.props.item.id}
                        displayAvatar={true}
                        avatarSize={'100%'}
                        avatarStyle={{borderRadius: 0}}
                        pydio={this.props.pydio}/>
                </div>
            );
        }

    }

    class RightPanelCard extends React.Component{

        render(){

            let content;

            let onDeleteAction;
            if(this.props.onDeleteAction){
                onDeleteAction = (item) => {
                    this.props.onDeleteAction(item._parent, [item]);
                };
            }
            const props = {...this.props, onDeleteAction};
            if(this.props.item.type === 'user'){
                content = <UserCard {...props}/>
            }else if(this.props.item.type === 'group' && this.props.item.id.indexOf('/AJXP_TEAM/') === 0){
                content = <TeamCard {...props}/>
            }

            return (
                <MaterialUI.Paper zDepth={2} style={{position:'relative', ...this.props.style}}>
                    <MaterialUI.IconButton style={{position:'absolute', right: 8, top: 0}} iconClassName="mdi mdi-close" onTouchTap={this.props.onRequestClose}/>
                    {content}
                </MaterialUI.Paper>
            );
        }

    }

    RightPanelCard.propTypes = UserCard.propTypes = TeamCard.propTypes = {
        pydio: React.PropTypes.instanceOf(Pydio),
        item: React.PropTypes.object,
        style: React.PropTypes.object,
        onRequestClose: React.PropTypes.func,
        onDeleteAction: React.PropTypes.func,
        onUpdateAction: React.PropTypes.func
    };

    const Panel = React.createClass({

        propTypes: {
            mode            : React.PropTypes.oneOf(['book', 'selector']).isRequired,
            onItemSelected  : React.PropTypes.func,
            usersOnly       : React.PropTypes.bool,
            disableSearch   : React.PropTypes.bool
        },

        getDefaultProps: function(){
            return {
                mode: 'book',
                usersOnly: false,
                disableSearch: false
            };
        },

        getInitialState: function(){
            let root = {
                id:'root',
                label:'',
                type:'root',
                collections: []
            };
            root.collections.push({
                id:'ext',
                label:'Your Users',
                icon:'mdi mdi-account-network',
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

            if(!this.props.usersOnly) {
                root.collections.push({
                    id: 'teams',
                    label: 'Your Teams',
                    icon: 'mdi mdi-account-multiple',
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
                icon:'mdi mdi-account-box',
                childrenLoader:Loaders.loadGroups,
                itemsLoader: Loaders.loadGroupUsers,
                _parent:root,
                _notSelectable:true
            });

            const ocsRemotes = this.props.pydio.getPluginConfigs('core.ocs').get('TRUSTED_SERVERS');
            if(ocsRemotes && !this.props.usersOnly){
                let remotes = JSON.parse(ocsRemotes);
                let remotesNodes = {
                    id:'remotes',
                    label:'Remote Servers',
                    icon:'mdi mdi-server',
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
                        parent:remotesNodes,
                        _notSelectable:true
                    });
                }
                if(remotesNodes.collections.length){
                    root.collections.push(remotesNodes);
                }
            }

            if(!this.props.disableSearch){
                root.collections.push({
                    id:'search',
                    label:'Search Local Users',
                    icon:'mdi mdi-account-search',
                    type:'search',
                    _parent:root
                });
            }

            return {
                root: root,
                selectedItem:this.props.mode === 'selector' ? root : root.collections[0],
                loading: false,
                rightPaneItem: null
            };
        },

        componentDidMount: function(){
            this.onFolderClicked(this.state.selectedItem);
        },

        onFolderClicked: function(item, callback = undefined){
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
            const {selectedItem, root, rightPaneItem, createDialogItem} = this.state;

            const leftColumnStyle = {width:'25%', minWidth: 256, maxWidth:400, overflowY:'auto', backgroundColor:'#ECEFF1'};
            let centerComponent, rightPanel, leftPanel;

            if(selectedItem.id === 'search'){

                centerComponent = (
                    <SearchForm
                        searchLabel={"Search local users by identifier"}
                        onItemClicked={this.onUserListItemClicked}
                        mode={this.props.mode}
                    />);

            }else if(selectedItem.type === 'remote'){

                centerComponent = (
                    <SearchForm
                        params={{trusted_server_id:selectedItem.id}}
                        searchLabel={"Search Remote Server '" + selectedItem.label + "'"}
                        onItemClicked={this.onUserListItemClicked}
                        mode={this.props.mode}
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
                        mode={this.props.mode}
                    />);

            }
            if(rightPaneItem){
                rightPanel = (
                    <RightPanelCard
                        pydio={this.props.pydio}
                        onRequestClose={() => {this.setState({rightPaneItem:null})}}
                        style={{...leftColumnStyle, backgroundColor: 'white', margin: 10}}
                        onDeleteAction={this.onDeleteAction}
                        onUpdateAction={this.onCardUpdateAction}
                        item={rightPaneItem}/>
                );
            }
            if(this.props.mode === 'book'){
                leftPanel = (
                    <MaterialUI.List style={leftColumnStyle}>
                        {root.collections.map(function(e){
                            return (
                                <BoxListItem
                                    selected={selectedItem.id}
                                    nestedLevel={0}
                                    entry={e}
                                    onTouchTap={this.onFolderClicked}
                                />
                            );
                        }.bind(this))}
                    </MaterialUI.List>
                );
            }

            let dialogTitle, dialogContent;
            if(createDialogItem){
                if(createDialogItem.actions.type === 'users'){
                    dialogTitle = 'Create New User';
                    dialogContent = <PydioComponents.UserCreationForm
                        zDepth={0}
                        style={{height:500}}
                        newUserName={""}
                        onUserCreated={this.closeCreateDialogAndReload}
                        onCancel={() => {this.setState({createDialogItem:null})}}
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
                    dialogContent = <Panel
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
                <div style={{display:'flex', height: 450, ...style}}>
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

    const ModalAddressBook = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogSize: 'xl',
                dialogPadding: false,
                dialogIsModal: false,
                dialogScrollBody: false
            };
        },

        submit: function(){
            this.dismiss();
        },

        render: function(){

            return (
                <div style={{width:'100%'}}>
                    <MaterialUI.AppBar
                        title={this.props.pydio.MessageHash['user_dash.1']}
                        showMenuIconButton={false}
                        iconClassNameRight="mdi mdi-close"
                        onRightIconButtonTouchTap={()=>{this.dismiss()}}
                    />
                    <Panel mode="book" {...this.props} style={{width:'100%', height: 600}}/>
                </div>
            );

        }

    });


    global.AddressBook = {
        Panel: Panel,
        Modal: ModalAddressBook
    };


})(window);