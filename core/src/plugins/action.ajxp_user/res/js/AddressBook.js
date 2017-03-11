(function(global){

    class Loaders{

        static listUsers(params, callback){
            let baseParams = {get_action:'user_list_authorized_users',format:'json'};
            baseParams = {...baseParams, ...params};
            PydioApi.getClient().request(baseParams, function(transport){
                callback(transport.responseJSON);
            });
        }

        static loadTeams(entry, callback){
            const wrapped = (children) => {
                children.map(function(child){
                    child.itemsLoader = Loaders.loadTeamUsers;
                });
                callback(children);
            };
            Loaders.listUsers({filter_value:8}, wrapped);
        }

        static loadGroups(entry, callback){
            const wrapped = (children) => {
                children.map(function(child){
                    child.childrenLoader = Loaders.loadGroups;
                    child.itemsLoader = Loaders.loadGroupUsers;
                });
                callback(children);
            };
            const path = entry.id.replace('AJXP_GRP_', '');
            Loaders.listUsers({filter_value:4, group_path:path}, wrapped);
        }

        static loadExternalUsers(entry, callback){
            Loaders.listUsers({filter_value:2}, callback);
        }

        static loadGroupUsers(entry, callback){
            const path = entry.id.replace('AJXP_GRP_', '');
            Loaders.listUsers({filter_value:1, group_path:path}, callback);
        }

        static loadTeamUsers(entry, callback){
            Loaders.listUsers({filter_value:3, group_path:entry.id}, callback);
        }

    }

    const BoxListItem = React.createClass({

        propTypes:{
            nestedLevel:React.PropTypes.number,
            selected:React.PropTypes.string
        },

        getInitialState: function(){
            return {
                loaded: this.props.entry.children ? true: false,
                children:this.props.entry.children || []
            };
        },

        onTouchTap: function(){
            const {entry} = this.props;
            const {childrenLoader} = entry;
            if(!this.state.loaded && childrenLoader){
                childrenLoader(entry, (newChildren)=>{this.setState({loaded:true, children:newChildren})} );
            }
            this.props.onTouchTap(entry);
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
            const {id, label} = this.props.entry;
            const children = this.state.children;
            const nested = this.buildNestedItems(this.state.children);
            return (
                <MaterialUI.ListItem
                    nestedLevel={this.props.nestedLevel}
                    key={id}
                    primaryText={label}
                    onTouchTap={this.onTouchTap}
                    nestedItems={nested}
                    initiallyOpen={true}
                    innerDivStyle={{fontWeight:this.props.selected === this.props.entry.id ? 500 : 400}}
                />
            );
        }

    });

    const UsersList = React.createClass({

        propTypes:{
            item: React.PropTypes.object,
            onItemClicked:React.PropTypes.func
        },

        getInitialState: function(){
            return {items: [], loading: false};
        },

        loadProps: function(nextProps){
            this.setState({items:[]});
            const {item} = nextProps;
            if(item.itemsLoader){
                this.setState({loading: true});
                item.itemsLoader(item, (newItems) => {
                    this.setState({items: newItems, loading: false});
                })
            }else if(item.items){
                this.setState({items: item.items});
            }
        },

        componentDidMount: function(){
            this.loadProps(this.props);
        },

        componentWillReceiveProps: function(nextProps){
            if(nextProps.item.id !== this.props.item.id || nextProps.item.items){
                this.loadProps(nextProps);
            }
        },

        render: function(){
            if(this.state.loading){
                return <PydioReactUI.Loader style={{flex:1}}/>;
            }
            const total = this.state.items.length;
            let elements = [];
            this.state.items.forEach(function(item, index){
                elements.push(<MaterialUI.ListItem key={item.id} primaryText={item.label} onTouchTap={()=>{this.props.onItemClicked(item)}}/>);
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
                    <UsersList onItemClicked={this.props.onItemClicked} item={{items: this.state.items}}/>
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

        getInitialState: function(){
            let root = [
                {id:'search', label:'Search Local Users', type:'search'},
                {id:'ext', label:'Your Users', itemsLoader: Loaders.loadExternalUsers},
                {id:'teams', label:'Your Teams', childrenLoader:Loaders.loadTeams},
                {id:'AJXP_GRP_/', label:'All Users', childrenLoader:Loaders.loadGroups, itemsLoader: Loaders.loadGroupUsers}
            ];
            const ocsRemotes = this.props.pydio.getPluginConfigs('core.ocs').get('TRUSTED_SERVERS');
            if(ocsRemotes){
                let children = [];
                let remotes = JSON.parse(ocsRemotes);
                for(let k in remotes){
                    if(!remotes.hasOwnProperty(k)) continue;
                    children.push({id:k, label:remotes[k], type:'remote'});
                }
                if(children.length){
                    root.push({id:'remotes', label:'Remote Servers', children:children});
                }
            }

            return {
                folders: root,
                items: [],
                loading: false,
                selectedItem:{id:'search'},
                rightPaneItem: null
            };
        },

        onBoxListItemClicked: function(item){
            this.setState({selectedItem:item});
        },

        onUserListItemClicked: function(item){
            this.setState({rightPaneItem:item});
        },

        render: function(){
            const leftColumnStyle = {width:'25%', minWidth: 256, maxWidth:400, overflowY:'auto', backgroundColor:'#fafafa'};
            let items = this.state.items;
            let centerComponent, rightPanel;
            const {selectedItem, folders} = this.state;
            if(selectedItem.itemsLoader){

                centerComponent = (
                    <UsersList
                        item={selectedItem}
                        onItemClicked={this.onUserListItemClicked}
                    />);

            }else if(selectedItem.id === 'search'){

                centerComponent = (
                    <SearchForm
                        searchLabel={"Search local users by identifier"}
                        onItemClicked={this.onUserListItemClicked}
                    />);

            }else if(selectedItem.type === 'remote'){

                centerComponent = (
                    <SearchForm
                        params={{trusted_server_id:selectedItem.id}}
                        searchLabel={"Search Remote Server '" + selectedItem.label + "'"}
                        onItemClicked={this.onUserListItemClicked}
                    />);

            }
            if(this.state.rightPaneItem){
                rightPanel = (
                    <UserCard
                        onRequestClose={() => {this.setState({rightPaneItem:null})}}
                        style={leftColumnStyle}
                        item={this.state.rightPaneItem}/>
                );
            }
            return (
                <div style={{display:'flex', height: 600}}>
                    <MaterialUI.List style={leftColumnStyle}>
                        {folders.map(function(e){
                            return (
                                <BoxListItem
                                    selected={selectedItem.id}
                                    nestedLevel={0}
                                    entry={e}
                                    onTouchTap={this.onBoxListItemClicked}/>
                                );
                        }.bind(this))}
                    </MaterialUI.List>
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