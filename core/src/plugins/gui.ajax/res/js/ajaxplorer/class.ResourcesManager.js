/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
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

/**
 * A manager that can handle the loading of JS, CSS and checks dependencies
 */
Class.create("ResourcesManager", {
	/**
	 * Constructor
	 */
	initialize : function(){
		this.mainFormContainerId = 'all_forms';
		this.resources = {};
		this.loaded = false;
	},	
	/**
	 * Adds a Javascript resource
	 * @param fileName String
	 * @param className String
	 */
	addJSResource : function(fileName, className){
		if(!this.resources.js){
			this.resources.js = [];
		}
		this.resources.js.push({fileName:fileName,className:className});
	},
	/**
	 * Adds a CSS resource
	 * @param fileName String
	 */
	addCSSResource : function(fileName){
		if(!this.resources.css){
			this.resources.css = [];
		}
		this.resources.css.push(fileName);
	},
	/**
	 * Adds a FORM from html snipper
	 * @param formId String
	 * @param htmlSnippet String
	 */
	addGuiForm : function(formId, htmlSnippet){
		if(!this.resources.forms){
			this.resources.forms = {};
		}
		this.resources.forms[formId] = htmlSnippet;
	},
	/**
	 * Add a dependency to another plugin
	 * @param data Object
	 */
	addDependency : function(data){
		if(!this.resources.dependencies){
			this.resources.dependencies = [];
		}
		this.resources.dependencies.push(data);
	},
	/**
	 * Check if some dependencies must be loaded before
	 * @returns Boolean
	 */
	hasDependencies : function(){
		return (this.resources.dependencies || false);
	},
	/**
	 * Load resources
	 * @param resourcesRegistry $H Ajaxplorer ressources registry
	 */
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
	/**
	 * Load a javascript file
	 * @param fileName String
	 * @param className String
	 */
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
	/**
	 * Load a CSS file
	 * @param fileName String
	 */
	loadCSSResource : function(fileName){
		var head = $$('head')[0];
        if(ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')){
            fileName = ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')+fileName;
        }
		var cssNode = new Element('link', {
			type : 'text/css',
			rel  : 'stylesheet',
			href : fileName+"?v="+window.ajxpBootstrap.parameters.get("ajxpVersion"),
			media : 'screen'
		});
		head.insert(cssNode);
	},
	/**
	 * Insert the HTML snipper and evaluate scripts
	 * @param formId String
	 * @param htmlSnippet String
	 */
	loadGuiForm : function(formId, htmlSnippet){
		if(!$(this.mainFormContainerId).select('[id="'+formId+'"]').length){
			htmlSnippet.evalScripts();
			$(this.mainFormContainerId).insert(htmlSnippet.stripScripts());
		}
	},
	/**
	 * Load the resources from XML
	 * @param node XMLNode
	 */
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
	/**
	 * Check if resources are tagged autoload and load them
	 * @param registry DOMDocument XML Registry
	 */
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