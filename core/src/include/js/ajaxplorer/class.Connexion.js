function Connexion(baseUrl)
{
	this._baseUrl = 'content.php';
	if(baseUrl) this._baseUrl = baseUrl;
	this._libUrl = 'include/js';
	this._parameters = new Hash();
	this._method = 'get';
	this.addParameter('get_action', 'xml_listing');
}

Connexion.prototype.addParameter = function (paramName, paramValue)
{
	this._parameters[paramName] = paramValue;	
}

Connexion.prototype.setParameters = function(hParameters)
{
	this._parameters = hParameters;
}

Connexion.prototype.setMethod = function(method)
{
	this._method = 'put';
}

Connexion.prototype.sendAsync = function()
{	
	var oThis = this;
	new Ajax.Request(this._baseUrl, 
	{
		method:this._method,
		onComplete:this.onComplete,
		parameters:this._parameters
	});
}

Connexion.prototype.loadLibrary = function(fileName, onLoadedCode)
{
	var oThis = this;
	new Ajax.Request(this._libUrl+'/'+fileName, 
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
				    	//alert('execScript for'+fileName);
				        window.execScript( script );
				    }
				    else{
				    	//alert('eval for'+fileName);
						window.eval( script );
						/* TO TEST, THIS SEEM TO WORK ON SAFARI
						window.my_code = script;
						var script_tag = document.createElement('script');
						script_tag.type = 'text/javascript';
						script_tag.innerHTML = 'eval(window.my_code)';
						document.getElementsByTagName('head')[0].appendChild(script_tag)
						*/
				    }
					if(onLoadedCode != null) window.setTimeout(onLoadedCode, 1);
				}
				catch(e)
				{
					alert('error loading '+fileName+':'+e);
					//errorWindow=window.open("", "", "height=500, width=600,toolbar=no,scrollbars=yes,menubar=no,resizable=yes");
					//errorWindow.document.write(transport.responseText);
				}
			}
		}
	});	
}