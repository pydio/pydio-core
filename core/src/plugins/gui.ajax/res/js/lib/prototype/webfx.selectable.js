/*----------------------------------------------------------------------------\
|                          Selectable Elements 1.02                           |
|-----------------------------------------------------------------------------|
|                         Created by Erik Arvidsson                           |
|                  (http://webfx.eae.net/contact.html#erik)                   |
|                      For WebFX (http://webfx.eae.net/)                      |
|                      Prototypized by Charles du Jeu                         |
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
| 2007-12-24 | Prototypized by Charles du Jeu                                 |
|-----------------------------------------------------------------------------|
| Created 2002-09-04 | All changes are in the log above. | Updated 2006-05-28 |
\----------------------------------------------------------------------------*/
SelectableElements = Class.create({

	initialize: function(oElement, bMultiple){
		if (oElement == null)
			return;
		this.initSelectableItems(oElement, bMultiple);
	},

    initNonSelectableItems:function(oElement){
        this._htmlElement = oElement;
        this._multiple = false;

        this._selectedItems = [];
        this._fireChange = true;
        this.hasFocus = false;
    },

	initSelectableItems: function(oElement, bMultiple, dragSelectionElement, addTouch) {
	
		this._htmlElement = oElement;
		this._multiple = Boolean(bMultiple);
	
		this._selectedItems = [];
		this._fireChange = true;
		this.hasFocus = false;
	
		this._onclick = function (e) {
			if (e == null) e = oElement.ownerDocument.parentWindow.event;		
			this.click(e);
		}.bind(this);
	
		$(this._htmlElement).observe('contextmenu', function(e){		
			//Event.stop(e);					
			if(this._selectedItems.length > 1) return;
			this.click(e);
		}.bind(this));
		
		this._ondblclick = function (e) {
			if (e == null) e = oElement.ownerDocument.parentWindow.event;
			this.dblClick(e);
		}.bind(this);

		if (oElement.addEventListener){
            if('ondblclick' in document.documentElement) oElement.addEventListener("dblclick", this._ondblclick, false);
			oElement.addEventListener("click", this._onclick, false);
		}else if (oElement.attachEvent){
			oElement.attachEvent("onclick", this._onclick);
			oElement.attachEvent("ondblclick", this._ondblclick);
		}
        if(addTouch){
            oElement.observe("touchstart", function(event){
                var touchData = event.changedTouches[0];
                  oElement.selectableTouchStart = touchData["clientY"];
            }.bind(this));
            oElement.observe("touchend", function(event){
                if(oElement.selectableTouchStart) {
                    var touchData = event.changedTouches[0];
                    var delta = touchData['clientY'] - oElement.selectableTouchStart;
                    if(Math.abs(delta) > 2){
                        return;
                    }
                }
                oElement.selectableTouchStart = null;
                this._onclick(event);
            }.bind(this) );
        }

		this.eventMouseUp = this.dragEnd.bindAsEventListener(this);
		this.eventMouseDown = this.dragStart.bindAsEventListener(this);
		this.eventMouseMove = this.drag.bindAsEventListener(this);
		
		this.selectorAdded = false;
		if(dragSelectionElement){
			this.dragSelectionElement = $(dragSelectionElement);
		}else{
			this.dragSelectionElement = $(oElement);
		}
		Event.observe(this.dragSelectionElement, "mousedown", this.eventMouseDown);
	},
	dragStart : function(e){
		this.originalX = e.clientX;
		this.originalY = e.clientY;
		
		this.dSEPosition = Position.cumulativeOffset(this.dragSelectionElement);
		this.dSEDimension = Element.getDimensions(this.dragSelectionElement);
		
		var h = this.dSEDimension.height;
		if(this.dragSelectionElement.scrollHeight > h){
			// there is a scroll bar 
			if(this.originalX > (this.dSEPosition[0]+this.dSEDimension.width) - 18) return;
		}
		if(this.dragSelectionElement.scrollWidth > this.dSEDimension.width){
			// there is a scroll bar 
			if(this.originalY > (this.dSEPosition[1]+this.dSEDimension.height) - 18) return;
		}
		Event.observe(document, "mousemove", this.eventMouseMove);
		Event.observe(document, "mouseup", this.eventMouseUp);
		if(!this.divSelector){
			this.divSelector = new Element('div', {
				style:"border : 1px dotted #999; background-color:#ddd;	filter:alpha(opacity=50);opacity: 0.5;-moz-opacity:0.5;z-index:100000;position:absolute;top:0px;left:0px;height:0px;width:0px;"
			});
		}
		$(this.dragSelectionElement).setStyle({
			cursor : "move"
		});
	},
	
	drag : function(e){
		if(!this.selectorAdded){
			this.body = document.getElementsByTagName('body')[0];
			this.body.appendChild(this.divSelector);			
			this.selectorAdded = true;
		}
		var crtX = e.clientX;
		var crtY = e.clientY;
		var minDSEX = this.dSEPosition[0];
		var minDSEY = this.dSEPosition[1];
		var maxDSEX = minDSEX + this.dSEDimension.width;
		var maxDSEY = minDSEY + this.dSEDimension.height;
		crtX = Math.max(crtX, minDSEX);
		crtY = Math.max(crtY, minDSEY);
		crtX = Math.min(crtX, maxDSEX);
		crtY = Math.min(crtY, maxDSEY);
		
		var top,left,width,height;
		left = Math.min(this.originalX, crtX);
		width = Math.abs((this.originalX - crtX));
		top = Math.min(this.originalY, crtY);
		height = Math.abs((this.originalY - crtY));
						
		this.divSelector.setStyle({
			top : top+'px',
			left : left+'px',
			width : width+'px',
			height : height+'px'
		});
		var allItems = this.getItems();
		var minX = left;
		var maxX = left+width;
		var minY = top;
		var maxY = top+height;
		for(var i=0; i<allItems.length;i++){
			var element = $(allItems[i]);
			var pos = Position.cumulativeOffset(element);
			pos[0] -= this.dragSelectionElement.scrollLeft;
			pos[1] -= this.dragSelectionElement.scrollTop;

			var dims = Element.getDimensions(element);
			var x1 = pos[0]; var x2 = pos[0]+dims.width;
			var y1 = pos[1]; var y2 = pos[1]+dims.height;
			if( ( (x1>=minX && x1<=maxX) || (x2>=minX && x2<=maxX)  || (minX>=x1 && maxX<=x2))
			&& ( (y1>=minY && y1<=maxY) || (y2>=minY && y2<=maxY) || (minY>=y1 && maxY<=y2) ))
			{
				this.setItemSelected(allItems[i], true);	
			}else{
				if(!e['shiftKey'] && !e['crtlKey']){
					this.setItemSelected(allItems[i], false);
				}
			}
				
		}
	},
	
	dragEnd : function(e){
		Event.stopObserving(document, "mousemove", this.eventMouseMove);
		Event.stopObserving(document, "mouseup", this.eventMouseUp);
		if(this.selectorAdded){
			this.body.removeChild(this.divSelector);
			this.selectorAdded = false;
		}
		$(this.dragSelectionElement).setStyle({
			cursor : "default"
		});		
	},
		
	setItemSelected: function (oEl, bSelected) {
		if (!this._multiple) {
			if (bSelected) {
				var old = this._selectedItems[0] ;
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
	},
	
	// This method updates the UI of the item
	setItemSelectedUi: function (oEl, bSelected) {
		if (bSelected){
            if(!this.options || !this.options.invisibleSelection){
                $(oEl).addClassName("selected");
                $(oEl).addClassName("selected-focus");
            }

			if(!this.skipScroll){
				// CHECK THAT SCROLLING IS OK
				var parent = this._htmlElement;
                if(this._htmlElement.up('.table_rows_container')) {
                    parent = this._htmlElement.up('.table_rows_container');
                }
                var direction = 'offsetTop';
                var dimMethod = 'getHeight';
                var scrollDir = 'scrollTop';
                if(this.options && this.options.horizontalScroll){
                    parent = $(parent.parentNode);
                    direction = 'offsetLeft';
                    dimMethod = 'getWidth';
                    scrollDir = 'scrollLeft';
                }

				var scrollOffset = oEl[direction];
				var parentHeight = parent[dimMethod]();
				var parentScrollTop = parent[scrollDir];
				var elHeight = $(oEl)[dimMethod]();

                var sTop = -1;
				if(scrollOffset+elHeight > (parentHeight+parentScrollTop)){			
					sTop = scrollOffset-parentHeight+elHeight;
				}else if(scrollOffset < (parentScrollTop)){
					sTop = scrollOffset-elHeight;
				}
                if(sTop != -1){
                    if(parent.scrollerInstance){
                        parent.scrollerInstance.scrollTo(sTop);
                    }else{
                        parent[scrollDir] = sTop;
                    }
                }
			}
		}
		else{		
			$(oEl).removeClassName("selected");
			$(oEl).removeClassName("selected-focus");
		}
	
		oEl._selected = bSelected;
	},
	
	focus: function()
	{
		this.hasFocus = true;
        if(!this._selectedItems.length && !this.options.skipSelectFirstOnFocus) this.selectFirst();
        if(this.options && this.options.invisibleSelection) return;
        for(var i=0; i < this._selectedItems.length;i++)
		{
			if(this._selectedItems[i])
			{
				$(this._selectedItems[i]).addClassName('selected-focus');
			}
		}
	},
	
	blur: function()
	{
		this.hasFocus = false;
        if(this.options && this.options.invisibleSelection) return;
		for(var i=0; i < this._selectedItems.length;i++)
		{
			if(this._selectedItems[i])
			{
				$(this._selectedItems[i]).removeClassName('selected-focus');
			}
		}
	},
	
	selectFirst: function()
	{
		if(this._selectedItems.length) return;	
		if(this.getItem(0) != null)
		{
			this.setItemSelected(this.getItem(0), true);
		}
	},
	
	selectAll: function()
	{
		var items = this.getItems();
		for(var i=0; i<items.length; i++)
		{
			this.setItemSelected(items[i], true);
		}
	},
	
	getItemSelected: function (oEl) {
		return Boolean(oEl._selected);
	},
	
	fireChange: function () {
		if (!this._fireChange)
			return;
		if (typeof this.onchange == "string")
			this.onchange = new Function(this.onchange);
		if (typeof this.onchange == "function")
			this.onchange();
	},
	
	fireDblClick: function () {
	    if(!this._fireChange)
	    	return;
	    if(typeof this.ondblclick == "string" && this.ondblclick != "")
	    	this.ondblclick = new Function(this.ondblclick);
	    if(typeof this.ondblclick == "function")
	    	this.ondblclick();
	},
	
	dblClick: function (e) {
		//alert('Dbl Click!');
        this.hasFocus = true;
		this.fireDblClick();
	},

    previousEventTime: null,
    previousEventTarget: null,
    ie10detailFilter : function(e){
        if(!Prototype.Browser.IE10){
            return false;
        }
        var result = true;
        if(!this.previousEventTime){
            result = false;
        }
        if(e.timeStamp - this.previousEventTime > 300 && e.target != this.previousEventTarget){
            result = false;
        }
        this.previousEventTarget = e.target;
        this.previousEventTime = e.timeStamp;
        return result;
    },
	
	click: function (e) {
        this.hasFocus = true;
		if(e.detail && e.detail > 1)
		{
            if(this.ie10detailFilter(e)){
                this.fireDblClick();
            }
		}

		var oldFireChange = this._fireChange;
		this._fireChange = false;

        // Adapt to MacOS Cmd key
        var ctrlOrCmd = e.ctrlKey || e.metaKey;
		
		// create a copy to compare with after changes
		var selectedBefore = this.getSelectedItems();	// is a cloned array
	
		// find row
        var el = Event.findElement(e, ".ajxpNodeProvider");
	
		if (el == null) {	// happens in IE when down and up occur on different items
			this._fireChange = oldFireChange;
			return;
		}
		var rIndex = el, items, item, i, dirUp;
		var aIndex = this._anchorIndex;
	
		// test whether the current row should be the anchor
		if (this._selectedItems.length == 0 || (ctrlOrCmd && !e.shiftKey && this._multiple)) {
			aIndex = this._anchorIndex = rIndex;
		}
	
		if (!ctrlOrCmd && !e.shiftKey || !this._multiple) {
			// deselect all
			items = this._selectedItems;
			for (i = items.length - 1; i >= 0; i--) {
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
		else if (this._multiple && ctrlOrCmd && !e.shiftKey) {
			this.setItemSelected(el, !el._selected);
			this._anchorIndex = rIndex;
		}
	
		// ctrl + shift
		else if (this._multiple && ctrlOrCmd && e.shiftKey) {
			// up or down?
			dirUp = this.isBefore(rIndex, aIndex);
	
			item = aIndex;
			while (item != null && item != rIndex) {
				if (!item._selected && item != el)
					this.setItemSelected(item, true);
				item = dirUp ? this.getPrevious(item) : this.getNext(item);
			}
	
			if (!el._selected)
				this.setItemSelected(el, true);
		}
	
		// shift
		else if (this._multiple && !ctrlOrCmd && e.shiftKey) {
			// up or down?
			dirUp = this.isBefore(rIndex, aIndex);
	
			// deselect all
			items = this._selectedItems;
			for (i = items.length - 1; i >= 0; i--)
				this.setItemSelectedUi(items[i], false);
			this._selectedItems = [];
	
			// select items in range
			item = aIndex;
			this.skipScroll=true;
			while (item != null) {
				this.setItemSelected(item, true);
				if (item == rIndex -1){
					this.skipScroll = false;
				}
				if (item == rIndex)
					break;
				item = dirUp ? this.getPrevious(item) : this.getNext(item);
			}
			this.skipScroll = false;
		}
	
		// find change!!!
		var found;
		var changed = selectedBefore.length != this._selectedItems.length;
		if (!changed) {
			for (i = 0; i < selectedBefore.length; i++) {
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
	},
	
	getSelectedItems: function () {
		//clone
		var items = this._selectedItems;
		var l = items.length;
		var tmp = new Array(l);
		for (var i = 0; i < l; i++)
			tmp[i] = items[i];
		return tmp;
	},
	
	getSelectedNodes : function(){
		var items = this._selectedItems;
		var nodes = $A([]);
		for(var i=0;i<items.length;i++){
			if(items[i].ajxpNode) nodes.push(items[i].ajxpNode);
		}
		return nodes;
	},
	
	setSelectedNodes : function(ajxpNodes){
		var items = this.getItems();
		for(var i=0;i<items.length;i++){
			if(items[i].ajxpNode && ajxpNodes.detect(function(el){return (el.getPath() == items[i].ajxpNode.getPath()); })){
				this.setItemSelected(items[i], true);
			}else{
				this.setItemSelected(items[i], false);
			}
		}
	},
	
	isItem: function (node) {
		return node != null && node.up('#' + this._htmlElement.id);
	},
	
	findSelectableParent : function(el, setSelected){
		while (el != null && !this.isItem(el)){
			el = el.parentNode;
		}
		if(el != null && setSelected){
			this.setItemSelected(el, true);
		}
		return el;
	},
	
	destroy: function () {
        if(!this._htmlElement) return;

		if (this._htmlElement.removeEventListener)
			this._htmlElement.removeEventListener("click", this._onclick, false);
		else if (this._htmlElement.detachEvent)
			this._htmlElement.detachEvent("onclick", this._onclick);
	
		this._htmlElement = null;
		this._onclick = null;
		this._selectedItems = null;
	},
	
	/* Traversable Collection Interface */
	
	getNext: function (el) {
		var n = el.nextSibling;
		if (n == null || this.isItem(n))
			return n;
		return this.getNext(n);
	},
	
	getPrevious: function (el) {
		var p = el.previousSibling;
		if (p == null || this.isItem(p))
			return p;
		return this.getPrevious(p);
	},
	
	isBefore: function (n1, n2) {
		var next = this.getNext(n1);
		while (next != null) {
			if (next == n2)
				return true;
			next = this.getNext(next);
		}
		return false;
	},
	
	/* End Traversable Collection Interface */
	
	/* Indexable Collection Interface */
	
	getItems: function () {
		var tmp = [];
		var j = 0;
		var cs = this._htmlElement.select('.ajxpNodeProvider');
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			if (cs[i].nodeType == 1)
				tmp[j++] = cs[i] ;
		}
		return tmp;
	},
	
	getItem: function (nIndex) {
		var j = 0;
		var cs = this._htmlElement.select('.ajxpNodeProvider');
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			if (cs[i].nodeType == 1) {
				if (j == nIndex)
					return cs[i];
				j++;
			}
		}
		return null;
	},
	
	getSelectedIndexes: function () {
		var items = this.getSelectedItems();
		var l = items.length;
		var tmp = new Array(l);
		for (var i = 0; i < l; i++)
			tmp[i] = this.getItemIndex(items[i]);
		return tmp;
	},
	
	
	getItemIndex: function (el) {
		var j = 0;
		var cs = this._htmlElement.select('.ajxpNodeProvider');
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			if (cs[i] == el)
				return j;
			if (cs[i].nodeType == 1)
				j++;
		}
		return -1;
	}
	
	/* End Indexable Collection Interface */	
});
