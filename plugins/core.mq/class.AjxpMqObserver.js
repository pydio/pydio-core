/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
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

/**
 * Use WebSocket or Poller
 */
Class.create("AjxpMqObserver", {

    pe:null,
    currentRepo:null,
    clientId:null,
    ws: null,

    initialize : function(){
        "use strict";
        this.clientId = window.ajxpBootstrap.parameters.get("SECURE_TOKEN");
        var configs = ajaxplorer.getPluginConfigs("mq");

        document.observe("ajaxplorer:repository_list_refreshed", function(event){

            var repoId;
            var data = event.memo;
            if(data.active) repoId = data.active;
            else if(ajaxplorer.repositoryId) repoId = ajaxplorer.repositoryId;

            if(window.WebSocket && parseInt(configs.get("WS_SERVER_ACTIVE"))){

                if(this.ws) {
                    if(!repoId){
                        this.ws.onclose = function(){
                            delete this.ws;
                        }.bind(this);
                        this.ws.close();

                    } else {
                        this.ws.send("register:" + repoId);
                    }
                }else{
                    if(repoId){
                        var url = "ws"+(parseInt(configs.get("WS_SERVER_SECURE"))?"s":"")+"://"+configs.get("WS_SERVER_HOST")+":"+configs.get("WS_SERVER_PORT")+configs.get("WS_SERVER_PATH");
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

                if(this.currentRepo){
                    var conn = new Connexion();
                    conn.setParameters($H({
                        get_action:'client_unregister_channel',
                        channel:'nodes:' + this.currentRepo,
                        client_id:this.clientId
                    }));
                    conn.discrete = true;
                    conn.sendSync();
                    this.currentRepo = null;
                }

                if(!repoId) return;

                this.currentRepo = repoId;
                var conn = new Connexion();
                conn.setParameters($H({
                    get_action:'client_register_channel',
                    channel:'nodes:' + repoId,
                    client_id:this.clientId
                }));
                conn.discrete = true;
                conn.sendAsync();

                this.pe = new PeriodicalExecuter(function(pe){
                    var conn = new Connexion();
                    conn.setParameters($H({
                        get_action:'client_consume_channel',
                        channel:'nodes:' + this.currentRepo,
                        client_id:this.clientId
                    }));
                    conn.discrete = true;
                    conn.onComplete = function(transport){
                        ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                        ajaxplorer.notify("server_message", transport.responseXML);
                    };
                    conn.sendAsync();
                }.bind(this), 10);

            }


        }.bind(this));

    }

});

if(!ajaxplorer.mqObserver){
    ajaxplorer.mqObserver = new AjxpMqObserver();
}