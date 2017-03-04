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
class PydioApi{

    constructor(){
        this._secureToken = '';
    }

    setPydioObject(pydioObject){
        this._pydioObject = pydioObject;
        this._baseUrl = pydioObject.Parameters.get('serverAccessPath');
        this._secureToken = pydioObject.Parameters.get('SECURE_TOKEN');
    }

    setSecureToken(token){
        this._secureToken = token;
    }

    request(parameters, onComplete=null, onError=null, settings={}){
        // Connexion already handles secure_token
        var c = new Connexion();
        if(settings.discrete){
            c.discrete = true;
        }
        c.setParameters(parameters);
        if(settings.method){
            c.setMethod(settings.method);
        }
        c.onComplete = onComplete;
        if(settings.async === false){
            c.sendSync();
        }else{
            c.sendAsync();
        }
    }

    loadFile(filePath, onComplete=null, onError=null){
        var c = new Connexion(filePath);
        c.setMethod('GET');
        c.onComplete = onComplete;
        c.sendAsync();
    }

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
    uploadFile(file, fileParameterName, queryStringParams='', onComplete=function(){}, onError=function(){}, onProgress=function(){}, uploadUrl='', xhrSettings={}){

        if(!uploadUrl){
            uploadUrl = pydio.Parameters.get('ajxpServerAccess');
        }
        if(queryStringParams){
            uploadUrl += (uploadUrl.indexOf('?') === -1 ? '?' : '&') + queryStringParams;
        }

        if(window.Connexion){
            // Warning, avoid double error
            let errorSent = false;
            let localError = function(xhr){
                if(!errorSent) onError('Request failed with status :' + xhr.status);
                errorSent = true;
            };
            var c = new Connexion();
            return c.uploadFile(file, fileParameterName, uploadUrl, onComplete, localError, onProgress, xhrSettings);

        }else if(window.jQuery){

            var formData = new FormData();
            formData.append(fileParameterName, file);
            return jQuery.ajax(uploadUrl, {
                method:'POST',
                data:formData,
                complete:onComplete,
                error:onError,
                progress:onProgress
            });

        }

    }

    /**
     *
     * @param userSelection UserSelection A Pydio DataModel with selected files
     * @param prototypeHiddenForm Element A hidden form element: currently relying on PrototypeJS.
     * @param dlActionName String Action name to trigger, download by default.
     * @param additionalParameters Object Optional set of key/values to pass to the download.
     */
    downloadSelection(userSelection, dlActionName='download', additionalParameters = {}){

        var ajxpServerAccess = this._pydioObject.Parameters.get("ajxpServerAccess");
        var agent = navigator.userAgent || '';
        var agentIsMobile = (agent.indexOf('iPhone')!=-1||agent.indexOf('iPod')!=-1||agent.indexOf('iPad')!=-1||agent.indexOf('iOs')!=-1);
        const hiddenForm = pydio && pydio.UI && pydio.UI.hasHiddenDownloadForm();
        if(agentIsMobile || !hiddenForm){
            var downloadUrl = ajxpServerAccess + '&get_action=' + dlActionName;
            if(additionalParameters){
                for(var param in additionalParameters){
                    if(additionalParameters.hasOwnProperty(param)) downloadUrl += "&" + param + "=" + additionalParameters[param];
                }
            }
            if(userSelection){
                downloadUrl = userSelection.updateFormOrUrl(null,downloadUrl);
            }
            document.location.href=downloadUrl;
        }else{

            let parameters = {...additionalParameters, secure_token:this._secureToken, get_action: dlActionName};
            const minisite_session = PydioApi.detectMinisiteSession(ajxpServerAccess);
            if(minisite_session){
                parameters['minisite_session'] = minisite_session;
            }
            try{
                pydio.UI.sendDownloadToHiddenForm(userSelection, parameters);
            }catch(e){
                if(window.console) window.console.error("Error while submitting hidden form for download", e);
            }
        }

    }

    postPlainTextContent(filePath, content, finishedCallback){

        this.request({
            get_action:'put_content',
            file: filePath,
            content: content
        }, function(transport){
            var success = this.parseXmlMessage(transport.responseXML);
            finishedCallback(success);
        }.bind(this), function(){
            finishedCallback(false);
        });


    }

    /**
     * Detect a minisite_session parameter in the URL
     * @param serverAccess
     * @returns string|bool
     */
    static detectMinisiteSession(serverAccess){
        var regex = new RegExp('.*?[&\\?]' + 'minisite_session' + '=(.*?)&.*');
        var val = serverAccess.replace(regex, "$1");
        return ( val == serverAccess ? false : val );
    }

    /**
     * Detects if current browser supports HTML5 Upload.
     * @returns boolean
     */
    static supportsUpload(){
        if(window.Connexion){
            return (window.FormData || window.FileReader);
        }else if(window.jQuery){
            return window.FormData;
        }
        return false;
    }

    /**
     * Instanciate a PydioApi client if it's not already instanciated and return it.
     * @returns PydioApi
     */
    static getClient(){
        if(PydioApi._PydioClient) return PydioApi._PydioClient;
        var client = new PydioApi();
        PydioApi._PydioClient = client;
        return client;
    }

    /**
     * Load a javascript library
     * @param fileName String
     * @param onLoadedCode Function Callback
     * @param aSync Boolean load library asynchroneously
     */
    static loadLibrary(fileName, onLoadedCode, aSync){
        if(window.pydio && pydio.Parameters.get("ajxpVersion") && fileName.indexOf("?")==-1){
            fileName += "?v="+ pydio.Parameters.get("ajxpVersion");
        }
        PydioApi._libUrl = false;
        if(window.pydio && pydio.Parameters.get('SERVER_PREFIX_URI')){
            PydioApi._libUrl = pydio.Parameters.get('SERVER_PREFIX_URI');
        }

        var conn = new Connexion();
        conn._libUrl = false;
        if(pydio.Parameters.get('SERVER_PREFIX_URI')){
            conn._libUrl = pydio.Parameters.get('SERVER_PREFIX_URI');
        }
        conn.loadLibrary(fileName, onLoadedCode, aSync);


    }

    switchRepository(repositoryId, completeCallback){
        var params = {
            get_action: 'switch_repository',
            repository_id:repositoryId
        };
        this.request(params, completeCallback);
    }

    switchLanguage(lang, completeCallback){
        var params = {
            get_action: 'get_i18n_messages',
            lang:lang,
            format:'json'
        };
        this.request(params, completeCallback);
    }

    loadXmlRegistry(completeCallback, xPath=null){
        var params = {get_action:'get_xml_registry'};
        if(xPath) params[xPath] = xPath;
        this.request(params, completeCallback);
    }

    getBootConf(completeCallback){
        var params = {get_action:'get_boot_conf'};
        var cB = function(transport){
            if(transport.responseJSON && transport.responseJSON.SECURE_TOKEN){
                this._pydioObject.Parameters.set('SECURE_TOKEN', transport.responseJSON.SECURE_TOKEN);
                this.setSecureToken(transport.responseJSON.SECURE_TOKEN);
            }
            if(completeCallback) {
                completeCallback(transport);
            }
        }.bind(this);
        this.request(params, cB);
    }

    userSavePreference(prefName, prefValue){
        this.request({get_action:"save_user_pref", "pref_name_0":prefName, "pref_value_0":prefValue}, null, null, {discrete:true, method:'post'});
    }

    userSavePreferences(preferences, completeCallback){
        var params = {'get_action':'save_user_pref'};
        var i=0;
        preferences.forEach(function(value, key){
            params["pref_name_"+i] = key;
            params["pref_value_"+i] = value;
            i++;
        });
        this.request(params, completeCallback, null, {discrete:true, method:'post'});
    }

    userSavePassword(oldPass, newPass, seed, completeCallback){
        this.request({
            get_action:'save_user_pref',
            pref_name_0:"password",
            pref_value_0:newPass,
            crt:oldPass,
            pass_seed:seed
        }, completeCallback, null, {discrete:true, method:'post'});

    }

    buildUserAvatarUrl(userId, avatarId = null){

        if(avatarId){
            return this._pydioObject.Parameters.get('ajxpServerAccess')
                + "&get_action=get_binary_param&binary_id="
                + avatarId + "&user_id=" + userId;
        }else{
            return null;
        }

    }

    applyCheckHook(node, hookName, hookArg, completeCallback, additionalParams){
        var params = {
            get_action : "apply_check_hook",
            file       : node.getPath(),
            hook_name  : hookName,
            hook_arg   : hookArg
        };
        if(additionalParams){
            params = LangUtils.objectMerge(params, additionalParams);
        }
        this.request(params, completeCallback, null, {async:false});
    }

    /**
     * Standard parser for server XML answers
     * @param xmlResponse DOMDocument
     */
    parseXmlMessage(xmlResponse)
    {
        if(xmlResponse == null || xmlResponse.documentElement == null) return null;
        var childs = xmlResponse.documentElement.childNodes;

        var reloadNodes = [];
        var error = false;
        this.LAST_ERROR_ID = null;

        for(var i=0; i<childs.length;i++)
        {
            if(childs[i].tagName === "message")
            {
                var messageTxt = "No message";
                if(childs[i].firstChild) messageTxt = childs[i].firstChild.nodeValue;
                if(childs[i].getAttribute('type') == 'ERROR') {
                    Logger.error(messageTxt);
                    error = true;
                }else{
                    Logger.log(messageTxt);
                }

            }else if(childs[i].tagName === "prompt"){

                if(pydio && pydio.UI && pydio.UI.openPromptDialog){
                    let jsonData = XMLUtils.XPathSelectSingleNode(childs[i], "data").firstChild.nodeValue;
                    pydio.UI.openPromptDialog(JSON.parse(jsonData));
                }
                // Interrupt further process
                throw new Error();

            }
            else if(childs[i].tagName == "reload_instruction")
            {
                var obName = childs[i].getAttribute('object');
                if(obName == 'data')
                {
                    var node = childs[i].getAttribute('node');
                    if(node){
                        reloadNodes.push(node);
                    }else{
                        var file = childs[i].getAttribute('file');
                        if(file){
                            this._pydioObject.getContextHolder().setPendingSelection(file);
                        }
                        reloadNodes.push(this._pydioObject.getContextNode());
                    }
                }
                else if(obName == 'repository_list')
                {
                    this._pydioObject.reloadRepositoriesList();
                }
            } else if(childs[i].nodeName == 'nodes_diff') {
                var dm = this._pydioObject.getContextHolder();
                if(dm.getAjxpNodeProvider().parseAjxpNodesDiffs){
                    dm.getAjxpNodeProvider().parseAjxpNodesDiffs(childs[i], dm, this._pydioObject.user.activeRepository, !window.currentLightBox);
                }
            }
            else if(childs[i].tagName == "logging_result")
            {
                if(childs[i].getAttribute("secure_token")){

                    var serverAccessPath = this._pydioObject.Parameters.get("ajxpServerAccess");
                    var minisite_session = PydioApi.detectMinisiteSession(serverAccessPath);

                    var secure_token = childs[i].getAttribute("secure_token");

                    var parts = serverAccessPath.split("?secure_token");
                    serverAccessPath = parts[0] + "?secure_token=" + secure_token;
                    if(minisite_session) serverAccessPath += "&minisite_session=" + minisite_session;

                    this.setSecureToken(secure_token);
                    this._pydioObject.Parameters.set("SECURE_TOKEN", secure_token);
                    // BACKWARD COMPAT
                    window.ajxpServerAccessPath = serverAccessPath;
                    this._pydioObject.Parameters.set("ajxpServerAccess", serverAccessPath);
                    if(window.pydioBootstrap && pydioBootstrap.parameters){
                        pydioBootstrap.parameters.set("ajxpServerAccess", serverAccessPath);
                        pydioBootstrap.parameters.set("SECURE_TOKEN", secure_token);
                    }
                    if(window.Connexion) Connexion.SECURE_TOKEN = secure_token;
                }
                var result = childs[i].getAttribute('value');
                var errorId = false;
                if(result == '1')
                {
                    try{
                        if(childs[i].getAttribute('remember_login') && childs[i].getAttribute('remember_pass')){
                            PydioApi.storeRememberData();
                        }
                    }catch(e){
                        Logger.error('Error after login, could prevent registry loading!', e);
                    }
                    this._pydioObject.loadXmlRegistry();
                }
                else if(result == '0' || result == '-1')
                {
                    errorId = 285;
                }
                else if(result == '2')
                {
                    this._pydioObject.loadXmlRegistry();
                }
                else if(result == '-2')
                {
                    errorId = 285;
                }
                else if(result == '-3')
                {
                    errorId = 366;
                }
                else if(result == '-4')
                {
                    errorId = 386;
                }
                if(errorId){
                    error = true;
                    this.LAST_ERROR_ID = errorId;
                    Logger.error(this._pydioObject.MessageHash[errorId]);
                }

            }else if(childs[i].tagName == "trigger_bg_action"){
                var name = childs[i].getAttribute("name");
                var messageId = childs[i].getAttribute("messageId");
                var parameters = {};
                for(var j=0;j<childs[i].childNodes.length;j++){
                    var paramChild = childs[i].childNodes[j];
                    if(paramChild.tagName == 'param'){
                        parameters[paramChild.getAttribute("name")] = paramChild.getAttribute("value");
                    }else if(paramChild.tagName == 'clientCallback' && paramChild.firstChild && paramChild.firstChild.nodeValue){
                        var callbackCode = paramChild.firstChild.nodeValue;
                        var callback = new Function(callbackCode);
                    }
                }
                if(name == "javascript_instruction" && callback){
                    callback();
                }
            }

        }
        this._pydioObject.notify("response.xml", xmlResponse);
        if(reloadNodes.length){
            this._pydioObject.getContextHolder().multipleNodesReload(reloadNodes);
        }
        return !error;
    }

    /**
     * Submits a form using Connexion class.
     * @param formName String The id of the form
     * @param post Boolean Whether to POST or GET
     * @param completeCallback Function Callback to be called on complete
     */
    submitForm(formName, post = true, completeCallback = null)
    {
        var params = {};
        // TODO: UI IMPLEMENTATION
        $(formName).getElements().each(function(fElement){
            var fValue = fElement.getValue();
            if(fElement.name == 'get_action' && fValue.substr(0,4) == 'http'){
                fValue = PathUtils.getBasename(fValue);
            }
            if(fElement.type == 'radio' && !fElement.checked) return;
            if(params[fElement.name] && fElement.name.endsWith('[]')){
                var existing = params[fElement.name];
                if(typeof(existing) == 'string') existing = [existing];
                existing.push(fValue);
                params[fElement.name] = existing;
            }else{
                params[fElement.name] = fValue;
            }
        });
        if(this._pydioObject.getContextNode()){
            params['dir'] = this._pydioObject.getContextNode().getPath();
        }
        var onComplete;
        if(completeCallback){
            onComplete = completeCallback;
        }else{
            onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this) ;
        }
        this.request(params, onComplete, null, {method:post?'post':'get'});
    }

    postSelectionWithAction(actionName, callback=null, selectionModel=null, additionalParameters=null){
        if(!selectionModel){
            selectionModel = this._pydioObject.getContextHolder();
        }
        let params = {
            get_action:actionName,
            dir: selectionModel.getContextNode().getPath()
        };
        params['nodes[]'] = selectionModel.getFileNames();
        if(additionalParameters){
            params = Object.assign(params, additionalParameters);
        }
        this.request(params, callback || FuncUtils.Empty);

    }

    /**
     * Trigger a simple download
     * @param url String
     */
    static triggerDownload(url){
        document.location.href = url;
    }

    static storeRememberData(){
        if(!CookiesManager.supported()) return false;
        var cManager = new CookiesManager({
            expires: 3600*24*10,
            path:'/',
            secure: true
        });
        cManager.putCookie('remember', 'true');
    }

    static clearRememberData(){
        if(!CookiesManager.supported()) return false;
        var cManager = new CookiesManager({
            path:'/',
            secure: true
        });
        return cManager.removeCookie('remember');
    }

    static hasRememberData(){
        if(!CookiesManager.supported()) return false;
        var cManager = new CookiesManager({
            path:'/',
            secure: true
        });
        return (cManager.getCookie('remember') === 'true');
    }

    tryToLogUserFromRememberData(){
        if(!CookiesManager.supported()) return false;
        if(PydioApi.hasRememberData()){
            this.request({
                get_action:'login',
                userid:'notify',
                password:'notify',
                cookie_login:'true'
            }, function(transport){
                this.parseXmlMessage(transport.responseXML);
            }.bind(this), null, {async:false});
        }
    }

}