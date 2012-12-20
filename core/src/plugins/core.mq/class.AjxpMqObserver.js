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


Class.create("AjxpMqObserver", {

    pe:null,
    currentRepo:null,
    clientId:null,

    initialize : function(){
        "use strict";
        this.clientId = window.ajxpBootstrap.parameters.get("SECURE_TOKEN");

        document.observe("ajaxplorer:repository_list_refreshed", function(event){

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

            var repoId;
            var data = event.memo;
            if(data.active) repoId = data.active;
            else if(ajaxplorer.repositoryId) repoId = ajaxplorer.repositoryId;
            if(!repoId) {
                return;
            }

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
                conn.onComplete = function(transport){ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);};
                conn.sendAsync();
            }.bind(this), 5);

        }.bind(this));

    }

});

if(!ajaxplorer.mqObserver){
    ajaxplorer.mqObserver = new AjxpMqObserver();
}