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
				
				if(selectedObj.getAttribute('is_file') && selectedObj.getAttribute('is_file') == '1') this._bFile = true;
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
		var has = false;
		mimeTypes.each(function(mime){
			for(i=0;i<this._selectedItems.length;i++){
				if(getFileExtension(this._selectedItems[i].getAttribute('filename')) == mime){
					has = true;
					return;
				}
			}
		}.bind(this));
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
		sUrl += '&dir='+this._currentRep;
		
		// UPDATE THE 'file' FIELDS
		if(this.isEmpty()) return sUrl;
		var fileNames = this.getFileNames();
		if(this.isUnique())
		{
			sUrl += '&'+'file='+fileNames[0];
			if(oFormElement) this._addHiddenField(oFormElement, 'file', fileNames[0]);
		}
		else
		{
			for(var i=0;i<fileNames.length;i++)
			{
				sUrl += '&'+'file_'+i+'='+fileNames[i];
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