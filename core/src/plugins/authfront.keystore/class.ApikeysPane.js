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
Class.create("ApikeysPane", AjxpPane, {

    initialize: function($super, element, options){
        $super(element, options);

        if(!ajaxplorer.user) return;
        element.down("#generate_token_button").observe("click", function(){

            var conn = new Connexion();
            conn.setParameters(new Hash({
                get_action:"keystore_generate_auth_token"
            }));
            conn.onComplete = function(transport){
                var data = transport.responseJSON;
                element.down("#token_results").update('Token : ' + data['t'] + '<br> Private : ' + data['p']);
            };
            conn.sendAsync();

        });

        element.down("#revoke_tokens_button").observe("click", function(){

            var conn = new Connexion();
            conn.setParameters(new Hash({
                get_action:"keystore_revoke_tokens"
            }));
            conn.onComplete = function(transport){
                element.down("#token_results").update('Tokens cleared');
            };
            conn.sendAsync();

        });


    }


});