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
		this._method = 'get';
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
				ajaxplorer.actionBar.fireAction('logout');
				ajaxplorer.actionBar.fireAction('login');
			}
			var messageNode = XPathSelectSingleNode(transport.responseXML.documentElement, "message");
			if(messageNode){
				var messageType = messageNode.getAttribute("type").toUpperCase();
				var messageContent = getDomNodeText(messageNode);
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