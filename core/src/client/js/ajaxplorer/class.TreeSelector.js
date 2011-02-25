/*
 * Copyright 2007-2011 Charles du Jeu
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
 * Independant widget for opening a form with a Tree in it
 */
Class.create("TreeSelector", {
	/**
	 * Constructor
	 * @param oElement HTMLElement
	 * @param options Object
	 */
	initialize : function(oElement, options){
		this.htmlElement = oElement;
		this.options = Object.extend({
			filterSelectorId : 'select[id="external_repository"]',
			targetField : 'input[name="dest"]',
			targetNode: 'input[name="dest_node"]',
			treeContainer : '.treeCopyContainer'
		}, options || {});
	},
	/**
	 * Load the tree data
	 * @param rootNode AjxpNode
	 */
	load : function(rootNode){
		if(webFXTreeHandler && webFXTreeHandler.selected){
			this.__initialWebFXSelection = webFXTreeHandler.selected;
		}
		this.filterSelector = this.htmlElement.select(this.options.filterSelectorId)[0];
		var target = this.targetField = this.htmlElement.select(this.options.targetField)[0];
		var targetNode = this.targetNode = this.htmlElement.select(this.options.targetNode)[0];
		this.treeContainer = this.htmlElement.select(this.options.treeContainer)[0];
		this.filterSelector.hide();
		this._nodeActionCallback = function(e){
			// Warning, this is the tree object
			target.value = this.ajxpNode.getPath();
			targetNode.value = this.ajxpNode.getPath();
 			this.select();			
		};
		this._nodeFilter = function(ajxpNode){
			return (!ajxpNode.isLeaf());
		};
		if(!rootNode){
			rootNode = new AjxpNode("/", false, MessageHash[373], "folder.png");
		}
		this.treeCopy = new AJXPTree(rootNode, this._nodeActionCallback, this._nodeFilter);							
		this.treeContainer.update(this.treeCopy.toString());
		$(this.treeCopy.id).observe("click", function(e){
			this.action();
			Event.stop(e);
		}.bind(this.treeCopy));
		this.treeCopy.focus();
		this.treeCopy.setAjxpRootNode(rootNode);
		
	},
	/**
	 * Clear the widget
	 */
	unload : function(){
		if(this.__initialWebFXSelection){
			this.__initialWebFXSelection.select();
		}
		this.treeCopy.remove();
	},
	/**
	 * Retrieve select node
	 * @returns String
	 */
	getSelectedNode : function(){
		return this.targetNode.getValue();
	},
	/**
	 * Retrieve selected label
	 * @returns String
	 */
	getSelectedLabel : function(){
		return this.target.getValue();
	},
	/**
	 * Whether to show / hide the filter <select> object
	 * @param bool Boolean
	 */
	setFilterShow : function(bool){
		if(bool) this.filterSelector.show();
		else this.filterSelector.hide();
	},
	/**
	 * Check whether the filter is active and its value is different from the original value
	 * @param refValue String Original value
	 * @returns Boolean
	 */
	getFilterActive : function(refValue){
		if(!this.filterSelector.visible()) return false;
		if(refValue && this.filterSelector.getValue() == refValue) return false;
		return true;
	},
	/**
	 * Add an option to the filter
	 * @param key String
	 * @param value String
	 * @param position String empty or "top"
	 */
	appendFilterValue : function(key, value, position){
		var newOption = new Element('option', {value:key}).update(value);
		var obj;
		if(position == 'top'){
			obj = {top : newOption};
		}else{
			obj = newOption;
		}
		this.filterSelector.insert(obj);
	},
	/**
	 * Sets an option as selected
	 * @param index Integer
	 */
	setFilterSelectedIndex : function(index){
		this.filterSelector.selectedIndex = index;
	},
	/**
	 * Add a changecallback to the filter
	 * @param func Function
	 */
	setFilterChangeCallback : function(func){
		this.filterSelector.observe("change", func.bind(this));
	},
	/**
	 * Reload the root node of the tree
	 * @param ajxpNode AjxpNode
	 */
	resetAjxpRootNode : function(ajxpNode){
		this.treeCopy.ajxpNode.clear();
		this.treeCopy.setAjxpRootNode(ajxpNode);		
		this.treeCopy.ajxpNode.load();
	}
});