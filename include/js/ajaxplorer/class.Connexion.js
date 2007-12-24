Connexion = Class.create({

	initialize: function(baseUrl)
	{
		this._baseUrl = 'content.php';
		if(baseUrl) this._baseUrl = baseUrl;
		this._libUrl = 'include/js';
		this._parameters = new Hash();
		this._method = 'get';
		this.addParameter('get_action', 'xml_listing');
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
			onComplete:this.onComplete,
			parameters:this._parameters.toObject()
		});
	},
	
	sendSync : function(){	
		// WARNING, FINALLY PAS PAREMETERS AS AN OBJECT, PROTOTYPE 1.6.0 BUG Hash.toQueryString();
		new Ajax.Request(this._baseUrl, 
		{
			method:this._method,
			asynchronous: false,
			onComplete:this.onComplete,
			parameters:this._parameters.toObject()
		});
	},
	
	loadLibrary : function(fileName, onLoadedCode){
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
							//window.eval( script );
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
						//errorWindow=window.open("", "", "height=500, width=600,toolbar=no,scrollbars=yes,menubar=no,resizable=yes");
						//errorWindow.document.write(transport.responseText);
					}
				}
			}
		});	
	},
	
	loadLibraries : function(){
		if(!dynamicLibLoading) {return;}
		var toLoad = $A([
			"lib/webfx/slider/js/timer.js", 
			"lib/webfx/slider/js/range.js", 
			"lib/webfx/slider/js/slider.js", 
			"lib/leightbox/lightbox.js", 
			"lib/jquery/dimensions.js", 
			"lib/jquery/splitter.js", 
			"lib/ufo/ufo.js",
			"lib/prototype/proto.menu.js",
			"lib/codepress/codepress.js",
			"lib/webfx/selectableelements.js", 
			"lib/webfx/selectabletablerows.js", 
			"lib/webfx/sortabletable.js", 
			"lib/webfx/numberksorttype.js", 
			"lib/webfx/slider/js/timer.js", 
			"lib/webfx/slider/js/range.js", 
			"lib/webfx/slider/js/slider.js", 
			"lib/xloadtree/xtree.js", 
			"lib/xloadtree/xloadtree.js", 
			"lib/xloadtree/xmlextras.js",
			"ajaxplorer/ajxp_multifile.js", 
			"ajaxplorer/ajxp_utils.js", 
			"ajaxplorer/class.User.js", 
			"ajaxplorer/class.AjxpDraggable.js",
			"ajaxplorer/class.AjxpAutoCompleter.js",
			"ajaxplorer/class.Diaporama.js",
			"ajaxplorer/class.Editor.js",
			"ajaxplorer/class.ActionsManager.js", 
			"ajaxplorer/class.FilesList.js", 
			"ajaxplorer/class.FoldersTree.js", 
			"ajaxplorer/class.SearchEngine.js", 
			"ajaxplorer/class.InfoPanel.js", 
			"ajaxplorer/class.ResizeableBar.js", 
			"ajaxplorer/class.UserSelection.js"]);
			
		modal.incrementStepCounts(toLoad.size());
		toLoad.each(function(fileName){
			var onLoad = function(){modal.updateLoadingProgress(fileName);};
			if(fileName == toLoad.last()) onLoad = function(){modal.updateLoadingProgress(fileName);};
			this.loadLibrary(fileName, onLoad);
		}.bind(this));
	}
});