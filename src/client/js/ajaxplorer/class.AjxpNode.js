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
 * Description : Abstract container for data
 */
AjxpNode = Class.create({
	initialize : function(path, isLeaf, label, icon){
		this._path = path;
		this._isLeaf = isLeaf || false;
		this._label = label || '';
		this._icon = icon || '';
		this._children = $A([]);
	},
	setChildren : function(ajxpNodes){
		this._children = $A(ajxpDataNodes);
		this._children.each.invoke('setParent', this);
	},
	getChildren : function(){
		return this._children;
	},
	addChild : function(ajxpNode){
		this._children.push(ajxpNode);
		ajxpNode.setParent(this);
	},
	setMetadata : function(data){
		this._metadata = data;
	},
	getMetadata : function(data){
		return this._metadata;
	},
	isLeaf : function(){
		return this._isLeaf;
	},
	getPath : function(){
		return this._path;
	},
	getLabel : function(){
		return this._label;
	},
	getIcon : function(){
		return this._icon;
	},
	isRecycle : function(){
		return (this._metadata && this._metadata.getAttribute("is_recycle") && this._metadata.getAttribute("is_recycle") == "true");
	},
	inZip : function(){
		
	},
	setParent : function(parentNode){
		this._parentNode = parentNode;
	},
	isRoot : function(){
		return (this._parentNode?false:true);
	},
	getAjxpMime : function(){
		if(this.isRoot()) return "ajxp_root";
		if(this._metadata && this._metadata["ajxp_mime"]) return this._metadata["ajxp_mime"];		
		if(this._metadata && this.isLeaf()) return getAjxpMimeType(this._metadata);
		return "";
	}
});