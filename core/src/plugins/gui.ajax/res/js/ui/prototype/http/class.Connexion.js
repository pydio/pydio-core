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
 */
var PydioLog = $A();
/**
 * Pydio encapsulation of Ajax.Request
 */
Class.create("Connexion", {

    discrete : false,

	/**
	 * Constructor
	 * @param baseUrl String The base url for services
	 */
	initialize: function(baseUrl)
	{
		this._baseUrl = window.ajxpServerAccessPath;
		if(baseUrl) this._baseUrl = baseUrl;
		this._libUrl = window.ajxpResourcesFolder+'/js';
		this._parameters = new Hash();
		this._method = 'post';
	},
	
	/**
	 * Add a parameter to the query
	 * @param paramName String
	 * @param paramValue String
	 */
	addParameter : function (paramName, paramValue){
        if(this._parameters.get(paramName) && paramName.endsWith('[]')){
            var existing =  this._parameters.get(paramName);
            if(Object.isString(existing)) existing = [existing];
            existing.push(paramValue);
            this._parameters.set(paramName, existing);
        }else{
            this._parameters.set(paramName, paramValue);
        }
	},
	
	/**
	 * Sets the whole parameter as a bunch
	 * @param hParameters $H()
	 */
	setParameters : function(hParameters){
		this._parameters = $H(hParameters);
	},
	
	/**
	 * Set the query method (get post)
	 * @param method String
	 */
	setMethod : function(method){
		this._method = method;
	},
	
	/**
	 * Add the secure token parameter
	 */
	addSecureToken : function(){
		if(Connexion.SECURE_TOKEN && this._baseUrl.indexOf('secure_token') == -1 && !this._parameters.get('secure_token')){
			this.addParameter('secure_token', Connexion.SECURE_TOKEN);
		}else if(this._baseUrl.indexOf('secure_token=') !== -1){
            // Remove from baseUrl and set inside params
            var parts = this._baseUrl.split('secure_token=');
            var toks = parts[1].split('&');
            var token = toks.shift();
            var rest = toks.join('&');
            this._baseUrl = parts[0] + (rest ? '&' + rest : '');
            this._parameters.set('secure_token', token);
        }
	},

    /**
     * Show a small loader
     */
    showLoader : function(){
        if(this.discrete) return;
        if(!$('AjxpConnexion-loader') && window.ajxpBootstrap.parameters.get("theme")){
            var span = new Element("span", {
                id:'AjxpConnexion-loader',
                style:'position:absolute;top:2px;right:2px;z-index:40000;display:none;'});
            var img = new Element("img", {
                src:ajxpResourcesFolder+"/images/loadingImage.gif"
            });
            span.insert(img);
            $$('body')[0].insert(span);
        }
        if($('AjxpConnexion-loader')) $('AjxpConnexion-loader').show();
    },

    /**
     * Hide a small loader
     */
    hideLoader : function(){
        if(this.discrete) return;
        if($('AjxpConnexion-loader'))$('AjxpConnexion-loader').hide();
    },

	/**
	 * Send Asynchronously
	 */
	sendAsync : function(){
        PydioLog.push({
            action:this._parameters.get("get_action"),
            type:"async"
        });
		this.addSecureToken();
        this.showLoader();
		var t = new Ajax.Request(this._baseUrl,
		{
			method:this._method,
			onComplete:this.applyComplete.bind(this),
			parameters:this._parameters.toObject()
		});
        try {if(Prototype.Browser.IE10) t.transport.responseType =  'msxml-document'; } catch(e){}
    },
	
	/**
	 * Send synchronously
	 */
	sendSync : function(){
        PydioLog.push({
            action:this._parameters.get("get_action"),
            type:"sync"
        });
		this.addSecureToken();
        this.showLoader();
		new Ajax.Request(this._baseUrl,
		{
			method:this._method,
			asynchronous: false,
			onComplete:this.applyComplete.bind(this),
			parameters:this._parameters.toObject(),
            msxmldoctype: true
		});
    },
	
	/**
	 * Apply the complete callback, try to grab maximum of errors
	 * @param transport Transpot
	 */
	applyComplete : function(transport){
        this.hideLoader();
		var message;
        var tokenMessage;
        var tok1 = "Ooops, it seems that your security token has expired! Please %s by hitting refresh or F5 in your browser!";
        var tok2 =  "reload the page";
        if(window.MessageHash && window.MessageHash[437]){
            tok1 = window.MessageHash[437];
            tok2 = window.MessageHash[438];
        }
        tokenMessage = tok1.replace("%s", "<a href='javascript:document.location.reload()' style='text-decoration: underline;'>"+tok2+"</a>");

		var headers = transport.getAllResponseHeaders();
		if(Prototype.Browser.Gecko && transport.responseXML && transport.responseXML.documentElement && transport.responseXML.documentElement.nodeName=="parsererror"){
			message = "Parsing error : \n" + transport.responseXML.documentElement.firstChild.textContent;					
		}else if(Prototype.Browser.IE && transport.responseXML && transport.responseXML.parseError && transport.responseXML.parseError.errorCode != 0){
			message = "Parsing Error : \n" + transport.responseXML.parseError.reason;
		}else if(headers.indexOf("text/xml")>-1 && transport.responseXML == null){
			message = "Unknown Parsing Error!";
		}else if(headers.indexOf("text/xml") == -1 && headers.indexOf("application/json") == -1 && transport.responseText.indexOf("<b>Fatal error</b>") > -1){
			message = transport.responseText.replace("<br />", "");
		}else if(transport.status == 500){
            message = "Internal Server Error: you should check your web server logs to find what's going wrong!";
        }
		if(message){
            if(message.startsWith("You are not allowed to access this resource.")) message = tokenMessage;
			if(ajaxplorer) ajaxplorer.displayMessage("ERROR", message);
			else alert(message);
		}
		if(transport.responseXML && transport.responseXML.documentElement){
			var authNode = XPathSelectSingleNode(transport.responseXML.documentElement, "require_auth");
			if(authNode && ajaxplorer){
				var root = ajaxplorer._contextHolder.getRootNode();
				if(root){
					ajaxplorer._contextHolder.setContextNode(root);
					root.clear();
				}
				pydio.getController().fireAction('logout');
				pydio.getController().fireAction('login');
			}
			var messageNode = XPathSelectSingleNode(transport.responseXML.documentElement, "message");
			if(messageNode){
				var messageType = messageNode.getAttribute("type").toUpperCase();
				var messageContent = XMLUtils.getDomNodeText(messageNode);
                if(messageContent == "You are not allowed to access this resource.") messageContent = tokenMessage;
				if(ajaxplorer){
					ajaxplorer.displayMessage(messageType, messageContent);
				}else{
					if(messageType == "ERROR"){
						alert(messageType+":"+messageContent);
					}
				}
                if(messageType == "SUCCESS") messageNode.parentNode.removeChild(messageNode);
			}
		}
		if(this.onComplete){
			this.onComplete(transport);
		}
		document.fire("ajaxplorer:server_answer", this);
	},

    uploadFile: function(file, fileParameterName, queryStringParams, onComplete, onError, onProgress){

        if(!onComplete) onComplete = function(){};
        if(!onError) onError = function(){};
        if(!onProgress) onProgress = function(){};
        var url = pydio.Parameters.get('ajxpServerAccess') + '&' +  queryStringParams;
        var xhr = this.initializeXHRForUpload(url, onComplete, onError, onProgress);
        if(window.FormData){
            this.sendFileUsingFormData(xhr, file, fileParameterName);
        }else if(window.FileReader){
            var fileReader = new FileReader();
            fileReader.onload = function(e){
                this.xhrSendAsBinary(xhr, file.name, e.target.result, fileParameterName);
            }.bind(this);
            fileReader.readAsBinaryString(file);
        }else if(file.getAsBinary){
            this.xhrSendAsBinary(xhr, file.name, file.getAsBinary(), fileParameterName)
        }

    },

    initializeXHRForUpload : function(url, onComplete, onError, onProgress){
        var xhr = new XMLHttpRequest();
        var upload = xhr.upload;
        upload.addEventListener("progress", function(e){
            if (!e.lengthComputable) return;
            onProgress(e);
        }, false);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4) {
                if (xhr.status === 200) {
                    onComplete(xhr);
                } else {
                    onError(xhr);
                }
            }
        }.bind(this);
        upload.onerror = function(){
            onError(xhr);
        };
        xhr.open("POST", url, true);
        return xhr;
    },

    sendFileUsingFormData : function(xhr, file, fileParameterName){
        var formData = new FormData();
        formData.append(fileParameterName, file);
        xhr.send(formData);
    },

    xhrSendAsBinary : function(xhr, fileName, fileData, fileParameterName){
        var boundary = '----MultiPartFormBoundary' + (new Date()).getTime();
        xhr.setRequestHeader("Content-Type", "multipart/form-data, boundary="+boundary);

        var body = "--" + boundary + "\r\n";
        body += "Content-Disposition: form-data; name='"+fileParameterName+"'; filename='" + unescape(encodeURIComponent(fileName)) + "'\r\n";
        body += "Content-Type: application/octet-stream\r\n\r\n";
        body += fileData + "\r\n";
        body += "--" + boundary + "--\r\n";

        xhr.sendAsBinary(body);
    },

	/**
	 * Load a javascript library
	 * @param fileName String
	 * @param onLoadedCode Function Callback
     * @param aSync Boolean load library asynchroneously
	 */
	loadLibrary : function(fileName, onLoadedCode, aSync){
        if(window.ajxpBootstrap && window.ajxpBootstrap.parameters.get("ajxpVersion") && fileName.indexOf("?")==-1){
            fileName += "?v="+window.ajxpBootstrap.parameters.get("ajxpVersion");
        }
        var path = (this._libUrl?this._libUrl+'/'+fileName:fileName);
		new Ajax.Request(path,
		{
			method:'get',
			asynchronous: (aSync?true:false),
            evalJS: false,
			onComplete:function(transport){
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
				document.fire("ajaxplorer:server_answer");				
			}
		});	
	}
});

// ADD STATIC FUNCTIONS
Connexion.parseXmlMessage = function(xmlResponse){

    if(xmlResponse == null || xmlResponse.documentElement == null) return false;
    var childs = xmlResponse.documentElement.childNodes;

    var reloadNodes = [];
    var error = false;
    Connexion.LAST_ERROR_ID = null;

    for(var i=0; i<childs.length;i++)
    {
        if(childs[i].tagName == "message")
        {

            var messageTxt = "No message";
            if(childs[i].firstChild) messageTxt = childs[i].firstChild.nodeValue;
            pydio.displayMessage(childs[i].getAttribute('type'), messageTxt);
            if(childs[i].getAttribute('type') == 'ERROR') error = true;

        }else if(childs[i].tagName == "prompt"){

            var message = XPathSelectSingleNode(childs[i], "message").firstChild.nodeValue;
            var jsonData = XPathSelectSingleNode(childs[i], "data").firstChild.nodeValue;
            var json = jsonData.evalJSON();
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
                        pydio.getContextHolder().setPendingSelection(file);
                    }
                    reloadNodes.push(pydio.getContextNode());
                }
            }
            else if(obName == 'repository_list')
            {
                pydio.reloadRepositoriesList();
            }
        }
        else if(childs[i].nodeName == 'nodes_diff'){
            var dm = pydio.getContextHolder();
            var removes = XPathSelectNodes(childs[i], "remove/tree");
            var adds = XPathSelectNodes(childs[i], "add/tree");
            var updates = XPathSelectNodes(childs[i], "update/tree");
            if(removes && removes.length){
                removes.each(function(r){
                    var p = r.getAttribute("filename");
                    var fake = new AjxpNode(p);
                    var n = fake.findInArbo(dm.getRootNode(), undefined);
                    if(n){
                        n.getParent().removeChild(n);
                    }
                });
            }
            if(adds && adds.length && dm.getAjxpNodeProvider().parseAjxpNode){
                adds.each(function(tree){
                    var newNode = dm.getAjxpNodeProvider().parseAjxpNode(tree);
                    var parentFake = new AjxpNode(getRepName(newNode.getPath()));
                    var parent = parentFake.findInArbo(dm.getRootNode(), undefined);
                    if(!parent && getRepName(newNode.getPath()) == "") parent = dm.getRootNode();
                    if(parent){
                        parent.addChild(newNode);
                        if(dm.getContextNode() == parent && !window.currentLightBox){
                            dm.setSelectedNodes([newNode], {});
                        }
                    }
                });
            }
            if(updates && updates.length && dm.getAjxpNodeProvider().parseAjxpNode){
                updates.each(function(tree){
                    var newNode = dm.getAjxpNodeProvider().parseAjxpNode(tree);
                    var original = newNode.getMetadata().get("original_path");
                    var fake, n;
                    if(original && original != newNode.getPath()
                        && getRepName(original) != getRepName(newNode.getPath())){
                        // Node was really moved to another folder
                        fake = new AjxpNode(original);
                        n = fake.findInArbo(dm.getRootNode(), undefined);
                        if(n){
                            n.getParent().removeChild(n);
                        }
                        var parentFake = new AjxpNode(getRepName(newNode.getPath()));
                        var parent = parentFake.findInArbo(dm.getRootNode(), undefined);
                        if(!parent && getRepName(newNode.getPath()) == "") parent = dm.getRootNode();
                        if(parent){
                            newNode.getMetadata().set("original_path", undefined);
                            parent.addChild(newNode);
                        }
                    }else{
                        fake = new AjxpNode(original);
                        n = fake.findInArbo(dm.getRootNode(), undefined);
                        if(n){
                            newNode._isLoaded = n._isLoaded;
                            n.replaceBy(newNode, "override");
                            if(!window.currentLightBox) dm.setSelectedNodes([n], {});
                        }
                    }
                });
            }
        }
        else if(childs[i].tagName == "logging_result")
        {
            if(childs[i].getAttribute("secure_token")){
                Connexion.SECURE_TOKEN = childs[i].getAttribute("secure_token");
                var minisite_session = PydioApi.detectMinisiteSession(window.ajxpServerAccessPath);
                var parts = window.ajxpServerAccessPath.split("?secure_token");
                window.ajxpServerAccessPath = parts[0] + "?secure_token=" + Connexion.SECURE_TOKEN;
                if(minisite_session) window.ajxpServerAccessPath += "&minisite_session=" + minisite_session;

                ajxpBootstrap.parameters.set('ajxpServerAccess', window.ajxpServerAccessPath);
            }
            if($("generic_dialog_box") && $("generic_dialog_box").down(".ajxp_login_error")){
                $("generic_dialog_box").down(".ajxp_login_error").remove();
            }
            var result = childs[i].getAttribute('value');
            var errorId = false;
            if(result == '1')
            {
                try{
                    modal.setCloseValidation(null);
                    hideLightBox(true);
                    if(childs[i].getAttribute('remember_login') && childs[i].getAttribute('remember_pass')){
                        var login = childs[i].getAttribute('remember_login');
                        var pass = childs[i].getAttribute('remember_pass');
                        storeRememberData(login, pass);
                    }
                }catch(e){
                    if(console) console.log('Error after login, could prevent registry loading!', e);
                }
                pydio.loadXmlRegistry();
            }
            else if(result == '0' || result == '-1')
            {
                errorId = 285;
            }
            else if(result == '2')
            {
                pydio.loadXmlRegistry();
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
                Connexion.LAST_ERROR_ID = errorId;
                if($("generic_dialog_box") && $("generic_dialog_box").visible() && $("generic_dialog_box").down("div.dialogLegend")){
                    $("generic_dialog_box").down("div.dialogLegend").insert({bottom:'<div class="ajxp_login_error" style="background-color: #D33131;display: block;font-size: 9px;color: white;border-radius: 3px;padding: 2px 6px;">'+MessageHash[errorId]+'</div>'});
                    Effect.ErrorShake($("generic_dialog_box").down('.ajxp_login_error'));
                }else{
                    alert(MessageHash[errorId]);
                }
            }

        }else if(childs[i].tagName == "trigger_bg_action"){
            var name = childs[i].getAttribute("name");
            var messageId = childs[i].getAttribute("messageId");
            var parameters = {};
            for(var j=0;j<childs[i].childNodes.length;j++){
                var paramChild = childs[i].childNodes[j];
                if(paramChild.tagName == 'param'){
                    parameters[paramChild.getAttribute("name")] = paramChild.getAttribute("value");
                }
            }
            pydio.getController().getBackgroundTasksManager().queueAction(name, parameters, messageId);
            pydio.getController().getBackgroundTasksManager().next();
        }

    }
    if(reloadNodes.length){
        pydio.getContextHolder().multipleNodesReload(reloadNodes);
    }
    return !error;

};