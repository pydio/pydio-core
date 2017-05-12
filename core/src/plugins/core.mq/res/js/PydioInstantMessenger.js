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


(function(global){

    let pydio = global.pydio;
    let pydioBootstrap = global.pydioBootstrap;
    /**
     * Use WebSocket or Poller
     */
    class PydioInstantMessenger {


        constructor() {
            this.pe = null;
            this.currentRepo = null;
            this.clientId = null;
            this.ws = null;
            this.configs = null;
            this.channel_pending = false;


            this.clientId = pydioBootstrap.parameters.get("SECURE_TOKEN");
            this.configs = pydio.getPluginConfigs("mq");
            this.defaultPollerFreq = this.configs.get('POLLER_FREQUENCY') || 15;
            this.pollingFrequency = this.defaultPollerFreq;

            pydio.observe("repository_list_refreshed", function (data) {

                var repoId;
                if (data.active) {
                    repoId = data.active;
                } else if (pydio.repositoryId) {
                    repoId = pydio.repositoryId;
                }
                if (this.currentRepo && this.currentRepo == repoId) { // Ignore, repoId did not change!
                    return;
                }
                this.initForRepoId(repoId);

            }.bind(this));

            if (pydio.repositoryId) {
                this.initForRepoId(pydio.repositoryId);
            }

        }

        initForRepoId(repoId) {

            if (global.WebSocket && !global.ajxpMinisite && this.configs.get("WS_ACTIVE")) {

                if (this.ws) {
                    if (!repoId) {
                        this.ws.onclose = function () {
                            delete this.ws;
                        }.bind(this);
                        this.ws.close();

                    } else {
                        try {
                            this.ws.send("register:" + repoId);
                        } catch (e) {
                            if (console) console.log('Error while sending WebSocket message: ' + e.message);
                        }
                    }
                } else {
                    if (repoId) {
                        var host = this.configs.get("BOOSTER_MAIN_HOST");
                        if (this.configs.get("BOOSTER_WS_ADVANCED") && this.configs.get("BOOSTER_WS_ADVANCED")['booster_ws_advanced'] === 'custom' && this.configs.get("BOOSTER_WS_ADVANCED")['WS_HOST']) {
                            host = this.configs.get("BOOSTER_WS_ADVANCED")['WS_HOST'];
                        }
                        var port = this.configs.get("BOOSTER_MAIN_PORT");
                        if (this.configs.get("BOOSTER_WS_ADVANCED") && this.configs.get("BOOSTER_WS_ADVANCED")['booster_ws_advanced'] === 'custom' && this.configs.get("BOOSTER_WS_ADVANCED")['WS_PORT']) {
                            port = this.configs.get("BOOSTER_WS_ADVANCED")['WS_PORT'];
                        }
                        var secure = this.configs.get("BOOSTER_MAIN_SECURE");
                        if (this.configs.get("BOOSTER_WS_ADVANCED") && this.configs.get("BOOSTER_WS_ADVANCED")['booster_ws_advanced'] === 'custom' && this.configs.get("BOOSTER_WS_ADVANCED")['WS_SECURE']) {
                            secure = this.configs.get("BOOSTER_WS_ADVANCED")['WS_SECURE'];
                        }

                        var url = "ws" + (secure ? "s" : "") + "://" + host + ":" + port + "/" + this.configs.get("WS_PATH");
                        this.ws = new WebSocket(url);
                        this.ws.onmessage = function (event) {
                            var obj = XMLUtils.parseXml(event.data);
                            if (obj) {
                                PydioApi.getClient().parseXmlMessage(obj);
                                pydio.notify("server_message", obj);
                            }
                        };
                        this.ws.onopen = function () {
                            this.ws.send("register:" + repoId);
                        }.bind(this);
                        this.ws.onclose = function (event) {
                            var reason;
                            // See http://tools.ietf.org/html/rfc6455#section-7.4.1
                            if (event.code == 1000)
                                reason = "Normal closure, meaning that the purpose for which the connection was established has been fulfilled.";
                            else if (event.code == 1001)
                                reason = "An endpoint is \"going away\", such as a server going down or a browser having navigated away from a page.";
                            else if (event.code == 1002)
                                reason = "An endpoint is terminating the connection due to a protocol error";
                            else if (event.code == 1003)
                                reason = "An endpoint is terminating the connection because it has received a type of data it cannot accept (e.g., an endpoint that understands only text data MAY send this if it receives a binary message).";
                            else if (event.code == 1004)
                                reason = "Reserved. The specific meaning might be defined in the future.";
                            else if (event.code == 1005)
                                reason = "No status code was actually present.";
                            else if (event.code == 1006)
                                reason = "The connection was closed abnormally, e.g., without sending or receiving a Close control frame";
                            else if (event.code == 1007)
                                reason = "An endpoint is terminating the connection because it has received data within a message that was not consistent with the type of the message (e.g., non-UTF-8 [http://tools.ietf.org/html/rfc3629] data within a text message).";
                            else if (event.code == 1008)
                                reason = "An endpoint is terminating the connection because it has received a message that \"violates its policy\". This reason is given either if there is no other sutible reason, or if there is a need to hide specific details about the policy.";
                            else if (event.code == 1009)
                                reason = "An endpoint is terminating the connection because it has received a message that is too big for it to process.";
                            else if (event.code == 1010) // Note that this status code is not used by the server, because it can fail the WebSocket handshake instead.
                                reason = "An endpoint (client) is terminating the connection because it has expected the server to negotiate one or more extension, but the server didn't return them in the response message of the WebSocket handshake. Specifically, the extensions that are needed are: " + event.reason;
                            else if (event.code == 1011)
                                reason = "A server is terminating the connection because it encountered an unexpected condition that prevented it from fulfilling the request.";
                            else if (event.code == 1015)
                                reason = "The connection was closed due to a failure to perform a TLS handshake (e.g., the server certificate can't be verified).";
                            else
                                reason = "Unknown reason";
                            if (console) {
                                console.error("WebSocket Closed Connection for this reason :" + reason + " (code " + event.code + ")");
                                console.error("Switching back to polling");
                            }
                            delete this.ws;
                            this.configs.set("WS_ACTIVE", false);
                            this.initForRepoId(repoId);

                        }.bind(this);
                        this.ws.onerror = function () {
                            if (console) {
                                console.error("Cannot login to websocket server, switching back to polling");
                            }
                            delete this.ws;
                            this.configs.set("WS_ACTIVE", false);
                            this.initForRepoId(repoId);
                        }.bind(this);
                    }
                }

            } else {

                if (this.pe) {
                    this.pe.stop();
                }

                if (this.currentRepo && repoId) {

                    this.unregisterCurrentChannel(function () {
                        this.registerChannel(repoId);
                    }.bind(this));

                } else if (this.currentRepo && !repoId) {

                    this.unregisterCurrentChannel();

                } else if (!this.currentRepo && repoId) {

                    this.registerChannel(repoId);

                }

            }

        }

        unregisterCurrentChannel(callback) {

            var conn = new Connexion();
            conn.setParameters({
                get_action: 'client_unregister_channel',
                channel: 'nodes:' + this.currentRepo,
                client_id: this.clientId
            });
            conn.discrete = true;
            conn.onComplete = function (transp) {
                this.currentRepo = null;
                if (callback) callback();
            }.bind(this);
            conn.sendAsync();

            if (this._consumeTriggerObs) {
                pydio.stopObserving("response.xml", this._consumeTriggerObs);
                this._consumeTriggerObs = null;
            }
            if (this._pollingFreqObs) {
                pydio.stopObserving("poller.frequency", this._pollingFreqObs);
                this._pollingFreqObs = null;
            }

        }

        registerChannel(repoId) {

            this.currentRepo = repoId;
            var conn = new Connexion();
            conn.setParameters({
                get_action: 'client_register_channel',
                channel: 'nodes:' + repoId,
                client_id: this.clientId
            });
            conn.discrete = true;
            conn.sendAsync();


            this.pe = new PeriodicalExecuter(this.consumeChannel.bind(this), this.pollingFrequency);

            this._consumeTriggerObs = function (responseXML) {
                if (XMLUtils.XPathSelectSingleNode(responseXML, "//consume_channel")) {
                    this.consumeChannel();
                }
            }.bind(this);
            pydio.observe("response.xml", this._consumeTriggerObs);

            this._pollingFreqObs = function (freq) {
                var value = freq.value ? freq.value : this.defaultPollerFreq;
                if (value == this.pollingFrequency) return;
                var lastPing = (value > this.pollingFrequency ? this.pollingFrequency : 0);
                this.pollingFrequency = value;
                this.pe.stop();
                this.pe = new PeriodicalExecuter(this.consumeChannel.bind(this), value);
                if (lastPing) {
                    global.setTimeout(this.consumeChannel.bind(this), lastPing * 1000);
                }
            }.bind(this);
            pydio.observe("poller.frequency", this._pollingFreqObs);

        }

        consumeChannel() {
            if (this.channel_pending) {
                return;
            }
            pydio.notify("poller.event");
            var conn = new Connexion();
            conn.setParameters({
                get_action: 'client_consume_channel',
                channel: 'nodes:' + this.currentRepo,
                client_id: this.clientId
            });
            conn.discrete = true;
            conn.onComplete = function (transport) {
                this.channel_pending = false;
                if (transport.responseXML) {
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                    pydio.notify("server_message", transport.responseXML);
                }
            }.bind(this);
            this.channel_pending = true;
            conn.sendAsync();
        }

    }

    window.PydioInstantMessenger = PydioInstantMessenger;


})(window);
