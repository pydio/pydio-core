/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
Class.create("ShareCenter", {

    currentNode : null,
    shareFolderMode : "workspace",

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
                    conn.addParameter("user_pass_"+index, entry.NEW_USER_PASSWORD);
                }
                conn.addParameter("entry_type_"+index, entry.hasClassName("group_entry")?"group":"user");
                index++;
            });
            if(oForm.down("#watch_folder")){
                conn.addParameter("self_watch_folder",oForm.down("#watch_folder").checked?"true":"false");
            }
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
                        oForm.down("#share_unshare").show();
                        oForm.down("#share_generate").hide();
                        oForm.down('#unshare_button').observe("click", this.performUnshareAction.bind(this));
                        this.updateDialogButtons(oForm.next("div.dialogButtons"), "folder");
                    }

                }.bind(this);
            }
            //closeFunc(oForm);
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
                oForm.down(".editable_users_list").setStyle({height: '80px'});
                oForm.down("#share_generate").insert({before:oForm.down("#target_user")});

            }else{
                oForm.select(".mode-minipriv").invoke('hide');
                oForm.select(".mode-minipub").invoke('hide');
                oForm.select(".mode-ws").invoke('show');
                oForm.down(".editable_users_list").setStyle({height: '160px'});
            }



            var nodeMeta = this.currentNode.getMetadata();
            if(nodeMeta.get("ajxp_shared")){
                // Reorganize
                var repoFieldset = oForm.down('div#target_repository');
            }

            var ppass = new Protopass($('shared_pass'), {
                barContainer : $('pass_strength_container'),
                barPosition:'bottom'
            });
            var mailerDetected = ajaxplorer.hasPluginOfType("mailer");
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
            };
            oForm.down('#repo_label').setValue(getBaseName(this.currentNode.getPath()));
            if(!$('share_folder_form').autocompleter){
                var pref = ajaxplorer.getPluginConfigs("ajxp_plugin[@name='share']").get("SHARED_USERS_TMP_PREFIX");
                $('share_folder_form').autocompleter = new AjxpUsersCompleter(
                    $("shared_user"),
                    $("shared_users_summary"),
                    $("shared_users_autocomplete_choices"),
                    {
                        tmpUsersPrefix:pref,
                        updateUserEntryAfterCreate:updateUserEntryAfterCreate,
                        createUserPanel:{
                            panel : $("create_shared_user"),
                            pass  : $("shared_pass"),
                            confirmPass: $("shared_pass_confirm")
                        },
                        indicator: $("complete_indicator"),
                        minChars:parseInt(ajaxplorer.getPluginConfigs("ajxp_plugin[@name='share']").get("SHARED_USERS_LIST_MINIMUM"))
                    }
                );
            }
            this._currentRepositoryId = null;
            this._currentRepositoryLink = null;
            this._currentRepositoryLabel = null;
            if(nodeMeta.get("ajxp_shared")){
                oForm.down('div#share_generate').hide();
                oForm.down('div#share_unshare').show();
                oForm.down('#unshare_button').observe("click", this.performUnshareAction.bind(this));
                oForm.down('#complete_indicator').show();
                this.loadSharedElementData(this.currentNode, function(json){
                    this._currentRepositoryId = json['repositoryId'];
                    this._currentRepositoryLabel = json['label'];
                    this._currentRepositoryLink = json['repository_url'];
                    oForm.down('input#repo_label').value = json['label'];
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
                    if(json["element_watch"]){
                        oForm.down("#watch_folder").checked = true;
                    }
                    if(reload){
                        removeLightboxFromElement(oForm.up(".dialogContent"));
                        ajaxplorer.fireNodeRefresh(this.currentNode);
                    }
                }.bind(this));
            }else{
                $('shared_user').observeOnce("focus", function(){
                    $('share_folder_form').autocompleter.activate();
                });
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
            this.updateDialogButtons($("share_folder_form").next("div.dialogButtons"), "folder");
            if(this.shareFolderMode != "workspace"){
                oForm.down("#generate_publiclet").observe("click", function(){submitFunc(oForm);} );
            }

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

    shareFile : function(userSelection){

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
                    oForm.down('div#share_unshare').show();
                    //oForm.down('div#share_optional_fields').hide();
                    oForm.down('div#share_generate').hide();
                    oForm.down('div#share_result').show();
                    //oForm.down('div#share_result legend').update(MessageHash[296]);
                    oForm.down('div#generate_indicator').show();
                    this.loadSharedElementData(this.currentNode, function(json){
                        oForm.down('[id="share_container"]').value = json['publiclet_link'];
                        oForm.down('div#generate_indicator').hide();
                        var optionsPane = oForm.down('div#share_optional_fields');
                        if(json['expire_time']){
                            optionsPane.down("[name='expiration']").setValue(json['expire_time']);
                            optionsPane.down("[name='expiration']").removeClassName("SF_number");
                        }else{
                            optionsPane.down("[name='expiration']").up().remove();
                        }
                        optionsPane.down("[name='password']").setAttribute("type", "text");
                        optionsPane.down("[name='password']").setValue(json['has_password'] ? "Password Set": "No Password");
                        var dlString = json['download_counter'];
                        if(json["download_limit"]) dlString += "/" + json["download_limit"];
                        optionsPane.down("[name='downloadlimit']").setAttribute("id","currentDownloadLimitField");
                        optionsPane.down("[name='downloadlimit']").setValue(dlString);
                        var resetLink = new Element('a', {style:'text-decoration:underline;cursor:pointer;display:inline-block;padding:5px;', title:MessageHash['share_center.17']}).update(MessageHash['share_center.16']).observe('click', this.resetDownloadCounterCallback.bind(this));
                        optionsPane.down("[name='downloadlimit']").insert({after:resetLink});
                        optionsPane.select("input").each(function(el){el.disabled = true;});
                        oForm.down('[id="share_container"]').select();
                        if(json["element_watch"]){
                            oForm.down("#watch_folder").checked = true;
                        }

                    }.bind(this));
                    this.updateDialogButtons(oForm.down("div.dialogButtons"), "file");
                }else{
                    var button = $(oForm).down('div#generate_publiclet');
                    button.observe("click", this.generatePublicLinkCallback.bind(this));
                }
                oForm.down('#unshare_button').observe("click", this.performUnshareAction.bind(this));
            }.bind(this),
            function(oForm){
                oForm.down('div#generate_publiclet').stopObserving("click");
                oForm.down('div#unshare_button').stopObserving("click");
                hideLightBox(true);
                return false;
            },
            null,
            true);

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

                var directLink = "";
                if(!jsonData.hasPassword){
                    directLink = '\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.60']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 45px;"><a href="'+ jsonData.publiclet_link +'?dl=true">Download '+node.getLabel()+'</a></textarea></td>\
                    </tr>\
                    ';
                    var editors = ajaxplorer.findEditorsForMime(node.getAjxpMime(), true);
                    if(editors.length){
                        var tplString ;
                        var messKey = "share_center.61";
                        if(Class.getByName(editors[0].editorClass).prototype.getSharedPreviewTemplate){
                            var template = Class.getByName(editors[0].editorClass).prototype.getSharedPreviewTemplate(node);
                            tplString = template.evaluate({WIDTH:480, HEIGHT:260, DL_CT_LINK:jsonData.publiclet_link +'?dl&true&ct=true'});
                        }else{
                            tplString = jsonData.publiclet_link +'?dl&true&ct=true';
                            messKey = "share_center.60";
                        }
                        directLink += '\
                            <tr>\
                                <td class="infoPanelLabel">'+MessageHash[messKey]+'</td>\
                                <td class="infoPanelValue"><textarea style="width:100%;height: 80px;">'+ tplString + '</textarea></td>\
                            </tr>\
                        ';
                    }
                }

                container.down('#ajxp_shared_info_panel table').update('\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.59']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 45px;">'+ jsonData.publiclet_link +'</textarea></td>\
                    </tr>'+directLink+'\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.51']+'</td>\
                        <td class="infoPanelValue">'+ jsonData.download_counter +' ' +  MessageHash['share_center.57'] + '</td>\
                    </tr>\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.52']+'</td>\
                        <td class="infoPanelValue">'+MessageHash['share_center.22']+' : '+ (jsonData.download_limit?jsonData.download_limit:MessageHash['share_center.53'])
                                +', '+MessageHash['share_center.11']+':'+ (jsonData.expiration_time?jsonData.expiration_time:MessageHash['share_center.53'])
                                +', '+MessageHash['share_center.12']+':'+ (jsonData.hasPassword?MessageHash['share_center.13']:MessageHash['share_center.14']) +'</td>\
                    </tr>\
                ');


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
                        <td class="infoPanelValue"><textarea style="width:100%;height: 40px;">'+ jsonData.minisite.public_link +'</textarea></td>\
                    </tr>\
                    <tr>\
                        <td class="infoPanelLabel">'+MessageHash['share_center.61']+'</td>\
                        <td class="infoPanelValue"><textarea style="width:100%;height: 80px;" id="embed_code"></textarea></td>\
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

    performUnshareAction : function(){
        modal.getForm().down("img#stop_sharing_indicator").src=window.ajxpResourcesFolder+"/images/autocompleter-loader.gif";
        var conn = new Connexion();
        conn.addParameter("get_action", "unshare");
        conn.addParameter("file", this.currentNode.getPath());
        conn.addParameter("element_type", this.currentNode.isLeaf()?"file":"repository");
        conn.onComplete = function(){
            var oForm = modal.getForm();
            if(oForm.down('div#generate_publiclet')){
                oForm.down('div#generate_publiclet').stopObserving("click");
            }
            oForm.down('div#unshare_button').stopObserving("click");
            hideLightBox(true);
            ajaxplorer.fireNodeRefresh(this.currentNode);
        }.bind(this);
        conn.sendAsync();
    },

    resetDownloadCounterCallback : function(){
        var conn = new Connexion();
        conn.addParameter("get_action", "reset_counter");
        conn.addParameter("file", ajaxplorer.getUserSelection().getUniqueNode().getPath());
        conn.onComplete = function(){
            var input = modal.getForm().down('input#currentDownloadLimitField');
            if(input.getValue().indexOf("/") > 0){
                var parts = input.getValue().split("/");
                input.setValue("0/" + parts[1]);
            }else{
                input.setValue("0");
            }
        };
        conn.sendAsync();
    },

    generatePublicLinkCallback : function(){
        var userSelection = ajaxplorer.getUserSelection();
        if(!userSelection.isUnique() || (userSelection.hasDir() && !userSelection.hasMime($A(['ajxp_browsable_archive'])))) return;
        var oForm = $(modal.getForm());
        oForm.down('img#generate_image').src = window.ajxpResourcesFolder+"/images/autocompleter-loader.gif";
        var publicUrl = window.ajxpServerAccessPath+'&get_action=share';
        publicUrl = userSelection.updateFormOrUrl(null,publicUrl);
        var conn = new Connexion(publicUrl);
        conn.setParameters(oForm.serialize(true));
        conn.addParameter('get_action','share');
        var oThis = this;
        conn.onComplete = function(transport){
            var cont = oForm.down('[id="share_container"]');
            cont.setValue(transport.responseText);
            var email = oForm.down('a[id="email"]');
            if (email){
                email.setAttribute('href', 'mailto:unknown@unknown.com?Subject=UPLOAD&Body='+transport.responseText);
            }
            new Effect.Fade(oForm.down('div[id="share_generate"]'), {
                duration:0.5,
                afterFinish : function(){
                    oThis.updateDialogButtons(oForm.down("div.dialogButtons"), "file");
                    oForm.down('#share_unshare').show();
                    oForm.down('#share_optional_fields').select("input").each(function(el){el.disabled = true;});
                    modal.refreshDialogAppearance();
                    new Effect.Appear(oForm.down('div[id="share_result"]'), {
                        duration:0.5,
                        afterFinish : function(){
                            cont.select();
                            modal.refreshDialogAppearance();
                            modal.setCloseAction(function(){
                                ajaxplorer.fireNodeRefresh(oThis.currentNode);
                            });
                        }
                    });
                }
            });
        };
        conn.sendSync();
    },

    updateDialogButtons : function(dialogButtons, shareType){
        if(ajaxplorer.hasPluginOfType("meta", "watch")){
            var st = (shareType == "folder" ? MessageHash["share_center.38"] : MessageHash["share_center.39"]);
            dialogButtons.insert("<div class='dialogButtonsCheckbox'><input type='checkbox' id='watch_folder'><label for='watch_folder'>"+st+"</label></div>");
            if(shareType == "file"){
                dialogButtons.down("#watch_folder").observe("change", function(event){
                    var conn = new Connexion();
                    conn.setParameters({
                        get_action: "toggle_link_watch",
                        set_watch : event.target.checked ?  "true" : "false",
                        file : this.currentNode.getPath()
                    });
                    conn.onComplete = function(transport){
                        ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                    }
                    conn.sendAsync();
                }.bind(this));
            }
        }
        if(ajaxplorer.hasPluginOfType("mailer")){
            var oForm = dialogButtons.parentNode;
            var unShare = oForm.down("#unshare_button");
            var mailerButton = unShare.cloneNode(true);
            mailerButton.writeAttribute("title", MessageHash["share_center.41"]);
            mailerButton.down("span").update(MessageHash["share_center.40"]);
            mailerButton.down("img").writeAttribute("src","plugins/gui.ajax/res/themes/umbra/images/actions/22/mail_generic.png");
            unShare.insert({after:mailerButton});
            //dialogButtons.insert({top:'<input type="image" name="mail" src="plugins/gui.ajax/res/themes/umbra/images/actions/22/mail_generic.png" height="22" width="22" title="Notify by email..." class="dialogButton dialogFocus">'});
            mailerButton.observe("click", function(event){
                Event.stop(event);
                if(shareType == "file"){
                    var s = MessageHash["share_center.42"];
                    if(s) s = s.replace("%s", ajaxplorer.appTitle);
                    var message = s + "\n\n " + oForm.down('[id="share_container"]').getValue();
                }else{
                    var s = MessageHash["share_center.43"];
                    if(s) s = s.replace("%s", ajaxplorer.appTitle);
                    var message = s + "\n\n " + "<a href='" + this._currentRepositoryLink+"'>" + MessageHash["share_center.46"].replace("%s1", this._currentRepositoryLabel).replace("%s2", ajaxplorer.appTitle) + "</a>";
                }
                var mailer = new AjxpMailer();
                var usersList = null;
                if(shareType) usersList = oForm.down(".editable_users_list");
                modal.showSimpleModal(oForm.up(".dialogContent"), mailer.buildMailPane(MessageHash["share_center.44"].replace("%s", ajaxplorer.appTitle), message, usersList, MessageHash["share_center.45"]), function(){
                    mailer.postEmail();
                    return true;
                },function(){
                    return true;
                });
            }.bind(this));
        }
    }

});
