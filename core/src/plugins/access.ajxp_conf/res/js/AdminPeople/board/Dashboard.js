const React = require('react')
const {IconButton, Paper, BottomNavigation, BottomNavigationItem, FontIcon, FlatButton} = require('material-ui')
import Editor from '../editor/Editor'
const PydioDataModel = require('pydio/model/data-model')
const {muiThemeable} = require('material-ui/styles')

let Dashboard = React.createClass({

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
        pydio.UI.openComponentInModal('AdminPeople','CreateUserForm', {dataModel: this.props.dataModel, openRoleEditor:this.openRoleEditor.bind(this)});
    },

    createGroupAction: function(){
        pydio.UI.openComponentInModal('AdminPeople','CreateRoleOrGroupForm', {type:'group', openRoleEditor:this.openRoleEditor.bind(this)});
    },

    createRoleAction: function(){
        pydio.UI.openComponentInModal('AdminPeople','CreateRoleOrGroupForm', {type:'role', roleNode:this.state.roleNode, openRoleEditor:this.openRoleEditor.bind(this)});
    },

    openUsersImporter: function(){
        pydio.UI.openComponentInModal('EnterprisePeople','UsersImportDialog', {dataModel: this.props.dataModel});
    },

    toggleStateShowRoles: function(){
        this.setState({showRolesActions:!this.state.showRolesActions});
    },

    openRoleEditor:function(node, initialSection = 'activity'){
        if(this.refs.editor && this.refs.editor.isDirty()){
            if(!window.confirm(this.props.pydio.MessageHash["role_editor.19"])) {
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
            if(!window.confirm(this.props.pydio.MessageHash["role_editor.19"])) {
                return false;
            }
        }
        //this.setState({selectedNode:null, showCreator:null});
        this.props.closeRightPane();
    },

    deleteAction: function(node){
        const dm = new PydioDataModel();
        dm.setSelectedNodes([node]);
        ResourcesManager.loadClassesAndApply(['AdminActions'], () => {
            AdminActions.Callbacks.deleteAction(null, [dm]);
        })
    },

    renderNodeActions: function(node){
        const mime = node.getAjxpMime();
        const iconStyle = {
            color: 'rgba(0,0,0,0.3)',
            fontSize: 20
        };
        let actions = [];
        if(mime === 'user_editable' || mime === 'group' || mime === 'role' || mime==='role_editable'){
            actions.push(<IconButton key="edit" iconClassName="mdi mdi-pencil" onTouchTap={() => {this.openRoleEditor(node)}} onClick={(e)=>{e.stopPropagation()}} iconStyle={iconStyle} />);
            actions.push(<IconButton key="delete" iconClassName="mdi mdi-delete" onTouchTap={() => {this.deleteAction(node)}} onClick={(e)=>{e.stopPropagation()}} iconStyle={iconStyle} />);
        }else if(mime === 'user'){
            actions.push(<IconButton key="edit" iconClassName="mdi mdi-pencil" onTouchTap={() => {this.openRoleEditor(node)}} onClick={(e)=>{e.stopPropagation()}} iconStyle={iconStyle} />);
        }
        return (
            <div>{actions}</div>
        )
    },

    render: function(){

        const fontIconStyle = {
            style : {
                backgroundColor: this.props.muiTheme.palette.accent2Color,
                borderRadius: '50%',
                width: 36,
                height: 36,
                padding: 8,
                marginRight: 10
            },
            iconStyle : {
                color: 'white',
                fontSize: 20
            }
        }
        const emptyToolbar = <div></div>;
        let importButton = <IconButton {...fontIconStyle} iconClassName="mdi mdi-file-excel" primary={false} tooltip={this.context.getMessage('171', 'ajxp_conf')} onTouchTap={this.openUsersImporter}/>;
        if(!ResourcesManager.moduleIsAvailable('EnterprisePeople')){
            let disabled = {style:{...fontIconStyle.style}, iconStyle:{...fontIconStyle.iconStyle}};
            disabled.style.backgroundColor = 'rgba(0,0,0,0.23)';
            importButton = <IconButton {...disabled} iconClassName="mdi mdi-file-excel" primary={false} tooltip={this.context.getMessage('171', 'ajxp_conf')} disabled={true}/>;
        }
        const leftPanelIndex = this.state.leftPanelIndex || 0;

        return (
            <div className={"main-layout-nav-to-stack vertical-layout people-dashboard"}>
                <div className="people-title horizontal-layout">
                    <div style={{display:'flex', width: '100%', alignItems: 'top'}}>
                        <div style={{display:'flex', flex: 1, alignItems: 'center'}}>
                            <h1 className="admin-panel-title">{this.context.getMessage('2', 'ajxp_conf')}</h1>
                            <div style={{flex: 1, paddingTop: 8}}>
                                <IconButton primary={true} {...fontIconStyle} iconClassName="mdi mdi-account-plus" tooltip={this.context.getMessage("user.1")} onTouchTap={this.createUserAction}/>
                                <IconButton primary={true} {...fontIconStyle} iconClassName="mdi mdi-account-multiple-plus" tooltip={this.context.getMessage("user.2")} onTouchTap={this.createGroupAction}/>
                                {importButton}
                            </div>
                        </div>
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
                </div>
                <div className="container horizontal-layout layout-fill">
                    <div className="hide-on-vertical-layout vertical-layout tab-vertical-layout people-tree" style={{flex:'none'}}>
                        <BottomNavigation selectedIndex={leftPanelIndex} style={{backgroundColor: '#f5f5f5', borderBottom: '1px solid #e0e0e0'}}>
                            <BottomNavigationItem label={this.context.getMessage("user.3")} icon={<FontIcon className="mdi mdi-file-tree"/>} onTouchTap={() => {this.setState({leftPanelIndex: 0})}}/>
                            <BottomNavigationItem label={this.context.getMessage("user.4")} icon={<FontIcon className="mdi mdi-ticket-account"/>} onTouchTap={() => {this.setState({leftPanelIndex: 1})}}/>
                        </BottomNavigation>
                        {leftPanelIndex === 0 &&
                        <div style={{marginLeft:8, flex: 1}}>
                            <PydioComponents.DNDTreeView
                                showRoot={true}
                                rootLabel={this.context.getMessage("user.5")}
                                node={this.props.rootNode}
                                dataModel={this.props.dataModel}
                                className="users-groups-tree"
                            />
                        </div>
                        }
                        {leftPanelIndex === 1 &&
                        <div className="layout-fill vertical-layout">
                            <PydioComponents.SimpleList
                                style={{flex:1}}
                                key={2}
                                node={this.state.roleNode}
                                observeNodeReload={true}
                                dataModel={this.state.dataModel}
                                className={"display-as-menu" + (this.state.showRolesActions ? '' : ' hideActions')}
                                openEditor={this.openRoleEditor}
                                skipParentNavigation={true}
                                customToolbar={emptyToolbar}
                                entryRenderIcon={function(node){return null;}}
                                entryRenderActions={this.renderNodeActions}
                                elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                            />
                            <div style={{height:48,padding:'8px 16px',backgroundColor:'rgb(247,247,247)',boxShadow:'0px 0px 1px rgba(0, 0, 0, 0.23)'}}>
                                <FlatButton secondary={true} label={this.context.getMessage("user.6")} onClick={this.createRoleAction}/>
                                <FlatButton secondary={true} onClick={this.toggleStateShowRoles} label={this.context.getMessage('93', 'ajxp_conf')}/>
                            </div>
                        </div>
                        }
                    </div>
                    <ReactMUI.Paper zDepth={0} className="layout-fill vertical-layout people-list">
                        <PydioComponents.SimpleList
                            ref="mainlist"
                            pydio={this.props.pydio}
                            node={this.state.currentNode}
                            dataModel={this.state.dataModel}
                            openEditor={this.openRoleEditor}
                            entryRenderIcon={this.renderListUserAvatar}
                            entryRenderFirstLine={this.renderListEntryFirstLine}
                            entryRenderSecondLine={this.renderListEntrySecondLine}
                            entryEnableSelector={this.renderListEntrySelector}
                            entryRenderActions={this.renderNodeActions}
                            searchResultData={this.state.searchResultData}
                            elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES}
                            hideToolbar={false}
                            computeActionsForNode={true}
                        />
                    </ReactMUI.Paper>
                </div>
            </div>
        );
    }

});

Dashboard = muiThemeable()(Dashboard)
export {Dashboard as default}