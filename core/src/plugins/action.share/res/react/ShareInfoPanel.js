(function(global){

    class Loader{

        static loadInfoPanel(container, node){
            let mainCont = container.querySelectorAll("#ajxp_shared_info_panel .infoPanelTable")[0];
            mainCont.destroy = function(){
                React.unmountComponentAtNode(mainCont);
            };
            mainCont.className += (mainCont.className ? ' ' : '') + 'infopanel-destroyable-pane';
            React.render(
                React.createElement(InfoPanel, {pydio:global.pydio, node:node}),
                mainCont
            );
        }
    }

    const InfoPanelInputRow = React.createClass({

        render: function(){
            return (
                <div className="infoPanelRow">
                    <PydioComponents.ClipboardTextField {...this.props} floatingLabelText={this.props.getMessage(this.props.inputTitle)}/>
                </div>
            );
        }

    });


    const TemplatePanel = React.createClass({

        propTypes: {
            node:React.PropTypes.instanceOf(AjxpNode),
            pydio:React.PropTypes.instanceOf(Pydio),
            getMessage:React.PropTypes.func,
            publicLink:React.PropTypes.string
        },

        getInitialState:function(){
            return {show: false};
        },

        generateTplHTML: function(){

            let editors = this.props.pydio.Registry.findEditorsForMime(this.props.node.getAjxpMime(), true);
            if(!editors.length){
                return null;
            }
            let newLink = ReactModel.Share.buildDirectDownloadUrl(this.props.node, this.props.publicLink, true);
            let editor = FuncUtils.getFunctionByName(editors[0].editorClass, global);
            if(editor && editor.getSharedPreviewTemplate){
                return {
                    messKey:61,
                    templateString:editor.getSharedPreviewTemplate(this.props.node, newLink, {WIDTH:350, HEIGHT:350, DL_CT_LINK:newLink})
                };
            }else{
                return{
                    messKey:60,
                    templateString:newLink
                }
            }

        },

        render : function(){
            let data = this.generateTplHTML();
            if(!data){
                return null;
            }
            return <InfoPanelInputRow
                inputTitle={data.messageKey}
                inputValue={data.templateString}
                inputClassName="share_info_panel_link"
                getMessage={this.props.getMessage}
                inputCopyMessage="229"
            />;
        }

    });

    const InfoPanel = React.createClass({

        propTypes: {
            node:React.PropTypes.instanceOf(AjxpNode),
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        getInitialState: function(){
            return {
                status:'loading',
                model : new ReactModel.Share(this.props.pydio, this.props.node)
            };
        },
        componentDidMount:function(){
            this.state.model.observe("status_changed", this.modelUpdated);
            this.state.model.initLoad();
        },

        modelUpdated: function(){
            if(this.isMounted()){
                this.setState({status:this.state.model.getStatus()});
            }
        },

        getMessage: function(id){
            try{
                return this.props.pydio.MessageHash['share_center.' + id];
            }catch(e){
                return id;
            }
        },

        render: function(){
            if(this.state.model.hasPublicLink()){
                var linkData = this.state.model.getPublicLinks()[0];
                var isExpired = linkData["is_expired"];

                // Main Link Field
                var linkField = (<InfoPanelInputRow
                    inputTitle="121"
                    inputValue={linkData['public_link']}
                    inputClassName={"share_info_panel_link" + (isExpired?" share_info_panel_link_expired":"")}
                    getMessage={this.getMessage}
                    inputCopyMessage="192"
                />);
                if(this.props.node.isLeaf() && this.props.pydio.getPluginConfigs("action.share").get("INFOPANEL_DISPLAY_DIRECT_DOWNLOAD")){
                    // Direct Download Field
                    var downloadField = <InfoPanelInputRow
                        inputTitle="60"
                        inputValue={ReactModel.Share.buildDirectDownloadUrl(this.props.node, linkData['public_link'])}
                        inputClassName="share_info_panel_link"
                        getMessage={this.getMessage}
                        inputCopyMessage="192"
                    />;
                }
                if(this.props.node.isLeaf() && this.props.pydio.getPluginConfigs("action.share").get("INFOPANEL_DISPLAY_HTML_EMBED")){
                    // HTML Code Snippet (may be empty)
                    var templateField = <TemplatePanel
                        {...this.props}
                        getMessage={this.getMessage}
                        publicLink={linkData.public_link}
                    />;
                }
            }
            var users = this.state.model.getSharedUsers();
            var sharedUsersEntries = [], remoteUsersEntries = [];
            if(users.length){
                sharedUsersEntries = users.map(function(u){
                    var rights = [];
                    if(u.RIGHT.indexOf('r') !== -1) rights.push(global.MessageHash["share_center.31"]);
                    if(u.RIGHT.indexOf('w') !== -1) rights.push(global.MessageHash["share_center.181"]);
                    return (
                        <div key={u.ID} className="uUserEntry">
                            <span className="uLabel">{u.LABEL}</span>
                            <span className="uRight">{rights.join(' & ')}</span>
                        </div>
                    );
                });
            }
            var ocsLinks = this.state.model.getOcsLinks();
            if(ocsLinks.length){
                remoteUsersEntries = ocsLinks.map(function(link){
                    var i = link['invitation'];
                    var status;
                    if(!i){
                        status = '214';
                    }else {
                        if(i.STATUS == 1){
                            status = '211';
                        }else if(i.STATUS == 2){
                            status = '212';
                        }else if(i.STATUS == 4){
                            status = '213';
                        }
                    }
                    status = this.getMessage(status);

                    return (
                        <div key={"remote-"+link.hash} className="uUserEntry">
                            <span className="uLabel">{i.USER} @ {i.HOST}</span>
                            <span className="uStatus">{status}</span>
                        </div>
                    );
                }.bind(this));
            }
            if(sharedUsersEntries.length || remoteUsersEntries.length){
                var sharedUsersBlock = (
                    <div className="infoPanelRow">
                        <div className="infoPanelLabel">{this.getMessage('54')}</div>
                        <div className="infoPanelValue">
                            {sharedUsersEntries}
                            {remoteUsersEntries}
                        </div>
                    </div>
                );
            }
            if(this.state.model.getStatus() !== 'loading' && !sharedUsersEntries.length
                && !remoteUsersEntries.length && !this.state.model.hasPublicLink()){
                let func = function(){
                    this.state.model.stopSharing();
                }.bind(this);
                var noEntriesFoundBlock = (
                    <div className="infoPanelRow">
                        <div className="infoPanelValue">{this.getMessage(232)} <a style={{textDecoration:'underline',cursor:'pointer'}} onClick={func}>{this.getMessage(233)}</a></div>
                    </div>
                );
            }

            return (
                <div>
                    {linkField}
                    {downloadField}
                    {templateField}
                    {sharedUsersBlock}
                    {noEntriesFoundBlock}
                </div>
            );
        }

    });

    const ReactInfoPanel = React.createClass({

        render: function(){

            let actions = [
                <ReactMUI.FlatButton
                    key="edit-share"
                    label="Edit share"
                    secondary={true}
                    onClick={()=>{global.pydio.getController().fireAction("share-edit-shared");}}
                />
            ];

            return (
                <PydioDetailPanes.InfoPanelCard title="Shared" actions={actions} icon="share-variant" iconColor="#009688" iconStyle={{fontSize:13, display:'inline-block', paddingTop:3}}>
                    <InfoPanel {...this.props}/>
                </PydioDetailPanes.InfoPanelCard>
            );

        }

    });

    global.ShareInfoPanel = {};
    global.ShareInfoPanel.loader = Loader.loadInfoPanel;
    global.ShareInfoPanel.InfoPanel = ReactInfoPanel;


})(window);
