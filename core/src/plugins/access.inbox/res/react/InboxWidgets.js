(function(global){

    var ns = global.InboxWidgets ||{};

    var LeftPanel = React.createClass({

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        getInitialState:function(){
            return {meta_filter:null};
        },

        handleChange: function(event) {
            var value = event.target.value;
            if(value) value += '*';
            document.getElementById('content_pane').ajxpPaneObject.addMetadataFilter('text', value);
        },

        filterByShareMetaType(type, event){
            if(type == '-1'){
                type = '';
            }
            this.setState({meta_filter:type});
            document.getElementById('content_pane').ajxpPaneObject.addMetadataFilter('share_meta_type', type);
        },

        render: function(){
            var messages = this.props.pydio.MessageHash;
            return (
                <div className="inbox-left-panel">
                    <h3 className="colorcode-folder">{messages['inbox_driver.6']}</h3>
                    <div>{messages['inbox_driver.7']}</div>
                    <h4>{messages['inbox_driver.8']}</h4>
                    <div>
                        <h5>{messages['inbox_driver.9']}</h5>
                        <input type="text" placeholder="Filter..." onChange={this.handleChange}/>
                    </div>
                    <div style={{paddingTop:20}}>
                        <h5><span className="clear" onClick={this.filterByShareMetaType.bind(this, '-1')}>{messages['inbox_driver.11']}</span>
                            {messages['inbox_driver.10']}
                        </h5>
                        <span className={(this.state.meta_filter === '0'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '0')}>{messages['inbox_driver.1p']}</span>
                        <span className={(this.state.meta_filter === '1'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '1')}>{messages['inbox_driver.2p']}</span>
                        <span className={(this.state.meta_filter === '2'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '2')}>{messages['inbox_driver.3p']}</span>
                    </div>
                </div>
            );
        }

    });


    function filesListCellModifier(element, ajxpNode, type, metadataDef, ajxpNodeObject){

        var messages = global.pydio.MessageHash;
        if(element != null){
            var nodeMetaValue = ajxpNode.getMetadata().get('share_meta_type');
            var nodeMetaLabel;
            if(nodeMetaValue == "0") nodeMetaLabel = messages['inbox_driver.1'];
            else if(nodeMetaValue == "1") nodeMetaLabel = messages['inbox_driver.2'];
            else if(nodeMetaValue == "2") nodeMetaLabel = messages['inbox_driver.3'];
            if(element.down('.text_label')){
                element.down('.text_label').update(nodeMetaLabel);
            }
            var mainElement;
            if(element.up('.ajxpNodeProvider')){
                mainElement = element.up('.ajxpNodeProvider');
            }else if(ajxpNodeObject){
                mainElement = ajxpNodeObject;
            }else{
                console.log(element, ajxpNodeObject);
            }
            if(mainElement){
                mainElement.addClassName('share_meta_type_' + nodeMetaValue);
            }

            if(type == 'row'){
                element.writeAttribute("data-sorter_value", nodeMetaValue);
            }else{
                element.writeAttribute("data-"+metadataDef.attributeName+"-sorter_value", nodeMetaValue);
            }

            var obj = document.getElementById('content_pane').ajxpPaneObject;
            var colIndex;
            obj.columnsDef.map(function(c, index){
                if (c.attributeName == "share_meta_type") {
                    colIndex = index;
                }
            }.bind(this));
            if(colIndex !== undefined){
                obj._sortableTable.sort(colIndex, false);
                obj._sortableTable.updateHeaderArrows();
            }
        }


    }

    let pydio = global.pydio;

    class Callbacks{

        static download(){
            var userSelection = ajaxplorer.getUserSelection();
            if(window.gaTrackEvent){
                var fileNames = userSelection.getFileNames();
                for(var i=0; i<fileNames.length;i++){
                    window.gaTrackEvent("Data", "Download", fileNames[i]);
                }
            }
            PydioApi.getClient().downloadSelection(userSelection, 'download');
        }

        static copyInbox(){
            if(pydio.user){
                var user = pydio.user;
                var activeRepository = user.getActiveRepository();
            }
            var context = pydio.getController();
            var onLoad = function(oForm){
                var getAction = oForm.select('input[name="get_action"]')[0];
                getAction.value = 'copy';
                this.treeSelector = new TreeSelector(oForm, {
                    nodeFilter : function(ajxpNode){
                        return (!ajxpNode.isLeaf() && !ajxpNode.hasMetadataInBranch("ajxp_readonly", "true"));
                    }
                });
                if(user && user.canCrossRepositoryCopy() && user.hasCrossRepositories()){
                    var firstKey ;
                    var reposList = new Hash();
                    ProtoCompat.map2hash(user.getCrossRepositories()).each(function(pair){
                        if(!firstKey) firstKey = pair.key;
                        reposList.set(pair.key, pair.value.getLabel());
                    }.bind(this));

                    var nodeProvider = new RemoteNodeProvider();
                    nodeProvider.initProvider({tmp_repository_id:firstKey});
                    var rootNode = new AjxpNode("/", false, MessageHash[373], "folder.png", nodeProvider);
                    this.treeSelector.load(rootNode);

                    this.treeSelector.setFilterShow(true);
                    reposList.each(function(pair){
                        this.treeSelector.appendFilterValue(pair.key, pair.value);
                    }.bind(this));

                    this.treeSelector.setFilterSelectedIndex(0);
                    this.treeSelector.setFilterChangeCallback(function(e){
                        var externalRepo = this.filterSelector.getValue();
                        var nodeProvider = new RemoteNodeProvider();
                        nodeProvider.initProvider({tmp_repository_id:externalRepo});
                        this.resetAjxpRootNode(new AjxpNode("/", false, MessageHash[373], "folder.png", nodeProvider));
                    });
                }else{
                    this.treeSelector.load();
                }
            }.bind(context);
            var onCancel = function(){
                this.treeSelector.unload();
                hideLightBox();
            }.bind(context);
            var onSubmit = function(){
                var oForm = modal.getForm();
                var getAction = oForm.select('input[name="get_action"]')[0];
                var selectedNode = this.treeSelector.getSelectedNode();
                if(activeRepository && this.treeSelector.getFilterActive(activeRepository)){
                    getAction.value = "cross_copy" ;
                }
                pydio.getUserSelection().updateFormOrUrl(oForm);
                this.submitForm(oForm);
                this.treeSelector.unload();
                hideLightBox();
            }.bind(context);
            modal.showDialogForm('Move/Copy', 'copymove_form', onLoad, onSubmit, onCancel);
        }

        static acceptInvitation(){
            var remoteShareId = pydio.getUserSelection().getUniqueNode().getMetadata().get("remote_share_id");
            PydioApi.getClient().request({get_action:'accept_invitation', remote_share_id:remoteShareId}, function(){
                pydio.fireContextRefresh();
            });
        }

        static rejectInvitation(){
            var remoteShareId = pydio.getUserSelection().getUniqueNode().getMetadata().get("remote_share_id");
            PydioApi.getClient().request({get_action:'reject_invitation', remote_share_id:remoteShareId}, function(){
                pydio.fireContextRefresh();
            });
        }

        static rejectRemoteShare(){
            var remoteShareId = pydio.getUserSelection().getUniqueNode().getMetadata().get("remote_share_id");
            PydioApi.getClient().request({get_action:'reject_invitation', remote_share_id:remoteShareId}, function(){
                pydio.fireContextRefresh();
            });
        }
        
        static copyContextListener(){
            this.rightsContext.write = true;
            var ajxpUser = pydio.user;
            if(ajxpUser && !ajxpUser.canWrite() && ajxpUser.canCrossRepositoryCopy() && ajxpUser.hasCrossRepositories()){
                this.rightsContext.write = false;
                pydio.getController().defaultActions['delete']('ctrldragndrop');
                pydio.getController().defaultActions['delete']('dragndrop');
            }
            if(ajxpUser && ajxpUser.canWrite() && pydio.getContextNode().hasAjxpMimeInBranch("ajxp_browsable_archive")){
                this.rightsContext.write = false;
            }
            if(pydio.getContextNode().hasAjxpMimeInBranch("ajxp_browsable_archive")){
                this.setLabel(247, 248);
                this.setIconSrc('ark_extract.png');
            }else{
                this.setLabel(66, 159);
                this.setIconSrc('editcopy.png');
            }
        }


    }

    ns.filesListCellModifier = filesListCellModifier;
    ns.LeftPanel = LeftPanel;
    global.InboxWidgets = ns;

})(window);