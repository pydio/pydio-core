(function(global){

    const {Breadcrumb, SearchForm, MainFilesList, EditionPanel} = global.PydioWorkspaces;

    const UniqueNodeTemplateMixin = {

        detectFirstNode: function(attachListener = false){
            let dm = this.props.pydio.getContextHolder();
            if(!dm.getSelectedNodes().length) {
                let first = dm.getRootNode().getFirstChildIfExists();
                if (first) {
                    dm.setSelectedNodes([first], "dataModel");
                    this.setState({node: first});
                }else{
                    global.setTimeout(this.detectFirstNode.bind(this), 1000);
                }
            }else{
                if(!this.state || !this.state.node){
                    this.setState({node: dm.getSelectedNodes()[0]});
                }
            }
            if(attachListener){
                dm.observe("selection_changed", function(){
                    let selection = dm.getSelectedNodes();
                    if(selection.length) this.setState({node: selection[0]});
                    else this.setState({node: null});
                }.bind(this));
            }
        }

    }

    var DLTemplate = React.createClass({

        mixins:[UniqueNodeTemplateMixin],

        triggerDL: function(){

            this.setState({downloadStarted: true});
            global.setTimeout(function(){
                this.props.pydio.Controller.fireAction("download");
                    global.setTimeout(function(){
                        this.setState({downloadStarted: false});
                    }.bind(this), 1500);
            }.bind(this), 100);

        },

        componentDidMount: function(){
            this.detectFirstNode();
            let pydio = this.props.pydio;
            if(pydio.user && pydio.user.activeRepository){
                this.setState({
                    repoObject:pydio.user.repositories.get(pydio.user.activeRepository)
                });
            }else{
                global.document.observe("ajaxplorer:repository_list_refreshed", function(e){
                    let repositoryList = e.memo.list;
                    let repositoryId = e.memo.active;
                    if(repositoryList && repositoryList.has(repositoryId)){
                        var repoObject = repositoryList.get(repositoryId);
                        this.setState({repoObject: repoObject});
                    }
                }.bind(this));
            }
        },

        render: function(){

            if(!this.props.pydio.user){
                return <div></div>;
            }
            let name1, name2, name3, owner;
            let classNames = ['download-block'];
            if(this.state && this.state.repoObject){
                owner = this.state.repoObject.getOwner();
                name1 = '%1 shared'.replace('%1', owner);
                name2 = this.state.repoObject.getLabel();
                name3 = 'with you';
            }
            let click = null;
            let fileDetails = <div className="dl-details">{this.props.pydio.MessageHash[466]}</div> ;
            if(this.state && this.state.node){
                click = this.triggerDL.bind(this);
                fileDetails = (
                    <div className="dl-details">
                        <div className="row">
                            <span className="label">{this.props.pydio.MessageHash[503]}</span>
                            <span className="value">{this.state.node.getMetadata().get('filesize')}</span>
                        </div>
                        <div className="click-legend">{this.props.pydio.MessageHash['share_center.231']}</div>
                    </div>
                );
            }else{
                classNames.push('not-ready');
            }
            if(this.state && this.state.downloadStarted){
                classNames.push('dl-started');
            }
            let sharePageAction = this.props.pydio.Controller.getActionByName('share_current_page');
            let shareButton;
            if(sharePageAction && !sharePageAction.deny){
                shareButton = (
                    <a
                        style={{display:'block',textAlign:'center', padding: 12, cursor: 'pointer'}}
                        onClick={() => {this.props.pydio.Controller.fireAction('share_current_page')}}>{sharePageAction.options.text}</a>
                );
            }
            return (
                <div>
                    <div className={classNames.join(' ')} onClick={click}>
                        <span className="dl-filename">{name2}</span>
                        <div className="dl-icon">
                            <span className="mdi mdi-file"/>
                            <span className="mdi mdi-download"/>
                        </div>
                        {fileDetails}
                    </div>
                    {shareButton}
                </div>
            );

        }

    });

    let StandardLayout = React.createClass({

        childContextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func
        },

        getChildContext: function() {
            var messages = this.props.pydio.MessageHash;
            return {
                messages: messages,
                getMessage: function(messageId){
                    try{
                        return messages[messageId] || messageId;
                    }catch(e){
                        return messageId;
                    }
                }
            };
        },

        getDefaultProps: function(){
            return {minisiteMode: 'standard'};
        },

        render: function(){

            const {minisiteMode} = this.props;

            return (
                <MaterialUI.MuiThemeProvider>
                    <div className="vertical_fit vertical_layout">
                        <MaterialUI.Paper zDepth={1} className="primaryColorPaper" rounded={false}>
                            {minisiteMode !== 'embed' &&
                                <div id="workspace_toolbar">
                                    <Breadcrumb {...this.props}/>
                                </div>
                            }
                            <div id="main_toolbar">
                                <PydioMenus.ButtonMenu {...this.props} id="create-button-menu" toolbars={["mfb"]} buttonTitle="New..." raised={true} primary={true}/>
                                <PydioMenus.Toolbar {...this.props} id="main-toolbar" toolbars={["change_main"]} groupOtherList={["more", "change", "remote"]} renderingType="button"/>
                                <PydioComponents.ListPaginator id="paginator-toolbar" dataModel={this.props.pydio.getContextHolder()} toolbarDisplay={true}/>
                                <PydioMenus.Toolbar {...this.props} id="display-toolbar" toolbars={["display_toolbar"]} renderingType="icon-font"/>
                            </div>
                        </MaterialUI.Paper>
                        {this.props.children}
                        <PydioReactUI.Modal {...this.props} containerId="pydio_modal"/>
                        <span className="context-menu"><PydioMenus.ContextMenu/></span>
                    </div>
                </MaterialUI.MuiThemeProvider>
            );

        }

    });

    if(window.ReactDND){
        let DropLayout = ReactDND.DropTarget(ReactDND.HTML5Backend.NativeTypes.FILE, {drop:function(props, monitor){}}, function (connect, monitor) {
            return {
                connectDropTarget: connect.dropTarget(),
                isOver: monitor.isOver(),
                canDrop: monitor.canDrop()
            };
        })(StandardLayout);
        StandardLayout = ReactDND.DragDropContext(ReactDND.HTML5Backend)(DropLayout);
    }



    var FolderMinisite = React.createClass({

        render: function(){

            return (
                <StandardLayout {...this.props}>
                    <MainFilesList ref="list" {...this.props}/>
                    <EditionPanel {...this.props}/>
                </StandardLayout>
            );

        }

    });

    var FileMinisite = React.createClass({

        mixins: [UniqueNodeTemplateMixin],

        componentDidMount: function(){
            this.detectFirstNode();
        },

        render: function(){

            let node = this.state && this.state.node ?  this.state.node : null;

            let content;
            if(node){
                content = (
                    <div className="editor_container vertical_layout vertical_fit">
                        <PydioComponents.ReactEditorOpener
                            pydio={this.props.pydio}
                            node={node}
                            registry={this.props.pydio.Registry}
                            closeEditorContainer={function(){return false;}}
                        />
                    </div>
                );
            }else{
                content = (
                    <PydioReactUI.Loader />
                );
            }

            return (
                <StandardLayout {...this.props}>{content}</StandardLayout>
            );


        }

    });

    var DropZoneMinisite = React.createClass({

        render: function(){

            return (
                <StandardLayout {...this.props}>
                    <div className="minisite-dropzone vertical_fit vertical_layout">
                        <MainFilesList ref="list" {...this.props}/>
                    </div>
                    <EditionPanel {...this.props}/>
                </StandardLayout>
            );

        }

    });

    var FilmStripMinisite = React.createClass({

        mixins: [UniqueNodeTemplateMixin],

        componentDidMount: function(){
            this.detectFirstNode(true);
        },

        render: function(){

            let node = this.state && this.state.node ?  this.state.node : null;

            let editor;
            if(node){
                editor = (
                    <PydioComponents.ReactEditorOpener
                        pydio={this.props.pydio}
                        node={node}
                        registry={this.props.pydio.Registry}
                        closeEditorContainer={function(){return false;}}
                    />
                );
            }else{
                editor = (
                    <PydioReactUI.Loader />
                );
            }


            return (
                <StandardLayout {...this.props}>
                    <div className="vertical_layout" style={{flex:1, backgroundColor:'#424242'}}>
                        {editor}
                    </div>
                    <MaterialUI.Paper zDepth={1} className="vertical_layout" style={{height: 160}}>
                        <MainFilesList ref="list" {...this.props} horizontalRibbon={true} displayMode={"grid-160"}/>
                    </MaterialUI.Paper>
                </StandardLayout>
            );
        }

    });

    var ns = global.ShareTemplates || {};
    ns.FolderMinisite = FolderMinisite;
    ns.FileMinisite = FileMinisite;
    ns.DLTemplate = DLTemplate;
    ns.DropZoneMinisite = DropZoneMinisite;
    ns.FilmStripMinisite = FilmStripMinisite;
    global.ShareTemplates = ns;

})(window);