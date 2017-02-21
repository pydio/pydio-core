(function(global){

    let pydio = global.pydio;
    let MessageHash = global.MessageHash;

    if(pydio.UI.modalSupportsComponents){

    }

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
            pydio.UI.openComponentInModal('FSActions', 'PromptDialog', {
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
            pydio.UI.openComponentInModal('FSActions', 'PromptDialog', {
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
            pydio.UI.openComponentInModal('FSActions', 'ConfirmDialog', {
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
                dialogTitle:'Copy Selection To...',
                submitValue:submit
            });

        }

        static move(){

            let selection = pydio.getUserSelection();
            let submit = function(path, wsId = null){
                Callbacks.applyCopyOrMove('move', selection, path, wsId);
            };

            pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
                isMove: true,
                dialogTitle:'Move Selection To...',
                submitValue:submit
            });

        }

        static upload(manager, uploaderArguments){

            pydio.UI.openComponentInModal('FSActions', 'UploadDialog');

            return;

        }

        static download(){
            const userSelection = pydio.getUserSelection();
            const downloadId = Math.random();
            const props = {
                actionName:'download',
                selection : userSelection,
                downloadId: downloadId,
                dialogTitleId:88
            } ;
            if(( userSelection.isUnique() && !userSelection.hasDir() ) || pydio.Parameters.get('multipleFilesDownloadEnabled')){
                pydio.UI.openComponentInModal('FSActions', 'HiddenDownloadForm', props);
            } else {
                pydio.UI.openComponentInModal('FSActions', 'MultiDownloadDialog', props);
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

            pydio.UI.openComponentInModal('FSActions', 'ConfirmDialog', {
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

            pydio.UI.openComponentInModal('FSActions', 'PromptDialog', {
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

            pydio.UI.openComponentInModal('FSActions', 'PromptDialog', {
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

    class LegacyCallbacks {

        static ls(){
            pydio.goTo(pydio.getUserSelection().getUniqueNode());
        }

        static mkdir(){

            pydio.UI.modal.showDialogForm('Create', 'mkdir_form', function (form) {
                if (form.down('a.create_file_alt_link')) {
                    form.down('a.create_file_alt_link').observe('click', function () {
                        pydio.getController().fireAction('mkfile');
                    });
                }
            }, function () {
                var oForm = $(pydio.UI.modal.getForm());
                var elementToCheck = (oForm['dirname']);
                if (pydio.getContextHolder().fileNameExists($(elementToCheck).getValue())) {
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
            var editorData = otherArguments && otherArguments.length ? otherArguments[0] : null;
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

        static openWithDynamicBuilder(){

            let builderMenuItems = [];
            var node = pydio.getUserSelection().getUniqueNode();
            var selectedMime = PathUtils.getAjxpMimeType(node);
            var nodeHasReadonly = node.getMetadata().get("ajxp_readonly") === "true";
            var editors = pydio.Registry.findEditorsForMime(selectedMime);
            if(editors.length){
                var index = 0;
                var sepAdded = false;
                editors.each(function(el){
                    if(!el.openable) return;
                    if(el.write && nodeHasReadonly) return;
                    if(el.mimes.include('*')){
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

    let EmptyDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: "Title",
                dialogIsModal: true
            };
        },
        submit(){
            this.dismiss();
        },
        render: function(){
            return <div>Empty</div>;
        }

    });

    let UploadDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: 'Upload',
                dialogClassName:'dialog-large dialog-no-padding',
                dialogIsModal: true
            };
        },

        submit(){
            this.dismiss();
        },

        render: function(){
            let tabs = [];
            let uploaders = pydio.Registry.getActiveExtensionByType("uploader");

            uploaders.sort(function(objA, objB){
                return objA.order - objB.order;
            });

            uploaders.map(function(uploader){

                if(uploader.moduleName){
                    let parts = uploader.moduleName.split('.');
                    tabs.push(

                        <MaterialUI.Tab label={uploader.xmlNode.getAttribute('label')} key={uploader.id}>
                            <PydioReactUI.AsyncComponent
                                pydio={pydio}
                                namespace={parts[0]}
                                componentName={parts[1]}
                            />
                        </MaterialUI.Tab>
                    );
                }else{

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
                        <LeftNavigation.FoldersTree
                            pydio={pydio}
                            dataModel={this.state.dataModel}
                            onNodeSelected={this.onNodeSelected}
                            showRoot={true}
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

    let HiddenDownloadForm = React.createClass({

        propTypes:{
            actionName:React.PropTypes.string,
            selection: React.PropTypes.instanceOf(PydioDataModel),
            additionalParameters:React.PropTypes.object
        },

        componentDidUpdate: function(prevProps, prevState){
            if(!HiddenDownloadForm.LAST_DOWNLOAD || HiddenDownloadForm.LAST_DOWNLOAD !== this.props.downloadId){
                this.refs.form.submit();
            }
            HiddenDownloadForm.LAST_DOWNLOAD = this.props.downloadId;
        },

        render: function(){
            let ajxpServerAccess = pydio.Parameters.get('ajxpServerAccess');
            let inputs = new Map();
            inputs.set('secure_token', PydioApi.getClient()._secureToken);
            inputs.set('get_action', this.props.actionName);
            if(this.props.additionalParameters){
                this.props.additionalParameters.forEach(function(value, key){
                    inputs.set(key, value);
                });
            }
            let minisite_session = PydioApi.detectMinisiteSession(ajxpServerAccess);
            if(minisite_session) {
                inputs.set('minisite_session', minisite_session);
            }
            let inputFields = [];
            inputs.forEach(function(value, key){
                inputFields.push(<input type="hidden" name={key} key={key} value={value}/>);
            });
            this.props.selection.getSelectedNodes().map(function(node){
                inputFields.push(<input type="hidden" name="nodes[]" key={node.getPath()} value={node.getPath()}/>);
            });

            return (
                <div>
                    <form ref="form" action={ajxpServerAccess} target="dl_form_iframe">{inputFields}</form>
                    <iframe ref="iframe" name="dl_form_iframe"></iframe>
                </div>
            );
        }

    });

    let ConfirmDialog = React.createClass({

        propTypes: {
            message: React.PropTypes.string,
            validCallback: React.PropTypes.func
        },

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: 'Confirm',
                dialogIsModal: true
            };
        },
        submit(){
            this.props.validCallback();
            this.dismiss();
        },
        render: function(){
            return <div>{this.props.message}</div>;
        }

    });

    let PromptDialog = React.createClass({

        propTypes: {
            dialogTitleId:React.PropTypes.integer,
            legendId:React.PropTypes.integer,
            fieldLabelId:React.PropTypes.integer,
            submitValue:React.PropTypes.func.isRequired,
            defaultValue:React.PropTypes.string,
            defaultInputSelection:React.PropTypes.string
        },

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogIsModal: true
            };
        },
        submit(){
            this.props.submitValue(this.refs.input.getValue());
            this.dismiss();
        },
        render: function(){
            return (
                <div>
                    <div className="dialogLegend">{MessageHash[this.props.legendId]}</div>
                    <MaterialUI.TextField
                        floatingLabelText={MessageHash[this.props.fieldLabelId]}
                        ref="input"
                        onKeyDown={this.submitOnEnterKey}
                        defaultValue={this.props.defaultValue}
                    />
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

    ns.HiddenDownloadForm = HiddenDownloadForm;
    ns.MultiDownloadDialog = MultiDownloadDialog;
    ns.ConfirmDialog = ConfirmDialog;
    ns.PromptDialog = PromptDialog;
    ns.TreeDialog = TreeDialog;
    ns.UploadDialog = UploadDialog;
    global.FSActions = ns;

})(window);
