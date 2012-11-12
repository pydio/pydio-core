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
                var repoFieldset = oForm.down('fieldset#target_repository');
                repoFieldset.down('div.dialogLegend').remove();
                repoFieldset.insert(oForm.down('fieldset#target_user div#textarea_sf_element'));
                repoFieldset.insert(oForm.down('fieldset#target_user div#create_shared_user'));
                repoFieldset.insert(oForm.down('fieldset#target_user div#create_shared_user_anchor_div'));
                oForm.down('fieldset#target_user').remove();
            }

            new Protopass($('shared_pass'), {
                barContainer : $('pass_strength_container'),
                barPosition:'bottom'
            });
            oForm.down('#repo_label').setValue(getBaseName(userSelection.getUniqueNode().getPath()));
            if(!$('share_folder_form').autocompleter){
                $('share_folder_form').autocompleter = new Ajax.Autocompleter(
                    "shared_user",
                    "shared_users_autocomplete_choices",
                    ajxpServerAccessPath + "&get_action=share&sub_action=list_shared_users",
                    {
                        minChars:ajaxplorer.getPluginConfigs("ajxp_plugin[@name='share']").get("SHARED_USERS_LIST_MINIMUM"),
                        paramName:'value',
                        tokens:[',', '\n'],
                        frequency:0.1,
                        indicator:oForm.down('#complete_indicator')
                    }
                );
                if(Prototype.Browser.IE){
                    $(document.body).insert($("shared_users_autocomplete_choices"));
                }
                if(!nodeMeta.get("ajxp_shared")){
                    $('shared_user').observeOnce("focus", function(){
                        $('share_folder_form').autocompleter.activate();
                    });
                }
                $('create_shared_user_anchor').observeOnce("click", function(){
                    var pref = ajaxplorer.getPluginConfigs("ajxp_plugin[@name='share']").get("SHARED_USERS_TMP_PREFIX");
                    if(pref){
                        $("new_shared_user").setValue(pref);
                    }
                    $('create_shared_user').appear();
                    $('create_shared_user_anchor').up('div.SF_element').fade();
                });
            }
            this._currentRepositoryId = null;
            if(nodeMeta.get("ajxp_shared")){
                oForm.down('fieldset#share_unshare').show();
                oForm.down('div[id="unshare_button"]').observe("click", this.performUnshareAction.bind(this));
                oForm.down('#complete_indicator').show();
                this.loadSharedElementData(userSelection.getUniqueNode(), function(json){
                    oForm.down('textarea#shared_user').value = json['users'].join("\n");
                    oForm.insert(new Element('input', {type:"hidden", name:"original_users", value:json['users'].join(',')}));
                    oForm.down('input#repo_label').value = json['label'];
                    oForm.down('select').setValue(json['rights']);
                    this._currentRepositoryId = json['repositoryId'];
                    oForm.down('#complete_indicator').hide();
                }.bind(this));
            }
        }.bind(this);
        var closeFunc = function (oForm){
            if(Prototype.Browser.IE){
                $(document.body).down("#shared_users_autocomplete_choices").remove();
                $(document.body).down("#shared_users_autocomplete_choices_iefix").remove();
            }
        }
        var submitFunc = function(oForm){
            if($('new_shared_user').value){
                if( !$('shared_pass').value || $('shared_pass').value.length < ajxpBootstrap.parameters.get('password_min_length')){
                    alert(MessageHash[378]);
                    return false;
                }
            }
            if(!oForm.down('input[name="repo_label"]').value){
                alert(MessageHash[349]);
                return false;
            }
            var userSelection = ajaxplorer.getUserSelection();
            var publicUrl = ajxpServerAccessPath+'&get_action=share&sub_action=delegate_repo';
            publicUrl = userSelection.updateFormOrUrl(null,publicUrl);
            var conn = new Connexion(publicUrl);
            conn.setParameters(modal.getForm().serialize(true));
            if(this._currentRepositoryId){
                conn.addParameter("repository_id", this._currentRepositoryId);
            }
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
