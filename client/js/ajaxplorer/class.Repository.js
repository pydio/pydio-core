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
 * Description : Abstraction of a Repository.
 */
Repository = Class.create({

	id:undefined,
	label:'No Repository',
	icon:ajxpResourcesFolder+'/images/crystal/actions/16/network-wired.png',
	searchEngine:'SearchEngine',
	resources:undefined,

	initialize:function(id, xmlDef){
		this.id = id;
		this.resources = {};
		if(xmlDef) this.loadFromXml(xmlDef);
	},
	
	getId : function(){
		return this.id;
	},
	
	getLabel : function(){
		return this.label;
	},
	setLabel : function(label){
		this.label = label;
	},
	
	getIcon : function(){
		return this.icon;
	},
	setIcon : function(icon){
		this.icon = icon;
	},
	
	getSearchEngine : function(){
		return this.searchEngine;
	},
	setSearchEngine : function(searchEngine){
		this.searchEngine = searchEngine;
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
	
	loadResources : function(){
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
	},
	
	loadJSResource : function(fileName, className){
		try{
			eval('window.testTemporaryObject = '+className);
			delete(window.testTemporaryObject);
		}catch(e){
			if(typeof(className)!='function' || typeof(className.prototype)!='object'){
				var conn = new Connexion();
				conn._libUrl = false;
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
	
	loadFromXml: function(repoNode){
		for(var i=0;i<repoNode.childNodes.length;i++){
			var childNode = repoNode.childNodes[i];
			if(childNode.nodeName == "label"){
				this.setLabel(childNode.firstChild.nodeValue);
			}else if(childNode.nodeName == "client_settings"){
				this.setIcon(childNode.getAttribute('icon'));
				this.setSearchEngine(childNode.getAttribute('search_engine'));
				for(var j=0; j<childNode.childNodes.length;j++){
					var subCh = childNode.childNodes[j];
					if(subCh.nodeName == 'resources'){
						for(var k=0;k<subCh.childNodes.length;k++){
							if(subCh.childNodes[k].nodeName == 'js'){
								this.addJSResource(subCh.childNodes[k].getAttribute('file'), subCh.childNodes[k].getAttribute('className'));
							}else if(subCh.childNodes[k].nodeName == 'css'){
								this.addCSSResource(subCh.childNodes[k].getAttribute('file'));
							}else if(subCh.childNodes[k].nodeName == 'img_library'){
								addImageLibrary(subCh.childNodes[k].getAttribute('alias'), subCh.childNodes[k].getAttribute('path'));
							}
						}
					}
				}
			}
		}			
	}
});
