(function(global){

    class StatusItem extends Observable{
        constructor(type){
            super();
            this._status = 'new';
            this._type = type;
        }
        getLabel(){

        }
        getType(){
            return this._type;
        }
        getStatus(){
            return this._status;
        }
        setStatus(status){
            this._status = status;
            this.notify('status');
        }
        process(completeCallback){
            this._doProcess(completeCallback);
        }
        abort(completeCallback){
            if(this._status !== 'loading') return;
            this._doAbort(completeCallback);
        }
    }

    class UploadItem extends StatusItem{
        constructor(file, targetNode, relativePath = null){
            super('file');
            this._file = file;
            this._status = 'new';
            this._progress = 0;
            this._targetNode = targetNode;
            this._repositoryId = global.pydio.user.activeRepository;
            this._relativePath = relativePath;
        }
        getFile(){
            return this._file;
        }
        getSize(){
            return this._file.size;
        }
        getLabel(){
            return this._relativePath ? this._relativePath : this._file.name;
        }
        getProgress(){
            return this._progress;
        }
        setProgress(newValue, bytes = null){
            this._progress = newValue;
            this.notify('progress', newValue);
            if(bytes !== null) {
                this.notify('bytes', bytes);
            }
        }
        getRelativePath(){
            return this._relativePath;
        }
        buildQueryString(){

            let fullPath = this._targetNode.getPath();
            if(this._relativePath) {
                fullPath += PathUtils.getDirname(this._relativePath);
            }
            let currentRepo = global.pydio.user.activeRepository;

            let queryString = '&get_action=upload&xhr_uploader=true&dir=' + encodeURIComponent(fullPath);

            let dataModel = global.pydio.getContextHolder();
            let nodeName = PathUtils.getBasename(this._file.name);
            var newNode = new AjxpNode(fullPath+"/"+nodeName);
            if(this._file.size){
                newNode.getMetadata().set("filesize", this._file.size);
            }
            try{
                let params = null;
                if(currentRepo !== this._repositoryId) {
                    params = {tmp_repository_id:this._repositoryId};
                }
                dataModel.applyCheckHook(newNode, params);
            }catch(e){
                throw new Error('Error while checking before uploads');
            }
            let overwriteStatus = UploaderConfigs.getInstance().getOption("DEFAULT_EXISTING", "upload_existing");
            if(overwriteStatus === 'rename'){
                queryString += '&auto_rename=true';
            }else if(overwriteStatus === 'alert' && !this._relativePath && currentRepo === this._repositoryId){
                if(dataModel.fileNameExists(nodeName, false, this._targetNode)){
                    if(!global.confirm(MessageHash[124])){
                        throw new Error('File already exists');
                    }
                }
            }
            if(currentRepo !== this._repositoryId){
                queryString += '&tmp_repository_id=' + this._repositoryId;
            }
            return queryString;
        }
        _doProcess(completeCallback){
            let complete = function(){
                this.setStatus('loaded');
                completeCallback();
            }.bind(this);
            let error = function(){
                this.setStatus('error');
                completeCallback();
            }.bind(this);
            let progress = function(computableEvent){
                let percentage = Math.round((computableEvent.loaded * 100) / computableEvent.total);
                let bytesLoaded = computableEvent.loaded;
                this.setProgress(percentage, bytesLoaded);
            }.bind(this);
            this.setStatus('loading');

            let queryString;
            try{
                queryString = this.buildQueryString();
            }catch(e){
                this.setStatus('error');
                completeCallback();
                return;
            }

            this.xhr = PydioApi.getClient().uploadFile(
                this._file,
                'userfile_0',
                queryString,
                complete,
                error,
                progress
            );
        }
        _doAbort(completeCallback){
            if(this.xhr){
                try{
                    this.xhr.abort();
                }catch(e){}
            }
        }
    }

    class FolderItem extends StatusItem{
        constructor(path, targetNode){
            super('folder');
            this._path = path;
            this._targetNode =  targetNode;
        }
        getPath(){
            return this._path;
        }
        getLabel(){
            return PathUtils.getBasename(this._path);
        }
        _doProcess(completeCallback){
            let fullPath = this._targetNode.getPath() + this._path;
            let params = {
                get_action: 'mkdir',
                dir: PathUtils.getDirname(fullPath),
                dirname:PathUtils.getBasename(fullPath),
                ignore_exists:true,
            };
            PydioApi.getClient().request(params, function(t){
                this.setStatus('loaded');
                completeCallback();
            }.bind(this));
        }
        _doAbort(completeCallback){
            if(global.console) global.console.log('Cannot abort folder creation');
        }
    }

    class UploadTask extends PydioTasks.Task{

        constructor(){
            super({
                id      : 'local-upload-task',
                userId  : global.pydio.user.id,
                wsId    : global.pydio.user.activeRepository,
                flags   : PydioTasks.Task.FLAG_HAS_PROGRESS|PydioTasks.Task.FLAG_STOPPABLE,
                label   : "Uploading files to server...",
                status  : PydioTasks.Task.STATUS_COMPLETE,
                statusMessage  : ''
            });
        }

        setProgress(progress){
            this._internal['progress'] = progress;
            this.updateStatus(PydioTasks.Task.STATUS_RUNNING);
        }
        setRunning(queueSize){
            this._internal['statusMessage'] = queueSize + ' files to upload';
            this.updateStatus(PydioTasks.Task.STATUS_RUNNING);
        }
        setIdle(){
            this._internal['statusMessage'] = '';
            this.updateStatus(PydioTasks.Task.STATUS_COMPLETE);
        }
        updateStatus(status){
            this._internal['status'] = status;
            this.notifyMainStore();
        }

        notifyMainStore(){
            PydioTasks.Store.getInstance().notify("tasks_updated");
        }

        hasOpenablePane(){
            return true;
        }
        openDetailPane(){
            global.pydio.Controller.fireAction("upload");
        }

        static getInstance(){
            if(!UploadTask.__INSTANCE) {
                UploadTask.__INSTANCE = new UploadTask();
                PydioTasks.Store.getInstance().enqueueLocalTask(UploadTask.__INSTANCE);
            }
            return UploadTask.__INSTANCE;
        }

    }

    class UploaderStore extends Observable{

        constructor(){
            super();
            this._folders = [];
            this._uploads = [];
            this._processing = [];
            this._processed = [];
            // Todo
            this._queueCounter = 0;
            this._maxQueueSize = 2;
        }
        recomputeGlobalProgress(){
            let totalCount      = 0;
            let totalProgress   = 0;
            this._uploads.concat(this._processing).forEach(function(item){
                if(!item.getProgress) return;
                totalCount ++;
                totalProgress += item.getProgress();
            });
            let progress;
            if(!totalCount) {
                progress = 0;
            }else{
                progress = Math.ceil(totalProgress / totalCount);
            }
            return progress;
        }
        getAutoStart(){
            return UploaderConfigs.getInstance().getOptionAsBool("DEFAULT_AUTO_START", "upload_auto_send");
        }
        pushFolder(folderItem){
            this._folders.push(folderItem);
            if(this.getAutoStart() && !this._processing.length) {
                this.processNext();
            } // Autostart with queue was empty before
            UploadTask.getInstance().setRunning(this.getQueueSize());
            this.notify('update');
        }
        pushFile(uploadItem){
            this._uploads.push(uploadItem);
            uploadItem.observe("progress", function(){
                let pg = this.recomputeGlobalProgress();
                UploadTask.getInstance().setProgress(pg);
            }.bind(this));
            if(this.getAutoStart() && !this._processing.length) {
                this.processNext();
            } // Autostart with queue was empty before
            UploadTask.getInstance().setRunning(this.getQueueSize());
            this.notify('update');
        }
        log(){
            if(global.console){
                global.console.log("Uploads", this._uploads);
                global.console.log("Folders", this._folders);
            }
        }
        processQueue(){
            let next = this.getNext();
            while(next !== null){
                next.process(function(){
                    this._processed.push(next);
                    this.notify("update");
                }.bind(this));
                next = this.getNext();
            }
        }
        getQueueSize(){
            return this._folders.length + this._uploads.length + this._processing.length;
        }
        clearAll(){
            this._folders = [];
            this._uploads = [];
            this._processing = [];
            this._processed = [];
            this.notify('update');
        }
        processNext(){
            let processable = this.getNext();
            if(processable){
                this._processing.push(processable);
                UploadTask.getInstance().setRunning(this.getQueueSize() + 1);
                processable.process(function(){
                    this._processing = LangUtils.arrayWithout(this._processing, this._processing.indexOf(processable));
                    this._processed.push(processable);
                    this.processNext();
                    this.notify("update");
                }.bind(this));
            }else{
                UploadTask.getInstance().setIdle();
            }
        }
        getNext(){
            if(this._folders.length){
                return this._folders.shift();
            }
            if(this._uploads.length){
                return this._uploads.shift();
            }
        }
        stopOrRemoveItem(item){
            item.abort();
            ['_uploads', '_folders', '_processing', '_processed'].forEach(function(key){
                let arr = this[key];
                if(arr.indexOf(item) !== -1) {
                    this[key] = LangUtils.arrayWithout(arr, arr.indexOf(item));
                }
            }.bind(this));
            this.notify("update");
        }
        getItems(){
            return {
                processing: this._processing,
                pending: this._folders.concat(this._uploads),
                processed: this._processed
            };
        }
        static getInstance(){
            if(!UploaderStore.__INSTANCE){
                UploaderStore.__INSTANCE = new UploaderStore();
            }
            return UploaderStore.__INSTANCE;
        }

        handleFolderPickerResult(files, targetNode){
            var folders = {};
            for(var i=0;i<files.length;i++){
                var relPath = null;
                if(files[i]['webkitRelativePath']){
                    relPath = '/' + files[i]['webkitRelativePath'];
                    var folderPath = PathUtils.getDirname(relPath);
                    if(!folders[folderPath]){
                        this.pushFolder(new FolderItem(folderPath, targetNode));
                        folders[folderPath] = true;
                    }
                }
                this.pushFile(new UploadItem(files[i], targetNode, relPath));
            }
        }

        handleDropEventResults(items, files, targetNode){

            let oThis = this;

            if (items && items.length && (items[0].getAsEntry || items[0].webkitGetAsEntry)) {
                let error = (global.console ? global.console.log : function(err){global.alert(err); }) ;
                let length = items.length;
                for (var i = 0; i < length; i++) {
                    var entry;
                    if(items[i].kind && items[i].kind != 'file') continue;
                    if(items[0].getAsEntry){
                        entry = items[i].getAsEntry();
                    }else{
                        entry = items[i].webkitGetAsEntry();
                    }
                    if (entry.isFile) {
                        entry.file(function(File) {
                            if(File.size == 0) return;
                            oThis.pushFile(new UploadItem(File, targetNode));
                        }, error );
                    } else if (entry.isDirectory) {
                        oThis.pushFolder(new FolderItem(entry.fullPath, targetNode));
                        this.recurseDirectory(entry, function(fileEntry){
                            var relativePath = fileEntry.fullPath;
                            fileEntry.file(function(File) {
                                if(File.size == 0) return;
                                oThis.pushFile(new UploadItem(File, targetNode, relativePath));
                            }, error );
                        }, function(folderEntry){
                            oThis.pushFolder(new FolderItem(folderEntry.fullPath, targetNode));
                        }, error );
                    }
                }
            }else{
                for(var j=0;j<files.length;j++){
                    oThis.pushFile(new UploadItem(files[j], targetNode));
                }
            }
            UploaderStore.getInstance().log();
        }

        recurseDirectory(item, fileHandler, folderHandler, errorHandler) {

            let recurseDir = this.recurseDirectory.bind(this);
            let dirReader = item.createReader();
            let entries = [];

            let toArray = function(list){
                return Array.prototype.slice.call(list || [], 0);
            };

            // Call the reader.readEntries() until no more results are returned.
            var readEntries = function() {
                dirReader.readEntries (function(results) {
                    if (!results.length) {

                        $A(entries).each(function(e){
                            if(e.isDirectory){
                                folderHandler(e);
                                recurseDir(e, fileHandler, folderHandler, errorHandler);
                            }else{
                                fileHandler(e);
                            }
                        });
                    } else {
                        entries = entries.concat(toArray(results));
                        readEntries();
                    }
                }, errorHandler);
            };

            readEntries(); // Start reading dirs.

        }


    }

    class UploaderConfigs extends Observable{

        static getInstance(){
            if(!UploaderConfigs.__INSTANCE) UploaderConfigs.__INSTANCE = new UploaderConfigs();
            return UploaderConfigs.__INSTANCE;
        }

        constructor(){
            super();
            this._global = global.pydio.getPluginConfigs("uploader");
            this._mq = global.pydio.getPluginConfigs("mq");
            this._user = global.pydio.user.preferences;
        }

        getOptionAsBool(name, userPref = '', defaultValue = undefined){
            let o = this.getOption(name, userPref, defaultValue);
            if(o === 'true') return true;
            else return false;
        }

        getOption(name, userPref = '', defaultValue = undefined){
            if(userPref){
                let test = this.getUserPreference('originalUploadForm_XHRUploader', userPref);
                if(test !== null) return test;
            }
            if(this._global.has(name)){
                return this._global.get(name);
            }
            if(this._mq.has(name)){
                return this._mq.get(name);
            }
            if(defaultValue !== undefined){
                return defaultValue;
            }
            return null;
        }

        updateOption(name, value, isBool = false){
            if(isBool){
                value = value? "true" : "false";
            }
            this.setUserPreference('originalUploadForm_XHRUploader', name, value);
            this.notify("change");
        }


        // TODO: SHOULD BE IN A "CORE" COMPONENT
        getUserPreference(guiElementId, prefName){
            let pydio = global.pydio;
            if(!pydio.user) return null;
            var gui_pref = pydio.user.getPreference("gui_preferences", true);
            if(!gui_pref || !gui_pref[guiElementId]) return null;
            if(pydio.user.activeRepository && gui_pref[guiElementId]['repo-'+pydio.user.activeRepository]){
                return gui_pref[guiElementId]['repo-'+pydio.user.activeRepository][prefName];
            }
            return gui_pref[guiElementId][prefName];
        }

        setUserPreference(guiElementId, prefName, prefValue){
            let pydio = global.pydio;
            if(!pydio || !pydio.user) return;
            var guiPref = pydio.user.getPreference("gui_preferences", true);
            if(!guiPref) guiPref = {};
            if(!guiPref[guiElementId]) guiPref[guiElementId] = {};
            if(pydio.user.activeRepository ){
                var repokey = 'repo-'+pydio.user.activeRepository;
                if(!guiPref[guiElementId][repokey]) guiPref[guiElementId][repokey] = {};
                if(guiPref[guiElementId][repokey][prefName] && guiPref[guiElementId][repokey][prefName] == prefValue){
                    return;
                }
                guiPref[guiElementId][repokey][prefName] = prefValue;
            }else{
                if(guiPref[guiElementId][prefName] && guiPref[guiElementId][prefName] == prefValue){
                    return;
                }
                guiPref[guiElementId][prefName] = prefValue;
            }
            pydio.user.setPreference("gui_preferences", guiPref, true);
            pydio.user.savePreference("gui_preferences");
        }

    }

    var ns = global.UploaderModel || {};
    ns.Store = UploaderStore;
    ns.Configs = UploaderConfigs;
    ns.UploadItem = UploadItem;
    ns.FolderItem = FolderItem;
    global.UploaderModel = ns;

})(window);