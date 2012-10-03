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

    performShareAction : function(){
        var userSelection = ajaxplorer.getUserSelection();
        if(userSelection.hasDir() && !userSelection.hasMime($A(['ajxp_browsable_archive']))){
            this.shareRepository(userSelection);
        }else{
            this.shareFile(userSelection);
        }
    },

    shareRepository : function(userSelection){

        var loadFunc = function(oForm){

            var nodeMeta = userSelection.getUniqueNode().getMetadata();
            if(nodeMeta.get("ajxp_shared")){
                // Reorganize
                var repoFieldset = oForm.down('div#target_repository');
            }

            var ppass = new Protopass($('shared_pass'), {
                barContainer : $('pass_strength_container'),
                barPosition:'bottom'
            });
            var mailerDetected = ajaxplorer.hasPluginOfType("mailer");
            var updateUserEntryAfterCreate = function(li, assignedRights){
                if(assignedRights == undefined) assignedRights = "r";
                var id = Math.random();
                li.insert({top:'<div class="user_entry_rights">' +
                    '<span class="cbContainer"><input type="checkbox" id="r'+id+'" name="r" '+(assignedRights.startsWith("r")?"checked":"") +'></span>' +
                    //'<label for="r'+id+'">'+MessageHash[361]+'</label>' +
                    '<span class="cbContainer"><input id="w'+id+'" type="checkbox" name="w"  '+(assignedRights.endsWith("w")?"checked":"") +'></span>' +
                    //'<label for="w'+id+'">'+MessageHash[362]+'</label>' +
                    '<span class="cbContainer"><input id="n'+id+'" type="checkbox" name="n"></span>' +
                    //'<label for="n'+id+'">Watch</label>' +
                    '</div>'
                });
            };
            oForm.down('#repo_label').setValue(getBaseName(userSelection.getUniqueNode().getPath()));
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
                        }
                    }
                );
            }
            this._currentRepositoryId = null;
            if(nodeMeta.get("ajxp_shared")){
                oForm.down('div#share_unshare').show();
                oForm.down('div[id="unshare_button"]').observe("click", this.performUnshareAction.bind(this));
                oForm.down('#complete_indicator').show();
                this.loadSharedElementData(userSelection.getUniqueNode(), function(json){
                    oForm.down('input#repo_label').value = json['label'];
                    this._currentRepositoryId = json['repositoryId'];
                    oForm.down('#complete_indicator').hide();
                    $A(json['entries']).each(function(u){
                        var newItem =  $('share_folder_form').autocompleter.createUserEntry(u.TYPE=="group", u.TYPE =="tmp_user", u.ID, u.LABEL);
                        updateUserEntryAfterCreate(newItem, (u.RIGHT?u.RIGHT:""));
                        newItem.appendToList($('shared_users_summary'));
                    });
                }.bind(this));
            }else{
                $('shared_user').observeOnce("focus", function(){
                    $('share_folder_form').autocompleter.activate();
                });
            }
            this.updateDialogButtons($("share_folder_form").next("div.dialogButtons"), "folder");
        }.bind(this);
        var closeFunc = function (oForm){
            if(Prototype.Browser.IE){
                $(document.body).down("#shared_users_autocomplete_choices").remove();
                if($(document.body).down("#shared_users_autocomplete_choices_iefix")){
                    $(document.body).down("#shared_users_autocomplete_choices_iefix").remove();
                }
                $('create_shared_user').select('div.dialogButtons>input').invoke("removeClassName", "dialogButtons");
            }
        }
        var submitFunc = function(oForm){
            if(!oForm.down('input[name="repo_label"]').value){
                alert(MessageHash[349]);
                return false;
            }
            var userSelection = ajaxplorer.getUserSelection();
            var publicUrl = ajxpServerAccessPath+'&get_action=share&sub_action=delegate_repo';
            publicUrl = userSelection.updateFormOrUrl(null, publicUrl);
            var conn = new Connexion(publicUrl);
            conn.setMethod("POST");
            var params = modal.getForm().serialize(true);
            conn.setParameters(params);
            if(this._currentRepositoryId){
                conn.addParameter("repository_id", this._currentRepositoryId);
            }
            var index = 0;
            $("shared_users_summary").select("div.user_entry").each(function(entry){
                conn.addParameter("user_"+index, entry.getAttribute("data-entry_id"));
                conn.addParameter("right_read_"+index, entry.down('input[name="r"]').checked ? "true":"false");
                conn.addParameter("right_write_"+index, entry.down('input[name="w"]').checked ? "true":"false");
                if(entry.NEW_USER_PASSWORD){
                    conn.addParameter("user_pass_"+index, entry.NEW_USER_PASSWORD);
                }
                conn.addParameter("entry_type_"+index, entry.hasClassName("group_entry")?"group":"user");
                index++;
            });
            conn.onComplete = function(transport){
                var response = parseInt(transport.responseText);
                if(response == 200){
                    if(this._currentRepositoryId){
                        ajaxplorer.displayMessage('SUCCESS', MessageHash['share_center.19']);
                    }else{
                        ajaxplorer.displayMessage('SUCCESS', MessageHash['share_center.18']);
                    }
                    ajaxplorer.fireContextRefresh();
                    hideLightBox(true);
                }else{
                    var messages = {100:349, 101:352, 102:350, 103:351};
                    ajaxplorer.displayMessage('ERROR', MessageHash[messages[response]]);
                }
            }.bind(this);
            conn.sendAsync();
            closeFunc(oForm);
            return false;
        }.bind(this);
        if(window.ajxpBootstrap.parameters.get("usersEditable") == false){
            ajaxplorer.displayMessage('ERROR', MessageHash[394]);
        }else{
            modal.showDialogForm('Get', 'share_folder_form', loadFunc, submitFunc, closeFunc);
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
                    minchar : 0
                });
                var nodeMeta = userSelection.getUniqueNode().getMetadata();
                if(nodeMeta.get("ajxp_shared")){
                    oForm.down('div#share_unshare').show();
                    //oForm.down('div#share_optional_fields').hide();
                    oForm.down('div#share_generate').hide();
                    oForm.down('div#share_result').show();
                    //oForm.down('div#share_result legend').update(MessageHash[296]);
                    oForm.down('div#generate_indicator').show();
                    this.loadSharedElementData(userSelection.getUniqueNode(), function(json){
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

                    }.bind(this));
                    oForm.down('div[id="unshare_button"]').observe("click", this.performUnshareAction.bind(this));
                    this.updateDialogButtons(oForm.down("div.dialogButtons"), "file");
                }else{
                    var button = $(oForm).down('div#generate_publiclet');
                    button.observe("click", this.generatePublicLinkCallback.bind(this));
                }
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

    loadSharedElementData : function(uniqueNode, jsonCallback){
        var conn = new Connexion();
        conn.addParameter("get_action", "load_shared_element_data");
        conn.addParameter("file", uniqueNode.getPath());
        conn.addParameter("element_type", uniqueNode.isLeaf() ? "file" : "repository");
        conn.onComplete = function(transport){
            jsonCallback(transport.responseJSON);
        };
        conn.sendAsync();
    },

    performUnshareAction : function(){
        var userSelection = ajaxplorer.getUserSelection();
        modal.getForm().down("img#stop_sharing_indicator").src=window.ajxpResourcesFolder+"/images/autocompleter-loader.gif";
        var conn = new Connexion();
        conn.addParameter("get_action", "unshare");
        conn.addParameter("file", userSelection.getUniqueNode().getPath());
        conn.addParameter("element_type", userSelection.getUniqueNode().isLeaf()?"file":"repository");
        conn.onComplete = function(){
            var oForm = modal.getForm();
            if(oForm.down('div#generate_publiclet')){
                oForm.down('div#generate_publiclet').stopObserving("click");
            }
            oForm.down('div#unshare_button').stopObserving("click");
            hideLightBox(true);
            ajaxplorer.fireContextRefresh();
        };
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
            if(cont){
                cont.setValue(transport.responseText);
                cont.select();
            }
            var email = oForm.down('a[id="email"]');
            if (email){
                email.setAttribute('href', 'mailto:unknown@unknown.com?Subject=UPLOAD&Body='+transport.responseText);
            }
            new Effect.Fade(oForm.down('div[id="share_generate"]'), {
                duration:0.5,
                afterFinish : function(){
                    modal.refreshDialogAppearance();
                    new Effect.Appear(oForm.down('div[id="share_result"]'), {
                        duration:0.5,
                        afterFinish : function(){
                            cont.select();
                            oThis.updateDialogButtons(oForm.down("div.dialogButtons"), "file");
                            modal.refreshDialogAppearance();
                            modal.setCloseAction(function(){
                                ajaxplorer.fireContextRefresh();
                            });
                        }
                    });
                }
            });
        };
        conn.sendSync();
    },

    updateDialogButtons : function(dialogButtons, shareType){
        if(ajaxplorer.hasPluginOfType("mailer")){
            var oForm = dialogButtons.parentNode;
            dialogButtons.insert("<div class='dialogButtonsCheckbox'><input type='checkbox' id='watch_folder'><label for='watch_folder'>Watch this "+(shareType=="folder"?"folder":"file activity")+"</label></div>");
            dialogButtons.insert({top:'<input type="image" name="mail" src="plugins/gui.ajax/res/themes/umbra/images/actions/22/mail_generic.png" height="22" width="22" title="Notify by email..." class="dialogButton dialogFocus">'});
            dialogButtons.down('input[name="mail"]').observe("click", function(event){
                Event.stop(event);
                if(shareType == "file"){
                    var message = "AjaXplorer user is sharing a link with you! \n\n " + oForm.down('[id="share_container"]').getValue();
                }else{
                    var message = "AjaXplorer user is sharing a folder with you! \n\n " + this._currentRepositoryId;
                }
                var mailer = new AjxpMailer();
                var usersList = null;
                if(shareType) usersList = oForm.down(".editable_users_list");
                modal.showSimpleModal(dialogButtons.up(".dialogContent"), mailer.buildMailPane("AjaXplorer Share", message, usersList), function(){
                    return true;
                },function(){
                    return true;
                });
            }.bind(this));
        }
    }

});
