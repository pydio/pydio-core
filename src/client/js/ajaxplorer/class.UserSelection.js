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
UserSelection = Class.create({

	_currentRep: undefined, 
	_selectedItems: undefined,
	_bEmpty: undefined,
	_bUnique: false,
	_bFile: false,
	_bDir: false,
	_isRecycle: false,


	initialize: function(aSelectedItems, sCurrentRep){
		this._currentRep = sCurrentRep;
		this._selectedItems = aSelectedItems;
		this._bEmpty = ((aSelectedItems && aSelectedItems.length)?false:true);
		if(!this._bEmpty)
		{
			this._bUnique = ((aSelectedItems.length == 1)?true:false);
			for(var i=0; i<aSelectedItems.length; i++)
			{
				var selectedObj = aSelectedItems[i];
				
				if(selectedObj.getAttribute('is_file') && (selectedObj.getAttribute('is_file') == '1' || selectedObj.getAttribute('is_file') == 'true')) this._bFile = true;
				else this._bDir = true;
				
				if(selectedObj.getAttribute('is_recycle') && selectedObj.getAttribute('is_recycle') == '1') this._isRecycle = true;
			}
		}		
	},
	
	isEmpty : function (){
		return this._bEmpty;
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
	
	getFileNames : function(){
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
		return tmp;
	},
	
	getUniqueFileName : function(){	
		if(this.getFileNames().length) return this.getFileNames()[0];
		return null;	
	},
	
	getUniqueItem : function(){
		return this._selectedItems[0];
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