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

    const UsersList = React.createClass({

        propTypes:{
            item: React.PropTypes.object,
            onItemClicked:React.PropTypes.func,
            onFolderClicked:React.PropTypes.func,
            mode:React.PropTypes.oneOf(['book', 'selector'])
        },

        render: function(){
            if(this.props.loading){
                return <PydioReactUI.Loader style={{flex:1}}/>;
            }
            const {item} = this.props;
            const folders = item.collections || [];
            const leafs = item.leafs || [];
            const items = [...folders, ...leafs];
            const total = items.length;
            let elements = [];
            if(item._parent){
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
                    addGroupButton = (<MaterialUI.IconButton
                        iconClassName={"mdi " + (this.props.mode === 'book' ? "mdi-dots-vertical":"mdi-account-multiple-plus")}
                        tooltip={"Add this group / team"}
                        onTouchTap={()=>{this.props.onItemClicked(item)}}
                    />);
                }
                elements.push(<MaterialUI.ListItem
                    key={item.id}
                    primaryText={item.label}
                    onTouchTap={touchTap}
                    leftIcon={fontIcon}
                    rightIconButton={addGroupButton}
                />);
                if(index < total - 1){
                    elements.push(<MaterialUI.Divider key={item.id + '-divider'}/>);
                }
            }.bind(this));
            return (
                <MaterialUI.List style={{flex:1, overflowY:'auto'}}>
                    {elements}
                </MaterialUI.List>
            );
        }

    });

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

    const UserCard = React.createClass({

        propTypes: {
            item: React.PropTypes.object,
            style: React.PropTypes.object,
            onRequestClose: React.PropTypes.func
        },

        render: function(){
            return (
                <MaterialUI.Paper zDepth={1} style={this.props.style}>
                    <MaterialUI.IconButton iconClassName="mdi mdi-close" onTouchTap={this.props.onRequestClose}/>
                    User : {this.props.item.label}
                    (User data here)
                </MaterialUI.Paper>
            );
        }

    });

    const Panel = React.createClass({

        propTypes: {
            mode            : React.PropTypes.oneOf(['book', 'selector']).isRequired,
            onItemSelected  : React.PropTypes.func
        },

        getDefaultProps: function(){
            return {mode: 'book'};
        },

        getInitialState: function(){
            let root = {
                id:'root',
                label:'',
                type:'root',
            };
            let search = {id:'search', label:'Search Local Users', icon:'mdi mdi-account-search', type:'search', _parent:root};
            root.collections = [
                search,
                {id:'ext', label:'Your Users', icon:'mdi mdi-account-network', itemsLoader: Loaders.loadExternalUsers, _parent:root},
                {id:'teams', label:'Your Teams', icon:'mdi mdi-account-multiple', childrenLoader:Loaders.loadTeams, _parent:root},
                {id:'AJXP_GRP_/', label:'All Users', icon:'mdi mdi-account-box', childrenLoader:Loaders.loadGroups, itemsLoader: Loaders.loadGroupUsers, _parent:root}
            ]

            const ocsRemotes = this.props.pydio.getPluginConfigs('core.ocs').get('TRUSTED_SERVERS');
            if(ocsRemotes){
                let remotes = JSON.parse(ocsRemotes);
                let remotesNodes = {id:'remotes', label:'Remote Servers', icon:'mdi mdi-server', collections:[], _parent:root};
                for(let k in remotes){
                    if(!remotes.hasOwnProperty(k)) continue;
                    remotesNodes.collections.push({id:k, label:remotes[k], icon:'mdi mdi-server-network', type:'remote', parent:remotesNodes});
                }
                if(remotesNodes.collections.length){
                    root.collections.push(remotesNodes);
                }
            }
            return {
                root: root,
                selectedItem:this.props.mode === 'selector' ? root : search,
                loading: false,
                rightPaneItem: null
            };
        },

        onFolderClicked: function(item){
            this.setState({loading: true});
            Loaders.childrenAsPromise(item, false).then((children) => {
                Loaders.childrenAsPromise(item, true).then((children) => {
                    this.setState({selectedItem:item, loading: false});
                });
            });
        },

        onUserListItemClicked: function(item){
            this.setState({rightPaneItem:item});
        },

        render: function(){
            const {selectedItem, root, rightPaneItem} = this.state;

            const leftColumnStyle = {width:'25%', minWidth: 256, maxWidth:400, overflowY:'auto', backgroundColor:'#fafafa'};
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
                        loading={this.state.loading}
                        mode={this.props.mode}
                    />);

            }
            if(rightPaneItem){
                rightPanel = (
                    <UserCard
                        onRequestClose={() => {this.setState({rightPaneItem:null})}}
                        style={leftColumnStyle}
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
            return (
                <div style={{display:'flex', height: 450}}>
                    {leftPanel}
                    {centerComponent}
                    {rightPanel}
                </div>
            );
        }

    });

    global.AddressBook = {
        Panel: Panel
    };


})(window);