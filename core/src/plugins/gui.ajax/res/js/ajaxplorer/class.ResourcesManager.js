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
     * @param callback Function
     * @param aSync Boolean
	 */
	loadJSResource : function(fileName, className, callback, aSync){
		if(!window[className]){
			if(typeof(className)!='function' || typeof(className.prototype)!='object'){
				var conn = new Connexion();
				conn._libUrl = false;
				if(ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')){
					conn._libUrl = ajxpBootstrap.parameters.get('SERVER_PREFIX_URI');
				}
				conn.loadLibrary(fileName, callback, aSync);
			}
		}else if(callback){
            callback();
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
        fileName = fileName+"?v="+window.ajxpBootstrap.parameters.get("ajxpVersion");
        var select = head.down('[href="'+fileName+'"]');
        if(!select){
            var cssNode = new Element('link', {
                type : 'text/css',
                rel  : 'stylesheet',
                href : fileName,
                media : 'screen'
            });
            head.insert(cssNode);
        }
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
        var clForm = {};
        var k;
		if(node.nodeName == "resources"){
			for(k=0;k<node.childNodes.length;k++){
				if(node.childNodes[k].nodeName == 'js'){
					this.addJSResource(node.childNodes[k].getAttribute('file'), node.childNodes[k].getAttribute('className'));
				}else if(node.childNodes[k].nodeName == 'css'){
					this.addCSSResource(node.childNodes[k].getAttribute('file'));
				}else if(node.childNodes[k].nodeName == 'img_library'){
					addImageLibrary(node.childNodes[k].getAttribute('alias'), node.childNodes[k].getAttribute('path'));
				}
			}		
		}else if(node.nodeName == "dependencies"){
			for(k=0;k<node.childNodes.length;k++){
				if(node.childNodes[k].nodeName == "pluginResources"){
					this.addDependency(node.childNodes[k].getAttribute("pluginName"));
				}
			}
		}else if(node.nodeName == "clientForm"){
            if(!node.getAttribute("theme") || node.getAttribute("theme") == ajxpBootstrap.parameters.get("theme")){
                clForm = {formId:node.getAttribute("id"), formCode:node.firstChild.nodeValue};
            }
		}
        if(clForm.formId){
            this.addGuiForm(clForm.formId, clForm.formCode);
        }
	},
	/**
	 * Check if resources are tagged autoload and load them
	 * @param registry DOMDocument XML Registry
	 */
	loadAutoLoadResources : function(registry){
		var jsNodes = XPathSelectNodes(registry, 'plugins/*/client_settings/resources/js[@autoload="true"]');
		if(jsNodes.length){
			jsNodes.each(function(node){
				this.loadJSResource(node.getAttribute('file'), node.getAttribute('className'));
			}.bind(this));
		}
		var imgNodes = XPathSelectNodes(registry, 'plugins/*/client_settings/resources/img_library');
		imgNodes.each(function(node){
			addImageLibrary(node.getAttribute('alias'), node.getAttribute('path'));
		}.bind(this));		
		var cssNodes = XPathSelectNodes(registry, 'plugins/*/client_settings/resources/css[@autoload="true"]');
		cssNodes.each(function(node){
			this.loadCSSResource(node.getAttribute("file"));
		}.bind(this));
	}
});