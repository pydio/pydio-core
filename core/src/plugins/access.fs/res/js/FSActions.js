(function(global){

    let pydio = global.pydio;
    let MessageHash = global.MessageHash;

    class Callbacks {

        static ls(){
            pydio.goTo(pydio.getUserSelection().getUniqueNode());
        }

        static mkdir(){

            let submit = function(value){
                PydioApi.getClient().request({
                    get_action:'mkdir',
                    dir: pydio.getContextNode().getPath(),
                    dirname:value
                });
            };
            pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
                dialogTitleId:154,
                legendId:155,
                fieldLabelId:173,
                submitValue:submit
            });
        }

        static mkfile(){
            let submit = function(value){
                PydioApi.getClient().request({
                    get_action:'mkfile',
                    dir: pydio.getContextNode().getPath(),
                    filename:value
                });
            };
            pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
                dialogTitleId:156,
                legendId:157,
                fieldLabelId:174,
                submitValue:submit
            });

        }

        static deleteAction(){
            let message = MessageHash[177];
            const repoHasRecycle = pydio.getContextHolder().getRootNode().getMetadata().get("repo_has_recycle");
            if(repoHasRecycle && pydio.getContextNode().getAjxpMime() != "ajxp_recycle"){
                message = MessageHash[176];
            }
            pydio.UI.openComponentInModal('PydioReactUI', 'ConfirmDialog', {
                message:message,
                dialogTitleId: 7,
                validCallback:function(){
                    PydioApi.getClient().postSelectionWithAction('delete');
                }
            });
        }

        static rename(){
            var callback = function(node, newValue){
                if(!node) node = pydio.getUserSelection().getUniqueNode();
                PydioApi.getClient().request({
                    get_action:'rename',
                    file:node.getPath(),
                    filename_new: newValue
                });
            };
            var n = pydio.getUserSelection().getSelectedNodes()[0];
            if(n){
                let res = n.notify("node_action", {type:"prompt-rename", callback:(value)=>{callback(n, value);}});
                if((!res || res[0] !== true) && n.getParent()){
                    n.getParent().notify("child_node_action", {type:"prompt-rename", child:n, callback:(value)=>{callback(n, value);}});
                }
            }
        }

        static applyCopyOrMove(type, selection, path, wsId){
            let action;
            let params = {
                dest:path
            };
            if(wsId) {
                action = 'cross_copy';
                params['dest_repository_id'] = wsId;
                if(type === 'move') params['moving_files'] = 'true';
            } else {
                action = type;
            }
            PydioApi.getClient().postSelectionWithAction(action, null, selection, params);
        }

        static copy(){
            // Todo
            // + Handle readonly rights
            // + Handle copy in same folder, move in same folder
            let selection = pydio.getUserSelection();
            let submit = function(path, wsId = null){
                Callbacks.applyCopyOrMove('copy', selection, path, wsId);
            };

            pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
                isMove: false,
                dialogTitle:MessageHash[159],
                submitValue:submit
            });

        }

        static move(controller, dndActionParameter = null){

            if(dndActionParameter && dndActionParameter instanceof PydioComponents.DNDActionParameter){
                if(dndActionParameter.getStep() === PydioComponents.DNDActionParameter.STEP_CAN_DROP){

                    if(dndActionParameter.getTarget().isLeaf()){
                       throw new Error('Cannot drop');
                    }else {
                        return false;
                    }

                }else if(dndActionParameter.getStep() === PydioComponents.DNDActionParameter.STEP_END_DRAG){
                    let selection = controller.getDataModel();
                    let path = dndActionParameter.getTarget().getPath();
                    Callbacks.applyCopyOrMove('move', selection, path);
                    return;
                }

                return;
            }

            let selection = pydio.getUserSelection();
            let submit = function(path, wsId = null){
                Callbacks.applyCopyOrMove('move', selection, path, wsId);
            };

            pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
                isMove: true,
                dialogTitle:MessageHash[160],
                submitValue:submit
            });

        }

        static upload(manager, uploaderArguments){

            pydio.UI.openComponentInModal('FSActions', 'UploadDialog');

            return;

        }

        static download(){
            const userSelection = pydio.getUserSelection();
            if(( userSelection.isUnique() && !userSelection.hasDir() ) || pydio.Parameters.get('multipleFilesDownloadEnabled')){
                PydioApi.getClient().downloadSelection(userSelection, 'download');
            } else {
                pydio.UI.openComponentInModal('FSActions', 'MultiDownloadDialog', {
                    actionName:'download',
                    selection : userSelection,
                    dialogTitleId:88
                });
            }
        }

        static downloadAll(){
            let dm = pydio.getContextHolder();
            dm.setSelectedNodes([dm.getRootNode()]);
            FSActions.download();
        }

        static downloadChunked(){

            var userSelection = pydio.getUserSelection();
            pydio.UI.openComponentInModal('FSActions', 'MultiDownloadDialog', {
                buildChunks:true,
                actionName:'download_chunk',
                chunkAction: 'prepare_chunk_dl',
                selection: userSelection
            });

        }

        static emptyRecycle(){

            pydio.UI.openComponentInModal('PydioReactUI', 'ConfirmDialog', {
                message:MessageHash[177],
                dialogTitleId: 220,
                validCallback:function(){
                    PydioApi.getClient().request({get_action:'empty_recycle'});
                }
            });

        }

        static restore(){

            PydioApi.getClient().postSelectionWithAction('restore');

        }

        static compressUI(){
            var userSelection = pydio.getUserSelection();
            if(!multipleFilesDownloadEnabled){
                return;
            }

            var zipName;
            if(userSelection.isUnique()){
                zipName = PathUtils.getBasename(userSelection.getUniqueFileName());
                if(!userSelection.hasDir()) zipName = zipName.substr(0, zipName.lastIndexOf("\."));
            }else{
                zipName = PathUtils.getBasename(userSelection.getContextNode().getPath());
                if(zipName == "") zipName = "Archive";
            }
            var index=1;
            var buff = zipName;
            while(userSelection.fileNameExists(zipName + ".zip")){
                zipName = buff + "-" + index; index ++ ;
            }

            pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
                dialogTitleId:313,
                legendId:314,
                fieldLabelId:315,
                defaultValue:zipName + '.zip',
                defaultInputSelection: zipName,
                submitValue:function(value){
                    PydioApi.getClient().postSelectionWithAction('compress', null, null, {archive_name:value});
                }
            });

        }

        static openInEditor(manager, otherArguments){
            var editorData = otherArguments && otherArguments.length ? otherArguments[0] : null;
            pydio.UI.openCurrentSelectionInEditor(editorData);
        }

        static ajxpLink(){
            let link;
            let url = global.document.location.href;
            if(url.indexOf('#') > 0){
                url = url.substring(0, url.indexOf('#'));
            }
            if(url.indexOf('?') > 0){
                url = url.substring(0, url.indexOf('?'));
            }
            var repoId = pydio.repositoryId || (pydio.user ? pydio.user.activeRepository : null);
            if(pydio.user){
                var slug = pydio.user.repositories.get(repoId).getSlug();
                if(slug) repoId = slug;
            }
            link = url + '?goto=' + repoId + encodeURIComponent(pydio.getUserSelection().getUniqueNode().getPath());

            pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
                dialogTitleId:369,
                fieldLabelId:296,
                defaultValue:link,
                submitValue:FuncUtils.Empty
            });


        }

        static chmod(){

            // TODO: Rewrite class.PropertyPanel.js to react CHMOD component
        }



    }

    class Listeners {

        static downloadSelectionChange(){

            var userSelection = pydio.getUserSelection();
            if(window.zipEnabled && window.multipleFilesDownloadEnabled){
                if((userSelection.isUnique() && !userSelection.hasDir()) || userSelection.isEmpty()){
                    this.setIconSrc('download_manager.png');
                }else{
                    this.setIconSrc('accessories-archiver.png');
                }
            }else if(userSelection.hasDir()){
                this.selectionContext.dir = false;
            }
        }

        static downloadAllInit(){

            if(!pydio.Parameters.get('zipEnabled') || !pydio.Parameters.get('multipleFilesDownloadEnabled')){
                this.hide();
                pydio.Controller.actions["delete"]("download_all");
            }

        }

        static compressUiSelectionChange(){
            var userSelection = pydio.getUserSelection();
            if(!window.zipEnabled || !window.multipleFilesDownloadEnabled){
                if(userSelection.isUnique()) this.selectionContext.multipleOnly = true;
                else this.selectionContext.unique = true;
            }
        }

        static copyContextChange(){

            this.rightsContext.write = true;
            var pydioUser = pydio.user;
            if(pydioUser && pydioUser.canRead() && pydioUser.canCrossRepositoryCopy() && pydioUser.hasCrossRepositories()){
                this.rightsContext.write = false;
                if(!pydioUser.canWrite()){
                    pydio.getController().defaultActions['delete']('ctrldragndrop');
                    pydio.getController().defaultActions['delete']('dragndrop');
                }
            }
            if(pydioUser && pydioUser.canWrite() && pydio.getContextNode().hasAjxpMimeInBranch("ajxp_browsable_archive")){
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

        static openWithDynamicBuilder(){

            let builderMenuItems = [];
            if(pydio.getUserSelection().isEmpty()){
                return builderMenuItems;
            }
            var node = pydio.getUserSelection().getUniqueNode();
            var selectedMime = PathUtils.getAjxpMimeType(node);
            var nodeHasReadonly = node.getMetadata().get("ajxp_readonly") === "true";
            var editors = pydio.Registry.findEditorsForMime(selectedMime);
            if(editors.length){
                var index = 0;
                var sepAdded = false;
                editors.forEach(function(el){
                    if(!el.openable) return;
                    if(el.write && nodeHasReadonly) return;
                    if(el.mimes.indexOf('*') > -1){
                        if(!sepAdded && index > 0){
                            builderMenuItems.push({separator:true});
                        }
                        sepAdded = true;
                    }
                    builderMenuItems.push({
                        name:el.text,
                        alt:el.title,
                        isDefault : (index == 0),
                        image:ResourcesManager.resolveImageSource(el.icon, '/images/actions/ICON_SIZE', 22),
                        icon_class: el.icon_class,
                        callback:function(e){this.apply([el]);}.bind(this)
                    });
                    index++;
                }.bind(this));
            }
            if(!index){
                builderMenuItems.push({
                    name:MessageHash[324],
                    alt:MessageHash[324],
                    image:ResourcesManager.resolveImageSource('button_cancel.png', '/images/actions/ICON_SIZE', 22),
                    callback:function(e){}
                } );
            }
            return builderMenuItems;

        }

    }

    let UploadDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: 'Upload',
                dialogSize: 'lg',
                dialogPadding: false,
                dialogIsModal: true
            };
        },

        submit(){
            this.dismiss();
        },

        render: function(){
            let tabs = [];
            let uploaders = pydio.Registry.getActiveExtensionByType("uploader");
            const dismiss = () => {this.dismiss()};

            uploaders.sort(function(objA, objB){
                return objA.order - objB.order;
            });

            uploaders.map(function(uploader){
                if(uploader.moduleName) {
                    let parts = uploader.moduleName.split('.');
                    tabs.push(
                        <MaterialUI.Tab label={uploader.xmlNode.getAttribute('label')} key={uploader.id}>
                            <PydioReactUI.AsyncComponent
                                pydio={pydio}
                                namespace={parts[0]}
                                componentName={parts[1]}
                                onDismiss={dismiss}
                            />
                        </MaterialUI.Tab>
                    );
                }
            });

            return (
                <MaterialUI.Tabs>
                    {tabs}
                </MaterialUI.Tabs>
            );
        }

    });

    let TreeDialog = React.createClass({

        propTypes:{
            isMove:React.PropTypes.bool.isRequired,
            submitValue:React.PropTypes.func.isRequired
        },

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: 'Copy Selection to...',
                dialogIsModal: true
            };
        },

        submit(){
            this.props.submitValue(this.state.selectedNode.getPath(), (this.state.wsId === '__CURRENT__' ? null : this.state.wsId));
            this.dismiss();
        },

        getInitialState: function(){
            let dm = new PydioDataModel();
            var nodeProvider = new RemoteNodeProvider();
            let root = new AjxpNode('/', false, 'ROOT', '', nodeProvider);
            nodeProvider.initProvider({});
            dm.setAjxpNodeProvider(nodeProvider);
            dm.setRootNode(root);
            root.load();
            return{
                dataModel: dm,
                selectedNode: root,
                wsId:'__CURRENT__'
            }
        },

        onNodeSelected: function(n){
            n.load();
            this.setState({
                selectedNode: n
            })
        },

        createNewFolder: function(){
            let parent = this.state.selectedNode;
            let nodeName = this.refs.newfolder_input.getValue();
            let oThis = this;

            PydioApi.getClient().request({
                get_action:'mkdir',
                dir: parent.getPath(),
                dirname:nodeName
            }, function(){
                let fullpath = parent.getPath() + '/' + nodeName;
                parent.observeOnce('loaded', function(){
                    let n = parent.getChildren().get(fullpath);
                    if(n) oThis.setState({selectedNode:n});
                });
                global.setTimeout(function(){
                    parent.reload();
                }, 500);
                oThis.setState({newFolderFormOpen: false});
            });

        },

        handleRepositoryChange: function(event, index, value){
            let dm = new PydioDataModel();
            var nodeProvider = new RemoteNodeProvider();
            let root = new AjxpNode('/', false, 'ROOT', '', nodeProvider);
            if(value === '__CURRENT__'){
                nodeProvider.initProvider({});
            }else{
                nodeProvider.initProvider({tmp_repository_id: value});
            }
            dm.setAjxpNodeProvider(nodeProvider);
            dm.setRootNode(root);
            root.load();
            this.setState({dataModel:dm, selectedNode: root, wsId: value});
        },

        render: function(){
            let openNewFolderForm = function(){
                this.setState({newFolderFormOpen: !this.state.newFolderFormOpen});
            }.bind(this)

            let user = pydio.user;
            let wsSelector ;
            if(user && user.canCrossRepositoryCopy() && user.hasCrossRepositories()){
                let items = [
                    <MaterialUI.MenuItem key={'current'} value={'__CURRENT__'} primaryText={"Current Workspace"} />
                ];
                user.getCrossRepositories().forEach(function(repo, key){
                    items.push(<MaterialUI.MenuItem key={key} value={key} primaryText={repo.getLabel()} />);
                });

                wsSelector = (
                    <div>
                        <MaterialUI.SelectField
                            style={{width:'100%'}}
                            floatingLabelText="Copy to another workspace"
                            value={this.state.wsId}
                            onChange={this.handleRepositoryChange}
                        >
                            {items}
                        </MaterialUI.SelectField>
                    </div>
                );
            }
            let openStyle = {flex:1,width:'100%'};
            let closeStyle = {width:0};
            return (
                <div>
                    {wsSelector}
                    <MaterialUI.Paper zDepth={1} style={{height: 300, overflowX:'auto'}}>
                        <PydioComponents.FoldersTree
                            pydio={pydio}
                            dataModel={this.state.dataModel}
                            onNodeSelected={this.onNodeSelected}
                            showRoot={true}
                            draggable={false}
                        />
                    </MaterialUI.Paper>
                    <div style={{display:'flex',alignItems:'baseline'}}>
                        <MaterialUI.TextField
                            style={{flex:1,width:'100%'}}
                            floatingLabelText="Selected files will be moved to ..."
                            ref="input"
                            value={this.state.selectedNode.getPath()}
                            disabled={true}
                        />
                        <MaterialUI.Paper zDepth={this.state.newFolderFormOpen ? 0 : 1} circle={true}>
                            <MaterialUI.IconButton
                                iconClassName="mdi mdi-folder-plus"
                                tooltip="Create folder"
                                onClick={openNewFolderForm}
                            />
                        </MaterialUI.Paper>
                    </div>
                    <MaterialUI.Paper
                        className="bezier-transitions"
                        zDepth={0}
                        style={{
                            height:this.state.newFolderFormOpen?80:0,
                            overflow:'hidden',
                            paddingTop: this.state.newFolderFormOpen?10:0,
                            display:'flex',
                            alignItems:'baseline'
                        }}
                    >
                        <MaterialUI.TextField hintText="New folder" ref="newfolder_input" style={{flex:1}}/>
                        <MaterialUI.RaisedButton style={{marginLeft:10, marginRight:2}} label="OK" onClick={this.createNewFolder}/>
                    </MaterialUI.Paper>
                </div>
            );
        }

    });

    let MultiDownloadDialog = React.createClass({

        propTypes:{
            actionName:React.PropTypes.string,
            selection: React.PropTypes.instanceOf(PydioDataModel),
            buildChunks: React.PropTypes.bool
        },

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitleId: 88,
                dialogIsModal: true
            };
        },
        getInitialState: function(){
            if(!this.props.buildChunks){
                let nodes = new Map();
                this.props.selection.getSelectedNodes().map(function(node){
                    nodes.set(node.getPath(), node.getLabel());
                });
                return {nodes: nodes};
            }else{
                return {uniqueChunkNode: this.props.selection.getUniqueNode()};
            }
        },
        submit(){
            this.dismiss();
        },
        removeNode: function(nodePath, event){
            let nodes = this.state.nodes;
            nodes.delete(nodePath);
            if(!nodes.size){
                this.dismiss();
            }else{
                this.setState({nodes: nodes});
            }
        },
        performChunking: function(){
            PydioApi.getClient().request({
                get_action:this.props.chunkAction,
                chunk_count:this.refs.chunkCount.getValue(),
                file:this.state.uniqueChunkNode.getPath()
            }, function(transport){
                this.setState({chunkData: transport.responseJSON});
            }.bind(this));
        },
        render: function(){
            let rows = [];
            let chunkAction;
            if(!this.props.buildChunks){
                const baseUrl = pydio.Parameters.get('ajxpServerAccess')+'&get_action='+this.props.actionName+'&file=';
                this.state.nodes.forEach(function(nodeLabel, nodePath){
                    rows.push(
                        <div>
                            <a key={nodePath} href={baseUrl + nodePath} onClick={this.removeNode.bind(this, nodePath)}>{nodeLabel}</a>
                        </div>
                    );
                }.bind(this));
            } else if(!this.state.chunkData){
                chunkAction = (
                    <div>
                        <MaterialUI.TextField floatingLabelText="Chunk Count" ref="chunkCount"/>
                        <MaterialUI.RaisedButton label="Chunk" onClick={this.performChunking}/>
                    </div>
                );
            } else{
                const chunkData = this.state.chunkData;
                const baseUrl = pydio.Parameters.get('ajxpServerAccess')+'&get_action='+this.props.actionName+'&file_id=' + chunkData.file_id;
                for(var i=0; i<chunkData.chunk_count;i++){
                    rows.push(<div><a href={baseUrl + "&chunk_index=" + i}>{chunkData.localname + " (part " + (i + 1) + ")"}</a></div>);
                }
            }
            return (
                <div>
                    {chunkAction}
                    <div>{rows}</div>
                </div>
            );
        }

    });


    let ns = global.FSActions || {};
    if(pydio.UI.openComponentInModal){
        ns.Callbacks = Callbacks;
    }else{
        ns.Callbacks = LegacyCallbacks;
    }
    ns.Listeners = Listeners;

    ns.MultiDownloadDialog = MultiDownloadDialog;

    ns.TreeDialog = TreeDialog;
    ns.UploadDialog = UploadDialog;
    global.FSActions = ns;

})(window);
