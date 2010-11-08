Class.create("TreeSelector", {
	initialize : function(oElement, options){
		this.htmlElement = oElement;
		this.options = Object.extend({
			filterSelectorId : 'select[id="external_repository"]',
			targetField : 'input[name="dest"]',
			targetNode: 'input[name="dest_node"]',
			treeContainer : '.treeCopyContainer'
		}, options || {});
	},
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
	unload : function(){
		if(this.__initialWebFXSelection){
			this.__initialWebFXSelection.select();
		}
		this.treeCopy.remove();
	},
	getSelectedNode : function(){
		return this.targetNode.getValue();
	},
	getSelectedLabel : function(){
		return this.target.getValue();
	},
	setFilterShow : function(bool){
		if(bool) this.filterSelector.show();
		else this.filterSelector.hide();
	},
	getFilterActive : function(refValue){
		if(!this.filterSelector.visible()) return false;
		if(refValue && this.filterSelector.getValue() == refValue) return false;
		return true;
	},
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
	setFilterSelectedIndex : function(index){
		this.filterSelector.selectedIndex = index;
	},
	setFilterChangeCallback : function(func){
		this.filterSelector.observe("change", func.bind(this));
	},
	resetAjxpRootNode : function(ajxpNode){
		this.treeCopy.ajxpNode.clear();
		this.treeCopy.setAjxpRootNode(ajxpNode);		
	}
});