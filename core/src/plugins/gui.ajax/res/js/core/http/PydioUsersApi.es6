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
 * The latest code can be found at <https://pydio.com>.
 */
import PydioApi from './PydioApi'
import XMLUtils from '../util/XMLUtils'

class User{
    _id;
    _label;
    _type;
    _group;
    _avatar;
    _temporary;
    _external;
    _extendedLabel;

    constructor(id, label, type, group, avatar, temporary, external, extendedLabel){
        this._id = id;
        this._label = label;
        this._type = type;
        if(this._type === 'group'){
            this._group = id;
        }
        this._avatar = avatar;
        this._temporary = temporary;
        this._external = external;
        this._extendedLabel = extendedLabel;
    }

    static fromObject(user){
        return new User(
            user.id,
            user.label,
            user.type,
            user.group,
            user.avatar,
            user.temporary,
            user.external
        );
    }

    asObject(){
        return {
            id:this._id,
            label:this._label,
            type:this._type,
            group:this._group,
            avatar:this._avatar,
            temporary:this._temporary,
            external:this._external,
            extendedLabel:this._extendedLabel
        }
    }

    getId() {
        return this._id;
    }

    getLabel() {
        return this._label;
    }

    getType() {
        return this._type;
    }

    getGroup() {
        return this._group;
    }

    getAvatar() {
        return this._avatar;
    }

    getTemporary() {
        return this._temporary;
    }

    getExternal() {
        return this._external;
    }

    getExtendedLabel() {
        return this._extendedLabel;
    }
}


class UsersApi{

    static authorizedUsersStartingWith(token, callback, usersOnly=false, existingOnly=false){

        let params = {
            get_action:'user_list_authorized_users',
            value:token,
            format:'json'
        };
        if(usersOnly){
            params['users_only'] = 'true';
        }
        if(existingOnly){
            params['existing_only'] = 'true';
        }
        PydioApi.getClient().request(params, function(transport){
            let suggestions = [];
            if(transport.responseXML){
                const lis = XMLUtils.XPathSelectNodes(transport.responseXML, '//li');
                lis.map(function(li){
                    const spanLabel = XMLUtils.XPathGetSingleNodeText(li, 'span[@class="user_entry_label"]');
                    suggestions.push(new User(
                        li.getAttribute('data-entry_id'),
                        li.getAttribute('data-label'),
                        li.getAttribute('class'),
                        li.getAttribute('data-group'),
                        li.getAttribute('data-avatar'),
                        li.getAttribute('data-temporary')?true:false,
                        li.getAttribute('data-external') == 'true',
                        spanLabel
                    ));
                });
            }else if(transport.responseJSON){
                const data = transport.responseJSON;
                data.map(function(entry){
                    const {id, label, type, group, avatar, temporary, external} = entry;
                    suggestions.push(new User(id, label, type, group, avatar, temporary, external, label));
                });
            }
            callback(suggestions);
        });

    }

    static createUserFromPost(postValues, callback){
        postValues['get_action'] = 'user_create_user';
        PydioApi.getClient().request(postValues, function(transport){
            callback(postValues, transport.responseJSON);
        }.bind(this));
    }

    static deleteUser(userId, callback){
        PydioApi.getClient().request({
            get_action:'user_delete_user',
            user_id:userId
        }, function(transport){
            callback();
        });
    }

    static saveSelectionSupported(){
        return global.pydio.getController().actions.get('user_team_create') !== undefined;
    }

    static deleteTeam(teamId, callback){
        teamId = teamId.replace('/AJXP_TEAM/', '');
        PydioApi.getClient().request({
            get_action:'user_team_delete',
            team_id:teamId
        }, function(transport){
            callback(transport.responseJSON);
        });
    }

    static saveSelectionAsTeam(teamName, userIds, callback){
        PydioApi.getClient().request({
            get_action:'user_team_create',
            team_label:teamName,
            'user_ids[]':userIds
        }, function(transport){
            callback(transport.responseJSON);
        });
    }

    static addUserToTeam(teamId, userId, callback){
        teamId = teamId.replace('/AJXP_TEAM/', '');
        PydioApi.getClient().request({
            get_action:'user_team_add_user',
            team_id:teamId,
            user_id:userId
        }, function(transport){
            callback(transport.responseJSON);
        });
    }

    static removeUserFromTeam(teamId, userId, callback){
        teamId = teamId.replace('/AJXP_TEAM/', '');
        PydioApi.getClient().request({
            get_action:'user_team_delete_user',
            team_id:teamId,
            user_id:userId
        }, function(transport){
            callback(transport.responseJSON);
        });
    }

    static updateTeamLabel(teamId, newLabel,callback){
        teamId = teamId.replace('/AJXP_TEAM/', '');
        PydioApi.getClient().request({
            get_action:'user_team_update_label',
            team_id:teamId,
            team_label:newLabel
        }, function(transport){
            callback(transport.responseJSON);
        });
    }

}

export {User, UsersApi}