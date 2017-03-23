import Editor from '../editor/Editor'

const Dashboard = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        rootNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        currentNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        openEditor:React.PropTypes.func.isRequired
    },

    getInitialState: function(){
        // find roles node
        var siblings = this.props.rootNode.getParent().getChildren();
        var roleNode;
        siblings.forEach(function(s){
            if(s.getPath() == '/data/roles'){
                roleNode = s;
            }
        });
        if(!roleNode){
            roleNode = new AjxpNode('/data/roles');
        }
        return {
            searchResultData: false,
            currentNode:this.props.currentNode,
            dataModel:this.props.dataModel,
            roleNode:roleNode
        };
    },

    componentWillReceiveProps: function(newProps){
        if(!this.state.searchResultData){
            this.setState({
                currentNode:newProps.currentNode,
                dataModel:newProps.dataModel
            });
        }
    },

    _extractMergedRole: function(node) {
        if (!node.getMetadata().get('merged_role') && node.getMetadata().get('json_merged_role')) {
            node.getMetadata().set('merged_role', JSON.parse(node.getMetadata().get('json_merged_role')));
            node.getMetadata().delete('json_merged_role');
        }
        return node.getMetadata().get('merged_role');
    },

    renderListUserAvatar:function(node){
        if(node.getMetadata().get("shared_user")){
            return <div className="sub-entry-icon"></div>;
        }
        var role = this._extractMergedRole(node);
        if(role){
            try{
                var avatar = role.PARAMETERS.AJXP_REPO_SCOPE_ALL['core.conf'].avatar;
            }catch(e){}
            if(avatar){
                var imgSrc = pydio.Parameters.get("ajxpServerAccess") + "&get_action=get_binary_param&user_id="+ PathUtils.getBasename(node.getPath()) +"&binary_id=" + avatar;
                return <img src={imgSrc} style={{borderRadius:30,width:33}}/>;
            }
        }
        var iconClass = node.getMetadata().get("icon_class")? node.getMetadata().get("icon_class") : (node.isLeaf()?"icon-file-alt":"icon-folder-close");
        return <ReactMUI.FontIcon className={iconClass}/>;
    },

    renderListEntryFirstLine:function(node){
        if(node.getMetadata().get("shared_user")) {
            return node.getLabel() + " ["+this.context.getMessage('user.13')+"]";
        }else if(node.getMetadata().get("isAdmin")== pydio.MessageHash['ajxp_conf.14']){
            return (
                <span>{node.getLabel()} <span className="icon-lock" style={{display:'inline-block',marginRight:5}}></span></span>
            );
        }else{
            return node.getLabel();
        }
    },

    renderListEntrySecondLine:function(node){
        if(node.isLeaf()){
            if(node.getPath() == '/data/users'){
                // This is the Root Group
                return this.context.getMessage('user.8');
            }
            var strings = [];
            if(node.getMetadata().get("last_connection_readable")){
                strings.push( this.context.getMessage('user.9') + ' ' + node.getMetadata().get("last_connection_readable"));
            }
            var role = this._extractMergedRole(node);
            if(role) {
                strings.push(this.context.getMessage('user.10').replace("%i", Object.keys(role.ACL).length));
            }
            var roles = node.getMetadata().get('ajxp_roles');
            if(roles && roles.split(',').length){
                strings.push(this.context.getMessage('user.11').replace("%i", roles.split(',').length));
            }
            return strings.join(" - ");
        }else{
            return this.context.getMessage('user.12') + ': ' + node.getPath().replace('/data/users', '');
        }
    },

    renderListEntrySelector:function(node){
        if(node.getPath() == '/data/users') return false;
        return node.isLeaf();
    },

    displaySearchResults:function(searchTerm, searchDataModel){
        this.setState({
            searchResultTerm:searchTerm,
            searchResultData: {
                term:searchTerm,
                toggleState:this.hideSearchResults
            },
            currentNode:searchDataModel.getContextNode(),
            dataModel:searchDataModel
        })
    },

    hideSearchResults:function(){
        this.setState({
            searchResultData: false,
            currentNode:this.props.currentNode,
            dataModel:this.props.dataModel
        });
    },

    createUserAction: function(){
        pydio.UI.openComponentInModal('AdminPeople','CreateUserForm', {dataModel: this.props.dataModel});
    },

    createGroupAction: function(){
        pydio.UI.openComponentInModal('AdminPeople','CreateRoleOrGroupForm', {type:'group'});
    },

    createRoleAction: function(){
        pydio.UI.openComponentInModal('AdminPeople','CreateRoleOrGroupForm', {type:'role', roleNode:this.state.roleNode});
    },

    openUsersImporter: function(){
        pydio.UI.openComponentInModal('EnterpriseComponents','UsersImportDialog', {dataModel: this.props.dataModel});
    },

    toggleStateShowRoles: function(){
        this.setState({showRolesActions:!this.state.showRolesActions});
    },

    openRoleEditor:function(node, initialSection = 'activity'){
        if(this.refs.editor && this.refs.editor.isDirty()){
            if(!window.confirm(this.props.pydio.MessageHash["ajxp_role_editor.19"])) {
                return false;
            }
        }
        let editor = Editor;
        const editorNode = XMLUtils.XPathSelectSingleNode(this.props.pydio.getXmlRegistry(), '//client_configs/component_config[@className="AdminPeople.Dashboard"]/editor');
        if(editorNode){
            editor = editorNode.getAttribute('namespace') + '.' + editorNode.getAttribute('component');
        }
        var editorData = {
            COMPONENT:editor,
            PROPS:{
                ref:"editor",
                node:node,
                pydio: this.props.pydio,
                initialEditSection:initialSection,
                onRequestTabClose:this.closeRoleEditor
            }
        };
        this.props.openRightPane(editorData);

    },

    closeRoleEditor:function(){
        if(this.refs.editor && this.refs.editor.isDirty()){
            if(!window.confirm(this.props.pydio.MessageHash["ajxp_role_editor.19"])) {
                return false;
            }
        }
        //this.setState({selectedNode:null, showCreator:null});
        this.props.closeRightPane();
    },

    render: function(){

        var emptyToolbar = <div></div>;
        let importButton = <ReactMUI.FlatButton primary={false} label={"+ Import Users"} onClick={this.openUsersImporter}/>;
        if(!ResourcesManager.moduleIsAvailable('EnterpriseComponents')){
            importButton = <ReactMUI.FlatButton primary={false} label={"+ Import Users"} disabled={true}/>;
        }

        return (
            <div className={"main-layout-nav-to-stack vertical-layout people-dashboard"}>
                <div className="people-title horizontal-layout">
                    <h1>{this.context.getMessage('2', 'ajxp_conf')}
                        <div className="buttonContainer">
                            <ReactMUI.FlatButton primary={true} label={this.context.getMessage("user.1")} onClick={this.createUserAction}/>
                            <ReactMUI.FlatButton primary={true} label={this.context.getMessage("user.2")} onClick={this.createGroupAction}/>
                            {importButton}
                        </div>
                    </h1>
                    <PydioComponents.SearchBox
                        displayResults={this.displaySearchResults}
                        displayResultsState={this.state.searchResultData}
                        hideResults={this.hideSearchResults}
                        className="search-box layout-fill"
                        parameters={{get_action:'admin_search_users',dir:this.props.dataModel.getContextNode().getPath()}}
                        queryParameterName="query"
                        limit={50}
                        textLabel={this.context.getMessage('user.7')}
                    />
                </div>
                <div className="container horizontal-layout layout-fill">
                    <div className="hide-on-vertical-layout vertical-layout tab-vertical-layout people-tree" style={{flex:'none'}}>
                        <ReactMUI.Tabs initialSelectedIndex={0}>
                            <ReactMUI.Tab label={this.context.getMessage("user.3")}>
                                <div style={{marginLeft:8}}>
                                    <PydioComponents.DNDTreeView
                                        showRoot={true}
                                        rootLabel={this.context.getMessage("user.5")}
                                        node={this.props.rootNode}
                                        dataModel={this.props.dataModel}
                                        className="users-groups-tree"
                                    />
                                </div>
                            </ReactMUI.Tab>
                            <ReactMUI.Tab label={this.context.getMessage("user.4")} style={{display:'flex',flexDirection:'column'}}>
                                <PydioComponents.SimpleList
                                    style={{height:'100%'}}
                                    key={2}
                                    node={this.state.roleNode}
                                    observeNodeReload={true}
                                    dataModel={this.state.dataModel}
                                    className={"display-as-menu" + (this.state.showRolesActions ? '' : ' hideActions')}
                                    openEditor={this.openRoleEditor}
                                    actionBarGroups={['get']}
                                    skipParentNavigation={true}
                                    customToolbar={emptyToolbar}
                                    entryRenderIcon={function(node){return null;}}
                                    elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                                    computeActionsForNode={true}
                                />
                                <div style={{height:48,padding:'8px 16px',backgroundColor:'rgb(247,247,247)',boxShadow:'0px 0px 1px rgba(0, 0, 0, 0.23)'}}>
                                    <ReactMUI.FlatButton secondary={true} label={this.context.getMessage("user.6")} onClick={this.createRoleAction}/>
                                    <ReactMUI.FlatButton secondary={true} onClick={this.toggleStateShowRoles} label={this.context.getMessage('93', 'ajxp_conf')}/>
                                </div>
                            </ReactMUI.Tab>
                        </ReactMUI.Tabs>
                    </div>
                    <ReactMUI.Paper zDepth={0} className="layout-fill vertical-layout people-list">
                        <PydioComponents.SimpleList
                            node={this.state.currentNode}
                            dataModel={this.state.dataModel}
                            openEditor={this.openRoleEditor}
                            entryRenderIcon={this.renderListUserAvatar}
                            entryRenderFirstLine={this.renderListEntryFirstLine}
                            entryRenderSecondLine={this.renderListEntrySecondLine}
                            entryEnableSelector={this.renderListEntrySelector}
                            searchResultData={this.state.searchResultData}
                            actionBarGroups={['get']}
                            elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES}
                            computeActionsForNode={true}
                        />
                    </ReactMUI.Paper>
                </div>
            </div>
        );
    }

});

export {Dashboard as default}