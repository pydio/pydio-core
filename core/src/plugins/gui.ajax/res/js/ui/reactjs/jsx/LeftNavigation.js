(function(global){


    var LeftPanel = React.createClass({

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            pydioId:React.PropTypes.string.isRequired
        },

        childContextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func
        },

        getChildContext: function() {
            var messages = this.props.pydio.MessageHash;
            return {
                messages: messages,
                getMessage: function(messageId, namespace='ajxp_admin'){
                    try{
                        return messages[namespace + (namespace?".":"") + messageId] || messageId;
                    }catch(e){
                        return messageId;
                    }
                }
            };
        },

        parseComponentConfigs:function(){
            var reg = this.props.pydio.Registry.getXML();
            //Does not work on IE 11
            //var contentNodes = XMLUtils.XPathSelectNodes(reg, 'client_configs/component_config[@className="AjxpReactComponent::'+this.props.pydioId+'"]/additional_content');
            var contentNodes = XMLUtils.XPathSelectNodes(reg, 'client_configs/component_config/additional_content');
            var result = [];
            var compId = "AjxpReactComponent::"+this.props.pydioId;
            contentNodes.map(function(node){
                if(node.parentNode.getAttribute('className') == compId){
                    result.push({
                        id:node.getAttribute('id'),
                        position: parseInt(node.getAttribute('position')),
                        type: node.getAttribute('type'),
                        options: JSON.parse(node.getAttribute('options'))
                    });
                }
            });
            result.sort(function(a,b){return a.position >= b.position ? 1 : -1});
            return result;
        },

        getInitialState:function(){
            return {
                statusOpen:true,
                blinkingBell: false,
                additionalContents:this.parseComponentConfigs(),
                workspaces: this.props.pydio.user.getRepositoriesList()
            };
        },

        componentDidMount:function(){
            if(this._timer) global.clearTimeout(this._timer);
            this._timer = global.setTimeout(this.closeNavigation, 3000);

            this._reloadObserver = function(){
                try{
                    if(this.isMounted()){
                        this.setState({
                            workspaces:this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []
                        });
                    }
                }catch(e){
                    if(global.console){
                        console.error('Error while setting state on LeftNavigation component - Probably height error on IE8', e);
                    }
                }
            }.bind(this);

            this.props.pydio.observe('repository_list_refreshed', this._reloadObserver);
        },

        componentWillUnmount:function(){
            if(this._reloadObserver){
                this.props.pydio.stopObserving('repository_list_refreshed', this._reloadObserver);
            }
        },

        openNavigation:function(){
            if(!this.state.statusOpen){
                this.setState({statusOpen:true});
            }
        },

        closeNavigation:function(){
            this.setState({statusOpen:false});
        },

        listNodeClicked:function(node){
            this.props.pydio.goTo(node);
            this.closeNavigation();
        },

        closeMouseover:function(){
            if(this._timer) global.clearTimeout(this._timer);
        },

        closeMouseout:function(){
            if(this._timer) global.clearTimeout(this._timer);
            this._timer = global.setTimeout(this.closeNavigation, 300);
        },

        onAlertPanelBadgeChange: function(paneData, newValue, oldValue, memoData){
            if(paneData.id !== 'navigation_alerts'){
                return;
            }
            if(newValue){
                this.setState({blinkingBell: newValue, blinkingBellClass:paneData.options['titleClassName']});
            }else{
                this.setState({blinkingBell: false});
            }

            if(newValue && newValue !== oldValue){
                if(Object.isNumber(newValue)){
                    if(oldValue !== '' && newValue > oldValue){
                        let notifText = 'Something happened!';
                        if(memoData instanceof PydioDataModel){
                            let node = memoData.getRootNode().getFirstChildIfExists();
                            if(node){
                                if(paneData.options['tipAttribute']){
                                    notifText = node.getMetadata().get(paneData.options['tipAttribute']);
                                }else{
                                    notifText = node.getLabel();
                                }
                            }
                        }
                        AlertTask.setCloser(this.openNavigation.bind(this));
                        let title = global.pydio.MessageHash[paneData.options.title] || paneData.options.title;
                        let alert = new AlertTask(title, notifText);
                        alert.show();
                    }
                }
            }

        },

        render:function(){
            const additional = this.state.additionalContents.map(function(paneData){
                if(paneData.type == 'ListProvider'){
                    return (
                        <CollapsableListProvider
                            pydio={this.props.pydio}
                            paneData={paneData}
                            nodeClicked={this.listNodeClicked}
                            onBadgeChange={this.onAlertPanelBadgeChange}
                        />
                    );
                }else{
                    return null;
                }
            }.bind(this));

            let badge;
            if(this.state.blinkingBell){
                badge = <span className={"badge-icon icon-bell-alt"}/>;
            }

            return (
                <span>
                    <div  id="repo_chooser" onClick={this.openNavigation} onMouseOver={this.openNavigation} className={this.state.statusOpen?"open":""}>
                        <span className="icon-reorder"/>{badge}
                    </div>
                    <div className={"left-panel" + (this.state.statusOpen?'':' hidden')} onMouseOver={this.closeMouseover} onMouseOut={this.closeMouseout}>
                        {additional}
                        <UserWorkspacesList
                            pydio={this.props.pydio}
                            workspaces={this.state.workspaces}
                        />
                    </div>
                </span>
            );
        }
    });

    var UserWidget = React.createClass({

        applyAction: function(actionName){
            switch (actionName){
                case 'alerts':
                    break;
                case 'home':
                    this.props.pydio.triggerRepositoryChange('ajxp_home');
                    break;
                case 'info':
                    this.props.pydio.Controller.getActionByName('splash').deny = false;
                    this.props.pydio.Controller.fireAction('splash');
                    break;
                case 'cog':
                    // Open dashboard in modal
                    this.props.pydio.Controller.fireAction('open_user_dashboard');
                    break;
                case 'logout':
                    this.props.pydio.Controller.fireAction('logout');
                    break;
                default:
                    break;
            }
        },

        render: function(){

            const messages = this.props.pydio.MessageHash;

            let avatar;
            if(this.props.pydio.user){
                avatar = (
                        <PydioComponents.UserAvatar
                            pydio={this.props.pydio}
                            userId={this.props.pydio.user.id}
                            avatarStyle={{marginRight:20}}
                            className="user-display"
                            labelClassName="userLabel"
                        >
                            <MaterialUI.IconButton
                                onTouchTap={this.applyAction.bind(this, 'cog')}
                                iconClassName="mdi mdi-settings"
                                tooltip={messages['165']}
                                style={{width: 38, height: 38}}
                                iconStyle={{fontSize: 16, color: 'rgba(255,255,255,0.87)'}}
                            />
                        </PydioComponents.UserAvatar>
                );
            }

            return (
                <MaterialUI.Paper zDepth={1} rounded={false} className="user-widget primaryColorDarkerPaper">
                    {avatar}
                    <div className="action_bar">
                        <MaterialUI.IconButton
                            onTouchTap={this.applyAction.bind(this, 'home')}
                            iconClassName="userActionIcon mdi mdi-home"
                            className="userActionButton"
                            tooltip={messages['305']}
                        />
                        <MaterialUI.IconButton
                            onTouchTap={this.applyAction.bind(this, 'info')}
                            iconClassName="userActionIcon mdi mdi-information-outline"
                            className="userActionButton"
                            tooltip={messages['166']}
                        />
                        <PydioReactUI.AsyncComponent
                            namespace="PydioNotifications"
                            componentName="Panel"
                            noLoader={true}
                            iconClassName="userActionIcon mdi mdi-bell-outline"
                            {...this.props}
                        />
                        <MaterialUI.IconButton
                            onTouchTap={this.applyAction.bind(this, 'logout')}
                            iconClassName="userActionIcon mdi mdi-logout"
                            className="userActionButton"
                            tooltip={messages['169']}
                        />
                    </div>
                </MaterialUI.Paper>
            );
        }
    });

    var PinnedLeftPanel = React.createClass({

        propTypes: {
            pydio: React.PropTypes.instanceOf(Pydio).isRequired
        },

        render: function(){
            return (
            <div className="left-panel vertical_fit vertical_layout">
                <UserWidget pydio={this.props.pydio}/>
                <UserWorkspacesList
                    className={"vertical_fit"}
                    style={{overflowY:'auto'}}
                    pydio={this.props.pydio}
                    workspaces={this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []}
                    showTreeForWorkspace={this.props.pydio.user?this.props.pydio.user.activeRepository:false}
                />
            </div>
            );
        }
    });

    if(global.PydioTasks){

        class AlertTask extends PydioTasks.Task{

            constructor(label, statusMessage){
                super({
                    id              : 'local-alert-task-' + Math.random(),
                    userId          : global.pydio.user.id,
                    wsId            : global.pydio.user.activeRepository,
                    label           : label,
                    status          : PydioTasks.Task.STATUS_PENDING,
                    statusMessage   : statusMessage,
                    className       : 'alert-task'
                });
            }

            show(){
                this._timer = global.setTimeout(function(){
                    this.updateStatus(PydioTasks.Task.STATUS_COMPLETE);
                }.bind(this), 7000);
                PydioTasks.Store.getInstance().enqueueLocalTask(this);
            }

            updateStatus(status, statusMessage = ''){
                this._internal['status'] = status;
                this._internal['statusMessage'] = statusMessage;
                this.notifyMainStore();
            }

            notifyMainStore(){
                PydioTasks.Store.getInstance().notify("tasks_updated");
            }

            hasOpenablePane(){
                return true;
            }
            openDetailPane(){
                AlertTask.close();
            }

            static setCloser(click){
                AlertTask.__CLOSER = click;
            }

            static close(){
                AlertTask.__CLOSER();
            }

        }
    }




    var DataModelBadge = React.createClass({

        propTypes:{
            dataModel:React.PropTypes.instanceOf(PydioDataModel),
            options:React.PropTypes.object,
            onBadgeIncrease: React.PropTypes.func,
            onBadgeChange: React.PropTypes.func
        },

        getInitialState:function(){
            return {value:''};
        },

        componentDidMount:function(){
            let options = this.props.options;
            let dm = this.props.dataModel;
            let newValue = '';
            this._observer = function(){
                switch (options.property){
                    case "root_children":
                        var l = dm.getRootNode().getChildren().size;
                        newValue = l ? l : 0;
                        break;
                    case "root_label":
                        newValue = dm.getRootNode().getLabel();
                        break;
                    case "root_children_empty":
                        var cLength = dm.getRootNode().getChildren().size;
                        newValue = !cLength?options['emptyMessage']:'';
                        break;
                    case "metadata":
                        if(options['metadata_sum']){
                            newValue = 0;
                            dm.getRootNode().getChildren().forEach(function(c){
                                if(c.getMetadata().get(options['metadata_sum'])) newValue += parseInt(c.getMetadata().get(options['metadata_sum']));
                            });
                        }
                        break;
                    default:
                        break;
                }
                let prevValue = this.state.value;
                if(newValue && newValue !== prevValue){
                    if(Object.isNumber(newValue) && this.props.onBadgeIncrease){
                        if(prevValue !== '' && newValue > prevValue) this.props.onBadgeIncrease(newValue, prevValue ? prevValue : 0, this.props.dataModel);
                    }
                }
                if(this.props.onBadgeChange){
                    this.props.onBadgeChange(newValue, prevValue, this.props.dataModel);
                }
                this.setState({value: newValue});
            }.bind(this);
            dm.getRootNode().observe("loaded", this._observer);
        },

        componentWillUnmount:function(){
            this.props.dataModel.stopObserving("loaded", this._observer);
        },

        render:function(){
            if(!this.state.value) {
                return null;
            } else {
                return (<span className={this.props.options['className']}>{this.state.value}</span>);
            }
        }

    });

    var CollapsableListProvider = React.createClass({

        propTypes:{
            paneData:React.PropTypes.object,
            pydio:React.PropTypes.instanceOf(Pydio),
            nodeClicked:React.PropTypes.func,
            startOpen:React.PropTypes.bool,
            onBadgeIncrease: React.PropTypes.func,
            onBadgeChange: React.PropTypes.func
        },

        getInitialState:function(){

            var dataModel = new PydioDataModel(true);
            var rNodeProvider = new RemoteNodeProvider();
            dataModel.setAjxpNodeProvider(rNodeProvider);
            rNodeProvider.initProvider(this.props.paneData.options['nodeProviderProperties']);
            var rootNode = new AjxpNode("/", false, "loading", "folder.png", rNodeProvider);
            dataModel.setRootNode(rootNode);

            return {
                open:false,
                componentLaunched:!!this.props.paneData.options['startOpen'],
                dataModel:dataModel
            };
        },

        toggleOpen:function(){
            this.setState({open:!this.state.open, componentLaunched:true});
        },

        onBadgeIncrease: function(newValue, prevValue, memoData){
            if(this.props.onBadgeIncrease){
                this.props.onBadgeIncrease(this.props.paneData, newValue, prevValue, memoData);
                if(!this.state.open) this.toggleOpen();
            }
        },

        onBadgeChange(newValue, prevValue, memoData){
            if(this.props.onBadgeChange){
                this.props.onBadgeChange(this.props.paneData, newValue, prevValue, memoData);
                if(!this.state.open) this.toggleOpen();
            }
        },

        render:function(){

            var messages = this.props.pydio.MessageHash;
            var paneData = this.props.paneData;

            const title = messages[paneData.options.title] || paneData.options.title;
            const className = 'simple-provider ' + (paneData.options['className'] ? paneData.options['className'] : '');
            const titleClassName = 'section-title ' + (paneData.options['titleClassName'] ? paneData.options['titleClassName'] : '');

            var badge;
            if(paneData.options.dataModelBadge){
                badge = <DataModelBadge
                    dataModel={this.state.dataModel}
                    options={paneData.options.dataModelBadge}
                    onBadgeIncrease={this.onBadgeIncrease}
                    onBadgeChange={this.onBadgeChange}
                />;
            }
            var emptyMessage;
            if(paneData.options.emptyChildrenMessage){
                emptyMessage = <DataModelBadge
                    dataModel={this.state.dataModel}
                    options={{
                        property:'root_children_empty',
                        className:'emptyMessage',
                        emptyMessage:messages[paneData.options.emptyChildrenMessage]
                    }}
                />
            }

            var component;
            if(this.state.componentLaunched){
                var entryRenderFirstLine;
                if(paneData.options['tipAttribute']){
                    entryRenderFirstLine = function(node){
                        var meta = node.getMetadata().get(paneData.options['tipAttribute']);
                        if(meta){
                            return <div title={meta.replace(/<\w+(\s+("[^"]*"|'[^']*'|[^>])+)?(\/)?>|<\/\w+>/gi, '')}>{node.getLabel()}</div>;
                        }else{
                            return node.getLabel();
                        }
                    };
                }
                component = (
                    <PydioComponents.NodeListCustomProvider
                        pydio={this.props.pydio}
                        ref={paneData.id}
                        title={title}
                        elementHeight={36}
                        heightAutoWithMax={4000}
                        entryRenderFirstLine={entryRenderFirstLine}
                        nodeClicked={this.props.nodeClicked}
                        presetDataModel={this.state.dataModel}
                        reloadOnServerMessage={paneData.options['reloadOnServerMessage']}
                        actionBarGroups={paneData.options['actionBarGroups']?paneData.options['actionBarGroups']:[]}
                    />
                );
            }

            return (
                <div className={className + (this.state.open?" open": " closed")}>
                    <div className={titleClassName}>
                        <span className="toggle-button" onClick={this.toggleOpen}>{this.state.open?messages[514]:messages[513]}</span>
                        {title} {badge}
                    </div>
                    {component}
                    {emptyMessage}
                </div>
            );

        }

    });

    var UserWorkspacesList = React.createClass({

        propTypes:{
            pydio                   : React.PropTypes.instanceOf(Pydio),
            workspaces              : React.PropTypes.instanceOf(Map),
            showTreeForWorkspace    : React.PropTypes.string,
            onHoverLink             : React.PropTypes.func,
            onOutLink               : React.PropTypes.func,
            className               : React.PropTypes.string,
            style                   : React.PropTypes.object
        },

        createRepositoryEnabled:function(){
            var reg = this.props.pydio.Registry.getXML();
            return XMLUtils.XPathSelectSingleNode(reg, 'actions/action[@name="user_create_repository"]') !== null;
        },

        render: function(){
            var entries = [], sharedEntries = [], inboxEntry;

            this.props.workspaces.forEach(function(object, key){

                if (object.getId().indexOf('ajxp_') === 0) return;
                if (object.hasContentFilter()) return;
                if (object.getAccessStatus() === 'declined') return;

                var entry = (
                    <WorkspaceEntry
                        {...this.props}
                        key={key}
                        workspace={object}
                        showFoldersTree={this.props.showTreeForWorkspace && this.props.showTreeForWorkspace===key}
                    />
                );
                if (object.getAccessType() == "inbox") {
                    inboxEntry = entry;
                } else if(object.getOwner()) {
                    sharedEntries.push(entry);
                } else {
                    entries.push(entry);
                }
            }.bind(this));

            if(inboxEntry){
                sharedEntries.unshift(inboxEntry);
            }

            var messages = this.props.pydio.MessageHash;

            if(this.createRepositoryEnabled()){
                var createClick = function(){
                    this.props.pydio.Controller.fireAction('user_create_repository');
                }.bind(this);
                var createAction = (
                    <div className="workspaces">
                        <div className="workspace-entry" onClick={createClick} title={messages[418]}>
                            <span className="workspace-badge">+</span>
                            <span className="workspace-label">{messages[417]}</span>
                            <span className="workspace-description">{messages[418]}</span>
                        </div>
                    </div>
                );
            }
            
            let workspacesTitle, sharedEntriesTitle, createActionTitle;
            if(entries.length){
                workspacesTitle = <div className="section-title">{messages[468]}</div>;
            }
            if(sharedEntries.length){
                sharedEntriesTitle = <div className="section-title">{messages[469]}</div>;
            }
            if(createAction){
                createActionTitle = <div className="section-title"></div>;
            }

            return (
                <div className={"user-workspaces-list" + (this.props.className ? ' ' + this.props.className  : '')} style={this.props.style}>
                    {workspacesTitle}
                    <div className="workspaces">
                        {entries}
                    </div>
                    {sharedEntriesTitle}
                    <div className="workspaces">
                        {sharedEntries}
                    </div>
                    {createActionTitle}
                    {createAction}
                </div>
            );
        }
    });

    var WorkspaceEntry = React.createClass({

        mixins:[PydioComponents.MessagesConsumerMixin],

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            workspace:React.PropTypes.instanceOf(Repository).isRequired,
            showFoldersTree:React.PropTypes.bool,
            onHoverLink:React.PropTypes.func,
            onOutLink:React.PropTypes.func
        },

        getInitialState:function(){
            return {
                openAlert:false,
                openFoldersTree: false,
                currentContextNode: this.props.pydio.getContextHolder().getContextNode()
            };
        },

        getLetterBadge:function(){
            return {__html:this.props.workspace.getHtmlBadge(true)};
        },

        componentDidMount: function(){
            if(this.props.showFoldersTree){
                this._monitorFolder = function(){
                    this.setState({currentContextNode: this.props.pydio.getContextHolder().getContextNode()});
                }.bind(this);
                this.props.pydio.getContextHolder().observe("context_changed", this._monitorFolder);
            }
        },

        componentWillUnmount: function(){
            if(this._monitorFolder){
                this.props.pydio.getContextHolder().stopObserving("context_changed", this._monitorFolder);
            }
        },

        handleAccept: function () {
            PydioApi.getClient().request({
                'get_action': 'accept_invitation',
                'remote_share_id': this.props.workspace.getShareId()
            }, function () {
                // Switching status to decline
                this.props.workspace.setAccessStatus('accepted');

                this.handleCloseAlert();
                this.onClick();

            }.bind(this), function () {
                this.handleCloseAlert();
            }.bind(this));
        },

        handleDecline: function () {
            PydioApi.getClient().request({
                'get_action': 'reject_invitation',
                'remote_share_id': this.props.workspace.getShareId()
            }, function () {
                // Switching status to decline
                this.props.workspace.setAccessStatus('declined');

                this.props.pydio.fire("repository_list_refreshed", {
                    list: this.props.pydio.user.getRepositoriesList(),
                    active: this.props.pydio.user.getActiveRepository()
                });

                this.handleCloseAlert();
            }.bind(this), function () {
                this.handleCloseAlert();
            }.bind(this));
        },

        handleOpenAlert: function (mode = 'new_share', event) {
            event.stopPropagation();
            this.wrapper = document.body.appendChild(document.createElement('div'));
            this.wrapper.style.zIndex = 11;
            var replacements = {
                '%%OWNER%%': this.props.workspace.getOwner()
            };
            ReactDOM.render(
                <Confirm
                    {...this.props}
                    mode={mode}
                    replacements={replacements}
                    onAccept={mode == 'new_share' ? this.handleAccept.bind(this) : this.handleDecline.bind(this)}
                    onDecline={mode == 'new_share' ? this.handleDecline.bind(this) : this.handleCloseAlert.bind(this)}
                    onDismiss={this.handleCloseAlert}
                />, this.wrapper);
        },

        handleCloseAlert: function() {
            ReactDOM.unmountComponentAtNode(this.wrapper);
            this.wrapper.remove();
        },

        handleRemoveTplBasedWorkspace: function(event){
            event.stopPropagation();
            if(!global.confirm(this.props.pydio.MessageHash['424'])){
                return;
            }
            PydioApi.getClient().request({get_action:'user_delete_repository', repository_id:this.props.workspace.getId()}, function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            });
        },

        onClick:function() {
            if(this.props.workspace.getId() === this.props.pydio.user.activeRepository && this.props.showFoldersTree){
                this.props.pydio.goTo('/');
            }else{
                this.props.pydio.triggerRepositoryChange(this.props.workspace.getId());
            }
        },

        toggleFoldersPanelOpen: function(ev){
            ev.stopPropagation();
            this.setState({openFoldersTree: !this.state.openFoldersTree});
        },

        render:function(){
            var current = (this.props.pydio.user.getActiveRepository() == this.props.workspace.getId()),
                currentClass="workspace-entry",
                messages = this.props.pydio.MessageHash,
                onHover, onOut, onClick,
                additionalAction,
                badge, badgeNum, newWorkspace;

            if (current) {
                currentClass +=" workspace-current";
            }
            if(this.props.showFoldersTree && this.state.currentContextNode && this.state.currentContextNode.getPath() === '/' ){
                currentClass +=" workspace-current-node";
            }

            currentClass += " workspace-access-" + this.props.workspace.getAccessType();

            if (this.props.onHoverLink) {
                onHover = function(event){
                    this.props.onHoverLink(event, this.props.workspace)
                }.bind(this);
            }

            if (this.props.onOutLink) {
                onOut = function(event){
                    this.props.onOutLink(event, this.props.ws)
                }.bind(this);
            }

            onClick = this.onClick;

            // Icons
            if (this.props.workspace.getAccessType() == "inbox") {
                var status = this.props.workspace.getAccessStatus();

                if (!isNaN(status) && status > 0) {
                    badgeNum = <span className="workspace-num-badge">{status}</span>;
                }

                badge = <span className="workspace-badge"><span className="access-icon"/></span>;
            } else if(this.props.workspace.getOwner()){
                var overlay = <span className="badge-overlay mdi mdi-share-variant"/>;
                if(this.props.workspace.getRepositoryType() == "remote"){
                    overlay = <span className="badge-overlay icon-cloud"/>;
                }
                badge = <span className="workspace-badge"><span className="mdi mdi-folder"/>{overlay}</span>;
            } else{
                badge = <span className="workspace-badge" dangerouslySetInnerHTML={this.getLetterBadge()}/>;
            }

            if (this.props.workspace.getOwner() && !this.props.workspace.getAccessStatus() && !this.props.workspace.getLastConnection()) {
                newWorkspace = <span className="workspace-new">NEW</span>;
                // Dialog for remote shares
                if (this.props.workspace.getRepositoryType() == "remote") {
                    onClick = this.handleOpenAlert.bind(this, 'new_share');
                }
            }else if(this.props.workspace.getRepositoryType() == "remote" && !current){
                // Remote share but already accepted, add delete
                additionalAction = <span className="workspace-additional-action mdi mdi-close" onClick={this.handleOpenAlert.bind(this, 'reject_accepted')} title={messages['550']}/>;
            }else if(this.props.workspace.userEditable && !current){
                additionalAction = <span className="workspace-additional-action mdi mdi-close" onClick={this.handleRemoveTplBasedWorkspace} title={messages['423']}/>;
            }

            if(this.props.showFoldersTree){
                let fTCName = this.state.openFoldersTree ? "workspace-additional-action icon-angle-up" : "workspace-additional-action icon-angle-down";
                additionalAction = <span className={fTCName} onClick={this.toggleFoldersPanelOpen}></span>;
            }

            let wsBlock = (
                <div
                    className={currentClass}
                    onClick={onClick}
                    title={this.props.workspace.getDescription()}
                    onMouseOver={onHover}
                    onMouseOut={onOut}
                >
                    {badge}
                    <span className="workspace-label">{this.props.workspace.getLabel()}{newWorkspace}{badgeNum}</span>
                    <span className="workspace-description">{this.props.workspace.getDescription()}</span>
                    {additionalAction}
                </div>
            );

            if(this.props.showFoldersTree){
                return (
                    <div>
                        {wsBlock}
                        <PydioComponents.FoldersTree
                            pydio={this.props.pydio}
                            dataModel={this.props.pydio.getContextHolder()}
                            className={this.state.openFoldersTree?"open":"closed"}
                            draggable={true}
                        />
                    </div>
                )
            }else{
                return wsBlock;
            }

        }

    });

    var Confirm = React.createClass({

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio),
            onDecline:React.PropTypes.func,
            onAccept:React.PropTypes.func,
            mode:React.PropTypes.oneOf(['new_share','reject_accepted'])
        },

        componentDidMount: function () {
            this.refs.dialog.show()
        },

        render: function () {
            var messages = this.props.pydio.MessageHash,
                messageTitle = messages[545],
                messageBody = messages[546],
                actions = [
                    { text: messages[548], ref: 'decline', onClick: this.props.onDecline},
                    { text: messages[547], ref: 'accept', onClick: this.props.onAccept}
                ];
            if(this.props.mode == 'reject_accepted'){
                messageBody = messages[549];
                actions = [
                    { text: messages[54], ref: 'decline', onClick: this.props.onDecline},
                    { text: messages[551], ref: 'accept', onClick: this.props.onAccept}
                ];
            }

            for (var key in this.props.replacements) {
                messageTitle = messageTitle.replace(new RegExp(key), this.props.replacements[key]);
                messageBody = messageBody.replace(new RegExp(key), this.props.replacements[key]);
            }

            return <div className='react-mui-context' style={{position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', background: 'transparent'}}>
                <ReactMUI.Dialog
                    ref="dialog"
                    title={messageTitle}
                    actions={actions}
                    modal={false}
                    dismissOnClickAway={true}
                    onDismiss={this.props.onDismiss.bind(this)}
                    open={true}
                >
                    {messageBody}
                </ReactMUI.Dialog>
            </div>
        }
    });

    var FakeDndBackend = function(){
        return{
            setup:function(){},
            teardown:function(){},
            connectDragSource:function(){},
            connectDragPreview:function(){},
            connectDropTarget:function(){}
        };
    };

    var ns = global.LeftNavigation || {};
    if(global.ReactDND){
        ns.Panel = ReactDND.DragDropContext(FakeDndBackend)(LeftPanel);
    }else{
        ns.Panel = LeftPanel;
    }
    ns.PinnedLeftPanel = PinnedLeftPanel;
    ns.UserWorkspacesList = UserWorkspacesList;
    global.LeftNavigation=ns;

})(window);
