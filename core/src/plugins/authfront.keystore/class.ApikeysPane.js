/*
 * Copyright 2007-2014 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

    __implements: ["IActionProvider"],
    _generateAllowed: false,
    _resultBox: null,

    initialize: function($super, element, options){
        $super(element, options);

        if(!ajaxplorer.user) return;

        this._resultBox = this.htmlElement.down("#token_results");
        this._resultBox.down('span').observe('click', function(){
            new Effect.Fade(this._resultBox);
        }.bind(this));
        this._resultBox.setStyle({opacity:0});
        this.reloadKeys();

        this._generateAllowed = ajaxplorer.getPluginConfigs("authfront.keystore").get("USER_GENERATE_KEYS");

    },

    reloadKeys: function(){

        this.htmlElement.select("li").invoke('remove');
        var list = this.htmlElement.down('ul');
        var oThis = this;

        // Load existing keys
        var conn = new Connexion();
        conn.setParameters($H({'get_action' : 'keystore_list_tokens'}));
        conn.onComplete = function(transport){
            if(!transport.responseJSON || !Object.keys(transport.responseJSON).length){
                return;
            }
            $H(transport.responseJSON).each(function(pair){
                var item = new Element('li').update(pair.value['DEVICE_DESC'] + ' - ' + pair.value['DEVICE_OS']
                    + ' (' + (pair.value['DEVICE_ID'] ? pair.value['DEVICE_ID'] : 'No ID') + ') <span class="icon-remove-sign"></span>');
                list.insert(item);
                item.down('span.icon-remove-sign').observe('click', function(){
                    oThis.revokeTokens(pair.key, item);
                });
            });
        };
        conn.sendAsync();
    },

    generateKey: function(){

        if(!this._generateAllowed) return;
        var conn = new Connexion();
        conn.setParameters(new Hash({
            get_action:"keystore_generate_auth_token"
        }));
        conn.onComplete = function(transport){
            var data = transport.responseJSON;
            this.reloadKeys();
            this._resultBox.down('#token_results_content').update('Token : ' + data['t'] + '<br> Private : ' + data['p']);
            new Effect.Appear(this._resultBox);
        }.bind(this);
        conn.sendAsync();

    },

    revokeTokens: function(keyId, removeItem){

        if(!window.confirm(MessageHash['keystore.7'])){
            return;
        }

        var c = new Connexion();
        var params = $H({get_action:'keystore_revoke_tokens'});
        if(keyId){
            params.set('key_id', keyId);
        }
        c.setParameters(params);
        c.onComplete = function(transport){
            if(transport.responseJSON.result == 'SUCCESS') {
                if(removeItem) {
                    removeItem.remove();
                }else{
                    this.reloadKeys();
                }
            }
            ajaxplorer.displayMessage(transport.responseJSON.result, transport.responseJSON.message);
        }.bind(this);
        c.sendAsync();

    },


    getActions:function(){

        var context = {
            selection:false,
            dir:true,
            actionBar:true,
            actionBarGroup:'keystore_bar',
            contextMenu:false,
            infoPanel:false
        };


        var options1 = {
            name:'keystore-generate-auth-token',
            src:'',
            icon_class:'icon-key',
            text_id:'keystore.3',
            title_id:'keystore.4',
            text:MessageHash['keystore.3'],
            title:MessageHash['keystore.4'],
            hasAccessKey:false,
            subMenu:false,
            subMenuUpdateImage:false,
            callback: function(){
                this.generateKey();
            }.bind(this)
        };

        var options2 = {
            name:'keystore-revoke-tokens',
            src:'',
            icon_class:'icon-key',
            text_id:'keystore.5',
            title_id:'keystore.6',
            text:MessageHash['keystore.5'],
            title:MessageHash['keystore.6'],
            hasAccessKey:false,
            subMenu:false,
            subMenuUpdateImage:false,
            callback: function(){
                this.revokeTokens();
            }.bind(this)
        };

        if(this._generateAllowed){
            return new $H({
                'keystore-generate-auth-token': new Action(options1, context, {},{}),
                'keystore-revoke-tokens': new Action(options2, context, {}, {})
            });
        }else{
            return new $H({
                'keystore-revoke-tokens': new Action(options2, context, {}, {})
            });
        }

    }


});