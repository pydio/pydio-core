Class.create("ResourcesManager", {
	initialize : function(){
		this.mainFormContainerId = 'all_forms';
		this.resources = {};
		this.loaded = false;
	},	
	
	addJSResource : function(fileName, className){
		if(!this.resources.js){
			this.resources.js = [];
		}
		this.resources.js.push({fileName:fileName,className:className});
	},
	
	addCSSResource : function(fileName){
		if(!this.resources.css){
			this.resources.css = [];
		}
		this.resources.css.push(fileName);
	},
	
	addGuiForm : function(formId, htmlSnippet){
		if(!this.resources.forms){
			this.resources.forms = {};
		}
		this.resources.forms[formId] = htmlSnippet;
	},
	
	addDependency : function(data){
		if(!this.resources.dependencies){
			this.resources.dependencies = [];
		}
		this.resources.dependencies.push(data);
	},
	
	hasDependencies : function(){
		return (this.resources.dependencies || false);
	},
	
	load : function(resourcesRegistry){
		if(this.loaded) return;
		if(this.hasDependencies()){
			this.resources.dependencies.each(function(el){
				if(resourcesRegistry[el]){
					resourcesRegistry[el].load(resourcesRegistry);
				}
			}.bind(this) );
		}		
		if(this.resources.forms){
			$H(this.resources.forms).each(function(pair){
				this.loadGuiForm(pair.key, pair.value);
			}.bind(this) );
		}
		if(this.resources.js){
			this.resources.js.each(function(value){
				this.loadJSResource(value.fileName, value.className);
			}.bind(this));
		}
		if(this.resources.css){
			this.resources.css.each(function(value){
				this.loadCSSResource(value);
			}.bind(this));
		}
		this.loaded = true;
	},
	
	loadJSResource : function(fileName, className){
		try{
			eval('window.testTemporaryObject = '+className);
			delete(window.testTemporaryObject);
		}catch(e){
			if(typeof(className)!='function' || typeof(className.prototype)!='object'){
				var conn = new Connexion();
				conn._libUrl = false;
				if(ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')){
					conn._libUrl = ajxpBootstrap.parameters.get('SERVER_PREFIX_URI');
				}
				conn.loadLibrary(fileName);
			}
		}
	},
	
	loadCSSResource : function(fileName){
		var head = $$('head')[0];
		var cssNode = new Element('link', {
			type : 'text/css',
			rel  : 'stylesheet',
			href : fileName,
			media : 'screen'
		});
		head.insert(cssNode);
	},

	loadGuiForm : function(formId, htmlSnippet){
		if(!$(this.mainFormContainerId).select('[id="'+formId+'"]').length){
			htmlSnippet.evalScripts();
			$(this.mainFormContainerId).insert(htmlSnippet.stripScripts());
		}
	},
	
	loadFromXmlNode : function(node){
		if(node.nodeName == "resources"){
			for(var k=0;k<node.childNodes.length;k++){
				if(node.childNodes[k].nodeName == 'js'){
					this.addJSResource(node.childNodes[k].getAttribute('file'), node.childNodes[k].getAttribute('className'));
				}else if(node.childNodes[k].nodeName == 'css'){
					this.addCSSResource(node.childNodes[k].getAttribute('file'));
				}else if(node.childNodes[k].nodeName == 'img_library'){
					addImageLibrary(node.childNodes[k].getAttribute('alias'), node.childNodes[k].getAttribute('path'));
				}
			}		
		}else if(node.nodeName == "dependencies"){
			for(var k=0;k<node.childNodes.length;k++){
				if(node.childNodes[k].nodeName == "pluginResources"){
					this.addDependency(node.childNodes[k].getAttribute("pluginName"));
				}
			}
		}else if(node.nodeName == "clientForm"){
			this.addGuiForm(node.getAttribute("id"), node.firstChild.nodeValue);
		}

	},
	
	loadAutoLoadResources : function(registry){
		var jsNodes = XPathSelectNodes(registry, '//client_settings/resources/js[@autoload="true"]');
		if(jsNodes.length){
			jsNodes.each(function(node){
				this.loadJSResource(node.getAttribute('file'), node.getAttribute('className'));
			}.bind(this));
		}
		var imgNodes = XPathSelectNodes(registry, '//client_settings/resources/img_library');
		imgNodes.each(function(node){
			addImageLibrary(node.getAttribute('alias'), node.getAttribute('path'));
		}.bind(this));		
	}
});