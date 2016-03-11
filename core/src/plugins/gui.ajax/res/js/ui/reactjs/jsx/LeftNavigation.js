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
                additionalContents:this.parseComponentConfigs(),
                workspaces: this.props.pydio.user.getRepositoriesList()
            };
        },

        componentDidMount:function(){
            if(this._timer) global.clearTimeout(this._timer);
            this._timer = global.setTimeout(this.closeNavigation, 3000);

            this._reloadObserver = function(){

                if(this.isMounted()){
                    this.setState({
                        workspaces:this.props.pydio.user.getRepositoriesList()
                    });
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

        render:function(){
            const additional = this.state.additionalContents.map(function(paneData){
                if(paneData.type == 'ListProvider'){
                    return (
                        <CollapsableListProvider
                            pydio={this.props.pydio}
                            paneData={paneData}
                            nodeClicked={this.listNodeClicked}
                        />
                    );
                }else{
                    return null;
                }
            }.bind(this));

            return (
                <span>
                    <div  id="repo_chooser" onClick={this.openNavigation} onMouseOver={this.openNavigation} className={this.state.statusOpen?"open":""}>
                        <span className="icon-reorder"/>
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

    var DataModelBadge = React.createClass({

        propTypes:{
            dataModel:React.PropTypes.instanceOf(PydioDataModel),
            options:React.PropTypes.object
        },

        getInitialState:function(){
            return {value:''};
        },

        componentDidMount:function(){
            var options = this.props.options;
            var dm = this.props.dataModel;
            this._observer = function(){
                switch (options.property){
                    case "root_children":
                        var l = dm.getRootNode().getChildren().size;
                        this.setState({value:l?l:''});
                        break;
                    case "root_label":
                        this.setState({value:dm.getRootNode().getLabel()});
                        break;
                    case "root_children_empty":
                        var cLength = dm.getRootNode().getChildren().size;
                        this.setState({value:!cLength?options['emptyMessage']:''});
                        break;
                    case "metadata":
                        if(options['metadata_sum']){
                            var sum = 0;
                            dm.getRootNode().getChildren().forEach(function(c){
                                if(c.getMetadata().get(options['metadata_sum'])) sum += parseInt(c.getMetadata().get(options['metadata_sum']));
                            });
                            this.setState({value:sum?sum:''});
                        }
                        break;
                    default:
                        break;
                }
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
            startOpen:React.PropTypes.bool
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

        render:function(){

            var messages = this.props.pydio.MessageHash;
            var paneData = this.props.paneData;

            const title = messages[paneData.options.title] || paneData.options.title;
            const className = 'simple-provider ' + (paneData.options['className'] ? paneData.options['className'] : '');
            const titleClassName = 'section-title ' + (paneData.options['titleClassName'] ? paneData.options['titleClassName'] : '');

            var badge;
            if(paneData.options.dataModelBadge){
                badge = <DataModelBadge dataModel={this.state.dataModel} options={paneData.options.dataModelBadge} />;
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
                component = (
                    <ReactPydio.NodeListCustomProvider
                        pydio={this.props.pydio}
                        ref={paneData.id}
                        title={title}
                        elementHeight={36}
                        heightAutoWithMax={400}
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
            pydio:React.PropTypes.instanceOf(Pydio),
            workspaces:React.PropTypes.instanceOf(Map),
            onHoverLink:React.PropTypes.func,
            onOutLink:React.PropTypes.func
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

            return (
                <div>
                    <div className="section-title">{messages[468]}</div>
                    <div className="workspaces">
                        {entries}
                    </div>
                    <div className="section-title">{messages[469]}</div>
                    <div className="workspaces">
                        {sharedEntries}
                    </div>
                    <div className="section-title"></div>
                    {createAction}
                </div>
            );
        }
    });

    var WorkspaceEntry = React.createClass({

        mixins:[ReactPydio.MessagesConsumerMixin],

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            workspace:React.PropTypes.instanceOf(Repository).isRequired,
            onHoverLink:React.PropTypes.func,
            onOutLink:React.PropTypes.func
        },

        getInitialState:function(){
            return {openAlert:false};
        },

        getLetterBadge:function(){
            return {__html:this.props.workspace.getHtmlBadge(true)};
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

        handleOpenAlert: function () {
            this.wrapper = document.body.appendChild(document.createElement('div'));
            this.wrapper.style.zIndex = 11;
            var replacements = {
                '%%OWNER%%': this.props.workspace.getOwner()
            };
            var component = React.render(
                <Confirm
                    {...this.props}
                    replacements={replacements}
                    onAccept={this.handleAccept.bind(this)}
                    onDecline={this.handleDecline.bind(this)}
                    onDismiss={this.handleCloseAlert}
                />, this.wrapper);
        },

        handleCloseAlert: function() {
            this.wrapper.remove();
        },

        onClick:function() {
            this.props.pydio.triggerRepositoryChange(this.props.workspace.getId());
        },

        render:function(){
            var current = this.props.pydio.user.getActiveRepository(),
                currentClass="workspace-entry",
                messages = this.props.pydio.MessageHash,
                onHover, onOut, onClick,
                badge, badgeNum, remoteDialog, newWorkspace;

            if (current == this.props.workspace.getId()) {
                currentClass +=" workspace-current";
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

            onClick = this.onClick.bind(this);

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
                    onClick = this.handleOpenAlert.bind(this);
                }
            }

            return (
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
                    {remoteDialog}
                </div>
            );
        }

    });

    var Confirm = React.createClass({
        getDefaultProps: function() {
            return {
                confirmLabel: 'OK',
                abortLabel: 'Cancel'
            };
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

    var ns = global.LeftNavigation || {};
    if(global.ReactDND){
        ns.Panel = ReactDND.DragDropContext(ReactDND.HTML5Backend)(LeftPanel);
    }else{
        ns.Panel = LeftPanel;
    }
    ns.UserWorkspacesList = UserWorkspacesList;
    global.LeftNavigation=ns;

})(window);
