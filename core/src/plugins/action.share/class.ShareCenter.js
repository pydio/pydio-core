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
                    {minChars:0, paramName:'value'}
                );
                $('shared_user').observeOnce("focus", function(){
                    $('share_folder_form').autocompleter.activate();
                });
                $('shared_user').observe("blur", function(){
                    var existing = $('shared_users_autocomplete_choices').select('li').detect(function(li){
                        return (li.innerHTML == $('shared_user').getValue());
                    });
                    if(existing) {
                        $('shared_pass_div').addClassName('SF_disabled');
                        $('shared_pass').disable();
                    }else {
                        $('shared_pass_div').removeClassName('SF_disabled');
                        $('shared_pass').enable();
                    }
                });
            }
        };
        var submitFunc = function(oForm){
            if(!$('shared_pass').disabled){
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
            conn.onComplete = function(transport){
                var response = parseInt(transport.responseText);
                if(response == 200){
                    ajaxplorer.displayMessage('SUCCESS', MessageHash[348]);
                    ajaxplorer.fireContextRefresh();
                    hideLightBox(true);
                }else{
                    var messages = {100:349, 101:352, 102:350, 103:351};
                    ajaxplorer.displayMessage('ERROR', MessageHash[messages[response]]);
                }
            };
            conn.sendAsync();
            return false;
        };
        if(window.ajxpBootstrap.parameters.get("usersEditable") == false){
            ajaxplorer.displayMessage('ERROR', MessageHash[394]);
        }else{
            modal.showDialogForm('Get', 'share_folder_form', loadFunc, submitFunc, null);
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
                    oForm.down('fieldset[id="share_generate"]').hide();
                    oForm.down('fieldset[id="share_result"]').show();
                    var conn = new Connexion();
                    conn.addParameter("get_action", "get_publiclet_link");
                    conn.addParameter("file", userSelection.getUniqueNode().getPath());
                    conn.onComplete = function(transport){
                        oForm.down('input[id="share_container"]').value = transport.responseText;
                    };
                    conn.sendAsync();
                    oForm.down('div[id="unshare_button"]').observe("click", this.performUnshareAction.bind(this));
                }else{
                    var button = $(oForm).down('div.fakeUploadButton');
                    button.observe("click", this.generatePublicLinkCallback.bind(this));
                }
            }.bind(this),
            function(oForm){
                var button = $(oForm).down('div.fakeUploadButton');
                button.stopObserving("click");
                hideLightBox(true);
                return false;
            },
            null,
            true);

    },


    performUnshareAction : function(){
        var userSelection = ajaxplorer.getUserSelection();
        var conn = new Connexion();
        conn.addParameter("get_action", "unshare");
        conn.addParameter("file", userSelection.getUniqueNode().getPath());
        conn.sendAsync();
    },

    generatePublicLinkCallback : function(){
        var userSelection = ajaxplorer.getUserSelection();
        if(!userSelection.isUnique() || (userSelection.hasDir() && !userSelection.hasMime($A(['ajxp_browsable_archive'])))) return;
        var publicUrl = window.ajxpServerAccessPath+'&get_action=share';
        publicUrl = userSelection.updateFormOrUrl(null,publicUrl);
        var oForm = $(modal.getForm());
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
                            ajaxplorer.fireContextRefresh();
                        }
                    });
                }
            });
        };
        conn.sendSync();
    }

});