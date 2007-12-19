/*----------------------------------------------------------------------------\
|                          Selectable Elements 1.02                           |
|-----------------------------------------------------------------------------|
|                         Created by Erik Arvidsson                           |
|                  (http://webfx.eae.net/contact.html#erik)                   |
|                      For WebFX (http://webfx.eae.net/)                      |
|-----------------------------------------------------------------------------|
|          A script that allows children of any element to be selected        |
|-----------------------------------------------------------------------------|
|                Copyright (c) 2002, 2003, 2006 Erik Arvidsson                |
|-----------------------------------------------------------------------------|
| Licensed under the Apache License, Version 2.0 (the "License"); you may not |
| use this file except in compliance with the License.  You may obtain a copy |
| of the License at http://www.apache.org/licenses/LICENSE-2.0                |
| - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - |
| Unless  required  by  applicable law or  agreed  to  in  writing,  software |
| distributed under the License is distributed on an  "AS IS" BASIS,  WITHOUT |
| WARRANTIES OR  CONDITIONS OF ANY KIND,  either express or implied.  See the |
| License  for the  specific language  governing permissions  and limitations |
| under the License.                                                          |
|-----------------------------------------------------------------------------|
| 2002-09-19 | Original Version Posted.                                       |
| 2002-09-27 | Fixed a bug in IE when mouse down and up occured on different  |
|            | rows.                                                          |
| 2003-02-11 | Minor problem with addClassName and removeClassName that       |
|            | triggered a bug in Opera 7. Added destroy method               |
| 2006-05-28 | Changed license to Apache Software License 2.0.                |
|-----------------------------------------------------------------------------|
| Created 2002-09-04 | All changes are in the log above. | Updated 2006-05-28 |
\----------------------------------------------------------------------------*/

function SelectableElements(oElement, bMultiple) {
	if (oElement == null)
		return;

	this._htmlElement = oElement;
	this._multiple = Boolean(bMultiple);

	this._selectedItems = [];
	this._fireChange = true;
	this.hasFocus = false;

	var oThis = this;
	this._onclick = function (e) {
		if (e == null) e = oElement.ownerDocument.parentWindow.event;		
		oThis.click(e);
	};

	$(this._htmlElement).observe('contextmenu', function(e){
		Event.stop(e);
		if(this._selectedItems.length > 1){
			 return;
		}
		this.click(e);
	}.bind(this));
	
	this._ondblclick = function (e) {
		if (e == null) e = oElement.ownerDocument.parentWindow.event;
		oThis.dblClick(e);
	};

	if (oElement.addEventListener)
		oElement.addEventListener("click", this._onclick, false);
	else if (oElement.attachEvent){
		oElement.attachEvent("onclick", this._onclick);
		oElement.attachEvent("ondblclick", this._ondblclick);
	}
}

SelectableElements.prototype.setItemSelected = function (oEl, bSelected) {
	if (!this._multiple) {
		if (bSelected) {
			var old = this._selectedItems[0]
			if (oEl == old)
				return;
			if (old != null)
				this.setItemSelectedUi(old, false);
			this.setItemSelectedUi(oEl, true);
			this._selectedItems = [oEl];
			this.fireChange();
		}
		else {
			if (this._selectedItems[0] == oEl) {
				this.setItemSelectedUi(oEl, false);
				this._selectedItems = [];
			}
		}
	}
	else {
		if (Boolean(oEl._selected) == Boolean(bSelected))
			return;

		this.setItemSelectedUi(oEl, bSelected);

		if (bSelected)
			this._selectedItems[this._selectedItems.length] = oEl;
		else {
			// remove
			var tmp = [];
			var j = 0;
			for (var i = 0; i < this._selectedItems.length; i++) {
				if (this._selectedItems[i] != oEl)
					tmp[j++] = this._selectedItems[i];
			}
			this._selectedItems = tmp;
		}
		this.fireChange();
	}
};

// This method updates the UI of the item
SelectableElements.prototype.setItemSelectedUi = function (oEl, bSelected) {
	if (bSelected){
		addClassName(oEl, "selected");
		addClassName(oEl, "selected-focus");
		// CHECK THAT SCROLLING IS OK
		
		var parent = $('selectable_div');
		if($('table_rows_container')) parent = $('table_rows_container');		
		var scrollOffset = oEl.offsetTop;
		
		if(scrollOffset+$(oEl).getHeight() > (parent.getHeight()+parent.scrollTop)){			
			parent.scrollTop = scrollOffset-parent.getHeight()+$(oEl).getHeight();
		}else if(scrollOffset < (parent.scrollTop)){
			parent.scrollTop = scrollOffset-$(oEl).getHeight();
		}
		
	}
	else{		
		removeClassName(oEl, "selected");
		removeClassName(oEl, "selected-focus");
	}

	oEl._selected = bSelected;
};

SelectableElements.prototype.focus = function()
{
	this.hasFocus = true;
	this.selectFirst();
	for(var i=0; i < this._selectedItems.length;i++)
	{
		if(this._selectedItems[i])
		{
			addClassName(this._selectedItems[i],'selected-focus');
		}
	}
}

SelectableElements.prototype.blur = function()
{
	this.hasFocus = false;
	for(var i=0; i < this._selectedItems.length;i++)
	{
		if(this._selectedItems[i])
		{
			removeClassName(this._selectedItems[i],'selected-focus');
		}
	}
}

SelectableElements.prototype.selectFirst = function()
{
	if(this._selectedItems.length) return;	
	if(this.getItem(0) != null)
	{
		this.setItemSelected(this.getItem(0), true);
	}
}

SelectableElements.prototype.selectAll = function()
{
	var items = this.getItems();
	for(var i=0; i<items.length; i++)
	{
		this.setItemSelected(items[i], true);
	}
}

SelectableElements.prototype.getItemSelected = function (oEl) {
	return Boolean(oEl._selected);
};

SelectableElements.prototype.fireChange = function () {
	if (!this._fireChange)
		return;
	if (typeof this.onchange == "string")
		this.onchange = new Function(this.onchange);
	if (typeof this.onchange == "function")
		this.onchange();
};

SelectableElements.prototype.fireDblClick = function () {
    if(!this._fireChange)
    	return;
    if(typeof this.ondblclick == "string" && this.ondblclick != "")
    	this.ondblclick = new Funtion(this.ondblclick);
    if(typeof this.ondblclick == "function")
    	this.ondblclick();
}

SelectableElements.prototype.dblClick = function (e) {
	//alert('Dbl Click!');
	this.fireDblClick();
}

SelectableElements.prototype.click = function (e) {
	if(e.detail && e.detail > 1)
	{ 
		this.fireDblClick();
	}
	var oldFireChange = this._fireChange;
	this._fireChange = false;
	
	
	// create a copy to compare with after changes
	var selectedBefore = this.getSelectedItems();	// is a cloned array

	// find row
	var el = e.target != null ? e.target : e.srcElement;
	while (el != null && !this.isItem(el))
		el = el.parentNode;

	if (el == null) {	// happens in IE when down and up occur on different items
		this._fireChange = oldFireChange;
		return;
	}
	var rIndex = el;
	var aIndex = this._anchorIndex;

	// test whether the current row should be the anchor
	if (this._selectedItems.length == 0 || (e.ctrlKey && !e.shiftKey && this._multiple)) {
		aIndex = this._anchorIndex = rIndex;
	}

	if (!e.ctrlKey && !e.shiftKey || !this._multiple) {
		// deselect all
		var items = this._selectedItems;
		for (var i = items.length - 1; i >= 0; i--) {
			if (items[i]._selected && items[i] != el)
				this.setItemSelectedUi(items[i], false);
		}
		this._anchorIndex = rIndex;
		if (!el._selected) {
			this.setItemSelectedUi(el, true);
		}
		this._selectedItems = [el];
	}

	// ctrl
	else if (this._multiple && e.ctrlKey && !e.shiftKey) {
		this.setItemSelected(el, !el._selected);
		this._anchorIndex = rIndex;
	}

	// ctrl + shift
	else if (this._multiple && e.ctrlKey && e.shiftKey) {
		// up or down?
		var dirUp = this.isBefore(rIndex, aIndex);

		var item = aIndex;
		while (item != null && item != rIndex) {
			if (!item._selected && item != el)
				this.setItemSelected(item, true);
			item = dirUp ? this.getPrevious(item) : this.getNext(item);
		}

		if (!el._selected)
			this.setItemSelected(el, true);
	}

	// shift
	else if (this._multiple && !e.ctrlKey && e.shiftKey) {
		// up or down?
		var dirUp = this.isBefore(rIndex, aIndex);

		// deselect all
		var items = this._selectedItems;
		for (var i = items.length - 1; i >= 0; i--)
			this.setItemSelectedUi(items[i], false);
		this._selectedItems = [];

		// select items in range
		var item = aIndex;
		while (item != null) {
			this.setItemSelected(item, true);
			if (item == rIndex)
				break;
			item = dirUp ? this.getPrevious(item) : this.getNext(item);
		}
	}

	// find change!!!
	var found;
	var changed = selectedBefore.length != this._selectedItems.length;
	if (!changed) {
		for (var i = 0; i < selectedBefore.length; i++) {
			found = false;
			for (var j = 0; j < this._selectedItems.length; j++) {
				if (selectedBefore[i] == this._selectedItems[j]) {
					found = true;
					break;
				}
			}
			if (!found) {
				changed = true;
				break;
			}
		}
	}

	this._fireChange = oldFireChange;
	if (changed && this._fireChange)
		this.fireChange();
};

SelectableElements.prototype.getSelectedItems = function () {
	//clone
	var items = this._selectedItems;
	var l = items.length;
	var tmp = new Array(l);
	for (var i = 0; i < l; i++)
		tmp[i] = items[i];
	return tmp;
};

SelectableElements.prototype.isItem = function (node) {
	return node != null && node.nodeType == 1 && node.parentNode == this._htmlElement;
};

SelectableElements.prototype.destroy = function () {
	if (this._htmlElement.removeEventListener)
		this._htmlElement.removeEventListener("click", this._onclick, false);
	else if (this._htmlElement.detachEvent)
		this._htmlElement.detachEvent("onclick", this._onclick);

	this._htmlElement = null;
	this._onclick = null;
	this._selectedItems = null;
};

/* Traversable Collection Interface */

SelectableElements.prototype.getNext = function (el) {
	var n = el.nextSibling;
	if (n == null || this.isItem(n))
		return n;
	return this.getNext(n);
};

SelectableElements.prototype.getPrevious = function (el) {
	var p = el.previousSibling;
	if (p == null || this.isItem(p))
		return p;
	return this.getPrevious(p);
};

SelectableElements.prototype.isBefore = function (n1, n2) {
	var next = this.getNext(n1);
	while (next != null) {
		if (next == n2)
			return true;
		next = this.getNext(next);
	}
	return false;
};

/* End Traversable Collection Interface */

/* Indexable Collection Interface */

SelectableElements.prototype.getItems = function () {
	var tmp = [];
	var j = 0;
	var cs = this._htmlElement.childNodes;
	var l = cs.length;
	for (var i = 0; i < l; i++) {
		if (cs[i].nodeType == 1)
			tmp[j++] = cs[i]
	}
	return tmp;
};

SelectableElements.prototype.getItem = function (nIndex) {
	var j = 0;
	var cs = this._htmlElement.childNodes;
	var l = cs.length;
	for (var i = 0; i < l; i++) {
		if (cs[i].nodeType == 1) {
			if (j == nIndex)
				return cs[i];
			j++;
		}
	}
	return null;
};

SelectableElements.prototype.getSelectedIndexes = function () {
	var items = this.getSelectedItems();
	var l = items.length;
	var tmp = new Array(l);
	for (var i = 0; i < l; i++)
		tmp[i] = this.getItemIndex(items[i]);
	return tmp;
};


SelectableElements.prototype.getItemIndex = function (el) {
	var j = 0;
	var cs = this._htmlElement.childNodes;
	var l = cs.length;
	for (var i = 0; i < l; i++) {
		if (cs[i] == el)
			return j;
		if (cs[i].nodeType == 1)
			j++;
	}
	return -1;
};

/* End Indexable Collection Interface */



function addClassName(el, sClassName) {
	var s = el.className;
	var p = s.split(" ");
	if (p.length == 1 && p[0] == "")
		p = [];

	var l = p.length;
	for (var i = 0; i < l; i++) {
		if (p[i] == sClassName)
			return;
	}
	p[p.length] = sClassName;
	el.className = p.join(" ");
}

function removeClassName(el, sClassName) {
	var s = el.className;
	var p = s.split(" ");
	var np = [];
	var l = p.length;
	var j = 0;
	for (var i = 0; i < l; i++) {
		if (p[i] != sClassName)
			np[j++] = p[i];
	}
	el.className = np.join(" ");
}