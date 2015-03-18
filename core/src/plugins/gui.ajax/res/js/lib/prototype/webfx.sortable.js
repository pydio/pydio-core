/*----------------------------------------------------------------------------\
|                            Sortable Table 1.12                              |
|-----------------------------------------------------------------------------|
|                         Created by Erik Arvidsson                           |
|                  (http://webfx.eae.net/contact.html#erik)                   |
|                      For WebFX (http://webfx.eae.net/)                      |
|                      Prototypized by Charles du Jeu                         |
|-----------------------------------------------------------------------------|
| A DOM 1 based script that allows an ordinary HTML table to be sortable.     |
|-----------------------------------------------------------------------------|
|                  Copyright (c) 1998 - 2004 Erik Arvidsson                   |
|-----------------------------------------------------------------------------|
| This software is provided "as is", without warranty of any kind, express or |
| implied, including  but not limited  to the warranties of  merchantability, |
| fitness for a particular purpose and noninfringement. In no event shall the |
| authors or  copyright  holders be  liable for any claim,  damages or  other |
| liability, whether  in an  action of  contract, tort  or otherwise, arising |
| from,  out of  or in  connection with  the software or  the  use  or  other |
| dealings in the software.                                                   |
| - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - |
| This  software is  available under the  three different licenses  mentioned |
| below.  To use this software you must chose, and qualify, for one of those. |
| - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - |
| The WebFX Non-Commercial License          http://webfx.eae.net/license.html |
| Permits  anyone the right to use the  software in a  non-commercial context |
| free of charge.                                                             |
| - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - |
| The WebFX Commercial license           http://webfx.eae.net/commercial.html |
| Permits the  license holder the right to use  the software in a  commercial |
| context. Such license must be specifically obtained, however it's valid for |
| any number of  implementations of the licensed software.                    |
| - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - |
| GPL - The GNU General Public License    http://www.gnu.org/licenses/gpl.txt |
| Permits anyone the right to use and modify the software without limitations |
| as long as proper  credits are given  and the original  and modified source |
| code are included. Requires  that the final product, software derivate from |
| the original  source or any  software  utilizing a GPL  component, such  as |
| this, is also licensed under the GPL license.                               |
|-----------------------------------------------------------------------------|
| 2003-01-10 | First version                                                  |
| 2003-01-19 | Minor changes to the date parsing                              |
| 2003-01-28 | JScript 5.0 fixes (no support for 'in' operator)               |
| 2003-02-01 | Sloppy typo like error fixed in getInnerText                   |
| 2003-07-04 | Added workaround for IE cellIndex bug.                         |
| 2003-11-09 | The bDescending argument to sort was not correctly working     |
|            | Using onclick DOM0 event if no support for addEventListener    |
|            | or attachEvent                                                 |
| 2004-01-13 | Adding addSortType and removeSortType which makes it a lot     |
|            | easier to add new, custom sort types.                          |
| 2004-01-27 | Switch to use descending = false as the default sort order.    |
|            | Change defaultDescending to suit your needs.                   |
| 2004-03-14 | Improved sort type None look and feel a bit                    |
| 2004-08-26 | Made the handling of tBody and tHead more flexible. Now you    |
|            | can use another tHead or no tHead, and you can chose some      |
|            | other tBody.                                                   |
| 2007-12-24 | Prototypized by Charles du Jeu                                 |
|-----------------------------------------------------------------------------|
| Created 2003-01-10 | All changes are in the log above. | Updated 2004-08-26 |
\----------------------------------------------------------------------------*/

SortableTable = Class.create({

    groupByData: null,

	initialize: function(oTable, oSortTypes, oTHead) {
	
		this.gecko = Prototype.Browser.Gecko;
		this.msie = Prototype.Browser.IE;
		this.removeBeforeSort = this.gecko;
		this.sortTypes = oSortTypes || [];
	
		this.sortColumn = null;
		this.descending = null;
	
		this._headerOnclick = function (e) {
			this.headerOnclick(e);
		}.bind(this);
	
		if (oTable && oTable.nodeName.toLowerCase() == 'table') {
			this.setTable( oTable, oTHead );
			this.document = oTable.ownerDocument || oTable.document;
		} else if( oTable ){
            this.container = oTable;
            this.document = document;
        } else {
			this.document = document;
		}
	
	
		// only IE needs this
		var win = this.document.defaultView || this.document.parentWindow;
		this._onunload = function () {
			this.destroy();
		}.bind(this);
		if (win && typeof win.attachEvent != "undefined") {
			win.attachEvent("onunload", this._onunload);
		}
		// add sort types
		this.addSortType("Number", Number);
		this.addSortType("CaseInsensitiveString", this.toUpperCase);
		this.addSortType("Date", this.toDate);
		this.addSortType("String");
		
	},

    setGroupByData : function(groupByData){
        this.groupByData = groupByData;
    },

    setMetaSortType : function(columnsDefs){
        this.columnsDefs = columnsDefs;
        this.sortTypes = [];
        this.columnsDefs.each(function(a){
            this.sortTypes.push("NodeMeta");
        }.bind(this));
    },
	
	onsort: function () {},
	
	// default sort order. true -> descending, false -> ascending
	defaultDescending: false,
	
	// shared between all instances. This is intentional to allow external files
	// to modify the prototype
	_sortTypeInfo: {},
	
	setTable: function (oTable, oTHead) {
		if ( this.tHead )
			this.uninitHeader();
		this.element = oTable;
		this.setTHead( (oTHead?oTHead:oTable.tHead) );
		this.setTBody( oTable.tBodies[0] );
	},
	
	setTHead: function (oTHead) {
		if (this.tHead && this.tHead != oTHead )
			this.uninitHeader();
		this.tHead = $(oTHead);
		this.initHeader( this.sortTypes );
	},

	setTBody: function (oTBody) {
		this.tBody = $(oTBody);
	},
	
	setSortTypes: function ( oSortTypes ) {
		if ( this.tHead )
			this.uninitHeader();
		this.sortTypes = oSortTypes || [];
		if ( this.tHead )
			this.initHeader( this.sortTypes );
	},
	
	getHeaderCells : function(){
		if(!this.headerCells && this.tHead){
			this.headerCells = this.tHead.select('div.header_cell');
			this.headerCells.each(function(cell){
				var prev = cell.previous('div');
				if( prev && prev.hasClassName('resizer')){
					cell.resizer = prev;
				}
                if(!cell.down('span.ajxp-order-icon')){
                    cell.down('div.header_label').insert('<span class="ajxp-order-icon icon-caret-up"></span><span class="ajxp-order-icon icon-caret-down"></span>');
                }
			});
		}
		return this.headerCells;
	},
		
	// adds arrow containers and events
	// also binds sort type to the header cells so that reordering columns does
	// not break the sort types
	initHeader: function (oSortTypes) {
		if (!this.tHead) return;
		var cells = this.getHeaderCells();
		this.sortTypes = oSortTypes || [];
		var l = cells.length;
		var c;
		for (var i = 0; i < l; i++) {
			c = cells[i];
			c.cellIndex = i;
			if (this.sortTypes[i] != null && this.sortTypes[i] != "None") {
				if (this.sortTypes[i] != null) c._sortType = this.sortTypes[i];
				c.observe('click', this._headerOnclick.bind(this));
			}
			else
			{
				c.setAttribute( "_sortType", oSortTypes[i] );
				c._sortType = "None";
			}
		}
		this.updateHeaderArrows();
	},
	
	
	
	onHeaderResize : function(headerCells){
		/*
		var data = ajaxplorer.user.getPreference("columns_size", true);
		data = (data?new Hash(data):new Hash());
		var repoData = data.get(ajaxplorer.user.getActiveRepository());
		repoData = (repoData?new Hash(repoData):new Hash());
		repoData.set('type', 'percent');

		var tableWidth = this.element.getWidth();
		for(var k=0;k<headerCellsWidth.length;k++){
			repoData.set(k, Math.round((headerCellsWidth[k]-10)/tableWidth*100));
		}
		data.set(ajaxplorer.user.getActiveRepository(), repoData);
		ajaxplorer.user.setPreference("columns_size", data, true);
		ajaxplorer.user.savePreference("columns_size");
		*/
	},

	
	setGridCellWidth : function(cell, width){
		var label = cell.down('.text_label');		
		if(!label) {			
			if(width) cell.setStyle({width:width + 'px'});
			return;
		}
		var computedWidth;
		if(width) computedWidth = parseInt(width);
		else {
			computedWidth = cell.getWidth() - 3;
		}
		
		var cellPaddLeft = cell.getStyle('paddingLeft') || 0;
		var cellPaddRight = cell.getStyle('paddingRight') || 0;
		var labelPaddLeft = label.getStyle('paddingLeft') || 0;
		var labelPaddRight = label.getStyle('paddingRight') || 0;
		var siblingWidth = 1;
		label.siblings().each(function(sib){
			siblingWidth += sib.getWidth();
		});
		//if(cell.getAttribute("ajxp_message_id"))console.log(computedWidth + ' // ' + (computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)));
		label.setStyle({width:(computedWidth - siblingWidth - parseInt(cellPaddLeft) - parseInt(cellPaddRight) - parseInt(labelPaddLeft) - parseInt(labelPaddRight)) + 'px'});
		if(width) cell.setStyle({width:computedWidth + 'px'});	
	},
	
	// remove arrows and events
	uninitHeader: function () {
		try{
			var cells = this.getHeaderCells();
		}catch(e){
			return;
		}
        if(!cells || !cells.length) return;
		var l = cells.length;
		var c;
		for (var i = 0; i < l; i++) {
			c = cells[i];
			if (c._sortType != null && c._sortType != "None") {
				if(c.firstChild) c.removeChild(c.firstChild);
				if (typeof c.removeEventListener != "undefined")
					c.removeEventListener("click", this._headerOnclick, false);
				else if (typeof c.detachEvent != "undefined")
					c.detachEvent("onclick", this._headerOnclick);
				c._sortType = null;
				c.removeAttribute( "_sortType" );
			}
		}
	},
	
	updateHeaderArrows: function () {
		if (!this.tHead) return;
		var cells = this.getHeaderCells();
		var l = cells.length;
		for (var i = 0; i < l; i++) {
			if (cells[i]._sortType != null && cells[i]._sortType != "None") {
				cells[i].removeClassName("descending");
				cells[i].removeClassName("ascending");
				if(cells[i].resizer){
					cells[i].resizer.removeClassName("resizer_sortable");
				}
				if (i == this.sortColumn)
				{
					cells[i].addClassName(this.descending ? "descending" : "ascending");
					if(cells[i].resizer){
						cells[i].resizer.addClassName("resizer_sortable");
					}
				}
			}
		}
	},
	
	headerOnclick: function (e) {
		var el = Event.findElement(e);	
		this.sort(el.cellIndex);
	},
	
	// IE returns wrong cellIndex when columns are hidden
	getCellIndex: function (oTd) {
		var cells = oTd.parentNode.childNodes ;
		var l = cells.length;
		var i;
		for (i = 0; cells[i] != oTd && i < l; i++)
			;
		return i;
	},
	
	getSortType: function (nColumn) {
        if(this.columnsDefs && this.columnsDefs[nColumn] && this.columnsDefs[nColumn]["sortType"]){
            return this.columnsDefs[nColumn]["sortType"];
        }
		return this.sortTypes[nColumn] || "String";
	},
	
	// only nColumn is required
	// if bDescending is left out the old value is taken into account
	// if sSortType is left out the sort type is found from the sortTypes array
	
	sort: function (nColumn, bDescending, sSortType) {
		if (!this.tBody && !this.container) return;
		if (sSortType == null)
			sSortType = this.getSortType(nColumn);
	
		// exit if None
		if (sSortType == "None")
			return;
	
		if (bDescending == null) {
			if (this.sortColumn != nColumn)
				this.descending = this.defaultDescending;
			else
				this.descending = !this.descending;
		}
		else
			this.descending = bDescending;
	
		this.sortColumn = nColumn;
	
		if (typeof this.onbeforesort == "function")
			this.onbeforesort();
	
		var f = this.getSortFunction(sSortType, nColumn);
		var a = this.getCache(sSortType, nColumn);
		var tBody = this.tBody ? this.tBody : this.container;
	
		a.sort(f);
	
		if (this.descending)
			a.reverse();
	
		if (this.removeBeforeSort) {
			// remove from doc
			var nextSibling = tBody.nextSibling;
			var p = tBody.parentNode;
            if(p) p.removeChild(tBody);
		}
        tBody.select('div[data-groupbyvalue]').invoke('remove');
	
		// insert in the new order
		var l = a.length;
        var groupColIndex, groupType, allBlocks, blockType;
        if(this.groupByData){
            if(this.columnsDefs){
                var col = this.columnsDefs.detect(function(c){
                    return c.attributeName == this.groupByData;
                }.bind(this));
                if(col){
                    groupColIndex = this.columnsDefs.indexOf(col);
                    groupType = this.getSortType(groupColIndex);
                    allBlocks = [];
                    blockType = 'div';
                }
            }else{
                groupColIndex = this.groupByData;
                groupType = this.getSortType(groupColIndex);
                allBlocks = [];
                blockType = 'tr';
            }
        }

		for (var i = 0; i < l; i++){
            if(groupColIndex){
                var fieldValue = this.getRowValue(a[i].element, groupType, groupColIndex);
                if(!fieldValue) fieldValue = "N/A";
                fieldValue = fieldValue.trim();
                var block;
                if(blockType == 'tr'){
                    block = tBody.down( 'tr[data-groupbyvalue="'+fieldValue+'"]');
                }else{
                    block = tBody.down( 'div[data-groupbyvalue="'+fieldValue+'"]');
                }
                if(!block){
                    block = new Element(blockType, {"data-groupbyvalue":fieldValue});
                    tBody.insert(block);
                    if(MessageHash[fieldValue]) fieldValue = MessageHash[fieldValue];
                    if(blockType == 'div'){
                        block.insert(new Element('h3').update(fieldValue));
                    }else if(blockType == 'tr'){
                        block.insert(new Element('td', {colspan:this.sortTypes.length}).update(new Element('h3').update(fieldValue)));
                    }
                    allBlocks[fieldValue] = block;
                }
                if(blockType == 'tr'){
                    block.insert({after: a[i].element});
                }else{
                    block.insert(a[i].element);
                }
            }else{
                tBody.appendChild(a[i].element);
            }
        }
        if(allBlocks && blockType == 'div'){
            var keys = Object.keys(allBlocks);
            keys.sort();
            for(var z=0 ; z<keys.length;z++){
                tBody.insert(allBlocks[keys[z]]);
                allBlocks[keys[z]].down('h3').insert('<span class="groupBy-count"> ('+allBlocks[keys[z]].select('.ajxpNodeProvider').length+')</span>');
            }
        }

		if (this.removeBeforeSort && p) {
			// insert into doc
			p.insertBefore(tBody, nextSibling);
		}
	
		this.updateHeaderArrows();
	
		this.destroyCache(a);
	
		if (typeof this.onsort == "function"){			
			this.onsort();
		}
	},
	
	asyncSort: function (nColumn, bDescending, sSortType) {
		var oThis = this;
		this._asyncsort = function () {
			oThis.sort(nColumn, bDescending, sSortType);
		};
		window.setTimeout(this._asyncsort, 1);
	},
	
	getCache: function (sType, nColumn) {
		if (!this.tBody && !this.container) return [];
        var rows;
        if(this.tBody){
            rows = this.tBody.rows;
        }else if(this.container){
            rows = this.container.select(".ajxpNodeProvider");
        }else{
            return [];
        }
		var l = rows.length;
		var a = new Array(l);
		var r;
		for (var i = 0; i < l; i++) {
			r = rows[i];
			a[i] = {
				value:		this.getRowValue(r, sType, nColumn),
				element:	r
			};
		}
		return a;
	},
	
	destroyCache: function (oArray) {
		var l = oArray.length;
		for (var i = 0; i < l; i++) {
			oArray[i].value = null;
			oArray[i].element = null;
			oArray[i] = null;
		}
	},
	
	getRowValue: function (oRow, sType, nColumn) {
        if(!this._sortTypeInfo[sType]) return 0;
		// if we have defined a custom getRowValue use that
        if(this.columnsDefs){
            var colDef = this.columnsDefs[nColumn];
            var attName = colDef['attributeName'];
            var stringValue;
            if(this._sortTypeInfo[sType].getNodeValue){
                stringValue = this._sortTypeInfo[sType].getNodeValue(oRow.ajxpNode, attName);
            }else {
                if(this._sortTypeInfo[sType].getRowValue){
                    stringValue = this._sortTypeInfo[sType].getRowValue(oRow, nColumn, attName);
                }
                if(stringValue === undefined) {
                    stringValue = oRow.ajxpNode.getMetadata().get(attName);
                }
            }
            return this.getValueFromString(stringValue);
        }
        if (this._sortTypeInfo[sType] && this._sortTypeInfo[sType].getRowValue){
            return this._sortTypeInfo[sType].getRowValue(oRow, nColumn);
        }

		var s;
		var c = oRow.cells[nColumn];
        var tC = c.textContent || c.innerText;
		if (c && typeof tC != "undefined")
			s = tC;
		else
			s = this.getInnerText(c);
		return this.getValueFromString(s, sType);
	},
	
	getInnerText: function (oNode) {
		var s = "";
		var cs = oNode.childNodes;
		var l = cs.length;
		for (var i = 0; i < l; i++) {
			switch (cs[i].nodeType) {
				case 1: //ELEMENT_NODE
					s += this.getInnerText(cs[i]);
					break;
				case 3:	//TEXT_NODE
					s += cs[i].nodeValue;
					break;
			}
		}
		return s;
	},
	
	getValueFromString: function (sText, sType) {
		if (this._sortTypeInfo[sType])
			return this._sortTypeInfo[sType].getValueFromString( sText );
		return sText;
		/*
		switch (sType) {
			case "Number":
				return Number(sText);
			case "CaseInsensitiveString":
				return sText.toUpperCase();
			case "Date":
				var parts = sText.split("-");
				var d = new Date(0);
				d.setFullYear(parts[0]);
				d.setDate(parts[2]);
				d.setMonth(parts[1] - 1);
				return d.valueOf();
		}
		return sText;
		*/
	},
	
	getSortFunction: function (sType, nColumn) {
		if (this._sortTypeInfo[sType])
			return this._sortTypeInfo[sType].compare;
		return this.basicCompare;
	},
	
	destroy: function () {
		this.uninitHeader();
		var win = this.document.parentWindow;
		if (win && typeof win.detachEvent != "undefined") {	// only IE needs this
			win.detachEvent("onunload", this._onunload);
		}
		this._onunload = null;
		this.element = null;
		this.tHead = null;
		this.tBody = null;
		this.headerCells = null;
		this.document = null;
		this._headerOnclick = null;
		this.sortTypes = null;
		this._asyncsort = null;
		this.onsort = null;
	},
	
	// Adds a sort type to all instance of SortableTable
	// sType : String - the identifier of the sort type
	// fGetValueFromString : function ( s : string ) : T - A function that takes a
	//    string and casts it to a desired format. If left out the string is just
	//    returned
	// fCompareFunction : function ( n1 : T, n2 : T ) : Number - A normal JS sort
	//    compare function. Takes two values and compares them. If left out less than,
	//    <, compare is used
	// fGetRowValue : function( oRow : HTMLTRElement, nColumn : int ) : T - A function
	//    that takes the row and the column index and returns the value used to compare.
	//    If left out then the innerText is first taken for the cell and then the
	//    fGetValueFromString is used to convert that string the desired value and type
	
	addSortType: function (sType, fGetValueFromString, fCompareFunction, fGetRowValue, fGetNodeValue) {
		this._sortTypeInfo[sType] = {
			type:				sType,
			getValueFromString:	fGetValueFromString || this.idFunction,
			compare:			fCompareFunction || this.basicCompare,
			getRowValue:		fGetRowValue,
            getNodeValue:       fGetNodeValue || null
		};
	},
	
	// this removes the sort type from all instances of SortableTable
	removeSortType: function (sType) {
		delete this._sortTypeInfo[sType];
	},
	
	basicCompare: function compare(n1, n2) {
		if (n1.value < n2.value)
			return -1;
		if (n2.value < n1.value)
			return 1;
		return 0;
	},
	
	idFunction: function (x) {
		return x;
	},
	
	toUpperCase: function (s) {
		return s.toUpperCase();
	},
	
	toDate: function (s) {
		var parts = s.split("-");
		var d = new Date(0);
		d.setFullYear(parts[0]);
		d.setDate(parts[2]);
		d.setMonth(parts[1] - 1);
		return d.valueOf();
	}

});
