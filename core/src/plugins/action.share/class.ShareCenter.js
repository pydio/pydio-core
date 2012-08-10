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

    createUserEntry : function(isGroup, isTemporary, entryId, entryLabel, assignedRights, skipObservers){
        var li = new Element("div", {className:"user_entry"}).update(entryLabel);
        if(isGroup){
            li.addClassName("group_entry");
        }else if(isTemporary){
            li.addClassName("user_entry_temp");
        }
        li.writeAttribute("data-entry_id", entryId);
        var id = Math.random();
        li.insert({top:'<div style="float: right;"><input type="checkbox" id="r'+id+'" name="r" '+(assignedRights.startsWith("r")?"checked":"") +'><label for="r'+id+'">'+MessageHash[361]+'</label><input id="w'+id+'" type="checkbox" name="w"  '+(assignedRights.endsWith("w")?"checked":"") +'><label for="w'+id+'">'+MessageHash[362]+'</label></div>'});
        li.insert({bottom:'<span style="display: none;" class="delete_user_entry">&nbsp;</span>'});

        if(!skipObservers){
            li.setStyle({opacity:0});
            li.observe("mouseover", function(event){li.down('span').show();});
            li.observe("mouseout", function(event){li.down('span').hide();});
            li.down("span").observe("click", function(){
                Effect.Fade(li, {duration:0.3, afterFinish:li.remove.bind(li)});
            });
            li.appendToList = function(htmlObject){
                htmlObject.insert({bottom:li});
                Effect.Appear(li, {duration:0.3});
            };
        }

        return li;

    },

    shareRepository : function(userSelection){

        var entryTplGenerator = this.createUserEntry.bind(this);

        var loadFunc = function(oForm){

            var nodeMeta = userSelection.getUniqueNode().getMetadata();
            if(nodeMeta.get("ajxp_shared")){
                // Reorganize
                var repoFieldset = oForm.down('fieldset#target_repository');
                //repoFieldset.down('div.dialogLegend').remove();
                //repoFieldset.insert(oForm.down('fieldset#target_user div#textarea_sf_element'));
                //repoFieldset.insert(oForm.down('fieldset#target_user div#create_shared_user'));
                //repoFieldset.insert(oForm.down('fieldset#target_user div#create_shared_user_anchor_div'));
                //oForm.down('fieldset#target_user').remove();
            }

            var ppass = new Protopass($('shared_pass'), {
                barContainer : $('pass_strength_container'),
                barPosition:'bottom'
            });
            oForm.down('#repo_label').setValue(getBaseName(userSelection.getUniqueNode().getPath()));
            if(!$('share_folder_form').autocompleter){
                var pref = ajaxplorer.getPluginConfigs("ajxp_plugin[@name='share']").get("SHARED_USERS_TMP_PREFIX");
                $('share_folder_form').autocompleter = new Ajax.Autocompleter(
                    "shared_user",
                    "shared_users_autocomplete_choices",
                    ajxpServerAccessPath + "&get_action=share&sub_action=list_shared_users",
                    {
                        minChars:ajaxplorer.getPluginConfigs("ajxp_plugin[@name='share']").get("SHARED_USERS_LIST_MINIMUM"),
                        paramName:'value',
                        tokens:[',', '\n'],
                        frequency:0.1,
                        indicator:oForm.down('#complete_indicator'),
                        afterUpdateElement: function(element, selectedLi){
                            id = Math.random();
                            var label = selectedLi.getAttribute("data-label");
                            if(selectedLi.getAttribute("data-temporary") && pref && ! label.startsWith(pref)){
                                label = pref + label;
                            }
                            var li = entryTplGenerator(selectedLi.getAttribute("data-group")?true:false,
                                selectedLi.getAttribute("data-temporary")?true:false,
                                selectedLi.getAttribute("data-group")?selectedLi.getAttribute("data-group"):label,
                                label,
                                "r"
                            );

                            if(selectedLi.getAttribute("data-temporary")){
                                element.readOnly = true;
                                $("shared_pass").setValue(""); $("shared_pass_confirm").setValue("");
                                element.setValue("Creating "+ label + " : choose a password");
                                $('create_shared_user').select('div.dialogButtons>input').invoke("addClassName", "dialogButtons");
                                $('create_shared_user').select('div.dialogButtons>input').invoke("stopObserving", "click");
                                $('create_shared_user').select('div.dialogButtons>input').invoke("observe", "click", function(event){
                                    Event.stop(event);
                                    var close = false;
                                    if(event.target.name == "ok"){
                                        if( !$('shared_pass').value || $('shared_pass').value.length < ajxpBootstrap.parameters.get('password_min_length')){
                                            alert(MessageHash[378]);
                                        }else if($("shared_pass").getValue() == $("shared_pass_confirm").getValue()){
                                            li.NEW_USER_PASSWORD = $("shared_pass").getValue();
                                            li.appendToList($('shared_users_summary'));
                                            close = true;
                                        }
                                    }else if(event.target.name == "cancel"){
                                        close = true;
                                    }
                                    if(close) {
                                        element.setValue("");
                                        element.readOnly = false;
                                        Effect.BlindUp('create_shared_user', {duration:0.4});
                                        $('create_shared_user').select('div.dialogButtons>input').invoke("removeClassName", "dialogButtons");
                                    }
                                });
                                Effect.BlindDown('create_shared_user', {duration:0.6, transition:Effect.Transitions.spring, afterFinish:function(){$('shared_pass').focus();}});
                            }else{
                                element.setValue("");
                                li.appendToList($('shared_users_summary'));
                            }
                        }
                    }
                );
                $('share_folder_form').autocompleter.options.onComplete  = function(transport){
                    var tmpElement = new Element('div');
                    tmpElement.update(transport.responseText);
                    $("shared_users_summary").select("div.user_entry").each(function(li){
                        var found = tmpElement.down('[data-label="'+li.getAttribute("data-entry_id")+'"]');
                        if(found) {
                            found.remove();
                        }
                    });
                    this.updateChoices(tmpElement.innerHTML);
                }.bind($('share_folder_form').autocompleter);
                if(Prototype.Browser.IE){
                    $(document.body).insert($("shared_users_autocomplete_choices"));
                }
                $('shared_user').observe("click", function(){
                    $('share_folder_form').autocompleter.activate();
                });
            }
            this._currentRepositoryId = null;
            if(nodeMeta.get("ajxp_shared")){
                oForm.down('fieldset#share_unshare').show();
                oForm.down('div[id="unshare_button"]').observe("click", this.performUnshareAction.bind(this));
                oForm.down('#complete_indicator').show();
                this.loadSharedElementData(userSelection.getUniqueNode(), function(json){
                    oForm.down('input#repo_label').value = json['label'];
                    oForm.insert(new Element('input', {type:"hidden", name:"original_users", value:json['users'].join(',')}));
                    this._currentRepositoryId = json['repositoryId'];
                    oForm.down('#complete_indicator').hide();
                    $A(json['users']).each(function(u){
                        var newItem = entryTplGenerator(false, false, u, u, json['rights'][u]);
                        newItem.appendToList($('shared_users_summary'));
                    });
                }.bind(this));
            }else{
                $('shared_user').observeOnce("focus", function(){
                    $('share_folder_form').autocompleter.activate();
                });
            }
        }.bind(this);
        var closeFunc = function (oForm){
            if(Prototype.Browser.IE){
                $(document.body).down("#shared_users_autocomplete_choices").remove();
                $(document.body).down("#shared_users_autocomplete_choices_iefix").remove();
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
                    oForm.down('fieldset#share_unshare').show();
                    oForm.down('fieldset#share_optional_fields').hide();
                    oForm.down('fieldset#share_generate').hide();
                    oForm.down('fieldset#share_result').show();
                    oForm.down('fieldset#share_result legend').update(MessageHash[296]);
                    oForm.down('div#generate_indicator').show();
                    this.loadSharedElementData(userSelection.getUniqueNode(), function(json){
                        oForm.down('input[id="share_container"]').value = json['publiclet_link'];
                        oForm.down('div#generate_indicator').hide();
                        var linkDescription = '<tr><td class="infoPanelValue">' + MessageHash['share_center.11']+'</td><td class="infoPanelValue">'+ (json['expire_time'] == 0 ? MessageHash['share_center.14']:json['expire_time']) + '</td></tr>';
                        linkDescription += '<tr class="even"><td class="infoPanelValue">'  + MessageHash['share_center.12']+'</td><td class="infoPanelValue">' + (json['has_password']?MessageHash['share_center.13']:MessageHash['share_center.14']) + '</td></tr>';
                        linkDescription += '<tr class="infoPanelValue"><td class="infoPanelValue">'  + MessageHash['share_center.22']+'</td><td class="infoPanelValue">' + (json['download_limit'] == 0 ? MessageHash['share_center.25']:json['download_limit']) + '</td></tr>';
                        linkDescription += '<tr><td class="even">' + MessageHash['share_center.15'].replace('%s', '<span id="downloaded_times">'+json['download_counter']+'</span>')+'</td><td class="infoPanelValue" id="ip_reset_button"></td></tr>';
                        var descDiv = new Element('div', {style:"margin-top: 10px;"}).update('<table class="infoPanelTable" cellspacing="0" cellpadding="0" style="border-top:1px solid #eee;border-left:1px solid #eee;">'+linkDescription+'</table>');
                        var resetLink = new Element('a', {style:'text-decoration:underline;cursor:pointer;', title:MessageHash['share_center.17']}).update(MessageHash['share_center.16']).observe('click', this.resetDownloadCounterCallback.bind(this));
                        descDiv.down('#ip_reset_button').insert(resetLink);
                        oForm.down('fieldset#share_result').insert(descDiv);
                        oForm.down('input[id="share_container"]').select();
                    }.bind(this));
                    oForm.down('div[id="unshare_button"]').observe("click", this.performUnshareAction.bind(this));
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
            modal.getForm().down('span#downloaded_times').update('0');
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
        conn.onComplete = function(transport){
            var cont = oForm.down('input[id="share_container"]');
            if(cont){
                cont.value = transport.responseText;
                cont.select();
            }
            var email = oForm.down('a[id="email"]');
            if (email){
                email.setAttribute('href', 'mailto:unknown@unknown.com?Subject=UPLOAD&Body='+transport.responseText);
            }
            new Effect.Fade(oForm.down('fieldset[id="share_generate"]'), {
                duration:0.5,
                afterFinish : function(){
                    modal.refreshDialogAppearance();
                    new Effect.Appear(oForm.down('fieldset[id="share_result"]'), {
                        duration:0.5,
                        afterFinish : function(){
                            cont.select();
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
    }

});
