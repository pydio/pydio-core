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
 * Description : Selection Model.
 */
Class.create("UserSelection", {

	_currentRep: undefined, 
	_selectedItems: undefined,
	_bEmpty: undefined,
	_bUnique: false,
	_bFile: false,
	_bDir: false,
	_isRecycle: false,
	
	_pendingContextPath:null, 
	_pendingSelection:null,
	_selectionSource : {}, // fake object
	
	_rootNode : null,


	initialize: function(){
		this._currentRep = '/';
		this._selectedItems = $A([]);
		this._bEmpty = true;
	},
	
	setRootNode : function(ajxpRootNode){
		this._rootNode = ajxpRootNode;
		document.fire("ajaxplorer:root_node_changed", this._rootNode);
	},
	
	getRootNode : function(ajxpRootNode){
		return this._rootNode;
	},
	
	setContextNode : function(ajxpDataNode){
		this._contextNode = ajxpDataNode;
		this._currentRep = ajxpDataNode.getPath();
		document.fire("ajaxplorer:context_changed", this);
	},
	
	getContextNode : function(){
		return this._contextNode;
	},
	
	setPendingSelection : function(selection){
		this._pendingSelection = selection;
	},
	
	getPendingSelection : function(){
		return this._pendingSelection;
	},
	
	clearPendingSelection : function(){
		this._pendingSelection = null;
	},
	
	setSelectedNodes : function(ajxpDataNodes, source){
		if(!source){
			this._selectionSource = {};
		}else{
			this._selectionSource = source;
		}
		this._selectedNodes = ajxpDataNodes;
		this._bEmpty = ((ajxpDataNodes && ajxpDataNodes.length)?false:true);
		this._selectedItems = $A([]);
		this._bFile = this._bDir = false;
		if(!this._bEmpty)
		{
			this._bUnique = ((ajxpDataNodes.length == 1)?true:false);
			for(var i=0; i<ajxpDataNodes.length; i++)
			{
				var selectedNode = ajxpDataNodes[i];
				if(selectedNode.isLeaf()){
					this._bFile = true;
				}
				else{
					this._bDir = true;
				}
				
				var meta = selectedNode.getMetadata();
				this._selectedItems.push(meta); // Backward compat
				if(meta.getAttribute('is_recycle') && meta.getAttribute('is_recycle') == '1') this._isRecycle = true;
			}
		}
		document.fire("ajaxplorer:selection_changed", this);	
	},
	
	getSelectedNodes : function(){
		return this._selectedNodes;
	},
	
	getSelectionSource : function(){
		return this._selectionSource;
	},
	
	getSelectedItems : function(){
		return this._selectedItems;
	},
	
	selectAll : function(){
		this.setSelectedNodes(this._contextNode.getChildren());
	},
	
	isEmpty : function (){
		return (this._selectedNodes?(this._selectedNodes.length==0):true);
	},
	
	isUnique : function (){
		return this._bUnique;
	},
	
	hasFile : function (){
		return this._bFile;
	},
	
	hasDir : function (){
		return this._bDir;
	},
			
	isRecycle : function (){
		return this._isRecycle;
	},
	
	getCurrentRep : function (){
		return this._currentRep;
	},
	
	isMultiple : function(){
		if(this._selectedItems.length > 1) return true;
		return false;
	},
	
	hasMime : function(mimeTypes){
		if(mimeTypes.length==1 && mimeTypes[0] == "*") return true;
		var has = false;
		var selectedItems = $A(this._selectedItems);
		mimeTypes.each(function(mime){
			if(has) return;
			has = selectedItems.any(function(item){
				return (getAjxpMimeType(item) == mime);
			});
		});
		return has;
	},
	
	getFileNames : function(separator){
		if(!this._selectedItems.length)
		{
			alert('Please select a file!');
			return;
		}
		var tmp = new Array(this._selectedItems.length);
		for(i=0;i<this._selectedItems.length;i++)
		{
			tmp[i] = this._selectedItems[i].getAttribute('filename');
		}
		if(separator){
			return tmp.join(separator);
		}else{
			return tmp;
		}
	},
	
	getContextFileNames : function(separator){
		var allItems = this._contextNode.getChildren();
		if(!allItems.length)
		{		
			return false;
		}
		var names = $A([]);
		for(i=0;i<allItems.length;i++)
		{
			var meta = allItems[i].getMetadata();
			var crtFileName = getBaseName(meta.getAttribute('filename'));
			names.push(crtFileName);
		}
		if(separator){
			return names.join(separator);
		}else{
			return names;
		}
	},
	
	fileNameExists: function(newFileName) 
	{	
		var allItems = this._contextNode.getChildren();
		if(!allItems.length)
		{		
			return false;
		}
		for(i=0;i<allItems.length;i++)
		{
			var meta = allItems[i].getMetadata();
			var crtFileName = getBaseName(meta.getAttribute('filename'));
			if(crtFileName && crtFileName.toLowerCase() == getBaseName(newFileName).toLowerCase()) 
				return true;
		}
		return false;
	},	
	
	getUniqueFileName : function(){	
		if(this.getFileNames().length) return this.getFileNames()[0];
		return null;	
	},
	
	getUniqueNode : function(){
		if(this._selectedNodes.length){
			return this._selectedNodes[0];
		}
		return null;
	},
	
	getUniqueItem : function(){
		return this._selectedItems[0];
	},

    getItem : function(i) {
        return this._selectedItems[i];
    },
	
	updateFormOrUrl : function (oFormElement, sUrl){
		// CLEAR FROM PREVIOUS ACTIONS!
		if(oFormElement)	
		{
			$(oFormElement).getElementsBySelector("input").each(function(element){
				if(element.name.indexOf("file_") != -1 || element.name=="file") element.value = "";
			});
		}
		// UPDATE THE 'DIR' FIELDS
		if(oFormElement && oFormElement.rep) oFormElement.rep.value = this._currentRep;
		sUrl += '&dir='+encodeURIComponent(this._currentRep);
		
		// UPDATE THE 'file' FIELDS
		if(this.isEmpty()) return sUrl;
		var fileNames = this.getFileNames();
		if(this.isUnique())
		{
			sUrl += '&'+'file='+encodeURIComponent(fileNames[0]);
			if(oFormElement) this._addHiddenField(oFormElement, 'file', fileNames[0]);
		}
		else
		{
			for(var i=0;i<fileNames.length;i++)
			{
				sUrl += '&'+'file_'+i+'='+encodeURIComponent(fileNames[i]);
				if(oFormElement) this._addHiddenField(oFormElement, 'file_'+i, fileNames[i]);
			}
		}
		return sUrl;
	},
	
	_addHiddenField : function(oFormElement, sFieldName, sFieldValue){
		if(oFormElement[sFieldName]) oFormElement[sFieldName].value = sFieldValue;
		else{
			var field = document.createElement('input');
			field.type = 'hidden';
			field.name = sFieldName;
			field.value = sFieldValue;
			oFormElement.appendChild(field);
		}
	}
});
