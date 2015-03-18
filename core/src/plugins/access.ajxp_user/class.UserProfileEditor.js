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
 *
 */
Class.create("UserProfileEditor", AjxpPane, {

    _formManager: null,

    initialize: function($super, oFormObject, editorOptions){

        $super(oFormObject, editorOptions);

        attachMobileScroll(oFormObject.down("#user_profile_form"), "vertical");

        if(ajaxplorer.actionBar.getActionByName('custom_data_edit')){

            this._formManager = new FormManager();
            var definitions = this._formManager.parseParameters(ajaxplorer.getXmlRegistry(), "user/preferences/pref[@exposed='true']|//param[contains(@scope,'user') and @expose='true']");
            this._formManager.createParametersInputs(oFormObject.down('#user_profile_form'), definitions, true, ajaxplorer.user.preferences, false, true);
            this._formManager.disableShortcutsOnForm(oFormObject.down('#user_profile_form'));

            var saveButton = new Element('a', {}).update('<span class="icon-save"></span> <span>'+MessageHash[53]+'</span>');
            oFormObject.down('.toolbarGroup').insert({top: saveButton});


            saveButton.observe("click", function(){
                var params = $H();
                this._formManager.serializeParametersInputs(oFormObject.down('#user_profile_form'), params, 'PREFERENCES_');
                var conn = new Connexion();
                params.set("get_action", "custom_data_edit");
                conn.setParameters(params);
                conn.setMethod("POST");
                conn.onComplete = function(transport){
                    ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                    document.observeOnce("ajaxplorer:registry_part_loaded", function(event){
                        if(event.memo != "user/preferences") return;
                        ajaxplorer.logXmlUser(ajaxplorer.getXmlRegistry());
                    });
                    ajaxplorer.loadXmlRegistry(false, "user/preferences");
                };
                conn.sendAsync();

            }.bind(this));

            fitHeightToBottom(oFormObject);
            fitHeightToBottom(oFormObject.down('#user_profile_form'));
            oFormObject.down('#user_profile_form').setStyle({overflow:'auto'});


        }

        if(ajaxplorer.actionBar.getActionByName('pass_change')){

            var chPassButton = new Element('a', {className:''}).update('<span class="icon-key"></span> <span>'+MessageHash[194]+'</span>');
            oFormObject.down('.toolbarGroup').insert(chPassButton);
            chPassButton.observe("click", function(){
                ajaxplorer.actionBar.getActionByName('pass_change').apply();
            });

        }

    },

    resize: function(size){
        fitHeightToBottom(this.htmlElement.down('#user_profile_form'));
    }

});