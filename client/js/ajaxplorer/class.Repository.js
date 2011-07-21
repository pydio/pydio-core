/*
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
 */
/** 
 * Container for a Repository.
 */
Class.create("Repository", {

	/**
	 * @var String
	 */
	id:undefined,
	/**
	 * @var String
	 */
	label:'No Repository',
	/**
	 * @var String
	 */
	icon:'',
	/**
	 * @var String
	 */
	accessType:'',
	/**
	 * @var object
	 */
	nodeProviderDef: null,
	/**
	 * @var ResourcesManager
	 */
	resourcesManager:undefined,
	/**
	 * @var Boolean
	 */
	allowCrossRepositoryCopy:false,
	/**
	 * @var String
	 */
	slug:'',
    /**
     * @var String
     */
    owner:'',

	/**
	 * Constructor
	 * @param id String
	 * @param xmlDef XMLNode
	 */
	initialize:function(id, xmlDef){
		if(MessageHash){
			this.label = MessageHash[391];
		}
		this.id = id;
		this.icon = ajxpResourcesFolder+'/images/actions/16/network-wired.png';
		this.resourcesManager = new ResourcesManager();
		if(xmlDef) this.loadFromXml(xmlDef);
	},
	
	/**
	 * @returns String
	 */
	getId : function(){
		return this.id;
	},
	
	/**
	 * @returns String
	 */
	getLabel : function(){
		return this.label;
	},
	/**
	 * @param label String
	 */
	setLabel : function(label){
		this.label = label;
	},
	
	/**
	 * @returns String
	 */
	getIcon : function(){
		return this.icon;
	},
	/**
	 * @param label String
	 */
	setIcon : function(icon){
		this.icon = icon;
	},

    /**
     * @return String
     */
    getOwner : function(){
        return this.owner;
    },

	/**
	 * @returns String
	 */
	getAccessType : function(){
		return this.accessType;
	},
	/**
	 * @param label String
	 */
	setAccessType : function(access){
		this.accessType = access;
	},
	
	/**
	 * Triggers ResourcesManager.load
	 */
	loadResources : function(){
		this.resourcesManager.load();
	},
	
	/**
	 * @returns Object
	 */
	getNodeProviderDef : function(){
		return this.nodeProviderDef;
	},
	
	/**
	 * @param slug String
	 */
	setSlug : function(slug){
		this.slug = slug;
	},
	
	/**
	 * @returns String
	 */
	getSlug : function(){
		return this.slug;
	},
	
	
	/**
	 * Parses XML Node
	 * @param repoNode XMLNode
	 */
	loadFromXml: function(repoNode){
		if(repoNode.getAttribute('allowCrossRepositoryCopy') && repoNode.getAttribute('allowCrossRepositoryCopy') == "true"){
			this.allowCrossRepositoryCopy = true;
		}
		if(repoNode.getAttribute('access_type')){
			this.setAccessType(repoNode.getAttribute('access_type'));
		}
		if(repoNode.getAttribute('repositorySlug')){
			this.setSlug(repoNode.getAttribute('repositorySlug'));
		}
		if(repoNode.getAttribute('owner')){
			this.owner = repoNode.getAttribute('owner');
		}
		for(var i=0;i<repoNode.childNodes.length;i++){
			var childNode = repoNode.childNodes[i];
			if(childNode.nodeName == "label"){
				this.setLabel(childNode.firstChild.nodeValue);
			}else if(childNode.nodeName == "client_settings"){
				this.setIcon(childNode.getAttribute('icon'));
				for(var j=0; j<childNode.childNodes.length;j++){
					var subCh = childNode.childNodes[j];
					if(subCh.nodeName == 'resources'){
						this.resourcesManager.loadFromXmlNode(subCh);
					}else if(subCh.nodeName == 'node_provider'){
						var nodeProviderName = subCh.getAttribute("ajxpClass");
						var nodeProviderOptions = subCh.getAttribute("ajxpOptions").evalJSON();
						this.nodeProviderDef = {name:nodeProviderName, options:nodeProviderOptions};
					}
				}
			}
		}			
	}
});
