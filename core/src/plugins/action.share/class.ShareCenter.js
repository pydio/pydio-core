/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
Class.create("ShareCenter", {

    currentNode : null,
    shareFolderMode : "workspace",
    readonlyMode : false,
    _currentFolderWatchValue:false,
    _dataModel:null,

    initialize: function(){
        document.observe("ajaxplorer:afterApply-delete", function(){
            try{
                var u = ajaxplorer.getContextHolder().getUniqueNode();
                if(u.getMetadata().get("ajxp_shared")){
                    var leaf = u.isLeaf();
                    var f = $("generic_dialog_box").down("#delete_message");
                    if(!f.down("#share_delete_alert")){
                        var message;
                        if(u.isLeaf()){
                            message = MessageHash["share_center.158"];
                        }else{
                            message = MessageHash["share_center.157"];
                        }
                        f.insert("<div id='share_delete_alert' style='padding-top: 10px;color: rgb(192, 0, 0);'><span style='float: left;display: block;height: 60px;margin: 4px 7px 4px 0;font-size: 2.4em;' class='icon-warning-sign'></span>"+message+"</div>");
                    }
                }
            }catch(e){
                if(console) console.log(e);
            }
        });
        var pluginConfigs = ajaxplorer.getPluginConfigs("action.share");
        this.authorizations = {
            folder_public_link : pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'both' ||  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'minisite' ,
            folder_workspaces :  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'both' ||  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'workspace' ,
            file_public_link : pluginConfigs.get("ENABLE_FILE_PUBLIC_LINK"),
            editable_hash : pluginConfigs.get("HASH_USER_EDITABLE")
        };
        var pass_mandatory = pluginConfigs.get("SHARE_FORCE_PASSWORD");
        if(pass_mandatory){
            this.authorizations.password_mandatory = true;
        }
        this.authorizations.password_placeholder = pass_mandatory ? MessageHash['share_center.176'] : MessageHash['share_center.148']
    },

    performShareAction : function(dataModel){
        var userSelection;
        if(dataModel){
            this._dataModel = dataModel;
            userSelection = dataModel;
        }else{
            userSelection =  ajaxplorer.getUserSelection();
        }
        var pass_mandatory = ajaxplorer.getPluginConfigs("action.share").get("SHARE_FORCE_PASSWORD");
        if(pass_mandatory){
            this.authorizations.password_mandatory = true;
        }
        this.authorizations.password_placeholder = pass_mandatory ? MessageHash['share_center.176'] : MessageHash['share_center.148']
        this.currentNode = userSelection.getUniqueNode();
        this.shareFolderMode = "workspace";
        this.readonlyMode = this.currentNode.getMetadata().get('share_data') ? true : false;
        if(( userSelection.hasDir() && !userSelection.hasMime($A(['ajxp_browsable_archive'])))
            || userSelection.isMultiple()
            || (this.currentNode.getMetadata().get('share_data') && !this.currentNode.getMetadata().get('share_link'))
            || (this.currentNode.getMetadata().get('ajxp_shared') && !this.currentNode.getMetadata().get('ajxp_shared_publiclet'))
            ){
            var nodeMeta = this.currentNode.getMetadata();
            if(!nodeMeta.get("ajxp_shared")){
                var oThis = this;
                modal.showDialogForm("Share", "share_folder_chooser", function(oForm){
                    oForm.down('[name="ok"]').setStyle({opacity:0.5});
                    oForm.down("ul.share_chooser_list").select("li").each(function(el){
                        el.observe("click", function(){
                            oForm.down("ul.share_chooser_list").select("li").invoke("removeClassName", "selected");
                            el.addClassName("selected");
                            oThis.shareFolderMode = el.getAttribute("data-shareListValue");
                            oForm.down('[name="ok"]').setStyle({opacity:1});
                        });
                    });
                }, function(){
                    this.shareRepository();
                }.bind(this), function(){}, false,false, true);
            }else{
                if(nodeMeta.get("ajxp_shared_minisite")){
                    this.shareFolderMode = nodeMeta.get("ajxp_shared_minisite") == "private" ? "minisite_private" : "minisite_public";
                }
                this.shareRepository();
            }
        }else{
            this.createQRCode = ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("CREATE_QRCODE");
            this.shareFile(userSelection);
        }
    },

    performShare: function(type){
        this.currentNode = ajaxplorer.getUserSelection().getUniqueNode();

        var pass_mandatory = ajaxplorer.getPluginConfigs("action.share").get("SHARE_FORCE_PASSWORD");
        if(pass_mandatory){
            this.authorizations.password_mandatory = true;
        }
        this.authorizations.password_placeholder = pass_mandatory ? MessageHash['share_center.176'] : MessageHash['share_center.148']
        if(!this.currentNode.isLeaf() && !this.authorizations.folder_public_link && !this.authorizations.folder_workspaces){
            alert('You are not authorized to share folders');
            return;
        }

        if(type == 'workspace'){
            this.shareFolderMode = 'workspace';
        }else if(type == 'minisite-public'){
            this.shareFolderMode = 'minisite_public';
        }else if(type == 'minisite-private'){
            this.shareFolderMode = 'minisite_private';
        }else if(type == 'file-dl'){
            this.shareFolderMode = 'file';
        }
        if(this.shareFolderMode == 'file'){
            if(this.currentNode.isLeaf() && !this.authorizations.file_public_link){
                alert('You are not authorized to share files');
                return;
            }
            this.shareFile();
        }else{
            if(!this.currentNode.isLeaf() && !this.authorizations.folder_public_link){
                this.shareFolderMode = "workspace";
            }else if(!this.currentNode.isLeaf() && !this.authorizations.folder_workspaces){
                this.shareFolderMode = "minisite_public";
            }
            this.shareRepository();
        }
    },

    shareRepository : function(reload){

        var submitFunc = function(oForm){
            if(!oForm.down('input[name="repo_label"]').value){
                alert(MessageHash[349]);
                return false;
            }
            var params = modal.getForm().serialize(true);
            var passwordField = modal.getForm().down('input[name="guest_user_pass"]');
            if(passwordField.readAttribute('data-password-set') === 'true' && !passwordField.getValue()){
                delete params['guest_user_pass'];
            }
            if(this.shareFolderMode == "minisite_public" && this.authorizations.password_mandatory && passwordField.readAttribute('data-password-set') !== 'true'
                && ( !params['guest_user_pass'] || params['guest_user_pass'].length < parseInt(pydio.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH")) ) ){
                pydio.displayMessage('ERROR', MessageHash["share_center.175"]);
                passwordField.addClassName("SF_failed");
                modal.getForm().down('#generate_publiclet').show();
                return;
            }
            var userSelection = ajaxplorer.getUserSelection();
            var publicUrl = ajxpServerAccessPath+'&get_action=share';
            publicUrl = userSelection.updateFormOrUrl(null, publicUrl);
            var conn = new Connexion(publicUrl);
            conn.setMethod("POST");
            oForm.addClassName("share_edit");
            conn.setParameters(params);
            if(this._currentRepositoryId){
                conn.addParameter("repository_id", this._currentRepositoryId);
            }
            if(this.shareFolderMode == "minisite_public"){
                conn.addParameter("sub_action", "create_minisite");
                if(this._currentRepositoryId){
                    conn.addParameter("repository_id",  this._currentRepositoryId);
                    conn.addParameter("guest_user_id",  this._currentGuestUserId);
                    conn.addParameter("hash",           this._currentMinisiteHash);
                }else{
                    conn.addParameter("create_guest_user", "true");
                }
            }else if(this.shareFolderMode == "minisite_private"){
                conn.addParameter("sub_action", "create_minisite");
            }else{
                conn.addParameter("sub_action", "delegate_repo");
            }
            var index = 0;
            $("shared_users_summary").select("div.user_entry").each(function(entry){
                conn.addParameter("user_"+index, entry.getAttribute("data-entry_id"));
                conn.addParameter("right_read_"+index, entry.down('input[name="r"]').checked ? "true":"false");
                conn.addParameter("right_write_"+index, entry.down('input[name="w"]').checked ? "true":"false");
                if(entry.down('input[name="n"]')){
                    conn.addParameter("right_watch_"+index, entry.down('input[name="n"]').checked ? "true":"false");
                }
                if(entry.NEW_USER_PASSWORD){
                    //check entry characters
                    var currentId =entry.getAttribute("data-entry_id");
                    var newCurrentId = currentId.replace(/[^a-zA-Z0-9.!@#$%&'*+-/=?\^_`{|}~-]/g, '');
                    if( newCurrentId != currentId ){
                        alert(MessageHash["share_center.78"].replace('%CURRENT%', currentId).replace('%NEW%', newCurrentId));
                        conn.addParameter("user_"+index, newCurrentId);
                        entry.setAttribute("data-entry_id", newCurrentId);
                    }
                    conn.addParameter("user_pass_"+index, entry.NEW_USER_PASSWORD);
                }
                conn.addParameter("entry_type_"+index, entry.hasClassName("group_entry")?"group":"user");
                index++;
            });
            if(oForm.down("#watch_folder")){
                conn.addParameter("self_watch_folder",oForm.down("#watch_folder").checked?"true":"false");
            }
            if(this._currentFolderWatchValue) conn.addParameter("self_watch_folder", "true");
            if(this.shareFolderMode == "workspace"){
                conn.onComplete = function(transport){
                    var response = parseInt(transport.responseText);
                    if(response == 200){
                        if(this._currentRepositoryId){
                            ajaxplorer.displayMessage('SUCCESS', MessageHash['share_center.19']);
                        }else{
                            ajaxplorer.displayMessage('SUCCESS', MessageHash['share_center.18']);
                        }
                        ajaxplorer.fireNodeRefresh(this.currentNode, function(newNode){
                            this.currentNode = newNode;
                            this.shareRepository(true);
                            modal.refreshDialogPosition();
                        }.bind(this));
                    }else{
                        var messages = {100:349, 101:352, 102:350, 103:351};
                        ajaxplorer.displayMessage('ERROR', MessageHash[messages[response]]);
                        if(response == 101){
                            oForm.down("#repo_label").focus();
                        }
                        modal.refreshDialogPosition();
                    }
                }.bind(this);
            }else{
                oForm.down('div#generate_indicator').show();
                conn.onComplete = function(transport){

                    oForm.down('div#generate_indicator').hide();
                    if(this.shareFolderMode == 'minisite_public' || this.shareFolderMode == 'minisite_private'){
                        oForm.down("#share_result").show();
                    }
                    var response = transport.responseText;
                    if(!response.startsWith('http')){
                        var iResponse = parseInt(response);
                        var messages = {100:349, 101:352, 102:350, 103:351};
                        var err;
                        if(messages[iResponse]) err = MessageHash[messages[iResponse]];
                        else if(MessageHash[response]) err = MessageHash[response];
                        else err = response;
                        ajaxplorer.displayMessage('ERROR', err);
                        if(response == 101){
                            oForm.down("#repo_label").focus();
                        }
                        modal.refreshDialogPosition();
                    }else{
                        this.currentNode.getMetadata().set("ajxp_shared", "true");
                        ajaxplorer.fireNodeRefresh(this.currentNode, function(newNode){
                            this.currentNode = newNode;
                            this.shareRepository(true);
                            modal.refreshDialogPosition();
                        }.bind(this));
                    }

                }.bind(this);
            }
            conn.sendAsync();
            return false;
        }.bind(this);

        var loadFunc = function(oForm){

            if(reload){
                addLightboxMarkupToElement(oForm.up(".dialogContent"));
            }
            var linkContainer =oForm.down("#share_container");
            linkContainer.observe("click", function(){
                linkContainer.select();
            });
            if(window.navigator.platform.startsWith("Mac")){
                $(oForm).select(".macos_only").invoke("show");
                $(oForm).select(".non_macos").invoke("hide");
            }
            oForm.down('#share_update').observe("click", function(){
                submitFunc(oForm);
            });

            oForm.removeClassName('share_leaf');
            oForm.removeClassName('readonly_mode');
            oForm.removeClassName('type-ws');
            oForm.removeClassName('share_expired');
            oForm.removeClassName("share_edit");
            var pluginConfigs = ajaxplorer.getPluginConfigs("action.share");
            if(this.currentNode.isLeaf()) oForm.addClassName('share_leaf');
            if(this.readonlyMode) oForm.addClassName('readonly_mode');
            this.maxexpiration = parseInt(pluginConfigs.get("FILE_MAX_EXPIRATION"));
            if(this.maxexpiration > 0){
                oForm.down("[name='expiration']").setValue(this.maxexpiration);
            }
            this.maxdownload = parseInt(pluginConfigs.get("FILE_MAX_DOWNLOAD"));
            if(this.maxdownload > 0){
                oForm.down("[name='downloadlimit']").setValue(this.maxdownload);
            }
            if(!this.authorizations){
                this.authorizations = {
                    folder_public_link : pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'both' ||  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'minisite' ,
                    folder_workspaces :  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'both' ||  pluginConfigs.get("ENABLE_FOLDER_SHARING") == 'workspace' ,
                    file_public_link : pluginConfigs.get("ENABLE_FILE_PUBLIC_LINK"),
                    editable_hash : pluginConfigs.get("HASH_USER_EDITABLE")
                };
            }

            if(!this.currentNode.isLeaf() && !this.authorizations.folder_public_link && !this.authorizations.folder_workspaces){
                alert('You are not authorized to share folders');
                hideLightBox();
                return;
            }else if(this.currentNode.isLeaf() && !this.authorizations.file_public_link){
                alert('You are not authorized to share files');
                hideLightBox();
                return;
            }else if(!this.currentNode.isLeaf() && !this.authorizations.folder_public_link){
                this.shareFolderMode = "workspace";
            }else if(!this.currentNode.isLeaf() && !this.authorizations.folder_workspaces){
                this.shareFolderMode = "minisite_public";
            }

            if(this.shareFolderMode == "minisite_public"){

                oForm.select(".mode-ws").invoke('hide');
                oForm.select(".mode-minipriv").invoke('hide');
                oForm.select(".mode-minipub").invoke('show');

            }else if(this.shareFolderMode == "minisite_private"){

                oForm.select(".mode-ws").invoke('hide');
                oForm.select(".mode-minipub").invoke('hide');
                oForm.select(".mode-minipriv").invoke('show');
                oForm.down(".editable_users_list").setStyle({height: '100px'});
                oForm.down("#share_generate").insert({before:oForm.down("#target_user")});

            }else{
                oForm.addClassName('type-ws');
                oForm.select(".mode-minipriv").invoke('hide');
                oForm.select(".mode-minipub").invoke('hide');
                oForm.select(".mode-ws").invoke('show');
                oForm.down(".editable_users_list").setStyle({height: ''});
            }

            if(this.shareFolderMode == "minisite_public" && this.currentNode.isLeaf()
                && this.currentNode.getMetadata().get("ajxp_shared_minisite") != "public"){
                    oForm.down('#simple_right_write').checked = false;
                    oForm.down('#simple_right_write').hide();
                    oForm.down('label[for="simple_right_write"]').hide();
            }

            if(!this.authorizations.folder_public_link || !this.authorizations.folder_workspaces){
                oForm.down("#share-folder-type-chooser").hide();
            }else{
                oForm.down('#share-folder-type-chooser').select("input").each(function(i){
                    if(i.id == 'share-type-minisite' && this.shareFolderMode == 'minisite_public'){
                        i.checked = true;
                    }else if(i.id == 'share-type-workspace' && this.shareFolderMode == 'workspace'){
                        i.checked = true;
                    }
                    i.observe("click", function(){
                        if(i.id == 'share-type-minisite') this.performShare('minisite-public');
                        else if(i.id == 'share-type-workspace') this.performShare('workspace');
                    }.bind(this));
                }.bind(this));
            }


            var nodeMeta = this.currentNode.getMetadata();
            if(nodeMeta.get("ajxp_shared")) oForm.addClassName("share_edit");
            oForm.down("div#target_repository_toggle").select("a").invoke( "observe", "click", function(){
                oForm.toggleClassName("edit_parameters");
                modal.refreshDialogPosition();
            });

            if(!this.authorizations.editable_hash){
                oForm.down('#editable_hash_container').hide();
            }

            this.createQRCode = ajaxplorer.getPluginConfigs("action.share").get("CREATE_QRCODE");
            var disableAutocompleter = (nodeMeta.get("ajxp_shared") && ( this.shareFolderMode != "workspace" || this.readonlyMode ));
            var updateUserEntryAfterCreate = function(li, assignedRights, watchValue){
                if(assignedRights == undefined) assignedRights = "r";
                var id = Math.random();
                var watchBox = '';
                if(ajaxplorer.hasPluginOfType("meta", "watch")){
                    if(watchValue) watchValue = ' checked';
                    else watchValue = '';
                    watchBox = '<span class="cbContainer"><input id="n'+id+'" type="checkbox" name="n"'+watchValue+'></span>';
                }
                li.insert({top:'<div class="user_entry_rights">' +
                    '<span class="cbContainer"><input type="checkbox" id="r'+id+'" name="r" '+(assignedRights.startsWith("r")?"checked":"") +'></span>' +
                    '<span class="cbContainer"><input id="w'+id+'" type="checkbox" name="w"  '+(assignedRights.endsWith("w")?"checked":"") +'></span>' +
                     watchBox +
                    '</div>'
                });
                if(disableAutocompleter){
                    li.select('input[type="checkbox"]').invoke("disable");
                    li.down('span.delete_user_entry').stopObserving("click");
                }
            };
            oForm.down('#repo_label').setValue(getBaseName(this.currentNode.getPath()));
            var shareFolderForm = oForm.down('#share_folder_form');
            if(!shareFolderForm.autocompleter){
                var pref = ajaxplorer.getPluginConfigs("action.share").get("SHARED_USERS_TMP_PREFIX");
                shareFolderForm.autocompleter = new AjxpUsersCompleter(
                    oForm.down("#shared_user"),
                    oForm.down("#shared_users_summary"),
                    $("shared_users_autocomplete_choices"),
                    {
                        tmpUsersPrefix:pref,
                        updateUserEntryAfterCreate:updateUserEntryAfterCreate,
                        indicator: oForm.down("#complete_indicator"),
                        minChars:parseInt(ajaxplorer.getPluginConfigs("conf").get("USERS_LIST_COMPLETE_MIN_CHARS"))
                    }
                );
            }
            oForm.down('input[name="guest_user_pass"]').writeAttribute('placeholder', this.authorizations.password_placeholder);
            if(this.readonlyMode){
                oForm.down("#shared_user").disabled = true;
            }
            var openBlocks = null;
            this._currentRepositoryId = null;
            this._currentRepositoryLink = null;
            this._currentRepositoryLabel = null;
            if(nodeMeta.get("ajxp_shared")){
                if (this.shareFolderMode != 'workspace'){
                    oForm.down('#share_result').show();
                    oForm.down(".editable_users_header").addClassName('autocomplete_readonly');
                    $("shared_user").addClassName('autocomplete_readonly').removeClassName("dialogFocus");
                    $("shared_users_summary").addClassName('autocomplete_readonly');
                    $("shared_users_autocomplete_choices").addClassName('autocomplete_readonly');
                }
                oForm.down('div#share_generate').hide();
                if(oForm.down("#share_unshare")) {
                    oForm.down('div#share_unshare').show();
                    oForm.down('#unshare_button').observe("click", this.performUnshareAction.bind(this));
                }
                oForm.down('#complete_indicator').show();
                this.loadSharedElementData(this.currentNode, function(json){
                    this._currentRepositoryId = json['repositoryId'];
                    this._currentRepositoryLabel = json['label'];
                    this._currentRepositoryLink = json['repository_url'];
                    oForm.down('input#repo_label').value = json['label'];
                    oForm.down('#repo_description').value = json['description'];
                    try{
                        if(json['password']){
                            oForm.down('input[name="guest_user_pass"]').setValue(json['password']);
                        }
                        var passwordField = oForm.down('input[name="guest_user_pass"]');
                        var passwordButton = oForm.down('#remove_user_pass');
                        var protopassContainer = oForm.down('#password_strength_checker');
                        if(json['has_password']){
                            var placeholder = this.authorizations.password_placeholder;
                            passwordField.writeAttribute('placeholder', '***********');
                            passwordField.writeAttribute('data-password-set', 'true');
                            protopassContainer.hide();
                            passwordButton.show();
                            passwordButton.observeOnce('click', function(){
                                passwordField.writeAttribute('data-password-set', 'false');
                                passwordField.writeAttribute('placeholder', placeholder);
                                passwordButton.hide();
                                protopassContainer.show();
                                new Protopass(passwordField, {
                                    barContainer : protopassContainer,
                                    barPosition:'bottom',
                                    labelWidth: 28
                                });
                            });
                        }else{
                            passwordField.writeAttribute('data-password-set', 'false');
                            passwordButton.hide();
                            new Protopass(passwordField, {
                                barContainer : protopassContainer,
                                barPosition:'bottom',
                                labelWidth: 28
                            });
                        }
                        if(json['expire_time']){
                            oForm.down('input[name="expiration"]').setValue(json['expire_after']);
                        }
                        if(json['download_limit']){
                            oForm.down('input[name="downloadlimit"]').setValue(json['download_limit']);
                        }
                        var chooser = this.createTemplateChooser(oForm);
                        var container = oForm.down('.layout_template_container');
                        container.insert(chooser);
                        if(json["minisite_layout"]){
                            chooser.setValue(json['minisite_layout']);
                        }
                        if(json['is_expired']){
                            oForm.addClassName('share_expired');
                        }
                    }catch(e){}
                    oForm.down('#complete_indicator').hide();
                    if(json.minisite){
                        oForm.down('#share_container').setValue(json.minisite.public_link);
                        this._currentRepositoryLink = json.minisite.public_link;
                        try{
                            this._currentGuestUserId = json.entries[0].ID;
                            this._currentMinisiteHash = json.minisite.hash;
                        }catch(e){}
                        oForm.down('#simple_right_download').checked = !(json.minisite.disable_download);
                        if(json.entries && json.entries.length){
                            oForm.down('#simple_right_read').checked = (json.entries[0].RIGHT.indexOf('r') !== -1 && json['minisite_layout']!='ajxp_unique_dl');
                            oForm.down('#simple_right_write').checked = (json.entries[0].RIGHT.indexOf('w') !== -1);
                        }
                        if(this.authorizations.editable_hash && this._currentMinisiteHash){
                            if(json.minisite.hash_is_shorten){
                                oForm.down('#editable_hash_container').hide();
                            }else{
                                oForm.down('#editable_hash_container').show();
                                oForm.down('#editable_hash_link').update(this._currentRepositoryLink.replace(this._currentMinisiteHash, oForm.down('#editable_hash_link').innerHTML));
                                oForm.down('#editable_hash_link').down('input').setValue(this._currentMinisiteHash);
                            }
                        }
                    }
                    $A(json['entries']).each(function(u){
                        var newItem =  $('share_folder_form').autocompleter.createUserEntry(u.TYPE=="group", u.TYPE =="tmp_user", u.ID, u.LABEL);
                        updateUserEntryAfterCreate(newItem, (u.RIGHT?u.RIGHT:""), u.WATCH);
                        newItem.appendToList($('shared_users_summary'));
                    });
                    this._currentFolderWatchValue = json['element_watch'];
                    if(reload){
                        removeLightboxFromElement(oForm.up(".dialogContent"));
                        ajaxplorer.fireNodeRefresh(this.currentNode);
                    }

                    if(this.shareFolderMode != "workspace"){
                        this.updateDialogButtons(oForm.down('#target_repository_toggle'), oForm, "folder", json);
                    }else{
                        var El = new Element('div', {className:"SF_horizontal_fieldsRow"}).update('<div class="SF_horizontal_actions SF_horizontal_pastilles" style="padding-top: 12px;padding-left: 7px;"></div>');
                        oForm.down("#shareDialogButtons").insert({top:El});
                        this.updateDialogButtons(El, oForm, "folder", json, !this.readonlyMode?function(){submitFunc(oForm);}:null);
                    }
                    oForm.select('span.simple_tooltip_observer').each(function(e){
                        modal.simpleTooltip(e, e.readAttribute('data-tooltipTitle'), 'top center', 'down_arrow_tip', 'element');
                    });

                }.bind(this));
            }else{
                $('shared_user').observeOnce("focus", function(){
                    $('share_folder_form').autocompleter.activate();
                });
                if(this.authorizations.editable_hash){
                    oForm.down('#editable_hash_link').insert({top:MessageHash['share_center.171'] + ': '});
                }
                if(this.authorizations.password_mandatory){
                    openBlocks = ["security_parameters"];
                }
                new Protopass(oForm.down('input[name="guest_user_pass"]'), {
                    barContainer : oForm.down('#password_strength_checker'),
                    barPosition:'bottom',
                    labelWidth: 28
                });
                if(this.shareFolderMode != "workspace"){
                    var generateButton = oForm.down("#generate_publiclet");
                    var container = oForm.down('.layout_template_container');
                    var tplChooser = this.createTemplateChooser(oForm);
                    if(tplChooser){
                        if(container){
                            container.insert(tplChooser);
                        }else{
                            generateButton.insert({before:tplChooser});
                            if(tplChooser.type != 'hidden'){
                                generateButton.setStyle({float: 'left'});
                            }
                        }
                    }
                    generateButton.observe("click", function(){
                        generateButton.hide();
                        submitFunc(oForm);
                    } );
                }
            }
            if(ajaxplorer.hasPluginOfType("meta", "watch")){
                oForm.down('#target_user').down('#header_watch').show();
            }else{
                oForm.down('#target_user').down('#header_watch').hide();
            }
            oForm.down('.editable_users_header').select("span").each(function(span){
                span.observe("click", function(event){
                    var checked = !event.target.status;
                    $('shared_users_summary').select("div.user_entry").each(function(entry){
                        var boxes = entry.select('input[type="checkbox"]');
                        if(event.target.id == 'header_read') boxes[0].checked = checked;
                        else if(event.target.id == 'header_write') boxes[1].checked = checked;
                        else if(event.target.id == 'header_watch' && boxes[2]) boxes[2].checked = checked;
                    });
                    event.target.status = checked;
                    event.target.setStyle({fontWeight: checked ? 'bold' : 'normal'});
                });
            });

            if(!reload){
                window.setTimeout(modal.refreshDialogPosition.bind(modal), 400);
            }
            this.accordionize(oForm, openBlocks);

        }.bind(this);
        var closeFunc = function (oForm){
            if(Prototype.Browser.IE){
                /*
                if($(document.body).down("#shared_users_autocomplete_choices")){
                    $(document.body).down("#shared_users_autocomplete_choices").remove();
                }
                */
                if($(document.body).down("#shared_users_autocomplete_choices_iefix")){
                    $(document.body).down("#shared_users_autocomplete_choices_iefix").remove();
                }
            }
        };
        if(window.ajxpBootstrap.parameters.get("usersEditable") == false){
            ajaxplorer.displayMessage('ERROR', MessageHash[394]);
        }else{
            modal.showDialogForm('Get',
                'share_folder_form',
                loadFunc,
                submitFunc, /*(this.shareFolderMode != "workspace" || this.readonlyMode ? function(){hideLightBox();} : submitFunc),*/
                closeFunc,
                false,
                !(this.shareFolderMode == "workspace" && !this.currentNode.getMetadata().get("ajxp_shared"))
            );
        }
    },

    createTemplateChooser : function(oForm){

        // Search registry for template nodes starting with minisite_
        var tmpl;
        var linkRightsToTemplates = false;
        if(ajaxplorer.getUserSelection().isUnique() && this.currentNode.isLeaf()){
            linkRightsToTemplates = true;
            var currentExt = this.currentNode.getAjxpMime();
            tmpl = XPathSelectNodes(ajaxplorer.getXmlRegistry(), "//template[contains(@name, 'unique_preview_')]");
        }else{
            tmpl = XPathSelectNodes(ajaxplorer.getXmlRegistry(), "//template[contains(@name, 'minisite_')]");
        }
        // Filter with theme
        // and @theme='"+ajxpBootstrap.parameters.get('theme')+"'
        if(!tmpl.length){
            return false;
        }
        if(tmpl.length == 1){
            return new Element('input', {
                type:'hidden',
                name:'minisite_layout',
                value:tmpl[0].getAttribute('element')
            });
        }
        var chooser = new Element('select', {
            name:'minisite_layout',
            className:'minisite_layout_selector'});
        var crtTheme = ajxpBootstrap.parameters.get('theme');
        var values = {};
        var noEditorsFound = false;
        tmpl.each(function(node){
            var theme = node.getAttribute('theme');
            if(theme && theme != crtTheme) return;
            var element = node.getAttribute('element');
            var name = node.getAttribute('name');
            var label = node.getAttribute('label');
            if(currentExt && name == "unique_preview_file"){
                var editors = ajaxplorer.findEditorsForMime(currentExt);
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
            chooser.insert(new Element('option', {value:element}).update(label));
        });
        var read;
        if(linkRightsToTemplates && values["unique_preview_file"] && values["unique_preview_download"]){
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

    },

    populateLinkData: function(linkData, oRow){

        oRow.down('[name="link_url"]').setValue(linkData["publiclet_link"]);
        oRow.down('[name="link_tag"]').setValue('');
        /*
        var actions = oRow.down('.SF_horizontal_actions');
        if(linkData['has_password']){
            actions.insert({top:new Element('span', {className:'icon-key simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.85"]}).update(' '+MessageHash["share_center.84"])});
        }
        if(linkData["expire_time"]){
            if(linkData['is_expired'] && linkData['expire_after'] <= 0 && (linkData['download_limit'] && linkData['download_limit'] != linkData['download_counter'])){
                actions.insert({top:new Element('span', {className:'icon-calendar simple_tooltip_observer SF_horizontal_action_destructive',"data-tooltipTitle":MessageHash["share_center.169"]}).update(' '+linkData["expire_time"])});
            }else{
                actions.insert({top:new Element('span', {className:'icon-calendar simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.87"]}).update(' '+linkData["expire_time"])});
            }
        }
        var dlC = new Element('span', {className:'icon-download-alt simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.89"]}).update(' '+MessageHash["share_center.88"]+' '+linkData['download_counter']+'/'+linkData['download_limit']);
        actions.insert({top:dlC});
        dlC.observe("click", function(){
            if(window.confirm(MessageHash['share_center.106'])){
                var conn = new Connexion();
                conn.setParameters({
                    file: this.currentNode.getPath(),
                    get_action: 'reset_counter',
                    element_id: linkData["element_id"]
                });
                conn.onComplete =  function(transport){
                    dlC.update(' '+MessageHash["share_center.88"]+' 0/'+linkData['download_limit']);
                };
                conn.sendAsync();
            }
        }.bind(this));
        */
        oRow.down('[data-action="remove"]').observe("click", function(){
            this.performUnshareAction(linkData['element_id'], oRow);
        }.bind(this));

        oRow.down('[name="link_url"]').select();

        this.updateDialogButtons(oRow, null, "file", linkData);

        if(linkData['tags']){
            oRow.down('[name="link_tag"]').setValue(linkData['tags']);
        }
        if(linkData['is_expired']){
            oRow.addClassName('share_expired');
        }
        oRow.down('[name="link_tag"]').observe("keydown", function(event){
            if(event.keyCode == Event.KEY_RETURN){
                Event.stop(event);
                var t = oRow.down('[name="link_tag"]').getValue();
                var conn = new Connexion();
                conn.setParameters({
                    file: this.currentNode.getPath(),
                    get_action: 'update_shared_element_data',
                    p_name: 'tags',
                    p_value: t,
                    element_id: linkData["element_id"]
                });
                conn.sendAsync();
            }
        }.bind(this));

        oRow.select('span.simple_tooltip_observer').each(function(e){
            modal.simpleTooltip(e, e.readAttribute('data-tooltipTitle'), 'top center', 'down_arrow_tip', 'element');
        });

    },

    accordionize: function(form, openBlocks){

        form.select('div[data-toggleBlock]').each(function(toggler){

            var toggleName = toggler.readAttribute('data-toggleBlock');
            var toggled = form.down('#' + toggleName);
            if(!toggled) return;

            if(openBlocks && openBlocks.indexOf(toggleName) > -1){
                toggled.addClassName('share_dialog_toggled_open');
            }
            toggler.addClassName('share_dialog_toggler');
            var initialHeight = toggled.getHeight();
            if(initialHeight){
                toggled.setStyle({height: initialHeight+'px'});
            }
            if(!toggled.hasClassName('share_dialog_toggled_open')){
                toggled.addClassName('share_dialog_toggled_closed');
            }else{
                toggler.addClassName('share_dialog_toggler_closed');
            }
            toggler.insert({top: '<span class="icon-angle-down toggler_arrow"></span>'});
            toggler.observe('click', function(){
                toggled.toggleClassName('share_dialog_toggled_closed');
                toggler.toggleClassName('share_dialog_toggler_closed');
                window.setTimeout(function(){
                    modal.refreshDialogPosition();
                }, (initialHeight?700:10));
            });

        });

    },

    shareFile : function(){

        var nodeMeta = this.currentNode.getMetadata();
        modal.showDialogForm(
            'Get',
            'share_form',
            function(oForm){
                /*
                new Protopass(oForm.down('input[name="password"]'), {
                    barContainer : $('public_pass_container'),
                    barPosition:'bottom',
                    labelWidth: 58
                });
                */
                if(nodeMeta.get("ajxp_shared")){
                    oForm.down('div#share_result').show();
                    oForm.down('div#generate_indicator').show();
                    this.loadSharedElementData(this.currentNode, function(json){

                        var firstRow = oForm.down('#share_result').down('div.SF_horizontal_fieldsRow');
                        if(json['repositoryId']){
                            this.shareFolderMode = 'minisite_public';
                            this.shareRepository(true);
                            return;
                        }
                        $A(json).each(function(linkData){
                            var row = firstRow.cloneNode(true);
                            firstRow.parentNode.insert(row);
                            row.setStyle({display:'block'});
                            this.populateLinkData(linkData, row);
                        }.bind(this));

                        oForm.down('div#generate_indicator').hide();

                    }.bind(this));

                }
            }.bind(this),
            function(oForm){
                oForm.down('#generate_publiclet').stopObserving("click");
                oForm.down('[data-action="remove"]').stopObserving("click");
                hideLightBox(true);
                return false;
            },
            null,
            'close',
            true
        );

    },

    loadInfoPanel : function(container, node){
        container.down('#ajxp_shared_info_panel .infoPanelTable').update('<div class="infoPanelRow">\
            <div class="infoPanelLabel">'+MessageHash['share_center.55']+'</div>\
            <div class="infoPanelValue"><span class="icon-spinner"></span></div>\
            </div>\
        ');
        ShareCenter.prototype.loadSharedElementData(node, function(jsonData){
            "use strict";

            if(jsonData.error){

                container.down("#ajxp_shared_info_panel .infoPanelTable").update('<div class="share_info_panel_main_legend"><span class="icon-warning-sign"></span> '+jsonData["label"]+'</div>');

            }else if(node.isLeaf() && !jsonData['repositoryId']){

                if(!jsonData) return ;
                var linksCount = jsonData.length;
                jsonData = jsonData[0];
                var linkExpired = jsonData['is_expired']?true:false;
                var directLink = "";
                if(!jsonData['has_password'] && !jsonData['is_expired']){
                    var shortlink = jsonData.publiclet_link + (jsonData.publiclet_link.indexOf('?') !== -1 ? '&' : '?') + 'dl=true';
                    directLink += '\
                    <div class="infoPanelRow">\
                        <div class="infoPanelLabel">'+MessageHash['share_center.60']+'</div>\
                        <div class="infoPanelValue"><textarea style="width:100%;height: 45px;" readonly="true"><a href="'+ shortlink +'">'+ MessageHash[88] + ' ' +node.getLabel()+'</a></textarea></div>\
                    </div>\
                    ';
                    var editors = ajaxplorer.findEditorsForMime(node.getAjxpMime(), true);
                    if(editors.length){
                        var tplString ;
                        var messKey = "share_center.61";
                        var newlink = jsonData.publiclet_link + (jsonData.publiclet_link.indexOf('?') !== -1 ? '&' : '?') + 'dl=true&ct=true';
                        if(Class.getByName(editors[0].editorClass).prototype.getSharedPreviewTemplate){
                            var template = Class.getByName(editors[0].editorClass).prototype.getSharedPreviewTemplate(node);
                            tplString = template.evaluate({WIDTH:480, HEIGHT:260, DL_CT_LINK:newlink});
                        }else{
                            tplString = newlink;
                            messKey = "share_center.60";
                        }
                        directLink += '\
                            <div class="infoPanelRow">\
                                <div class="infoPanelLabel">'+MessageHash[messKey]+'</div>\
                                <div class="infoPanelValue"><textarea style="width:100%;height: 80px;" readonly="true">'+ tplString + '</textarea></div>\
                            </div>\
                        ';
                    }
                }

                container.down('#ajxp_shared_info_panel .infoPanelTable').update('\
                    <div class="share_info_panel_main_legend">'+MessageHash["share_center.140"+(jsonData['is_expired']?'b':'')]+ '</div>\
                    <div class="infoPanelRow">\
                        <div class="infoPanelLabel">'+MessageHash['share_center.59']+'</div>\
                        <div class="infoPanelValue"><textarea style="width:100%;height: 45px;" class="'+(jsonData['is_expired']?'share_info_panel_link_expired':'')+'" readonly="true">'+ jsonData.publiclet_link +'</textarea></div>\
                    </div>'+directLink+'\
                    <div class="infoPanelRow">\
                        <div class="infoPanelLabel">'+MessageHash['share_center.51']+'</div>\
                        <div class="infoPanelValue">'+ jsonData.download_counter +' ' +  MessageHash['share_center.57'] + '</div>\
                    </div>\
                    <div class="infoPanelRow">\
                        <div class="infoPanelLabel">'+MessageHash['share_center.52']+'</div>\
                        <div class="infoPanelValue">'+MessageHash['share_center.22']+' '+ (jsonData.download_limit?jsonData.download_limit:MessageHash['share_center.53'])
                                +', '+MessageHash['share_center.11']+':'+ (jsonData.expiration_time?jsonData.expiration_time:MessageHash['share_center.53'])
                                +', '+MessageHash['share_center.12']+':'+ (jsonData.has_password?MessageHash['share_center.13']:MessageHash['share_center.14']) +'</div>\
                    </div>\
                ');

                if(linksCount > 1){
                    container.down('#ajxp_shared_info_panel .infoPanelTable').insert({bottom:'<div class="infoPanelRow">\
                        <div class="infoPanelLabel" colspan="2" style="text-align: center;font-style: italic;">'+MessageHash['share_center.'+(linksCount>2?'104':'105')].replace('%s', linksCount-1)+'</div>\
                    </div>'});
                }

            }else{
                var mainCont = container.down("#ajxp_shared_info_panel .infoPanelTable");
                var entries = [];
                $A(jsonData.entries).each(function(entry){
                    entries.push(entry.LABEL + ' ('+ entry.RIGHT +')');
                });
                var pwdProtected = '';
                if(jsonData['has_password']){
                    pwdProtected = ' ' + MessageHash['share_center.170'];
                }
                if(node.isLeaf()){
                    // LEAF SHARE
                    mainCont.update('<div class="share_info_panel_main_legend">'+MessageHash["share_center.140"+(jsonData['is_expired']?'b':'')]+ ' ' + pwdProtected + '</div>');
                    mainCont.insert('<div class="infoPanelRow">\
                            <div class="infoPanelLabel">'+MessageHash['share_center.121']+'</div>\
                            <div class="infoPanelValue"><input type="text" class="share_info_panel_link'+(jsonData['is_expired']?' share_info_panel_link_expired':'')+'" readonly="true" value="'+ jsonData.minisite.public_link +'"></div>\
                        </div>\
                    ');
                    if(!jsonData['is_expired'] && !jsonData['has_password'] && jsonData['content_filter']){
                        mainCont.insert(
                            '<div class="infoPanelRow">\
                            <div class="infoPanelLabel">'+MessageHash['share_center.61']+'</div>\
                            <div class="infoPanelValue"><textarea style="padding: 4px;width:97%;height: 80px;" id="embed_code" readonly="true"></textarea></div>\
                        </div>');
                    }
                    var dlPath = jsonData['content_filter']['filters'][node.getPath()];
                    mainCont.down("#embed_code").setValue("<a href='"+ jsonData.minisite.public_link +"?dl=true&file="+dlPath+"'>Download "+ getBaseName(node.getPath()) +"</a>");

                }else if(jsonData.minisite){
                    // MINISITE FOLDER SHARE
                    mainCont.update('<div class="share_info_panel_main_legend">'+MessageHash["share_center.138"+(jsonData['is_expired']?'b':'')]+ pwdProtected + '</div>');
                    // Links textearea
                    mainCont.insert('\
                        <div class="infoPanelRow">\
                            <div class="infoPanelLabel">'+MessageHash['share_center.62']+'</div>\
                            <div class="infoPanelValue"><input type="text" class="share_info_panel_link'+(jsonData['is_expired']?' share_info_panel_link_expired':'')+'" readonly="true" value="'+ jsonData.minisite.public_link +'"></div>\
                        </div>');
                    if(!jsonData['is_expired']){
                        mainCont.insert(
                            '<div class="infoPanelRow">\
                            <div class="infoPanelLabel">'+MessageHash['share_center.61']+'</div>\
                            <div class="infoPanelValue"><textarea style="padding: 4px;width:97%;height: 80px;" id="embed_code" readonly="true"></textarea></div>\
                        </div>');
                    }
                    mainCont.down("#embed_code").setValue("<iframe height='500' width='600' style='border:1px solid black;' src='"+jsonData.minisite.public_link+"'></iframe>");
                }else{
                    // WORKSPACE FOLDER
                    mainCont.update('<div class="share_info_panel_main_legend">'+MessageHash["share_center.139"+(jsonData['is_expired']?'b':'')]+'</div>');
                    mainCont.insert('<div class="infoPanelRow">\
                        <div class="infoPanelLabel">'+MessageHash['share_center.54']+'</div>\
                        <div class="infoPanelValue">'+ entries.join(', ') +'</div>\
                    </div>\
                    ');
                }
            }
            mainCont.select('textarea,input').each(function(t){
                t.observe("focus", function(e){ ajaxplorer.disableShortcuts();});
                t.observe("blur", function(e){ ajaxplorer.enableShortcuts();});
                t.observe("click", function(event){event.target.select();});
            });
            container.up("div[ajxpClass]").ajxpPaneObject.resize();
        }, true);
    },

    _prepareShareActionParameters: function(uniqueNode, conn){
        var meta = uniqueNode.getMetadata();
        if(meta.get('shared_element_hash')){
            conn.addParameter("hash", meta.get('shared_element_hash'));
            conn.addParameter("tmp_repository_id", meta.get('shared_element_parent_repository'));
            conn.addParameter("element_type", meta.get('share_type'));
        }else{
            conn.addParameter("file", uniqueNode.getPath());
            conn.addParameter("element_type", uniqueNode.isLeaf() ? "file" : meta.get("ajxp_shared_minisite")? "minisite" : "repository");
        }
    },

    loadSharedElementData : function(uniqueNode, jsonCallback, discrete){
        var conn = new Connexion();
        if(discrete){
            conn.discrete = true;
        }
        conn.addParameter("get_action", "load_shared_element_data");
        this._prepareShareActionParameters(uniqueNode, conn);
        conn.onComplete = function(transport){
            jsonCallback(transport.responseJSON);
        };
        conn.sendAsync();
    },


    performUnshareAction : function(elementId, removeRow){
        var conn = new Connexion();
        if(elementId){
            conn.addParameter("element_id", elementId);
        }
        conn.addParameter("get_action", "unshare");
        this._prepareShareActionParameters(this.currentNode, conn);
        conn.onComplete = function(){
            if(this._dataModel){
                hideLightBox(true);
                this._dataModel.requireContextChange(this._dataModel.getRootNode(), true);
                return;
            }
            if(removeRow){
                new Effect.Fade(removeRow, {duration: 0.3});
            }else{

                var oForm = modal.getForm();
                if(oForm.down('#generate_publiclet')){
                    oForm.down('#generate_publiclet').stopObserving("click");
                }
                if(oForm.down('#unshare_button')) {
                    oForm.down('#unshare_button').stopObserving("click");
                }
                hideLightBox(true);

            }
            ajaxplorer.fireNodeRefresh(this.currentNode);

        }.bind(this);
        conn.sendAsync();
    },

    generatePublicLinkCallback : function(){
        var userSelection = ajaxplorer.getUserSelection();
        if(!userSelection.isUnique() || (userSelection.hasDir() && !userSelection.hasMime($A(['ajxp_browsable_archive'])))) return;
        var oForm = $(modal.getForm());
        var publicUrl = window.ajxpServerAccessPath+'&get_action=share';
        publicUrl = userSelection.updateFormOrUrl(null,publicUrl);
        var conn = new Connexion(publicUrl);
        var serialParams = oForm.serialize(true);
        if(serialParams["expiration"] && ! this.checkPositiveNumber(serialParams["expiration"])
            || serialParams["downloadlimit"] && ! this.checkPositiveNumber(serialParams["downloadlimit"])){
            ajaxplorer.displayMessage("ERROR", MessageHash["share_center.75"]);
            return;
        }
        if(this.maxexpiration > 0 && !(serialParams["expiration"] > 0 && serialParams["expiration"] <= this.maxexpiration) ){
            ajaxplorer.displayMessage("ERROR", "Expiration must be between 1 and " + this.maxexpiration);
            return;
        }
        if(this.maxdownload > 0 && !(serialParams["downloadlimit"] > 0 && serialParams["downloadlimit"] <= this.maxdownload) ){
            ajaxplorer.displayMessage("ERROR", MessageHash["share_center.107"].replace("%s", this.maxdownload));
            return;
        }

        conn.setParameters(serialParams);

        conn.addParameter('get_action','share');
        conn.addParameter('format','json');
        var oThis = this;
        conn.onComplete = function(transport){
            var data = Object.extend({
                expiration_time:serialParams['expiration'],
                download_limit: serialParams['downloadlimit'],
                download_counter: 0,
                has_password: serialParams['password']?true:false
            }, transport.responseJSON);

            var firstRow = oForm.down('#share_result').down('.SF_horizontal_fieldsRow');
            var newRow = firstRow.cloneNode(true);
            firstRow.parentNode.insert(newRow);
            newRow.setStyle({display:'block'});
            this.populateLinkData(data, newRow);
            modal.refreshDialogAppearance();
            new Effect.Appear(oForm.down('div[id="share_result"]'), {
                duration:0.5,
                afterFinish : function(){
                    oForm.down('[id="share_container"]').select();
                    modal.refreshDialogAppearance();
                    modal.setCloseAction(function(){
                        ajaxplorer.fireNodeRefresh(oThis.currentNode);
                    });
                }.bind(this)
            });
        }.bind(this);
        conn.sendSync();
    },

    updateDialogButtons : function(dialogButtonsOrRow, bottomButtonsContainer, shareType, jsonData, updateFunc){

        if(bottomButtonsContainer && bottomButtonsContainer.down("#shareDialogButtons")){
            bottomButtonsContainer = bottomButtonsContainer.down("#shareDialogButtons");
        }
        // WATCH BUTTON
        if(ajaxplorer.hasPluginOfType("meta", "watch")){
            var st = (shareType == "folder" ? MessageHash["share_center.38"] : MessageHash["share_center.39"]);
            if(!dialogButtonsOrRow.down('#watch_folder_eye')) {
                dialogButtonsOrRow.down('.SF_horizontal_actions').insert({top:"<span class='simple_tooltip_observer' id='watch_folder_eye' data-tooltipTitle='"+MessageHash["share_center."+(shareType=='folder'?'83b':'83')]+"'><span class='icon-eye-close'></span> "+MessageHash["share_center.82"]+"<input type='checkbox' id='watch_folder' style='display:none;'></span>"});
            }
            var folderEye = dialogButtonsOrRow.down('#watch_folder_eye');
            var folderCheck = dialogButtonsOrRow.down('#watch_folder');

            folderCheck.checked = ((jsonData && jsonData['element_watch']) || this._currentFolderWatchValue);
            folderEye.update( '<span class="'+'icon-eye-'+ ( folderCheck.checked ? 'open' : 'close')+'"></span> ' + MessageHash['share_center.' + (folderCheck.checked ? '81' : '82')] );
            if(folderEye){
                folderEye.observe('click', function(){
                    folderCheck.checked = !folderCheck.checked;
                    folderEye.update( '<span class="'+'icon-eye-'+ ( folderCheck.checked ? 'open' : 'close')+'"></span> ' + MessageHash['share_center.' + (folderCheck.checked ? '81' : '82')] );
                    this._currentFolderWatchValue = folderCheck.checked;
                    if(shareType == "file" || this.shareFolderMode != "workspace"){
                        var conn = new Connexion();
                        conn.setParameters({
                            get_action: "toggle_link_watch",
                            set_watch : folderCheck.checked ?  "true" : "false",
                            file : this.currentNode.getPath()
                        });
                        if(shareType != "file"){
                            conn.addParameter('element_type', 'folder');
                        }
                        if(jsonData && jsonData['element_id']){
                            conn.addParameter("element_id", jsonData['element_id']);
                        }else if(this._currentRepositoryId){
                            conn.addParameter("repository_id", this._currentRepositoryId);
                        }
                        conn.onComplete = function(transport){
                            ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                        };
                        conn.sendAsync();
                    }
                }.bind(this));
            }
        }

        // MAILER BUTTON
        var forceOldSchool = ajaxplorer.getPluginConfigs("action.share").get("EMAIL_INVITE_EXTERNAL");
        var mailerButton, mailerShower;
        if(shareType == "file" && !dialogButtonsOrRow.down('#mailer_button')){
            dialogButtonsOrRow.down('.SF_horizontal_actions').insert({bottom:"<span class='simple_tooltip_observer' id='mailer_button' data-tooltipTitle='"+MessageHash['share_center.'+(shareType=='file'?'80':'80b')]+"'><span class='icon-envelope'></span> "+MessageHash['share_center.79']+"</span>"});
            mailerButton = dialogButtonsOrRow.down('#mailer_button');
            mailerButton.writeAttribute("data-tooltipTitle", MessageHash["share_center.41"]);
            mailerShower = dialogButtonsOrRow.up(".dialogContent");
        }else if(!bottomButtonsContainer.down('#mailer_button')){
            bottomButtonsContainer.insert("<div class='largeButton' id='mailer_button'><span title='"+MessageHash['share_center.'+(shareType=='file'?'80':'80b')]+"'><span class='icon-envelope'></span> "+MessageHash['share_center.79']+"</span></div>");
            mailerButton = bottomButtonsContainer.down("#mailer_button");
            mailerShower = bottomButtonsContainer.up(".dialogContent");
            bottomButtonsContainer.show();
        }
        var oForm = dialogButtonsOrRow.up('.dialogContent');

        if(ajaxplorer.hasPluginOfType("mailer") && !forceOldSchool){
            mailerButton.observe("click", function(event){
                Event.stop(event);
                var s, message, link;
                if(shareType == "file"){
                    s = MessageHash["share_center.42"];
                    if(s) s = s.replace("%s", ajaxplorer.appTitle);
                    link = dialogButtonsOrRow.down('[name="link_url"]').getValue();
                    message = s + "\n\n " + "<a href='"+link+"'>"+link+"</a>";
                }else{
                    if(this.currentNode.isLeaf()){
                        s = MessageHash["share_center.42"]
                    }else{
                        s = MessageHash["share_center.43"];
                    }
                    if(s) s = s.replace("%s", ajaxplorer.appTitle);
                    link = this._currentRepositoryLink;
                    if(this.shareFolderMode == 'workspace'){
                        message = s + "\n\n " + "<a href='" + link +"'>" + MessageHash["share_center.46"].replace("%s1", this._currentRepositoryLabel).replace("%s2", ajaxplorer.appTitle) + "</a>";
                    }else{
                        message = s + "\n\n " + "<a href='" + link +"'>" + MessageHash["share_center.46" + (this.currentNode.isLeaf()?'_file':'_mini')].replace("%s1", this._currentRepositoryLabel) + "</a>";
                    }
                }
                if(dialogButtonsOrRow.down('.share_qrcode') && dialogButtonsOrRow.down('.share_qrcode').visible() && dialogButtonsOrRow.down('.share_qrcode > img')){
                    message += "\n\n "+ MessageHash["share_center.108"] + "\n\n" + dialogButtonsOrRow.down('.share_qrcode').innerHTML;
                }
                var mailer = new AjxpMailer();
                var usersList = null;
                if(this.shareFolderMode == 'workspace' && oForm) {
                    usersList = oForm.down(".editable_users_list");
                }
                modal.showSimpleModal(
                    mailerShower,
                    mailer.buildMailPane(MessageHash["share_center.44"].replace("%s", ajaxplorer.appTitle), message, usersList, MessageHash["share_center.45"], link),
                    function(){
                        mailer.postEmail();
                        return true;
                    },function(){
                        return true;
                    });
            }.bind(this));
        }else{
            var subject = encodeURIComponent(MessageHash["share_center.44"].replace("%s", ajaxplorer.appTitle));
            var body;
            if(shareType == 'file'){
                body = dialogButtonsOrRow.down('[name="link_url"]').getValue();
                mailerButton.update('<a href="mailto:unknown@unknown.com?Subject='+subject+'&Body='+body+'"> invite</a>');
            }else{
                body = this.shareFolderMode != 'workspace' ?  dialogButtonsOrRow.up('.dialogContent').down('#share_container').getValue() : MessageHash["share_center.43"].replace("%s", ajaxplorer.appTitle);
                mailerButton.update('<a href="mailto:unknown@unknown.com?Subject='+subject+'&Body='+body+'"> invite</a>');
            }
            mailerButton.down('a').observe("click", function(e){
                var body;
                if(shareType == 'file'){
                    body = dialogButtonsOrRow.down('[name="link_url"]').getValue();
                }else{
                    body = this.shareFolderMode != 'workspace' ?  dialogButtonsOrRow.up('.dialogContent').down('#share_container').getValue() : MessageHash["share_center.43"].replace("%s", ajaxplorer.appTitle);
                }
                e.target.href = 'mailto:unknown@unknown.com?Subject='+subject+'&Body='+body;
            }.bind(this));

        }

        // QRCODE BUTTON
        var qrcodediv = dialogButtonsOrRow.down('.share_qrcode');
        if (this.createQRCode) {
            if(!qrcodediv){
                qrcodediv = new Element('div', {className:'share_qrcode'});
                dialogButtonsOrRow.insert(qrcodediv);
                qrcodediv.hide();
            }
            if(!dialogButtonsOrRow.down('#qrcode_button')){
                dialogButtonsOrRow.down('.SF_horizontal_actions').insert({bottom:"<span class='simple_tooltip_observer' id='qrcode_button' data-tooltipTitle='"+MessageHash['share_center.94']+"'><span class='icon-qrcode'></span> "+MessageHash['share_center.95']+"</span>"});
            }
            var qrcodeButton = dialogButtonsOrRow.down('#qrcode_button');
            qrcodeButton.writeAttribute("data-tooltipTitle", MessageHash["share_center.95"]);
            qrcodediv.update('');
            var randId = 'share_qrcode-' + new Date().getTime();
            qrcodediv.id = randId;

            qrcodeButton.observe("click", function(event){
                Event.stop(event);
                if (qrcodediv.innerHTML == '') {
                    var url;
                    if(shareType == "file"){
                        url = dialogButtonsOrRow.down('[name="link_url"]').getValue();
                    }else{
                        url = this._currentRepositoryLink;
                    }
                    var qrcode = new QRCode(randId, {
                        width: 128,
                        height: 128
                    });
                    qrcode.makeCode(url);
                }
                if(qrcodediv.visible()) qrcodediv.hide();
                else qrcodediv.show();

            }.bind(this));
        }

        // DOWNLOAD COUNTER BUTTON
        if(jsonData && jsonData["download_limit"]){

            var dlC;
            if(jsonData['is_expired'] && jsonData['download_limit'] && jsonData['download_limit'] == jsonData['download_counter']){
                dlC = new Element('span', {className:'simple_tooltip_observer SF_horizontal_action_destructive',"data-tooltipTitle":MessageHash["share_center.168"]}).update('<span class="icon-download-alt"></span> '+MessageHash["share_center.88"]+' '+jsonData['download_counter']+'/'+jsonData['download_limit']);
            }else{
                dlC = new Element('span', {className:'simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.89"]}).update('<span class="icon-download-alt"></span> '+MessageHash["share_center.88"]+' '+jsonData['download_counter']+'/'+jsonData['download_limit']);
            }


            dialogButtonsOrRow.down('.SF_horizontal_actions').insert({bottom:dlC});
            dlC.observe("click", function(){
                if(window.confirm(MessageHash['share_center.106'])){
                    var conn = new Connexion();
                    conn.addParameter("get_action", "reset_counter");
                    this._prepareShareActionParameters(this.currentNode, conn);
                    if(jsonData["element_id"]) conn.addParameter("element_id", jsonData["element_id"]);
                    conn.onComplete =  function(transport){
                        dlC.update(' '+MessageHash["share_center.88"]+' 0/'+jsonData['download_limit']);
                    };
                    conn.sendAsync();
                }
            }.bind(this));

        }

        // PASSWORD BUTTON
        if(jsonData['has_password']){
            dialogButtonsOrRow.down('.SF_horizontal_actions').insert({top:new Element('span', {className:'simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.85"]}).update('<span class="icon-key"></span> '+MessageHash["share_center.84"])});
        }

        // EXPIRATION TIME
        if(jsonData && jsonData["expire_time"]){
            if(jsonData['is_expired'] && jsonData['expire_after'] <= 0 && (jsonData['download_limit'] && jsonData['download_limit'] != jsonData['download_counter'])){
                dialogButtonsOrRow.down('.SF_horizontal_actions').insert({top:new Element('span', {className:'simple_tooltip_observer SF_horizontal_action_destructive',"data-tooltipTitle":MessageHash["share_center.169"]}).update('<span class="icon-calendar"></span> '+ jsonData["expire_time"])});
            }else{
                dialogButtonsOrRow.down('.SF_horizontal_actions').insert({top:new Element('span', {className:'simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.87"]}).update('<span class="icon-calendar"></span> '+jsonData["expire_time"])});
            }
        }

        if(updateFunc){
            var aSpan = new Element('span', {
                className:'',
                title:MessageHash['share_center.152']
            }).update('<span class="icon-save"></span> '+MessageHash['share_center.152']);
            var editButton = new Element('div', {className:'largeButton'}).update(aSpan);
            bottomButtonsContainer.insert(editButton);
            bottomButtonsContainer.show();
            editButton.observe("click", updateFunc);
        }

        // STOP SHARING BUTTON
        if(shareType == 'folder' && !bottomButtonsContainer.down(".largeButton.destruct")){

            // OPEN IN NEW WINDOW
            // Not working, problem with "window.opener" detection on the other end.
            /*
             var openButton = new Element('span', {
             className:'icon-external-link simple_tooltip_observer',
             'data-tooltipTitle':MessageHash['share_center.97']
             }).update(' '+MessageHash['share_center.96']);
             dialogButtonsOrRow.down('.SF_horizontal_actions').insert(openButton);
             openButton.observe("click", function(){
             var input = dialogButtonsOrRow.down('.SF_horizontal_actions').next("#share_container");
             if(input){
             window.open(input.getValue());
             }
             });
             */

            var span = new Element('span', {
                className:'',
                title:MessageHash['share_center.97']
            }).update('<span class="icon-remove"></span> '+MessageHash['share_center.96']);
            var stopButton = new Element('div', {className:'largeButton destruct'}).update(span);
            bottomButtonsContainer.insert(stopButton);
            bottomButtonsContainer.show();
            stopButton.observe("click", this.performUnshareAction.bind(this));
        }

    },

    checkPositiveNumber : function(str){
        var n = ~~Number(str);
        return String(n) === str && n >= 0;
    }

});

if(ajaxplorer && ajaxplorer.actionBar){
    ajaxplorer.actionBar.shareCenter = new ShareCenter();
}
