/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
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
 */

/**
 * Use WebSocket or Poller
 */
Class.create("AjxpMqObserver", {

    pe:null,
    currentRepo:null,
    clientId:null,
    ws: null,
    configs: null,
    channel_pending: false,

    initialize : function(){
        "use strict";

        if(window.ajxpMinisite) return;

        this.clientId = window.ajxpBootstrap.parameters.get("SECURE_TOKEN");
        this.configs = ajaxplorer.getPluginConfigs("mq");

        document.observe("ajaxplorer:repository_list_refreshed", function(event){

            var repoId;
            var data = event.memo;
            if(data.active) repoId = data.active;
            else if(ajaxplorer.repositoryId) repoId = ajaxplorer.repositoryId;
            this.initForRepoId(repoId);

        }.bind(this));

        if(ajaxplorer.repositoryId){
            this.initForRepoId(ajaxplorer.repositoryId);
        }

    },

    initForRepoId:function(repoId){
        if(window.WebSocket && this.configs.get("WS_SERVER_ACTIVE")){

            if(this.ws) {
                if(!repoId){
                    this.ws.onclose = function(){
                        delete this.ws;
                    }.bind(this);
                    this.ws.close();

                } else {
                    try{
                        this.ws.send("register:" + repoId);
                    }catch(e){
                        if(console) console.log('Error while sending WebSocket message: '+ e.message);
                    }
                }
            }else{
                if(repoId){
                    var url = "ws"+(this.configs.get("WS_SERVER_SECURE")?"s":"")+"://"+this.configs.get("WS_SERVER_HOST")+":"+this.configs.get("WS_SERVER_PORT")+this.configs.get("WS_SERVER_PATH");
                    this.ws = new WebSocket(url);
                    this.ws.onmessage = function(event){
                        var obj = parseXml(event.data);
                        if(obj){
                            ajaxplorer.actionBar.parseXmlMessage(obj);
                            ajaxplorer.notify("server_message", obj);
                        }
                    };
                    this.ws.onopen = function(){
                        this.ws.send("register:" + repoId);
                    }.bind(this);
                }
            }

        }else{

            if(this.pe){
                this.pe.stop();
            }

            if(this.currentRepo && repoId){

                this.unregisterCurrentChannel(function(){
                    this.registerChannel(repoId);
                }.bind(this));

            }else if(this.currentRepo && !repoId){

                this.unregisterCurrentChannel();

            }else if(!this.currentRepo && repoId){

                this.registerChannel(repoId);

            }

        }

    },

    unregisterCurrentChannel : function(callback){

        var conn = new Connexion();
        conn.setParameters($H({
            get_action:'client_unregister_channel',
            channel:'nodes:' + this.currentRepo,
            client_id:this.clientId
        }));
        conn.discrete = true;
        conn.onComplete = function(transp){
            this.currentRepo = null;
            if(callback) callback();
        }.bind(this);
        conn.sendAsync();

    },

    registerChannel : function(repoId){

        this.currentRepo = repoId;
        var conn = new Connexion();
        conn.setParameters($H({
            get_action:'client_register_channel',
            channel:'nodes:' + repoId,
            client_id:this.clientId
        }));
        conn.discrete = true;
        conn.sendAsync();

        this.pe = new PeriodicalExecuter(this.consumeChannel.bind(this), this.configs.get('POLLER_FREQUENCY') || 5);

    },

    consumeChannel : function(){
        if(this.channel_pending) {
            return;
        }
        var conn = new Connexion();
        conn.setParameters($H({
            get_action:'client_consume_channel',
            channel:'nodes:' + this.currentRepo,
            client_id:this.clientId
        }));
        conn.discrete = true;
        conn.onComplete = function(transport){
            this.channel_pending = false;
            if(transport.responseXML){
                ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                ajaxplorer.notify("server_message", transport.responseXML);
            }
        }.bind(this);
        this.channel_pending = true;
        conn.sendAsync();
    }

});