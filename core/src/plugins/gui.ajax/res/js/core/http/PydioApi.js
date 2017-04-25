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
/**
 * API Client
 */
'use strict';

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

var PydioApi = (function () {
    function PydioApi() {
        _classCallCheck(this, PydioApi);

        this._secureToken = '';
    }

    PydioApi.prototype.setPydioObject = function setPydioObject(pydioObject) {
        this._pydioObject = pydioObject;
        this._baseUrl = pydioObject.Parameters.get('serverAccessPath');
        this._secureToken = pydioObject.Parameters.get('SECURE_TOKEN');
    };

    PydioApi.prototype.setSecureToken = function setSecureToken(token) {
        this._secureToken = token;
    };

    PydioApi.prototype.request = function request(parameters) {
        var onComplete = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
        var onError = arguments.length <= 2 || arguments[2] === undefined ? null : arguments[2];
        var settings = arguments.length <= 3 || arguments[3] === undefined ? {} : arguments[3];

        if (window.Connexion) {
            // Connexion already handles secure_token
            var c = new Connexion();
            if (settings.discrete) {
                c.discrete = true;
            }
            c.setParameters($H(parameters));
            if (settings.method) {
                c.setMethod(settings.method);
            }
            c.onComplete = onComplete;
            if (settings.async === false) {
                c.sendSync();
            } else {
                c.sendAsync();
            }
        } else if (window.jQuery) {
            parameters['secure_token'] = this._secureToken;
            jQuery.ajax(this._baseUrl, {
                method: settings.method || 'post',
                data: parameters,
                async: settings && settings.async !== undefined ? settings.async : true,
                complete: onComplete,
                error: onError
            });
        }
    };

    PydioApi.prototype.loadFile = function loadFile(filePath) {
        var onComplete = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
        var onError = arguments.length <= 2 || arguments[2] === undefined ? null : arguments[2];

        if (window.Connexion) {
            var c = new Connexion(filePath);
            c.setMethod('GET');
            c.onComplete = onComplete;
            c.sendAsync();
        } else if (window.jQuery) {
            jQuery.ajax(filePath, {
                method: 'GET',
                async: true,
                complete: onComplete,
                error: onError
            });
        }
    };

    /**
     * 
     * @param file
     * @param fileParameterName
     * @param queryStringParams
     * @param onComplete
     * @param onError
     * @param onProgress
     * @returns XHR Handle to abort transfer
     */

    PydioApi.prototype.uploadFile = function uploadFile(file, fileParameterName) {
        var queryStringParams = arguments.length <= 2 || arguments[2] === undefined ? '' : arguments[2];
        var onComplete = arguments.length <= 3 || arguments[3] === undefined ? function () {} : arguments[3];
        var onError = arguments.length <= 4 || arguments[4] === undefined ? function () {} : arguments[4];
        var onProgress = arguments.length <= 5 || arguments[5] === undefined ? function () {} : arguments[5];
        var uploadUrl = arguments.length <= 6 || arguments[6] === undefined ? '' : arguments[6];
        var xhrSettings = arguments.length <= 7 || arguments[7] === undefined ? {} : arguments[7];

        if (!uploadUrl) {
            uploadUrl = pydio.Parameters.get('ajxpServerAccess');
        }
        if (queryStringParams) {
            uploadUrl += (uploadUrl.indexOf('?') === -1 ? '?' : '&') + queryStringParams;
        }

        if (window.Connexion) {
            var c;

            var _ret = (function () {
                // Warning, avoid double error
                var errorSent = false;
                var localError = function localError(xhr) {
                    if (!errorSent) onError('Request failed with status :' + xhr.status);
                    errorSent = true;
                };
                c = new Connexion();

                return {
                    v: c.uploadFile(file, fileParameterName, uploadUrl, onComplete, localError, onProgress, xhrSettings)
                };
            })();

            if (typeof _ret === 'object') return _ret.v;
        } else if (window.jQuery) {

            var formData = new FormData();
            formData.append(fileParameterName, file);
            return jQuery.ajax(uploadUrl, {
                method: 'POST',
                data: formData,
                complete: onComplete,
                error: onError,
                progress: onProgress
            });
        }
    };

    /**
     *
     * @param userSelection UserSelection A Pydio DataModel with selected files
     * @param prototypeHiddenForm Element A hidden form element: currently relying on PrototypeJS.
     * @param dlActionName String Action name to trigger, download by default.
     * @param additionalParameters Object Optional set of key/values to pass to the download.
     */

    PydioApi.prototype.downloadSelection = function downloadSelection(userSelection) {
        var prototypeHiddenForm = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
        var dlActionName = arguments.length <= 2 || arguments[2] === undefined ? 'download' : arguments[2];
        var additionalParameters = arguments.length <= 3 || arguments[3] === undefined ? {} : arguments[3];

        var ajxpServerAccess = this._pydioObject.Parameters.get("ajxpServerAccess");
        var agent = navigator.userAgent || '';
        var agentIsMobile = agent.indexOf('iPhone') != -1 || agent.indexOf('iPod') != -1 || agent.indexOf('iPad') != -1 || agent.indexOf('iOs') != -1;
        if (agentIsMobile || !prototypeHiddenForm) {
            var downloadUrl = ajxpServerAccess + '&get_action=' + dlActionName;
            if (additionalParameters) {
                for (var param in additionalParameters) {
                    if (additionalParameters.hasOwnProperty(param)) downloadUrl += "&" + param + "=" + additionalParameters[param];
                }
            }
            if (userSelection) {
                downloadUrl = userSelection.updateFormOrUrl(null, downloadUrl);
            }
            document.location.href = downloadUrl;
        } else {
            prototypeHiddenForm.action = window.ajxpServerAccessPath;
            prototypeHiddenForm.secure_token.value = this._secureToken;
            prototypeHiddenForm.get_action.value = dlActionName;
            prototypeHiddenForm.select("input").each(function (input) {
                if (input.name != 'get_action' && input.name != 'secure_token') input.remove();
            });
            var minisite_session = PydioApi.detectMinisiteSession(ajxpServerAccess);
            if (minisite_session) {
                prototypeHiddenForm.insert(new Element('input', { type: 'hidden', name: 'minisite_session', value: minisite_session }));
            }
            if (additionalParameters) {
                for (var parameter in additionalParameters) {
                    if (additionalParameters.hasOwnProperty(parameter)) {
                        prototypeHiddenForm.insert(new Element('input', { type: 'hidden', name: parameter, value: additionalParameters[parameter] }));
                    }
                }
            }
            if (userSelection) {
                userSelection.updateFormOrUrl(prototypeHiddenForm);
            }
            try {
                prototypeHiddenForm.submit();
            } catch (e) {
                if (window.console) window.console.error("Error while submitting hidden form for download", e);
            }
        }
    };

    PydioApi.prototype.postPlainTextContent = function postPlainTextContent(filePath, content, finishedCallback) {

        this.request({
            get_action: 'put_content',
            file: filePath,
            content: content
        }, (function (transport) {
            var success = this.parseXmlMessage(transport.responseXML);
            finishedCallback(success);
        }).bind(this), function () {
            finishedCallback(false);
        });
    };

    /**
     * Detect a minisite_session parameter in the URL
     * @param serverAccess
     * @returns string|bool
     */

    PydioApi.detectMinisiteSession = function detectMinisiteSession(serverAccess) {
        var regex = new RegExp('.*?[&\\?]' + 'minisite_session' + '=(.*?)&.*');
        var val = serverAccess.replace(regex, "$1");
        return val == serverAccess ? false : val;
    };

    /**
     * Detects if current browser supports HTML5 Upload.
     * @returns boolean
     */

    PydioApi.supportsUpload = function supportsUpload() {
        if (window.Connexion) {
            return window.FormData || window.FileReader;
        } else if (window.jQuery) {
            return window.FormData;
        }
        return false;
    };

    /**
     * Instanciate a PydioApi client if it's not already instanciated and return it.
     * @returns PydioApi
     */

    PydioApi.getClient = function getClient() {
        if (PydioApi._PydioClient) return PydioApi._PydioClient;
        var client = new PydioApi();
        PydioApi._PydioClient = client;
        return client;
    };

    /**
     * Load a javascript library
     * @param fileName String
     * @param onLoadedCode Function Callback
     * @param aSync Boolean load library asynchroneously
     */

    PydioApi.loadLibrary = function loadLibrary(fileName, onLoadedCode, aSync) {
        if (window.pydio && pydio.Parameters.get("ajxpVersion") && fileName.indexOf("?") == -1) {
            fileName += "?v=" + pydio.Parameters.get("ajxpVersion");
        }
        PydioApi._libUrl = false;
        if (window.pydio && pydio.Parameters.get('SERVER_PREFIX_URI')) {
            PydioApi._libUrl = pydio.Parameters.get('SERVER_PREFIX_URI');
        }
        //var path = (PydioApi._libUrl?PydioApi._libUrl+'/'+fileName:fileName);

        /*if(window.jQuery && window.jQuery.ajax){
            jQuery.ajax(path,
                {
                    method:'get',
                    async: (aSync?true:false),
                    complete:function(transport){
                        if(transport.responseText)
                        {
                            try
                            {
                                var script = transport.responseText;
                                if (window.execScript){
                                    window.execScript( script );
                                }
                                else{
                                    // TO TEST, THIS SEEM TO WORK ON SAFARI
                                    window.my_code = script;
                                    var script_tag = document.createElement('script');
                                    script_tag.type = 'text/javascript';
                                    script_tag.innerHTML = 'eval(window.my_code)';
                                    document.getElementsByTagName('head')[0].appendChild(script_tag);
                                }
                                if(onLoadedCode != null) onLoadedCode();
                            }
                            catch(e)
                            {
                                alert('error loading '+fileName+':'+ e.message);
                            }
                        }
                        pydio.fire("server_answer");
                    }
                });
        }else */if (window.Connexion) {

            var conn = new Connexion();
            conn._libUrl = false;
            if (pydio.Parameters.get('SERVER_PREFIX_URI')) {
                conn._libUrl = pydio.Parameters.get('SERVER_PREFIX_URI');
            }
            conn.loadLibrary(fileName, onLoadedCode, aSync);
        }
    };

    PydioApi.prototype.switchRepository = function switchRepository(repositoryId, completeCallback) {
        var params = {
            get_action: 'switch_repository',
            repository_id: repositoryId
        };
        this.request(params, completeCallback);
    };

    PydioApi.prototype.switchLanguage = function switchLanguage(lang, completeCallback) {
        var params = {
            get_action: 'get_i18n_messages',
            lang: lang,
            format: 'json'
        };
        this.request(params, completeCallback);
    };

    PydioApi.prototype.loadXmlRegistry = function loadXmlRegistry(completeCallback) {
        var xPath = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];

        var params = { get_action: 'get_xml_registry' };
        if (xPath) params[xPath] = xPath;
        this.request(params, completeCallback);
    };

    PydioApi.prototype.getBootConf = function getBootConf(completeCallback) {
        var params = { get_action: 'get_boot_conf' };
        var cB = (function (transport) {
            if (transport.responseJSON && transport.responseJSON.SECURE_TOKEN) {
                this._pydioObject.Parameters.set('SECURE_TOKEN', transport.responseJSON.SECURE_TOKEN);
                this.setSecureToken(transport.responseJSON.SECURE_TOKEN);
            }
            if (completeCallback) {
                completeCallback(transport);
            }
        }).bind(this);
        this.request(params, cB);
    };

    PydioApi.prototype.userSavePreference = function userSavePreference(prefName, prefValue) {
        this.request({ get_action: "save_user_pref", "pref_name_0": prefName, "pref_value_0": prefValue }, null, null, { discrete: true, method: 'post' });
    };

    PydioApi.prototype.userSavePreferences = function userSavePreferences(preferences, completeCallback) {
        var params = { 'get_action': 'save_user_pref' };
        var i = 0;
        preferences.forEach(function (value, key) {
            params["pref_name_" + i] = key;
            params["pref_value_" + i] = value;
            i++;
        });
        this.request(params, completeCallback, null, { discrete: true, method: 'post' });
    };

    PydioApi.prototype.userSavePassword = function userSavePassword(oldPass, newPass, seed, completeCallback) {
        this.request({
            get_action: 'save_user_pref',
            pref_name_0: "password",
            pref_value_0: newPass,
            crt: oldPass,
            pass_seed: seed
        }, completeCallback, null, { discrete: true, method: 'post' });
    };

    PydioApi.prototype.applyCheckHook = function applyCheckHook(node, hookName, hookArg, completeCallback, additionalParams) {
        var params = {
            get_action: "apply_check_hook",
            file: node.getPath(),
            hook_name: hookName,
            hook_arg: hookArg
        };
        if (additionalParams) {
            params = LangUtils.objectMerge(params, additionalParams);
        }
        this.request(params, completeCallback, null, { async: false });
    };

    /**
     * Standard parser for server XML answers
     * @param xmlResponse DOMDocument
     */

    PydioApi.prototype.parseXmlMessage = function parseXmlMessage(xmlResponse) {
        if (xmlResponse == null || xmlResponse.documentElement == null) return null;
        var childs = xmlResponse.documentElement.childNodes;

        var reloadNodes = [];
        var error = false;
        this.LAST_ERROR_ID = null;

        for (var i = 0; i < childs.length; i++) {
            if (childs[i].tagName == "message") {
                var messageTxt = "No message";
                if (childs[i].firstChild) messageTxt = childs[i].firstChild.nodeValue;
                if (childs[i].getAttribute('type') == 'ERROR') {
                    Logger.error(messageTxt);
                    error = true;
                } else {
                    Logger.log(messageTxt);
                }
            } else if (childs[i].tagName == "prompt") {

                var message = XMLUtils.XPathSelectSingleNode(childs[i], "message").firstChild.nodeValue;
                var jsonData = XMLUtils.XPathSelectSingleNode(childs[i], "data").firstChild.nodeValue;
                var json = JSON.parse(jsonData);
                // TODO: DELEGATE TO UI
                /*
                var dialogContent = new Element('div').update(json["DIALOG"]);
                modal.showSimpleModal(modal.messageBox?modal.messageBox:document.body, dialogContent, function(){
                    // ok callback;
                    if(json["OK"]["GET_FIELDS"]){
                        var params = $H();
                        $A(json["OK"]["GET_FIELDS"]).each(function(fName){
                            params.set(fName, dialogContent.down('input[name="'+fName+'"]').getValue());
                        });
                        var conn = new Connexion();
                        conn.setParameters(params);
                        if(json["OK"]["EVAL"]){
                            conn.onComplete = function(){
                                eval(json["OK"]["EVAL"]);
                            };
                        }
                        conn.sendAsync();
                    }else{
                        if(json["OK"]["EVAL"]){
                            eval(json["OK"]["EVAL"]);
                        }
                    }
                    return true;
                }, function(){
                    // cancel callback
                    if(json["CANCEL"]["EVAL"]){
                        eval(json["CANCEL"]["EVAL"]);
                    }
                    return true;
                });
                */
                throw new Error();
            } else if (childs[i].tagName == "reload_instruction") {
                var obName = childs[i].getAttribute('object');
                if (obName == 'data') {
                    var node = childs[i].getAttribute('node');
                    if (node) {
                        reloadNodes.push(node);
                    } else {
                        var file = childs[i].getAttribute('file');
                        if (file) {
                            this._pydioObject.getContextHolder().setPendingSelection(file);
                        }
                        reloadNodes.push(this._pydioObject.getContextNode());
                    }
                } else if (obName == 'repository_list') {
                    this._pydioObject.reloadRepositoriesList();
                }
            } else if (childs[i].nodeName == 'nodes_diff') {
                var dm = this._pydioObject.getContextHolder();
                if (dm.getAjxpNodeProvider().parseAjxpNodesDiffs) {
                    dm.getAjxpNodeProvider().parseAjxpNodesDiffs(childs[i], dm, !window.currentLightBox);
                }
            } else if (childs[i].tagName == "logging_result") {
                if (childs[i].getAttribute("secure_token")) {

                    var serverAccessPath = this._pydioObject.Parameters.get("ajxpServerAccess");
                    var minisite_session = PydioApi.detectMinisiteSession(serverAccessPath);

                    var secure_token = childs[i].getAttribute("secure_token");

                    var parts = serverAccessPath.split("?secure_token");
                    serverAccessPath = parts[0] + "?secure_token=" + secure_token;
                    if (minisite_session) serverAccessPath += "&minisite_session=" + minisite_session;

                    this.setSecureToken(secure_token);
                    this._pydioObject.Parameters.set("SECURE_TOKEN", secure_token);
                    // BACKWARD COMPAT
                    window.ajxpServerAccessPath = serverAccessPath;
                    this._pydioObject.Parameters.set("ajxpServerAccess", serverAccessPath);
                    if (window.ajxpBootstrap && ajxpBootstrap.parameters) {
                        ajxpBootstrap.parameters.set("ajxpServerAccess", serverAccessPath);
                        ajxpBootstrap.parameters.set("SECURE_TOKEN", secure_token);
                    }
                    if (window.Connexion) Connexion.SECURE_TOKEN = secure_token;
                }
                var result = childs[i].getAttribute('value');
                var errorId = false;
                if (result == '1') {
                    try {
                        if (childs[i].getAttribute('remember_login') && childs[i].getAttribute('remember_pass')) {
                            PydioApi.storeRememberData();
                        }
                    } catch (e) {
                        Logger.error('Error after login, could prevent registry loading!', e);
                    }
                    this._pydioObject.loadXmlRegistry();
                } else if (result == '0' || result == '-1') {
                    errorId = 285;
                } else if (result == '2') {
                    this._pydioObject.loadXmlRegistry();
                } else if (result == '-2') {
                    errorId = 285;
                } else if (result == '-3') {
                    errorId = 366;
                } else if (result == '-4') {
                    errorId = 386;
                }
                if (errorId) {
                    error = true;
                    this.LAST_ERROR_ID = errorId;
                    Logger.error(this._pydioObject.MessageHash[errorId]);
                }
            } else if (childs[i].tagName == "trigger_bg_action") {
                var name = childs[i].getAttribute("name");
                var messageId = childs[i].getAttribute("messageId");
                var parameters = {};
                for (var j = 0; j < childs[i].childNodes.length; j++) {
                    var paramChild = childs[i].childNodes[j];
                    if (paramChild.tagName == 'param') {
                        parameters[paramChild.getAttribute("name")] = paramChild.getAttribute("value");
                    } else if (paramChild.tagName == 'clientCallback' && paramChild.firstChild && paramChild.firstChild.nodeValue) {
                        var callbackCode = paramChild.firstChild.nodeValue;
                        var callback = new Function(callbackCode);
                    }
                }
                if (name == "javascript_instruction" && callback) {
                    callback();
                } else {
                    var bgManager = this._pydioObject.getController().getBackgroundTasksManager();
                    bgManager.queueAction(name, parameters, messageId);
                    bgManager.next();
                }
            }
        }
        this._pydioObject.notify("response.xml", xmlResponse);
        if (reloadNodes.length) {
            this._pydioObject.getContextHolder().multipleNodesReload(reloadNodes);
        }
        return !error;
    };

    /**
     * Submits a form using Connexion class.
     * @param formName String The id of the form
     * @param post Boolean Whether to POST or GET
     * @param completeCallback Function Callback to be called on complete
     */

    PydioApi.prototype.submitForm = function submitForm(formName) {
        var post = arguments.length <= 1 || arguments[1] === undefined ? true : arguments[1];
        var completeCallback = arguments.length <= 2 || arguments[2] === undefined ? null : arguments[2];

        var params = {};
        // TODO: UI IMPLEMENTATION
        $(formName).getElements().each(function (fElement) {
            var fValue = fElement.getValue();
            if (fElement.name == 'get_action' && fValue.substr(0, 4) == 'http') {
                fValue = getBaseName(fValue);
            }
            if (fElement.type == 'radio' && !fElement.checked) return;
            if (params[fElement.name] && fElement.name.endsWith('[]')) {
                var existing = params[fElement.name];
                if (typeof existing == 'string') existing = [existing];
                existing.push(fValue);
                params[fElement.name] = existing;
            } else {
                params[fElement.name] = fValue;
            }
        });
        if (this._pydioObject.getContextNode()) {
            params['dir'] = this._pydioObject.getContextNode().getPath();
        }
        var onComplete;
        if (completeCallback) {
            onComplete = completeCallback;
        } else {
            onComplete = (function (transport) {
                this.parseXmlMessage(transport.responseXML);
            }).bind(this);
        }
        this.request(params, onComplete, null, { method: post ? 'post' : 'get' });
    };

    /**
     * Trigger a simple download
     * @param url String
     */

    PydioApi.triggerDownload = function triggerDownload(url) {
        document.location.href = url;
    };

    PydioApi.storeRememberData = function storeRememberData() {
        if (!CookiesManager.supported()) return false;
        var cManager = new CookiesManager({
            expires: 3600 * 24 * 10,
            path: '/',
            secure: true
        });
        cManager.putCookie('remember', 'true');
    };

    PydioApi.clearRememberData = function clearRememberData() {
        if (!CookiesManager.supported()) return false;
        var cManager = new CookiesManager({
            path: '/',
            secure: true
        });
        return cManager.removeCookie('remember');
    };

    PydioApi.hasRememberData = function hasRememberData() {
        if (!CookiesManager.supported()) return false;
        var cManager = new CookiesManager({
            path: '/',
            secure: true
        });
        return cManager.getCookie('remember') === 'true';
    };

    PydioApi.prototype.tryToLogUserFromRememberData = function tryToLogUserFromRememberData() {
        if (!CookiesManager.supported()) return false;
        if (PydioApi.hasRememberData()) {
            this.request({
                get_action: 'login',
                userid: 'notify',
                password: 'notify',
                cookie_login: 'true'
            }, (function (transport) {
                this.parseXmlMessage(transport.responseXML);
            }).bind(this), null, { async: false });
        }
    };

    return PydioApi;
})();
