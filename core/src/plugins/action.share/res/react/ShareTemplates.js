(function(global){

    const {Textfit} = require('react-textfit');
    const Workspaces = require('pydio/http/resources-manager').requireLib('workspaces');
    const Components = require('pydio/http/resources-manager').requireLib('components');
    const Color = require('color');

    const {Breadcrumb, SearchForm, MainFilesList, EditionPanel} = Workspaces;
    const {ButtonMenu, Toolbar, ListPaginator, ReactEditorOpener} = Components;

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

    const DLTemplate = React.createClass({

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
                pydio.observe("repository_list_refreshed", function(e){
                    let repositoryList = e.list;
                    let repositoryId = e.active;
                    if(repositoryList && repositoryList.has(repositoryId)){
                        var repoObject = repositoryList.get(repositoryId);
                        this.setState({repoObject: repoObject});
                    }
                }.bind(this));
            }
        },

        render: function(){

            let style = {};
            if(this.props.imageBackgroundFromConfigs){
                if(PydioReactUI.BackgroundImage.SESSION_IMAGE){
                    style = PydioReactUI.BackgroundImage.SESSION_IMAGE;
                }else{
                    style = PydioReactUI.BackgroundImage.getImageBackgroundFromConfig(this.props.imageBackgroundFromConfigs);
                    PydioReactUI.BackgroundImage.SESSION_IMAGE = style;
                }
            }
            style = {...style,
                flex: 1,
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'center',
                width: '100%'
            };

            if(!this.props.pydio.user){
                return <div className="vertical_fit" style={{...style, width:'100%'}}></div>;
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
                <div style={style}>
                    <div className={classNames.join(' ')} onClick={click}>
                        <span className="dl-filename"><Textfit min={12} max={25} perfectFit={false} mode="single">{name2}</Textfit></span>
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
            getMessage:React.PropTypes.func,
            showSearchForm: React.PropTypes.bool
        },

        getChildContext: function() {
            const messages = this.props.pydio.MessageHash;
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

            const styles = {
                appBarStyle : {
                    zIndex: 1,
                    backgroundColor: this.props.muiTheme.palette.primary1Color
                },
                buttonsStyle : {
                    color: this.props.muiTheme.appBar.textColor
                },
                iconButtonsStyle :{
                    color: Color(this.props.muiTheme.palette.primary1Color).darken(0.4).toString()
                },
                raisedButtonStyle : {
                    height: 30
                },
                raisedButtonLabelStyle : {
                    height: 30,
                    lineHeight: '30px'
                }
            }

            let style = {};
            if(this.props.imageBackgroundFromConfigs){
                if(PydioReactUI.BackgroundImage.SESSION_IMAGE){
                    style = PydioReactUI.BackgroundImage.SESSION_IMAGE;
                }else{
                    style = PydioReactUI.BackgroundImage.getImageBackgroundFromConfig(this.props.imageBackgroundFromConfigs);
                    PydioReactUI.BackgroundImage.SESSION_IMAGE = style;
                }
            }

            const {minisiteMode, showSearchForm} = this.props;

            if(!this.props.pydio.user){
                return <div className="vertical_fit vertical_layout" style={style}/>;
            }

            return (
                <div className="vertical_fit vertical_layout" style={style}>
                    <MaterialUI.Paper zDepth={1} rounded={false} style={styles.appBarStyle}>
                        {minisiteMode !== 'embed' &&
                            <div id="workspace_toolbar" style={{display:'flex'}}>
                                <Breadcrumb {...this.props} rootStyle={{padding: (showSearchForm ? 14 : 8), maxWidth:null}}/>
                                {showSearchForm && <SearchForm {...this.props} uniqueSearchScope="ws"/>}
                            </div>
                        }
                        <div id="main_toolbar" style={{display:'flex', padding: '0 8px'}}>
                            <ButtonMenu {...this.props} id="create-button-menu" toolbars={["upload", "create"]} buttonTitle="New..." raised={true} secondary={true} controller={this.props.pydio.Controller}/>
                            <Toolbar {...this.props} id="main-toolbar" toolbars={["info_panel"]} groupOtherList={["change_main", "more", "change", "remote"]} renderingType="button" buttonStyle={styles.buttonsStyle}/>
                            <div style={{flex:1}}></div>
                            <ListPaginator id="paginator-toolbar" dataModel={this.props.pydio.getContextHolder()} toolbarDisplay={true}/>
                            <Toolbar {...this.props} id="display-toolbar" toolbars={["display_toolbar"]} renderingType="icon-font" buttonStyle={styles.iconButtonsStyle}/>
                        </div>
                    </MaterialUI.Paper>
                    {this.props.children}
                    <span className="context-menu"><PydioComponents.ContextMenu pydio={this.props.pydio}/></span>
                </div>
            );

        }

    });

    if(window.UploaderModel){
        StandardLayout = window.UploaderModel.DropProvider(StandardLayout);
    }

    const FolderMinisite = React.createClass({

        render: function(){

            return (
                <StandardLayout {...this.props} showSearchForm={this.props.pydio.getPluginConfigs('action.share').get('SHARED_FOLDER_SHOW_SEARCH')}>
                    <MainFilesList ref="list" {...this.props}/>
                    <EditionPanel {...this.props}/>
                </StandardLayout>
            );

        }

    });

    const InlineEditor = React.createClass({

        getInitialState: function(){
            return {node: this.props.node};
        },

        componentDidMount: function(){
            this.props.pydio.UI.registerEditorOpener(this);
        },

        componentWillUnmount: function(){
            this.props.pydio.UI.unregisterEditorOpener(this);
        },

        openEditorForNode: function(node, editorData){
            this.setState({node, editorData});
        },

        _getEditorData: function(node) {
            const {pydio} = this.props;
            const selectedMime = PathUtils.getAjxpMimeType(node);
            const editors = pydio.Registry.findEditorsForMime(selectedMime, false);
            if (editors.length && editors[0].openable){
                return editors[0];
            }
        },

        render: function(){
            const {pydio} = this.props;
            const {node, editorData} = this.state;
            return (
                <ReactEditorOpener
                    pydio={pydio}
                    node={node}
                    registry={pydio.Registry}
                    editorData={editorData || this._getEditorData(node)}
                />
            );
        }

    });

    const FileMinisite = React.createClass({

        mixins: [UniqueNodeTemplateMixin],

        componentDidMount: function(){
            this.detectFirstNode();
        },

        _getEditorData: function(node) {
            const selectedMime = PathUtils.getAjxpMimeType(node);
            const editors = this.props.pydio.Registry.findEditorsForMime(selectedMime, false);
            if (editors.length && editors[0].openable){
                return editors[0];
            }
        },

        render: function(){

            let node = this.state && this.state.node ?  this.state.node : null;

            let content;
            if(node){
                content = (
                    <div className="editor_container vertical_layout vertical_fit" style={{backgroundColor:'white'}}>
                        <InlineEditor
                            pydio={this.props.pydio}
                            node={node}
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

    const DropZoneMinisite = React.createClass({

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

    const FilmStripMinisite = React.createClass({

        mixins: [UniqueNodeTemplateMixin],

        componentDidMount: function(){
            this.detectFirstNode(true);
        },

        render: function(){


            let node = this.state && this.state.node ?  this.state.node : null;

            let editor;
            if(node){
                editor = (
                    <InlineEditor
                        pydio={this.props.pydio}
                        node={node}
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

    global.ShareTemplates = {
        FolderMinisite      : MaterialUI.Style.muiThemeable()(FolderMinisite),
        FileMinisite        : MaterialUI.Style.muiThemeable()(FileMinisite),
        DLTemplate          : MaterialUI.Style.muiThemeable()(DLTemplate),
        DropZoneMinisite    : MaterialUI.Style.muiThemeable()(DropZoneMinisite),
        FilmStripMinisite   : MaterialUI.Style.muiThemeable()(FilmStripMinisite)
    };

})(window);
