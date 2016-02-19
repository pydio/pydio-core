(function(global) {

    class ShareModel extends Observable {


        constructor(pydio, userSelection){
            super();
            this._userSelection = userSelection;
            this._node = userSelection.getUniqueNode();
            this._status = 'idle';
            this._edit = false;
            this._data = {};
            this._pendingData = {};
            this._pydio = pydio;
            if(this._node.getMetadata().get('ajxp_shared')){
                this.load();
                this._edit = true;
            }
            if(this._node.isLeaf()){
                this._previewEditors = pydio.Registry.findEditorsForMime(this._node.getAjxpMime()).filter(function(entry){
                    return !(entry.editorClass == "OtherEditorChooser" || entry.editorClass == "BrowserOpener");
                });

            }
        }

        getNode(){
            return this._node;
        }

        getSelectionLabel(){
            return this._node.getLabel();
        }

        getStatus(){
            return this._status;
        }

        hasPublicLink(){
            return (this._data['minisite'] && this._data['minisite']['public_link']);
        }

        getPublicLink(){
            if(this.hasPublicLink()){
                return this._data['minisite']['public_link'];
            }else{
                return null;
            }
        }

        getPublicLinkHash(){
            if(this.hasPublicLink()){
                return this._data['minisite']['hash'];
            }else{
                return null;
            }
        }

        publicLinkIsShorten(){
            if(this.hasPublicLink()){
                return this._data['minisite']['hash_is_shorten'];
            }else{
                return false;
            }
        }

        togglePublicLink(){
            this._pendingData['enable_public_link'] = !this.hasPublicLink();
            this.save();
        }

        _initPendingData(){
            if(!this._pendingData['minisite']){
                this._pendingData['minisite'] = {
                    permissions:{},
                    expiration:{}
                };
            }
            if(!this._pendingData['entries']){
                this._pendingData['entries'] = [];
                if(this._data['entries']){
                    // Deep duplicate
                    this._pendingData['entries'] = JSON.parse(JSON.stringify(this._data['entries']));
                }
            }
        }

        revertChanges(){
            this._pendingData = {};
            this._setStatus('idle');
        }

        getSharedUsers(){
            var data = [], sharedData = [];
            if(this._pendingData['entries']){
                data = this._pendingData['entries'];
            }else if(this._data['entries']){
                data = this._data['entries'];
            }
            // Skip minisite temporary user
            data.map(function(entry){
                if(!entry['HIDDEN']) sharedData.push(entry);
            });
            return sharedData;
        }

        updateSharedUser(operation, userId, userData){
            this._initPendingData();
            if(userData['ID']) {
                userData['ID'] = userId;
            }
            var updatedData = [];
            if(operation == 'add'){
                this._pendingData['entries'].push(userData);
            }else if(operation == 'remove'){
                this._pendingData['entries'].map(function(entry){
                    if(entry['ID'] != userId) updatedData.push(entry);
                });
                this._pendingData['entries'] = updatedData;
            }else if(operation == 'update') {
                this._pendingData['entries'].map(function (entry) {
                    if (entry['ID'] != userId) updatedData.push(entry);
                    else updatedData.push(userData);
                });
                this._pendingData['entries'] = updatedData;
            }else if(operation == 'update_right'){
                // UserData is {right:'read'|'right', add:true|false}
                this._pendingData['entries'].map(function (entry) {
                    if (entry['ID'] != userId) {
                        updatedData.push(entry);
                    }
                    else {
                        if(userData['right'] == 'watch'){
                            entry.WATCH = userData['add'];
                        }else{
                            var crtRead = (entry.RIGHT.indexOf('r') !== -1 || (userData['right'] == 'read' && userData['add'])) && !(userData['right'] == 'read' && !userData['add']);
                            var crtWrite = (entry.RIGHT.indexOf('w') !== -1 || (userData['right'] == 'write' && userData['add'])) && !(userData['right'] == 'write' && !userData['add']);
                            if(!crtRead && !crtWrite){
                                crtRead = true;
                            }
                            entry.RIGHT = (crtRead ? 'r' : '') + (crtWrite ? 'w' : '');
                        }
                        updatedData.push(entry);
                    }
                });
                this._pendingData['entries'] = updatedData;

            }else{
                throw new Error('Unsupported operation, should be add, update, update_right or remove');
            }
            this._setStatus('modified');
        }

        _sharedUsersToParameters(params){
            var entries = this.getSharedUsers();
            var index = 0;
            entries.map(function(e){
                params['user_' + index] = e.ID;
                params['right_read_' + index] = (e.RIGHT.indexOf('r') !== -1) ? 'true' : 'false';
                params['right_write_' + index] = (e.RIGHT.indexOf('w') !== -1) ? 'true' : 'false';
                if(e.WATCH){
                    params['right_watch_' + index] = 'true';
                }
                params['entry_type_' + index] = e.TYPE == 'group' ? 'group' : 'user';
                index ++;
            });
        }

        saveSelectionSupported(pydio){
            return pydio.getController().actions.get('user_team_create') !== undefined;
        }

        saveSelectionAsTeam(teamName){
            var userIds = [];
            this.getSharedUsers().map(function(e){
                if(e.TYPE == 'user') userIds.push(e.ID);
            });
            PydioApi.getClient().request({
                get_action:'user_team_create',
                team_label:teamName,
                'user_ids[]':userIds
            }, function(){
                // Flatten Team?
            });
        }

        /**********************************************/
        /* GLOBAL PARAMETERS : label, desc, notif     */
        /**********************************************/
        getGlobal(name){
            if(this._pendingData[name] !== undefined){
                return this._pendingData[name];
            }
            if(name == 'watch') {
                return this._data["element_watch"] == 'META_WATCH_BOTH';
            }else{
                return this._data[name];
            }
        }
        setGlobal(name, value){
            this._pendingData[name] = value;
            this._setStatus('modified');
        }
        _globalsAsParameters(params){
            params['repo_label'] = this.getGlobal("label");
            params['repo_description'] = this.getGlobal("description");
            params['self_watch_folder'] = this.getGlobal("watch") ? 'true' : 'false';
        }

        /**************************/
        /* SHARE VISIBILITY       */
        /**************************/
        isPublic(){
            if(this._pendingData["scope"] !== undefined){
                return this._pendingData["scope"];
            }
            return this._data["share_scope"] == 'public';
        }
        toggleVisibility(){
            this._pendingData['scope'] = !this.isPublic();
            this._setStatus('modified');
        }
        getShareOwner(){
            return this._data['share_owner'];
        }
        setNewShareOwner(owner){
            this._pendingData['new_owner'] = owner;
            this.save();
        }
        _visibilityDataToParameters(params){
            params['share_scope'] = this.isPublic() ? 'public' : 'private';
            if(this._pendingData['new_owner'] && this._pendingData['new_owner'] != this._data['owner']){
                params['transfer_owner'] = this._pendingData['new_owner'];
            }
        }


        /****************************/
        /* PUBLIC LINK PASSWORD     */
        /****************************/
        hasHiddenPassword(){
            return this._data['has_password'];
        }
        getPassword(){
            if(this._pendingData['minisite'] && this._pendingData['minisite']['password']){
                return this._pendingData['minisite']['password'];
            }
            return '';
        }
        updatePassword(newValue){
            this._initPendingData();
            this._pendingData['minisite']['password'] = newValue;
            this._setStatus('modified');
        }
        resetPassword(){
            this._data['has_password'] = false;
            this._data['password_cleared'] = true;
            this.updatePassword('');
        }

        _passwordAsParameter(params){
            if(this._pendingData['minisite'] && this._pendingData['minisite']['password']){
                params['guest_user_pass'] = this._pendingData['minisite']['password'];
            }else if(this._data['password_cleared']){
                params['guest_user_pass'] = '';
            }
        }

        /****************************/
        /* PUBLIC LINK EXPIRATION   */
        /****************************/
        getExpirationFor(name){
            if(this._pendingData['minisite'] && this._pendingData['minisite']['expiration'][name] !== undefined){
                return this._pendingData['minisite']['expiration'][name];
            }
            var current; var defaults={days:0, downloads:0};
            if(this._data['minisite']){
                if(name == 'days'){
                    current = this._data['expire_after'];
                }else if(name == 'downloads'){
                    current = this._data['download_limit'];
                }
            }else{
                current = defaults[name];
            }
            return current;
        }

        setExpirationFor(name, value){
            this._initPendingData();
            this._pendingData['minisite']['expiration'][name] = value;
            this._setStatus('modified');
        }

        _expirationsToParameters(params){
            if(this.getExpirationFor('days')) params['expiration'] = this.getExpirationFor('days');
            if(this.getExpirationFor('downloads')) params['downloadlimit'] = this.getExpirationFor('downloads');
        }


        /****************************/
        /* PUBLIC LINKS PERMISSIONS */
        /****************************/
        getPublicLinkPermission(name){
            if(this._pendingData['minisite'] && this._pendingData['minisite']['permissions'][name] !== undefined){
                return this._pendingData['minisite']['permissions'][name];
            }
            var current;
            var defaults = {
                read : (!this._previewEditors || this._previewEditors.length > 0),
                download: true,
                write:false
            };
            if(this._data['minisite']){
                var json = this._data;
                if(name == 'download'){
                    current = ! json['minisite']['disable_download'];
                }else if(name == 'read'){
                    current = (json.entries[0].RIGHT.indexOf('r') !== -1 && json['minisite_layout']!='ajxp_unique_dl');
                }else if(name == 'write'){
                    current = (json.entries[0].RIGHT.indexOf('w') !== -1);
                }
            }else{
                current = defaults[name];
            }
            return current;
        }

        isPublicLinkPreviewDisabled(){
            return (this._previewEditors && this._previewEditors.length == 0);
        }

        setPublicLinkPermission(name, value){
            this._initPendingData();
            this._pendingData['minisite']['permissions'][name] = value;
            this._setStatus('modified');
        }

        _permissionsToParameters(params){
            if(this.getPublicLinkPermission('read')){
                params['simple_right_read'] = 'on';
            }
            if(this.getPublicLinkPermission('download')){
                params['simple_right_download'] = 'on';
            }
            if(this.getPublicLinkPermission('write')){
                params['simple_right_write'] = 'on';
            }
        }

        /****************************/
        /* PUBLIC LINKS TEMPLATE    */
        /****************************/
        getTemplate(){
            if(this._pendingData["minisite"] && this._pendingData["minisite"]["layout"]){
                return this._pendingData["minisite"]["layout"];
            }
            if(this._node.isLeaf()){
                if(this.getPublicLinkPermission('read')){
                    this._data['minisite_layout'] = 'ajxp_unique_preview';
                }else{
                    this._data['minisite_layout'] = 'ajxp_unique_dl';
                }
            }
            if(this._data['minisite_layout']){
                return this._data['minisite_layout'];
            }
        }

        setTemplate(tplName){
            this._initPendingData();
            this._pendingData["minisite"]["layout"] = tplName;
            this._setStatus('modified');
        }

        _templateToParameter(params){
            if(this.getTemplate()){
                params['minisite_layout'] = this.getTemplate();
            }
        }

        /**********************/
        /* CUSTOM LINK HANDLE */
        /**********************/
        updateCustomLink(newValue){
            this._initPendingData();
            this._pendingData['minisite']['custom_link'] = newValue;
            this.save();
        }

        /*********************************/
        /* GENERIC: STATUS / LOAD / SAVE */
        /*********************************/
        _setStatus(status){
            this._status = status;
            this.notify('status_changed', {
                status: status,
                model: this
            });
        }

        load(){
            if(this._status == 'loading') return;
            this._setStatus('loading');
            ShareModel.loadSharedElementData(this._node, function(transport){
                this._data = transport.responseJSON;
                this._pendingData = {};
                this._setStatus('idle');
            }.bind(this));
        }

        save(){
            if(Object.keys(this._pendingData).length){
                this.submitToServer();
            }
        }

        stopSharing(){
            var params = {get_action:'unshare'};
            ShareModel.prepareShareActionParameters(this.getNode(), params);
            PydioApi.getClient().request(params, this.load.bind(this), null);
        }

        submitToServer(){
            var params = {
                get_action:'share',
                sub_action:'share_node',
                return_json:'true'
            };
            if(this._pendingData["enable_public_link"] !== undefined){
                if(this._pendingData["enable_public_link"]) {
                    params["enable_public_link"] = "true";
                } else if(this._data["minisite"]){
                    params["disable_public_link"] = this._data["minisite"]["hash"];
                }
            }else if(this._data["minisite"]){
                params["enable_public_link"] = "true";
            }
            params['repo_label'] = this._node.getLabel();
            params["file"] = this._node.getPath();
            if(this._data['repositoryId']){
                params['repository_id'] = this._data['repositoryId'];
            }else{
                params["element_type"] = this._node.isLeaf() ? "file" : this._node.getMetadata().get("ajxp_shared_minisite")? "minisite" : "repository";
                params['create_guest_user'] =  'true';
            }
            if(this._data.minisite){
                params['guest_user_id'] = this._data.entries[0].ID;
                params['hash'] = this._data.minisite.hash;
            }
            this._globalsAsParameters(params);
            this._permissionsToParameters(params);
            this._expirationsToParameters(params);
            this._passwordAsParameter(params);
            this._templateToParameter(params);
            this._visibilityDataToParameters(params);
            this._sharedUsersToParameters(params);
            if(this._pendingData && this._pendingData['minisite'] && this._pendingData['minisite']['custom_link']){
                params['custom_handle'] = this._pendingData['minisite']['custom_link'];
            }
            PydioApi.getClient().request(params, function(transport){
                var _data = transport.responseJSON;
                if(_data !== null){
                    this._data = _data;
                    this._pendingData = {};
                    this._setStatus('saved');
                }else{
                    // There must have been an error, revert
                }
            }.bind(this), null);
        }

        static prepareShareActionParameters(uniqueNode, params){
            var meta = uniqueNode.getMetadata();
            if(meta.get('shared_element_hash')){
                params["hash"] = meta.get('shared_element_hash');
                params["tmp_repository_id"] = meta.get('shared_element_parent_repository');
                params["element_type"] = meta.get('share_type');
            }else{
                params["file"] = uniqueNode.getPath();
                params["element_type"] = uniqueNode.isLeaf() ? "file" : meta.get("ajxp_shared_minisite")? "minisite" : "repository";
            }
        }

        static loadSharedElementData(node, completeCallback=null, errorCallback=null, settings={}){
            var meta = node.getMetadata();
            var options = {
                get_action  : 'load_shared_element_data',
                merged      : 'true'
            };
            if(meta.get('shared_element_hash')){
                options["hash"] = meta.get('shared_element_hash');
                options["tmp_repository_id"] = meta.get('shared_element_parent_repository');
                options["element_type"] = meta.get('share_type');
            }else{
                options["file"] = node.getPath();
                options["element_type"] = node.isLeaf() ? "file" : meta.get("ajxp_shared_minisite")? "minisite" : "repository";
            }
            PydioApi.getClient().request(options, completeCallback, errorCallback, settings);
        }

        static getAuthorizations(pydio){
            var pluginConfigs = pydio.getPluginConfigs("action.share");
            var authorizations = {
                folder_public_link : pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'both' ||  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'minisite' ,
                folder_workspaces :  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'both' ||  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'workspace' ,
                file_public_link : pluginConfigs.get("ENABLE_FILE_PUBLIC_LINK"),
                file_workspaces : true, //pluginConfigs.get("ENABLE_FILE_SHARING"),
                editable_hash : pluginConfigs.get("HASH_USER_EDITABLE"),
                pass_mandatory: false
            };
            var pass_mandatory = pluginConfigs.get("SHARE_FORCE_PASSWORD");
            if(pass_mandatory){
                authorizations.password_mandatory = true;
            }
            authorizations.password_placeholder = pass_mandatory ? pydio.MessageHash['share_center.176'] : pydio.MessageHash['share_center.148'];
            return authorizations;
        }

        static compileLayoutData(pydio, node){

            // Search registry for template nodes starting with minisite_
            var tmpl;
            if(node.isLeaf()){
                var currentExt = node.getAjxpMime();
                tmpl = XPathSelectNodes(pydio.getXmlRegistry(), "//template[contains(@name, 'unique_preview_')]");
            }else{
                tmpl = XPathSelectNodes(pydio.getXmlRegistry(), "//template[contains(@name, 'minisite_')]");
            }

            if(!tmpl.length){
                return [];
            }
            if(tmpl.length == 1){
                return [{LAYOUT_NAME:tmpl[0].getAttribute('element'), LAYOUT_LABEL:''}];
            }
            var crtTheme = ajxpBootstrap.parameters.get('theme');
            var values = [];
            var noEditorsFound = false;
            tmpl.map(function(node){
                var theme = node.getAttribute('theme');
                if(theme && theme != crtTheme) return;
                var element = node.getAttribute('element');
                var name = node.getAttribute('name');
                var label = node.getAttribute('label');
                if(currentExt && name == "unique_preview_file"){
                    var editors = pydio.Registry.findEditorsForMime(currentExt);
                    if(!editors.length || (editors.length == 1 && editors[0].editorClass == "OtherEditorChooser")) {
                        noEditorsFound = true;
                        return;
                    }
                }
                if(label) {
                    if(MessageHash[label]) label = MessageHash[label];
                }else{
                    label = node.getAttribute('name');
                }
                values[name] = element;
                //chooser.insert(new Element('option', {value:element}).update(label));
                values.push({LAYOUT_NAME:name, LAYOUT_ELEMENT:element, LAYOUT_LABEL: label});
            });
            return values;
            /*
            var read;
            if(node.isLeaf() && linkRightsToTemplates && values["unique_preview_file"] && values["unique_preview_download"]){
                read = oForm.down("#simple_right_read");
                var download = oForm.down("#simple_right_download");
                var observer = function(){
                    if(!read.checked && !download.checked){
                        download.checked = true;
                    }
                    if(read.checked){
                        chooser.setValue(values["unique_preview_file"]);
                    }else{
                        chooser.setValue(values["unique_preview_download"]);
                    }
                };
                read.observe("click", observer);
                download.observe("click", observer);

            }else if(noEditorsFound){
                read = oForm.down("#simple_right_read");
                read.checked = false;
                read.disabled = true;
                read.next("label").insert(" (no preview for this file)");
            }
            return chooser;

            */


        }

    }

    var ReactModel = global.ReactModel || {};
    ReactModel['Share'] = ShareModel;
    global.ReactModel = ReactModel;
    // Set for dependencies management
    global.ReactModelShare = ShareModel;


})(window);