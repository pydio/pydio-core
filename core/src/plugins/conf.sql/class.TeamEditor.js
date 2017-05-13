/*
* Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
* The latest code can be found at <https://pydio.com>.
*/

Class.create("TeamEditor", {
    CONTEXT_HOLDER: null,
    INSTANCE:null,

    initialize:function(){

    },

    open: function(oForm, contextHolder){

        var textA = oForm.down('#team_edit_entries');
        var users = [], userLabels = [];

        if(contextHolder){
            this.CONTEXT_HOLDER = contextHolder;
            users = contextHolder.getUniqueNode().getMetadata().get('users').stripTags().strip().split(",");
            userLabels = contextHolder.getUniqueNode().getMetadata().get('users_labels').stripTags().strip().split(", ");
            oForm.down('#team_edit_label').setValue(contextHolder.getUniqueNode().getLabel());
        }

        var createEntry = function(id, label){
            var uElem = new Element('div', {className:'user_entry'});
            uElem.writeAttribute('data-entry_id', id);
            uElem.update('<span class="user_entry_label">'+label+'</span>');
            textA.insert(uElem);
            var remove = new Element('span', {className:'delete_user_entry icon-remove-sign', style:'display:none'}).observe("click", function(){uElem.remove()});
            uElem.insert(remove);
            uElem.observe('mouseover', function(){remove.setStyle({display:'inline'})});
            uElem.observe('mouseout', function(){remove.setStyle({display:'none'})});
        };

        for(var i = 0; i<users.length; i++){
            createEntry(users[i], userLabels[i]);
        }

        new AjxpUsersCompleter(
            oForm.down('#team_edit_input'),
            null,
            oForm.down('#team_edit_container'),
            {
                tmpUsersPrefix:'',
                usersOnly: true,
                existingOnly: true,
                updateUserEntryAfterCreate:null,
                createUserPanel:null,
                indicator: oForm.down("#team_loader"),
                minChars:parseInt(ajaxplorer.getPluginConfigs("conf").get("USERS_LIST_COMPLETE_MIN_CHARS")),
                afterUpdateElement: function(elem, selectedLi){
                    var label = selectedLi.readAttribute('data-label');
                    var id = selectedLi.readAttribute('data-entry_id') || selectedLi.readAttribute('data-group');
                    if(!textA.down('div[data-entry_id="'+id+'"]')){
                        createEntry(id, label);
                    }
                    oForm.down('#team_edit_input').setValue("");
                }
            }
        );

    },

    complete: function(oForm){

        var newValues = oForm.down('#team_edit_entries').select('div[data-entry_id]').map(function(e){return e.readAttribute('data-entry_id')});
        var teamId;
        var c = new Connexion();
        var teamLabel = oForm.down('#team_edit_label').getValue();

        if(this.CONTEXT_HOLDER){
            // EDIT
            teamId = getBaseName(this.CONTEXT_HOLDER.getUniqueNode().getPath());
            c.setParameters($H({
                get_action:'user_team_edit_users',
                team_id:teamId,
                team_label:teamLabel,
                'users[]':newValues
            }));
        }else{
            // CREATE
            c.setParameters($H({
                get_action:'user_team_create',
                team_label:teamLabel,
                'user_ids[]':newValues
            }));
        }

        c.setMethod('POST');
        c.onComplete = function(t){
            hideLightBox();
            $('team_panel').ajxpPaneObject.reloadDataModel();
        };
        c.sendAsync();
        this.close();

    },

    close: function(){
        this.CONTEXT_HOLDER = null;
    },

    getInstance: function(){
        if(!TeamEditor.prototype.INSTANCE){
            TeamEditor.prototype.INSTANCE = new TeamEditor;
        }
        return TeamEditor.prototype.INSTANCE;
    }

});
