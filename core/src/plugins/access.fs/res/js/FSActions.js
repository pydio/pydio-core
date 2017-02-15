(function(global){

    let pydio = global.pydio;

    class Callbacks {

        static mkdir(){
            pydio.UI.modal.showDialogForm('Create', 'mkdir_form', function(form){
                if(form.down('a.create_file_alt_link')){
                    form.down('a.create_file_alt_link').observe('click', function(){
                        pydio.getController().fireAction('mkfile');
                    });
                }
            }, function(){
                var oForm = $(pydio.UI.modal.getForm());
                var elementToCheck=(oForm['dirname']);
                if(pydio.getContextHolder().fileNameExists($(elementToCheck).getValue()))
                {
                    alert(MessageHash[125]);
                    return false;
                }
                PydioApi.getClient().submitForm(oForm);
                hideLightBox(true);
                return false;
            });
        }

        static mkfile(){
            pydio.UI.modal.showDialogForm('Create', 'mkfile_form', null, function(){
                var oForm = $(pydio.UI.modal.getForm());
                var elementToCheck=(oForm['filename']);
                if(pydio.getContextHolder().fileNameExists($(elementToCheck).getValue()))
                {
                    alert(MessageHash[125]);
                    return false;
                }
                PydioApi.getClient().submitForm(oForm);
                hideLightBox(true);
                return false;
            });
        }

        static rename(){
            if(pydio.getUserSelection()){
                var orig = pydio.getUserSelection().getSelectionSource();
            }
            var callback = function(node, newValue){
                if(!node) node = pydio.getUserSelection().getUniqueNode();
                var filename = node.getPath();
                var conn = new Connexion();
                conn.addParameter('get_action', 'rename');
                conn.addParameter('file', filename);
                conn.addParameter('filename_new', newValue);
                conn.onComplete = function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                    if(orig && pydio.getUserSelection()){
                        pydio.getUserSelection().setSelectionSource(orig);
                    }
                };
                conn.sendAsync();
            };
            var found = false;
            if(pydio.getUserSelection() && pydio.getUserSelection().getSelectionSource() && pydio.getUserSelection().getSelectionSource().switchCurrentLabelToEdition) {
                pydio.getUserSelection().getSelectionSource().switchCurrentLabelToEdition(callback);
                found = true;
            }else{
                var test = pydio.UI._focusables.detect(function(obj){return obj.hasFocus;});
                if (test && test.switchCurrentLabelToEdition){
                    test.switchCurrentLabelToEdition(callback);
                    found = true;
                }
            }
            if(!found){
                var n = pydio.getUserSelection().getSelectedNodes()[0];
                if(n){
                    let res = n.notify("node_action", {type:"prompt-rename", callback:(value)=>{callback(n, value);}});
                    if((!res || res[0] !== true) && n.getParent()){
                        n.getParent().notify("child_node_action", {type:"prompt-rename", child:n, callback:(value)=>{callback(n, value);}});
                    }
                }
            }
        }
        
        static copy(){

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
                    if(!user.canWrite()){
                        var nodeProvider = new RemoteNodeProvider();
                        nodeProvider.initProvider({tmp_repository_id:firstKey});
                        var rootNode = new AjxpNode("/", false, MessageHash[373], "folder.png", nodeProvider);
                        this.treeSelector.load(rootNode);
                    }else{
                        this.treeSelector.load();
                    }
                    this.treeSelector.setFilterShow(true);
                    reposList.each(function(pair){
                        this.treeSelector.appendFilterValue(pair.key, pair.value);
                    }.bind(this));
                    if(user.canWrite()) this.treeSelector.appendFilterValue(activeRepository, "&lt;"+MessageHash[372]+"&gt;", 'top');
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

        static move(){
            var context = pydio.getController();
            if(pydio.user){
                var user = pydio.user;
                var activeRepository = user.getActiveRepository();
            }
            var context = pydio.getController();
            var onLoad = function(oForm){
                var getAction = oForm.select('input[name="get_action"]')[0];
                getAction.value = 'move';
                this.treeSelector = new TreeSelector(oForm, {
                    nodeFilter : function(ajxpNode){
                        return (!ajxpNode.isLeaf() && !ajxpNode.hasMetadataInBranch("ajxp_readonly", "true"));
                    }
                });
                this.treeSelector.load();
                if(!pydio.getUserSelection().hasDir() && user && user.canCrossRepositoryCopy() && user.hasCrossRepositories()){
                    this.treeSelector.setFilterShow(true);
                    ProtoCompat.map2hash(user.getCrossRepositories()).each(function(pair){
                        this.treeSelector.appendFilterValue(pair.key, pair.value.getLabel());
                    }.bind(this));
                    this.treeSelector.appendFilterValue(activeRepository, "&lt;"+MessageHash[372]+"&gt;", 'top');
                    this.treeSelector.setFilterSelectedIndex(0);
                    this.treeSelector.setFilterChangeCallback(function(e){
                        var externalRepo = this.filterSelector.getValue();
                        var nodeProvider = new RemoteNodeProvider();
                        nodeProvider.initProvider({tmp_repository_id:externalRepo});
                        this.resetAjxpRootNode(new AjxpNode("/", false, MessageHash[373], "folder.png", nodeProvider));
                    });
                }
            }.bind(context);
            var onCancel = function(){
                this.treeSelector.unload();
                hideLightBox();
            }.bind(context);
            var onSubmit = function(){
                var oForm = modal.getForm();
                var getAction = oForm.down('input[name="get_action"]');
                var selectedNode = this.treeSelector.getSelectedNode();
                if(!this.treeSelector.getFilterActive(activeRepository) && selectedNode == pydio.getContextNode().getPath()){
                    alert(MessageHash[183]);
                    return false;
                }
                pydio.getUserSelection().updateFormOrUrl(oForm);
                if(activeRepository && this.treeSelector.getFilterActive(activeRepository)){
                    getAction.value = "cross_copy" ;
                    var subAction = new Element('input', {type:'hidden',name:'moving_files',value:'true'});
                    oForm.insert(subAction);
                    this.submitForm(oForm, false, function(transport){
                        var res = this.parseXmlMessage(transport.responseXML);
                        if(!res) return;

                        pydio.fireContextRefresh();
                    }.bind(this));
                }else{
                    this.submitForm(oForm);
                }
                this.treeSelector.unload();
                hideLightBox();
            }.bind(context);
            modal.showDialogForm('Move/Copy', 'copymove_form', onLoad, onSubmit, onCancel);

        }

        static deleteAction(){
            var onLoad = function(oForm){
                var message = MessageHash[177];
                var repoHasRecycle = pydio.getContextHolder().getRootNode().getMetadata().get("repo_has_recycle");
                if(repoHasRecycle && pydio.getContextNode().getAjxpMime() != "ajxp_recycle"){
                    message = MessageHash[176];
                }
                $(oForm).getElementsBySelector('span[id="delete_message"]')[0].innerHTML = message;
            };
            modal.showDialogForm('Delete', 'delete_form', onLoad, function(){
                var oForm = modal.getForm();
                pydio.getUserSelection().updateFormOrUrl(oForm);
                PydioApi.getClient().submitForm(oForm, true, function(transport){
                    var result = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                }.bind(pydio.getController()));
                hideLightBox(true);
                return false;
            });
        }
        
        static chmod(){
            var userSelection =  pydio.getUserSelection();
            var loadFunc = function(oForm){
                pydio.getController().propertyPane = new PropertyPanel(userSelection, oForm);
            };
            var completeFunc = function(){
                if(!pydio.getController().propertyPane.valueChanged()){
                    hideLightBox();
                    return false;
                }
                userSelection.updateFormOrUrl(modal.getForm());
                PydioApi.getClient().submitForm(modal.getForm());
                hideLightBox();
                return false;
            };
            modal.showDialogForm('Edit Online', 'properties_box', loadFunc, completeFunc);
        }

        static upload(manager, uploaderArguments){
            var uploaders = pydio.Registry.getActiveExtensionByType("uploader");
            uploaders.sort(function(objA, objB){
                return objA.order - objB.order;
            });
            if(!uploaders.length) return;

            let uploader;
            if(uploaderArguments && uploaderArguments.length) {
                uploader = uploaderArguments[0];
            }else{
                uploader = uploaders[0];
            }
            if(pydio.getController().getActionByName("trigger_remote_copy")){
                pydio.UI.modal.setCloseAction(function(){
                    pydio.fireContextRefresh();
                    PydioApi.getClient().request({get_action:'trigger_remote_copy'});
                });
            }
            if(uploader.dialogOnOpen){
                uploader.resourcesManager.load();
                var dialogOnOpen = new Function("oForm", uploader.dialogOnOpen);
            }
            if(uploader.dialogOnComplete){
                uploader.resourcesManager.load();
                var dialogOnComplete = new Function("oForm", uploader.dialogOnComplete);
                pydio.UI.modal.setCloseAction(dialogOnComplete);
            }
            var dialogOpen = function(form){
                if (form.down('#uploader_chooser')) form.down('#uploader_chooser').remove();
                var chooser = new Element('div', {id:'uploader_chooser'});
                form.insert({top:chooser});
                var chooserList = new Element('ul');
                chooser.insert(chooserList);
                uploaders.each(function(up){
                    var label = up.xmlNode.getAttribute("label");
                    var desc = up.xmlNode.getAttribute("description")
                    var item = new Element('li', {title:desc}).update(label).observe("click", function(){
                        pydio.getController().fireAction("upload", up);
                    });
                    if(up == uploader) item.addClassName('current');
                    chooserList.insert(item);
                });
                if(uploader.dialogOnOpen){
                    uploader.resourcesManager.load();
                    var dialogOnOpen = new Function("oForm", uploader.dialogOnOpen);
                    dialogOnOpen(form);
                }
            };
            pydio.UI.modal.showDialogForm('Upload', uploader.formId, dialogOpen, null, dialogOnComplete, true, true);

        }

        static download(){
            var userSelection = pydio.getUserSelection();
            if(( userSelection.isUnique() && !userSelection.hasDir() ) || pydio.Parameters.get('multipleFilesDownloadEnabled'))
            {
                if(window.gaTrackEvent){
                    var fileNames = userSelection.getFileNames();
                    for(var i=0; i<fileNames.length;i++){
                        window.gaTrackEvent("Data", "Download", fileNames[i]);
                    }
                }
                PydioApi.getClient().downloadSelection(userSelection, $('download_form'), 'download');
            }
            else
            {
                var loadFunc = function(oForm){
                    var dObject = oForm.getElementsBySelector('div[id="multiple_download_container"]')[0];
                    var downloader = new MultiDownloader(dObject, ajxpServerAccessPath+'&action=download&file=');
                    downloader.triggerEnd = function(){hideLightBox()};
                    var fileNames = userSelection.getFileNames();
                    for(var i=0; i<fileNames.length;i++)
                    {
                        downloader.addListRow(fileNames[i]);
                    }
                };
                var closeFunc = function(){
                    hideLightBox();
                    return false;
                };
                modal.showDialogForm('Download Multiple', 'multi_download_form', loadFunc, closeFunc, null, true);
            }
        }
        
        static downloadAll(){
            var dm = pydio.getContextHolder();
            dm._selectedNodes = $A([dm.getRootNode()]);
            dm._bEmpty = dm._bDir = true; dm._bFile = false;
            dm.publish("selection_changed", dm);
            window.setTimeout(function(){
                pydio.getController().fireAction("download");
            }, 200);

        }

        static downloadChunked(){
            
            var userSelection = pydio.getUserSelection();

            var loadFunc = function(oForm){
                var dObject = oForm.down('div[id="multiple_download_container"]');
                var legendDiv = oForm.down('div.dialogLegend');
                legendDiv.next("br").remove();
                legendDiv.update(MessageHash[399]+'<br>'+MessageHash[401]+'<a href="'+MessageHash[402]+'" target="_blank">'+MessageHash[402]+'</a>');
                dObject.insert({before:'\
							<div id="chunk_dl_form" style="height:36px;"> \
								<span style="display:inline-block;float:left;margin-top: 11px;margin-left: 4px;margin-right: 4px;">'+MessageHash[400]+'</span> <input type="text" style="float:left;margin-top:5px; text-align:right; width:30px;height:24px;" name="chunk_count" id="chunk_count" value="4"> \
								<a id="dl_form_submit" class="dialogButton dialogFocus">OK</a>\
							</div> \
						'});
                $("dl_form_submit").observe("click", function(e){
                    Event.stop(e);
                    var conn = new Connexion();
                    conn.addParameter("get_action", "prepare_chunk_dl");
                    conn.addParameter("chunk_count", $("chunk_count").value );
                    conn.addParameter("file", userSelection.getUniqueNode().getPath());
                    var downloader = new MultiDownloader(dObject, '');
                    conn.onComplete = function(transp){
                        var chunkData = transp.responseJSON;
                        downloader.setDownloadUrl(ajxpServerAccessPath+'&action=download_chunk&file_id='+chunkData.file_id);
                        downloader.triggerEnd = function(){hideLightBox();};
                        for(var i=0; i<chunkData.chunk_count;i++){
                            downloader.addListRow("&chunk_index=" + i, chunkData.localname + " (part " + (i + 1) + ")");
                        }
                        downloader.removeOnLoad();
                    };
                    downloader.setOnLoad();
                    conn.sendAsync();
                });
            };
            var closeFunc = function(){
                hideLightBox();
                return false;
            };
            modal.showDialogForm('Download Multiple', 'multi_download_form', loadFunc, closeFunc, null, true);


        }

        static emptyRecycle(){
            modal.showDialogForm('EmptyRecycle', 'empty_recycle_form', null, function(){
                PydioApi.getClient().request({get_action:'empty_recycle'}, function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                });
                hideLightBox(true);
                return false;
            });
        }

        static restore(){
            var userSelection = pydio.getUserSelection();
            var fileNames = $A(userSelection.getFileNames());
            var connexion = new Connexion();
            connexion.addParameter('get_action', 'restore');
            connexion.addParameter('dir', userSelection.getContextNode().getPath());
            connexion.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            };
            fileNames.each(function(filename){
                connexion.addParameter('file', filename);
                connexion.sendAsync();
            });
        }

        static compressUI(){
            var userSelection = pydio.getUserSelection();
            if((multipleFilesDownloadEnabled))
            {
                var loadFunc = function(oForm){
                    var zipName;
                    if(userSelection.isUnique()){
                        zipName = getBaseName(userSelection.getUniqueFileName());
                        if(!userSelection.hasDir()) zipName = zipName.substr(0, zipName.lastIndexOf("\."));
                    }else{
                        zipName = getBaseName(userSelection.getContextNode().getPath());
                        if(zipName == "") zipName = "Archive";
                    }
                    var index=1;
                    var buff = zipName;
                    while(userSelection.fileNameExists(zipName + ".zip")){
                        zipName = buff + "-" + index; index ++ ;
                    }
                    oForm.select('input[id="archive_name"]')[0].value = zipName + ".zip" ;
                };
                var closeFunc = function(){
                    userSelection.updateFormOrUrl(modal.getForm());
                    PydioApi.getClient().submitForm(modal.getForm(), true);
                    hideLightBox();
                };
                modal.showDialogForm('Compress selection to ...', 'compress_form', loadFunc, closeFunc);
            }
        }

        static openInEditor(manager, otherArguments){
            var editorData = otherArguments[0];
            pydio.UI.openCurrentSelectionInEditor(editorData);
        }
        
        static ajxpLink(){
            var loadFunc = function (oForm){
                var link;
                var input = oForm.down('input[type="text"]');
                var url = document.location.href;
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
                input.value = link;
                var email = oForm.down('a[id="email"]');
                if (email){
                    email.setAttribute('href', 'mailto:unknown@unknown.com?Subject=UPLOAD&Body='+encodeURIComponent(link));
                }
                input.select();
            };
            modal.showDialogForm('Get', 'ajxp_link_form', loadFunc, function(){
                hideLightBox(true);
                return false;
            }, null, true);
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
    }

    let ns = global.FSActions || {};
    ns.Callbacks = Callbacks;
    ns.Listeners = Listeners;
    global.FSActions = ns;

})(window);