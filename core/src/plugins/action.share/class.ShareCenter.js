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
    _currentFolderWatchValue:false,

    performShareAction : function(){
        var userSelection = ajaxplorer.getUserSelection();
        this.currentNode = userSelection.getUniqueNode();
        this.shareFolderMode = "workspace";
        if(userSelection.hasDir() && !userSelection.hasMime($A(['ajxp_browsable_archive']))){
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
                    this.shareFolderMode = nodeMeta.get("ajxp_shared_minisite") == "public" ? "minisite_public" : "minisite_private";
                }
                this.shareRepository();
            }
        }else{
            this.createQRCode = ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("CREATE_QRCODE");
            this.shareFile(userSelection);
        }
    },

    shareRepository : function(reload){

        var submitFunc = function(oForm){
            if(!oForm.down('input[name="repo_label"]').value){
                alert(MessageHash[349]);
                return false;
            }
            var userSelection = ajaxplorer.getUserSelection();
            var publicUrl = ajxpServerAccessPath+'&get_action=share';
            publicUrl = userSelection.updateFormOrUrl(null, publicUrl);
            var conn = new Connexion(publicUrl);
            conn.setMethod("POST");
            var params = modal.getForm().serialize(true);
            conn.setParameters(params);
            if(this._currentRepositoryId){
                conn.addParameter("repository_id", this._currentRepositoryId);
            }
            if(this.shareFolderMode == "minisite_public"){
                conn.addParameter("create_guest_user", "true");
                conn.addParameter("sub_action", "create_minisite");
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
                        ajaxplorer.fireNodeRefresh(this.currentNode);
                        this.currentNode.getMetadata().set("ajxp_shared", "true");
                        this.shareRepository(true);
                    }else{
                        var messages = {100:349, 101:352, 102:350, 103:351};
                        ajaxplorer.displayMessage('ERROR', MessageHash[messages[response]]);
                        if(response == 101){
                            oForm.down("#repo_label").focus();
                        }
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
                        else err = 'Unknown error code ' + response;
                        ajaxplorer.displayMessage('ERROR', err);
                        if(response == 101){
                            oForm.down("#repo_label").focus();
                        }
                    }else{
                        this.currentNode.getMetadata().set("ajxp_shared", "true");
                        ajaxplorer.fireNodeRefresh(this.currentNode);
                        ajaxplorer.displayMessage('SUCCESS', 'Created a new public folder at ' + response);
                        oForm.down("#share_container").setValue(response);
                        this._currentRepositoryLink = response;
                        this._currentRepositoryLabel = oForm.down("#repo_label").getValue();
                        oForm.down("#share_container").select();
                        if(oForm.down("#share_unshare")) {
                            oForm.down("#share_unshare").show();
                            oForm.down('#unshare_button').observe("click", this.performUnshareAction.bind(this));
                        }
                        oForm.down("#share_generate").hide();
                        this.updateDialogButtons(oForm.down('#share_result').down("div.SF_horizontal_fieldsRow"), "folder");
                        oForm.select('span.simple_tooltip_observer').each(function(e){
                            modal.simpleTooltip(e, e.readAttribute('data-tooltipTitle'), 'top center', 'down_arrow_tip', 'element');
                        });
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

            oForm.down("#target_user_title").insert({after:oForm.down("#target_user")});
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
                oForm.select(".mode-minipriv").invoke('hide');
                oForm.select(".mode-minipub").invoke('hide');
                oForm.select(".mode-ws").invoke('show');
                oForm.down(".editable_users_list").setStyle({height: ''});
            }



            var nodeMeta = this.currentNode.getMetadata();
            if(nodeMeta.get("ajxp_shared")){
                // Reorganize
                var repoFieldset = oForm.down('div#target_repository');
            }
            
            this.createQRCode = ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("CREATE_QRCODE");
            var disableAutocompleter = (nodeMeta.get("ajxp_shared") && this.shareFolderMode != "workspace");
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
                }
            };
            oForm.down('#repo_label').setValue(getBaseName(this.currentNode.getPath()));
            if(!$('share_folder_form').autocompleter){
                var pref = ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("SHARED_USERS_TMP_PREFIX");
                $('share_folder_form').autocompleter = new AjxpUsersCompleter(
                    $("shared_user"),
                    $("shared_users_summary"),
                    $("shared_users_autocomplete_choices"),
                    {
                        tmpUsersPrefix:pref,
                        updateUserEntryAfterCreate:updateUserEntryAfterCreate,
                        /*
                        createUserPanel:{
                            panel : $("create_shared_user"),
                            pass  : $("shared_pass"),
                            confirmPass: $("shared_pass_confirm")
                        },*/
                        indicator: $("complete_indicator"),
                        minChars:parseInt(ajaxplorer.getPluginConfigs("conf").get("USERS_LIST_COMPLETE_MIN_CHARS"))
                    }
                );
            }
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
                    oForm.down('#complete_indicator').hide();
                    if(json.minisite){
                        oForm.down('#share_container').setValue(json.minisite.public_link);
                        this._currentRepositoryLink = json.minisite.public_link;
                        oForm.down('#simple_right_download').checked = !(json.minisite.disable_download);
                        if(json.entries && json.entries.length){
                            oForm.down('#simple_right_read').checked = (json.entries[0].RIGHT.indexOf('r') !== -1);
                            oForm.down('#simple_right_write').checked = (json.entries[0].RIGHT.indexOf('w') !== -1);
                        }
                        oForm.down('#simple_right_download').disable();
                        oForm.down('#simple_right_read').disable();
                        oForm.down('#simple_right_write').disable();
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
                        this.updateDialogButtons(oForm.down('#share_result').down("div.SF_horizontal_fieldsRow"), "folder");
                    }else{
                        var El = new Element('div', {className:"SF_horizontal_fieldsRow"}).update('<div class="SF_horizontal_actions SF_horizontal_pastilles" style="padding-top: 12px;padding-left: 7px;"></div>');
                        $("share_folder_form").next("div.dialogButtons").insert({top:El});
                        this.updateDialogButtons(El, "folder");
                    }
                    oForm.select('span.simple_tooltip_observer').each(function(e){
                        modal.simpleTooltip(e, e.readAttribute('data-tooltipTitle'), 'top center', 'down_arrow_tip', 'element');
                    });

                }.bind(this));
            }else{
                $('shared_user').observeOnce("focus", function(){
                    $('share_folder_form').autocompleter.activate();
                });
                if(this.shareFolderMode != "workspace"){
                    oForm.down("#generate_publiclet").observe("click", function(){submitFunc(oForm);} );
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

        }.bind(this);
        var closeFunc = function (oForm){
            if(Prototype.Browser.IE){
                if($(document.body).down("#shared_users_autocomplete_choices")){
                    $(document.body).down("#shared_users_autocomplete_choices").remove();
                }
                if($(document.body).down("#shared_users_autocomplete_choices_iefix")){
                    $(document.body).down("#shared_users_autocomplete_choices_iefix").remove();
                }
                $('create_shared_user').select('div.dialogButtons>input').invoke("removeClassName", "dialogButtons");
            }
        }
        if(window.ajxpBootstrap.parameters.get("usersEditable") == false){
            ajaxplorer.displayMessage('ERROR', MessageHash[394]);
        }else{
            modal.showDialogForm('Get',
                'share_folder_form',
                loadFunc,
                (this.shareFolderMode != "workspace" ? function(){hideLightBox();} : submitFunc),
                closeFunc,
                (this.shareFolderMode != "workspace" ? true: false),
                false
            );
        }
    },

    populateLinkData: function(linkData, oRow){

        oRow.down('[name="link_url"]').setValue(linkData["publiclet_link"]);
        oRow.down('[name="link_tag"]').setValue('');
        var actions = oRow.down('.SF_horizontal_actions');
        if(linkData['has_password']){
            actions.insert({top:new Element('span', {className:'icon-key simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.85"]}).update(' '+MessageHash["share_center.84"])});
        }
        if(linkData["expire_time"]){
            actions.insert({top:new Element('span', {className:'icon-calendar simple_tooltip_observer',"data-tooltipTitle":MessageHash["share_center.87"]}).update(' '+linkData["expire_time"])});
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

        oRow.down('[data-action="remove"]').observe("click", function(){
            this.performUnshareAction(linkData['element_id'], oRow);
        }.bind(this));

        oRow.down('[name="link_url"]').select();

        this.updateDialogButtons(oRow, "file", linkData);

        if(linkData['tags']){
            oRow.down('[name="link_tag"]').setValue(linkData['tags']);
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

    shareFile : function(){

        modal.showDialogForm(
            'Get',
            'share_form',
            function(oForm){
                new Protopass(oForm.down('input[name="password"]'), {
                    barContainer : $('public_pass_container'),
                    barPosition:'bottom',
                    labelWidth: 58
                });
                var nodeMeta = this.currentNode.getMetadata();
                if(nodeMeta.get("ajxp_shared")){
                    oForm.down('div#share_result').show();
                    oForm.down('div#generate_indicator').show();
                    this.loadSharedElementData(this.currentNode, function(json){

                        var firstRow = oForm.down('#share_result').down('div.SF_horizontal_fieldsRow');
                        $A(json).each(function(linkData){
                            var row = firstRow.cloneNode(true);
                            firstRow.parentNode.insert(row);
                            row.setStyle({display:'block'});
                            this.populateLinkData(linkData, row);
                            oForm.down('div#generate_indicator').hide();
                        }.bind(this));

                        return;

                    }.bind(this));

                }
                this.maxexpiration = parseInt(ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("FILE_MAX_EXPIRATION"));
                if(this.maxexpiration > 0){
                    oForm.down("[name='expiration']").setValue(this.maxexpiration);
                }
                this.maxdownload = parseInt(ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("FILE_MAX_DOWNLOAD"));
                if(this.maxdownload > 0){
                    oForm.down("[name='downloadlimit']").setValue(this.maxdownload);
                }
                var button = $(oForm).down('#generate_publiclet');
                button.observe("click", this.generatePublicLinkCallback.bind(this));
            }.bind(this),
            function(oForm){
                oForm.down('#generate_publiclet').stopObserving("click");
                oForm.down('[data-action="remove"]').stopObserving("click");
                hideLightBox(true);
                return false;
            },
            null,
            'close');

    },

    loadSharedElementData : function(uniqueNode, jsonCallback, discrete){
        var conn = new Connexion();
        if(discrete){
            conn.discrete = true;
        }
        conn.addParameter("get_action", "load_shared_element_data");
        conn.addParameter("file", uniqueNode.getPath());
        conn.addParameter("element_type", uniqueNode.isLeaf() ? "file" : "repository");
        conn.onComplete = function(transport){
            jsonCallback(transport.responseJSON);
        };
        conn.sendAsync();
    },

    loadInfoPanel : function(container, node){
        container.down('#ajxp_shared_info_panel table').update('<tr>\
            <td class="infoPanelLabel">'+MessageHash['share_center.55']+'</td>\
            <td class="infoPanelValue"><span class="icon-spinner"></span></td>\
            </tr>\
        ');
        ShareCenter.prototype.loadSharedElementData(node, function(jsonData){
            "use strict";
            if(node.isLeaf()){

                if(!jsonData) return ;
                var linksCount = jsonData.length;
                jsonData = jsonData[0];
                var directLink = "";
                if(!jsonData.has_password){
                    var shortlink = jsonData.publiclet_link + (jsonData.publiclet_link.indexOf('?') !== -1 ? '&' : '?') + 'dl=true';
                    directLink += '\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.60']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 45px;" readonly="true"><a href="'+ shortlink +'">'+ MessageHash[88] + ' ' +node.getLabel()+'</a></textarea></td>\
                    </tr>\
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
                            <tr>\
                                <td class="infoPanelLabel">'+MessageHash[messKey]+'</td>\
                                <td class="infoPanelValue"><textarea style="width:100%;height: 80px;" readonly="true">'+ tplString + '</textarea></td>\
                            </tr>\
                        ';
                    }
                }

                container.down('#ajxp_shared_info_panel table').update('\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.59']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 45px;" readonly="true">'+ jsonData.publiclet_link +'</textarea></td>\
                    </tr>'+directLink+'\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.51']+'</td>\
                        <td class="infoPanelValue">'+ jsonData.download_counter +' ' +  MessageHash['share_center.57'] + '</td>\
                    </tr>\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.52']+'</td>\
                        <td class="infoPanelValue">'+MessageHash['share_center.22']+' '+ (jsonData.download_limit?jsonData.download_limit:MessageHash['share_center.53'])
                                +', '+MessageHash['share_center.11']+':'+ (jsonData.expiration_time?jsonData.expiration_time:MessageHash['share_center.53'])
                                +', '+MessageHash['share_center.12']+':'+ (jsonData.has_password?MessageHash['share_center.13']:MessageHash['share_center.14']) +'</td>\
                    </tr>\
                ');

                if(linksCount > 1){
                    container.down('#ajxp_shared_info_panel table').insert({bottom:'<tr>\
                        <td class="infoPanelLabel" colspan="2" style="text-align: center;font-style: italic;">'+MessageHash['share_center.'+(linksCount>2?'104':'105')].replace('%s', linksCount-1)+'</td>\
                    </tr>'});
                }

            }else{
                var entries = [];
                $A(jsonData.entries).each(function(entry){
                    entries.push(entry.LABEL + ' ('+ entry.RIGHT +')');
                });
                var linkString = '';
                if(jsonData.minisite){
                    linkString = '\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.62']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 40px;" readonly="true">'+ jsonData.minisite.public_link +'</textarea></td>\
                    </tr>\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.61']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 80px;" id="embed_code" readonly="true"></textarea></td>\
                    </tr>\
                    ';
                }
                container.down('#ajxp_shared_info_panel table').update(linkString + '\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.35']+'</td>\
                        <td class="infoPanelValue">'+ jsonData.label +'</td>\
                    </tr>\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.54']+'</td>\
                        <td class="infoPanelValue">'+ entries.join(', ') +'</td>\
                    </tr>\
                ');
                if(jsonData.minisite){
                    container.down("#embed_code").setValue("<iframe height='500' width='600' style='border:1px solid black;' src='"+jsonData.minisite.public_link+"'></iframe>");
                }
            }
            container.select("textarea").each(function(t){
                t.observe("focus", function(e){ ajaxplorer.disableShortcuts();});
                t.observe("blur", function(e){ ajaxplorer.enableShortcuts();});
                t.observe("click", function(event){event.target.select();});
            });
            container.up("div[ajxpClass]").ajxpPaneObject.resize();
        }, true);
    },

    performUnshareAction : function(elementId, removeRow){
        var conn = new Connexion();
        if(elementId){
            conn.addParameter("element_id", elementId);
        }
        conn.addParameter("get_action", "unshare");
        conn.addParameter("file", this.currentNode.getPath());
        conn.addParameter("element_type", this.currentNode.isLeaf()?"file":"repository");
        conn.onComplete = function(){
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

    updateDialogButtons : function(dialogButtonsOrRow, shareType, jsonData){

        // WATCH BUTTON
        if(ajaxplorer.hasPluginOfType("meta", "watch")){
            var st = (shareType == "folder" ? MessageHash["share_center.38"] : MessageHash["share_center.39"]);
            if(!dialogButtonsOrRow.down('#watch_folder')) {
                dialogButtonsOrRow.down('.SF_horizontal_actions').insert({top:"<span class='icon-eye-close simple_tooltip_observer' id='watch_folder_eye' data-tooltipTitle='"+MessageHash["share_center."+(shareType=='folder'?'83b':'83')]+"'> "+MessageHash["share_center.82"]+"<input type='checkbox' id='watch_folder' style='display:none;'></span>"});
            }
            var folderEye = dialogButtonsOrRow.down('#watch_folder_eye');
            var folderCheck = dialogButtonsOrRow.down('#watch_folder');

            folderCheck.checked = ((jsonData && jsonData['element_watch']) || this._currentFolderWatchValue);
            folderEye
                .removeClassName('icon-eye-close')
                .removeClassName('icon-eye-open')
                .addClassName('icon-eye-'+ ( folderCheck.checked ? 'open' : 'close'))
                .update( ' ' + MessageHash['share_center.' + (folderCheck.checked ? '81' : '82')] )
            ;
            if(folderEye){
                folderEye.observe('click', function(){
                    folderCheck.checked = !folderCheck.checked;
                    folderEye
                        .removeClassName('icon-eye-close')
                        .removeClassName('icon-eye-open')
                        .addClassName('icon-eye-'+ ( folderCheck.checked ? 'open' : 'close'))
                        .update( ' ' + MessageHash['share_center.' + (folderCheck.checked ? '81' : '82')] )
                    ;
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
                        }
                        conn.sendAsync();
                    }
                }.bind(this));
            }
        }

        // MAILER BUTTON
        var forceOldSchool = ajaxplorer.getPluginConfigs("ajxp_plugin[@id='action.share']").get("EMAIL_INVITE_EXTERNAL");
        var mailerButton;
        if(!dialogButtonsOrRow.down('#mailer_button')){
            dialogButtonsOrRow.down('.SF_horizontal_actions').insert({bottom:"<span class='icon-envelope simple_tooltip_observer' id='mailer_button' data-tooltipTitle='"+MessageHash['share_center.'+(shareType=='file'?'80':'80b')]+"'> "+MessageHash['share_center.79']+"</span>"});
        }
        mailerButton = dialogButtonsOrRow.down('#mailer_button');
        mailerButton.writeAttribute("data-tooltipTitle", MessageHash["share_center.41"]);
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
                    s = MessageHash["share_center.43"];
                    if(s) s = s.replace("%s", ajaxplorer.appTitle);
                    link = this._currentRepositoryLink;
                    message = s + "\n\n " + "<a href='" + this._currentRepositoryLink+"'>" + MessageHash["share_center.46"].replace("%s1", this._currentRepositoryLabel).replace("%s2", ajaxplorer.appTitle) + "</a>";
                }
                if(dialogButtonsOrRow.down('.share_qrcode') && dialogButtonsOrRow.down('.share_qrcode').visible() && dialogButtonsOrRow.down('.share_qrcode > img')){
                    message += "\n\n "+ MessageHash["share_center.108"] + "\n\n" + dialogButtonsOrRow.down('.share_qrcode').innerHTML;
                }
                var mailer = new AjxpMailer();
                var usersList = null;
                if(shareType == 'folder' && oForm) {
                    usersList = oForm.down(".editable_users_list");
                }
                modal.showSimpleModal(
                    dialogButtonsOrRow.up(".dialogContent"),
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
        if (this.createQRCode && qrcodediv) {

            if(!dialogButtonsOrRow.down('#qrcode_button')){
                dialogButtonsOrRow.down('.SF_horizontal_actions').insert({bottom:"<span class='icon-qrcode simple_tooltip_observer' id='qrcode_button' data-tooltipTitle='"+MessageHash['share_center.94']+"'> "+MessageHash['share_center.95']+"</span>"});
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

        // STOP SHARING BUTTON
        if(shareType == 'folder'){
            var stopButton = new Element('span', {
                className:'icon-remove simple_tooltip_observer SF_horizontal_action_destructive',
                'data-tooltipTitle':MessageHash['share_center.97']
            }).update(' '+MessageHash['share_center.96']);
            dialogButtonsOrRow.down('.SF_horizontal_actions').insert(stopButton);
            stopButton.observe("click", this.performUnshareAction.bind(this));
        }

    },

    checkPositiveNumber : function(str){
        var n = ~~Number(str);
        return String(n) === str && n >= 0;
    }

});
