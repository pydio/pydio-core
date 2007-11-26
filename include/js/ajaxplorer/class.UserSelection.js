function UserSelection(aSelectedItems, sCurrentRep)
{
	this._currentRep = sCurrentRep;
	this._selectedItems = aSelectedItems;
	this._bEmpty = ((aSelectedItems && aSelectedItems.length)?false:true);
	this._bUnique = false;
	this._bFile = false;
	this._bDir = false;
	this._bEditable = false;
	this._bImage = false;
	if(!this._bEmpty)
	{
		this._bUnique = ((aSelectedItems.length == 1)?true:false);
		for(var i=0; i<aSelectedItems.length; i++)
		{
			var selectedObj = aSelectedItems[i];
			
			if(selectedObj.getAttribute('is_file') && selectedObj.getAttribute('is_file') == 'oui') this._bFile = true;
			else this._bDir = true;
			
			if(selectedObj.getAttribute('is_editable') && selectedObj.getAttribute('is_editable') == '1') this._bEditable = true;
			
			if(selectedObj.getAttribute('is_image') && selectedObj.getAttribute('is_image') == '1') this._bImage = true;
		}
	}
	
}

UserSelection.prototype.isEmpty = function ()
{
	return this._bEmpty;
}

UserSelection.prototype.isUnique = function ()
{
	return this._bUnique;
}
UserSelection.prototype.hasFile = function ()
{
	return this._bFile;
}
UserSelection.prototype.hasDir = function ()
{
	return this._bDir;
}
UserSelection.prototype.isEditable = function ()
{
	return this._bEditable;
}
UserSelection.prototype.isImage = function ()
{
	return this._bImage;
}
UserSelection.prototype.getCurrentRep = function ()
{
	return this._currentRep;
}
UserSelection.prototype.isMultiple = function()
{
	if(this._selectedItems.length > 1) return true;
	return false;
}


UserSelection.prototype.getFileNames = function() {
	
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
}

UserSelection.prototype.getUniqueFileName = function() 
{	
	if(this.getFileNames().length) return this.getFileNames()[0];
	return null;	
}

UserSelection.prototype.getUniqueItem = function()
{
	return this._selectedItems[0];
}

UserSelection.prototype.updateFormOrUrl = function (oFormElement, sUrl)
{
	// CLEAR FROM PREVIOUS ACTIONS!
	if(oFormElement)	
	{
		$(oFormElement).getElementsBySelector("input").each(function(element){
			if(element.name.indexOf("fic_") != -1 || element.name=="fic") element.value = "";
		});
	}
	// UPDATE THE 'REP' FIELDS
	if(oFormElement && oFormElement.rep) oFormElement.rep.value = this._currentRep;
	sUrl += '&rep='+this._currentRep;
	
	// UPDATE THE 'FIC' FIELDS
	if(this.isEmpty()) return sUrl;
	var fileNames = this.getFileNames();
	if(this.isUnique())
	{
		sUrl += '&'+'fic='+fileNames[0];
		if(oFormElement) this._addHiddenField(oFormElement, 'fic', fileNames[0]);
	}
	else
	{
		for(var i=0;i<fileNames.length;i++)
		{
			sUrl += '&'+'fic_'+i+'='+fileNames[i];
			if(oFormElement) this._addHiddenField(oFormElement, 'fic_'+i, fileNames[i]);
		}
	}
	return sUrl;
}

UserSelection.prototype._addHiddenField = function(oFormElement, sFieldName, sFieldValue)
{
	if(oFormElement[sFieldName]) oFormElement[sFieldName].value = sFieldValue;
	else{
		var field = document.createElement('input');
		field.type = 'hidden';
		field.name = sFieldName;
		field.value = sFieldValue;
		oFormElement.appendChild(field);
	}
}