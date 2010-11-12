/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : AjaXplorer encapsulation of Ajax.Request
 */
Class.create("Connexion", {

	initialize: function(baseUrl)
	{
		this._baseUrl = window.ajxpServerAccessPath;
		if(baseUrl) this._baseUrl = baseUrl;
		this._libUrl = window.ajxpResourcesFolder+'/js';
		this._parameters = new Hash();
		this._method = 'get';
	},
	
	addParameter : function (paramName, paramValue){
		this._parameters.set(paramName, paramValue);	
	},
	
	setParameters : function(hParameters){
		this._parameters = $H(hParameters);
	},
	
	setMethod : function(method){
		this._method = 'put';
	},
	
	sendAsync : function(){	
		// WARNING, FINALLY PAS PAREMETERS AS AN OBJECT, PROTOTYPE 1.6.0 BUG Hash.toQueryString();
		new Ajax.Request(this._baseUrl, 
		{
			method:this._method,
			onComplete:this.applyComplete.bind(this),
			parameters:this._parameters.toObject()
		});
	},
	
	sendSync : function(){	
		// WARNING, FINALLY PAS PAREMETERS AS AN OBJECT, PROTOTYPE 1.6.0 BUG Hash.toQueryString();
		new Ajax.Request(this._baseUrl, 
		{
			method:this._method,
			asynchronous: false,
			onComplete:this.applyComplete.bind(this),
			parameters:this._parameters.toObject()
		});
	},
	
	applyComplete : function(transport){
		var message;
		var headers = transport.getAllResponseHeaders();
		if(Prototype.Browser.Gecko && transport.responseXML && transport.responseXML.documentElement && transport.responseXML.documentElement.nodeName=="parsererror"){
			message = "Parsing error : \n" + transport.responseXML.documentElement.firstChild.textContent;					
		}else if(Prototype.Browser.IE && transport.responseXML.parseError && transport.responseXML.parseError.errorCode != 0){
			message = "Parsing Error : \n" + transport.responseXML.parseError.reason;
		}else if(headers.indexOf("text/xml")>-1 && transport.responseXML == null){
			message = "Unknown Parsing Error!";
		}else if(headers.indexOf("text/xml") == -1 && headers.indexOf("application/json") == -1 && transport.responseText.indexOf("<b>Fatal error</b>") > -1){
			message = transport.responseText.replace("<br />", "");
		}
		if(message){
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
				messageType = messageNode.getAttribute("type").toUpperCase();
				messageContent = getDomNodeText(messageNode);
				if(ajaxplorer){
					ajaxplorer.displayMessage(messageType, messageContent);
				}else{
					if(messageType == "ERROR"){
						alert(messageType+":"+messageContent);
					}
				}
			}
		}
		if(this.onComplete){
			this.onComplete(transport);
		}
		document.fire("ajaxplorer:server_answer");
	},
	
	loadLibrary : function(fileName, onLoadedCode){
		var path = (this._libUrl?this._libUrl+'/'+fileName:fileName);
		new Ajax.Request(path, 
		{
			method:'get',
			asynchronous: false,
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
							document.getElementsByTagName('head')[0].appendChild(script_tag)
					    }
						if(onLoadedCode != null) onLoadedCode();
					}
					catch(e)
					{
						alert('error loading '+fileName+':'+e);
					}
				}
				document.fire("ajaxplorer:server_answer");				
			}
		});	
	}
});