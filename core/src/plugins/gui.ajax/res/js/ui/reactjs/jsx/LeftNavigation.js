(function(global){


    var LeftPanel = React.createClass({

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio).isRequired
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
            var contentNodes = XMLUtils.XPathSelectNodes(reg, 'client_configs/component_config[@className="AjxpReactComponent::left_navigator"]/additional_content');
            return contentNodes.map(function(node){
                return {
                    id:node.getAttribute('id'),
                    position: parseInt(node.getAttribute('position')),
                    type: node.getAttribute('type'),
                    options: JSON.parse(node.getAttribute('options'))
                };
            });
        },

        createRepositoryEnabled:function(){
            var reg = this.props.pydio.Registry.getXML();
            return XMLUtils.XPathSelectSingleNode(reg, 'actions/action[@name="user_create_repository"]') !== null;
        },

        getInitialState:function(){
            return {
                statusOpen:false,
                additionalContents:this.parseComponentConfigs(),
                workspaces: this.props.pydio.user.getRepositoriesList()
            };
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
            var entries = [], sharedEntries = [];
            this.state.workspaces.forEach(function(object, key){
                var entry = (
                    <WorkspaceEntry
                        {...this.props}
                        key={key}
                        workspace={object}
                    />
                );
                if(object.getOwner()){
                    sharedEntries.push(entry);
                }else{
                    entries.push(entry);
                }
            }.bind(this));

            var messages = this.props.pydio.MessageHash;
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

            if(this.createRepositoryEnabled()){
                var createClick = function(){
                    this.props.pydio.Controller.fireAction('user_create_repository');
                }.bind(this);
                var createAction = (
                    <div className="workspaces">
                        <div className="workspace-entry" onClick={createClick} title={messages[418]}>
                            <span className="workspace-badge">+</span>
                            <span className="workspace-label">{messages[417]}</span>
                        </div>
                    </div>
                );
            }

            return (
                <span>
                    <div  id="repo_chooser" onClick={this.openNavigation} onMouseOver={this.openNavigation} className={this.state.statusOpen?"open":""}>
                        <span className="icon-reorder"/>
                    </div>
                    <div className={"left-panel" + (this.state.statusOpen?'':' hidden')} onMouseOver={this.closeMouseover} onMouseOut={this.closeMouseout}>
                        {additional}
                        <div>
                            <div className="section-title" onClick={this.closeNavigation}>{messages[468]}</div>
                            <div className="workspaces">
                                {entries}
                            </div>
                            <div className="section-title">{messages[469]}</div>
                            <div className="workspaces">
                                {sharedEntries}
                            </div>
                        </div>
                        {createAction}
                    </div>
                </span>
            )
        }
    });

    var CollapsableListProvider = React.createClass({

        propTypes:{
            paneData:React.PropTypes.object,
            pydio:React.PropTypes.instanceOf(Pydio),
            nodeClicked:React.PropTypes.func
        },

        getInitialState:function(){
            return {open:false, componentLaunched:false};
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
                        nodeProviderProperties={paneData.options['nodeProviderProperties']}
                        reloadOnServerMessage={paneData.options['reloadOnServerMessage']}
                        actionBarGroups={[]}
                    />
                );
            }

            return (
                <div className={className + (this.state.open?" open": " closed")}>
                    <div className={titleClassName}>
                        <span className="toggle-button" onClick={this.toggleOpen}>{this.state.open?'Hide':'Show'}</span>
                        {title}
                    </div>
                    {component}
                </div>
            );

        }

    });

    var WorkspaceEntry = React.createClass({

        mixins:[ReactPydio.MessagesConsumerMixin],

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            workspace:React.PropTypes.instanceOf(Repository).isRequired
        },
        getLetterBadge:function(){
            return {__html:this.props.workspace.getHtmlBadge(true)};
        },
        onClick:function(){
            this.props.pydio.triggerRepositoryChange(this.props.workspace.getId());
        },
        render:function(){
            var current = this.props.pydio.user.getActiveRepository();
            var currentClass="workspace-entry";
            if(current == this.props.workspace.getId()){
                currentClass +=" workspace-current";
            }
            return (
                <div className={currentClass} onClick={this.onClick} title={this.props.workspace.getDescription()}>
                    <span className="workspace-badge" dangerouslySetInnerHTML={this.getLetterBadge()}/>
                    <span className="workspace-label">{this.props.workspace.getLabel()}</span>
                </div>
            );
        }

    });

    var ns = global.LeftNavigation || {};
    if(global.ReactDND){
        ns.Panel = ReactDND.DragDropContext(ReactDND.HTML5Backend)(LeftPanel);
    }else{
        ns.Panel = LeftPanel;
    }
    global.LeftNavigation=ns;

})(window);