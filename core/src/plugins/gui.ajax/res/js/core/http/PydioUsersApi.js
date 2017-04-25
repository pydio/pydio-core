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
'use strict';

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

(function (global) {
    var User = (function () {
        function User(id, label, type, group, avatar, temporary, external, extendedLabel) {
            _classCallCheck(this, User);

            this._id = id;
            this._label = label;
            this._type = type;
            this._group = group;
            this._avatar = avatar;
            this._temporary = temporary;
            this._external = external;
            this._extendedLabel = extendedLabel;
        }

        User.prototype.asObject = function asObject() {
            return {
                id: this._id,
                label: this._label,
                type: this._type,
                group: this._group,
                avatar: this._avatar,
                temporary: this._temporary,
                external: this._external,
                extendedLabel: this._extendedLabel
            };
        };

        User.prototype.getId = function getId() {
            return this._id;
        };

        User.prototype.getLabel = function getLabel() {
            return this._label;
        };

        User.prototype.getType = function getType() {
            return this._type;
        };

        User.prototype.getGroup = function getGroup() {
            return this._group;
        };

        User.prototype.getAvatar = function getAvatar() {
            return this._avatar;
        };

        User.prototype.getTemporary = function getTemporary() {
            return this._temporary;
        };

        User.prototype.getExternal = function getExternal() {
            return this._external;
        };

        User.prototype.getExtendedLabel = function getExtendedLabel() {
            return this._extendedLabel;
        };

        return User;
    })();

    var UsersApi = (function () {
        function UsersApi() {
            _classCallCheck(this, UsersApi);
        }

        UsersApi.authorizedUsersStartingWith = function authorizedUsersStartingWith(token, callback) {
            var usersOnly = arguments.length <= 2 || arguments[2] === undefined ? false : arguments[2];
            var existingOnly = arguments.length <= 3 || arguments[3] === undefined ? false : arguments[3];

            var params = {
                get_action: 'user_list_authorized_users',
                value: token,
                format: 'xml'
            };
            if (usersOnly) {
                params['users_only'] = 'true';
            }
            if (existingOnly) {
                params['existing_only'] = 'true';
            }
            PydioApi.getClient().request(params, function (transport) {
                var suggestions = [];
                var lis = XMLUtils.XPathSelectNodes(transport.responseXML, '//li');
                lis.map(function (li) {
                    var spanLabel = XMLUtils.XPathGetSingleNodeText(li, 'span[@class="user_entry_label"]');
                    suggestions.push(new User(li.getAttribute('data-entry_id'), li.getAttribute('data-label'), li.getAttribute('class'), li.getAttribute('data-group'), li.getAttribute('data-avatar'), li.getAttribute('data-temporary') ? true : false, li.getAttribute('data-external') == 'true', spanLabel));
                });
                callback(suggestions);
            });
        };

        UsersApi.getCreateUserParameters = function getCreateUserParameters() {
            var basicParameters = [];
            basicParameters.push({
                description: MessageHash['533'],
                editable: false,
                expose: "true",
                label: MessageHash['522'],
                name: "new_user_id",
                scope: "user",
                type: "string",
                mandatory: "true"
            }, {
                description: MessageHash['534'],
                editable: "true",
                expose: "true",
                label: MessageHash['523'],
                name: "new_password",
                scope: "user",
                type: "valid-password",
                mandatory: "true"
            }, {
                description: MessageHash['536'],
                editable: "true",
                expose: "true",
                label: MessageHash['535'],
                name: "send_email",
                scope: "user",
                type: "boolean",
                mandatory: true
            });

            var params = global.pydio.getPluginConfigs('conf').get('NEWUSERS_EDIT_PARAMETERS').split(',');
            for (var i = 0; i < params.length; i++) {
                params[i] = "user/preferences/pref[@exposed]|//param[@name='" + params[i] + "']";
            }
            var xPath = params.join('|');
            PydioForm.Manager.parseParameters(global.pydio.getXmlRegistry(), xPath).map(function (el) {
                basicParameters.push(el);
            });
            return basicParameters;
        };

        UsersApi.getCreateUserPostPrefix = function getCreateUserPostPrefix() {
            return 'NEW_';
        };

        UsersApi.createUserFromPost = function createUserFromPost(postValues, callback) {
            postValues['get_action'] = 'user_create_user';
            PydioApi.getClient().request(postValues, (function (transport) {
                callback(postValues);
            }).bind(this));
        };

        UsersApi.saveSelectionSupported = function saveSelectionSupported() {
            return global.pydio.getController().actions.get('user_team_create') !== undefined;
        };

        UsersApi.saveSelectionAsTeam = function saveSelectionAsTeam(teamName, userIds, callback) {
            PydioApi.getClient().request({
                get_action: 'user_team_create',
                team_label: teamName,
                'user_ids[]': userIds
            }, function () {
                callback();
            });
        };

        return UsersApi;
    })();

    var ns = global.PydioUsers || {};
    ns.Client = UsersApi;
    ns.User = User;
    global.PydioUsers = ns;
})(window);
