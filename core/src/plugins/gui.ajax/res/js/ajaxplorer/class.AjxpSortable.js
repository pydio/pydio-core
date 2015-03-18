/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

/**
 * Pydio Encapsulation of the Sortable Table
 */
Class.create("AjxpSortable", SortableTable, {

	/**
	 * Constructor
	 * @param $super klass Superclass
	 * @param oTable HTMLElement Table tag
	 * @param oSortTypes Array The sort types of the defined columns
	 * @param oTHead HTMLElement The Head of the columns
	 */
	initialize: function($super, oTable, oSortTypes, oTHead) {
		$super(oTable, oSortTypes, oTHead);
		this.addSortType( "NumberK", this.replace8a8 );
		this.addSortType( "NumberKo", this.replace8oa8 );
		this.addSortType( "MyDate", null, false, this.sortTimes);
		this.addSortType( "CellSorterValue", null, false, this.cellSorterValue);
		this.addSortType( "StringDirFile", this.toUpperCase, false, this.splitDirsAndFiles.bind(this), this.splitDirsAndFilesNodes.bind(this) );
	},
		
	/**
	 * How the sorting and the pagination interfere
	 * @param loaderFunc Function Callback called on sorting
	 * @param columnsDefs Array All columns definition
	 * @param crtOrderName String The current column of ordering
	 * @param crtOrderDir String ASC or DESC
	 */
	setPaginationBehaviour : function(loaderFunc, columnsDefs, crtOrderName, crtOrderDir){
		this.paginationLoaderFunc = loaderFunc;
		this.columnsDefs = columnsDefs;
		var found = -1;
		for(var i=0;i<columnsDefs.length;i++){
			if(columnsDefs[i]['attributeName'] == crtOrderName){
				found = i;
				break;
			}
		}
		this.sortColumn = found;
		this.descending = (crtOrderDir == 'desc');
		this.updateHeaderArrows();
	},
	
	/**
	 * Listener for header click
	 * @param e Event The click event
	 */
	headerOnclick: function (e) {

		var el = Event.findElement(e, 'div.header_cell');
		var cellColumn = el.cellIndex;
		
		if(this.paginationLoaderFunc){
			var params = $H({});
			if (this.sortColumn != cellColumn){
				this.descending = this.defaultDescending;
			}else{
				this.descending = !this.descending;
			}
			var column = this.columnsDefs[cellColumn];
			params.set('order_column', column['attributeName'] || cellColumn);
			params.set('order_direction', (this.descending?'desc':'asc'));
			this.paginationLoaderFunc(params);
		}else{
			this.sort(cellColumn);
		}
	},
	
	/**
	 * Function for handling Bytes / Binary
	 * @param str String
	 * @returns Number
	 */
	replace8a8: function(str) {
		str = str.toUpperCase();
		var splitstr = "____";
		var ar = str.replace(
			/(([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?)(.*)/,
		 "$1"+splitstr+"$4").split(splitstr);
        var num;
        num = Number(ar[0]).valueOf();
		if(ar[1]){
			var ml = ar[1].replace(/\s*([KMGB])\s*/, "$1");
		
			if (ml == "K")
				num *= 1024;
			else if(ml == "M")
				num *= 1024 * 1024;
			else if (ml == "G")
				num *= 1024 * 1024 * 1024;
			else if (ml == "T")
				num *= 1024 * 1024 * 1024 * 1024;
			// B and no prefix
		}	
		return num;
	},
	/**
	 * Handling Bytes
	 * @param str String
	 */
	replace8oa8: function(str) {
		str = str.toUpperCase();
		if(str == "-")
		{
			return 0;
		}
		var splitstr = "____";
		var ar = str.replace(
			/(([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?)(.*)/,
		 "$1"+splitstr+"$4").split(splitstr);
		var num = Number(ar[0]).valueOf();
		if(ar[1]){
			var ml = ar[1].replace(/\s*(KO|MO|GO|T|KB|MB|GB)\s*/, "$1");
		
			if (ml == "KO" || ml == "KB")
				num *= 1024;
			else if(ml == "MO" || ml == "MB")
				num *= 1024 * 1024;
			else if (ml == "GO" || ml == "GB")
				num *= 1024 * 1024 * 1024;
			else if (ml == "T")
				num *= 1024 * 1024 * 1024 * 1024;
			// B and no prefix
		}
		return num;
	},
	/**
	 * Sorting function for dates
	 * @param s String
	 * @returns Number
	 */
	replaceDate: function(s) {
		var parts1 = s.split(" ");
		
		var parts = parts1[0].split("/");
		var d = new Date(0);
		d.setFullYear(parts[2]);
		d.setDate(parts[0]);
		d.setMonth(parts[1] - 1);
		
		var hours = parts1[1].split(":");
		d.setHours(hours[0]);
		d.setMinutes(hours[1]);	
		return d.getTime();
	},
	
	/**
	 * Sort dirs and files each on their side
	 * @param oRow HTMLElement Row
	 * @param nColumn Integer
	 * @returns String
	 */
	splitDirsAndFiles: function(oRow, nColumn) {
		var s;
		var c = oRow.cells[nColumn];
        var cT = c.textContent || c.innerText;
		if (typeof cT != "undefined")
			s = cT;
		else
			s = this.getInnerText(c);

		if(s[0] == ' ') s = s.substr(1, (s.length-1));	
		if(!oRow.ajxpNode.isLeaf()){
			s = '000'+s;
		}
		return s.toUpperCase();
	},

    /**
     * Sort dirs and files each on their side
     * @returns String
     * @param oNode
     * @param attName
     */
	splitDirsAndFilesNodes: function(oNode, attName) {
		var s;
        if(attName == "ajxp_label") attName = "text";
        s = oNode.getMetadata().get(attName);
		if(s[0] == ' ') s = s.substr(1, (s.length-1));
		if(!oNode.isLeaf()){
			s = '000'+s;
		}
		return s.toUpperCase();
	},

	/**
	 * If the cell has a sorter_value attribute, use this as sorting
	 * @param oRow HTMLElement Row
	 * @param nColumn Integer
	 * @returns Integer|String
	 */
	cellSorterValue : function(oRow, nColumn, attName){
        if(attName && (oRow.getAttribute('data-'+attName+'-sorter_value') || oRow.down('[data-'+attName+'-sorter_value]') )){
            if(oRow.down('[data-'+attName+'-sorter_value]')) return parseInt( oRow.down('[data-'+attName+'-sorter_value]').getAttribute('data-'+attName+'-sorter_value') );
            else return oRow.getAttribute('data-'+attName+'-sorter_value');
        }
		var tds = oRow.select('td');
		if(tds[nColumn] && tds[nColumn].getAttribute('data-sorter_value')){
			return parseInt(tds[nColumn].getAttribute('data-sorter_value'));
		}
        return 0;
	},

	/**
	 * Sort by ajxp_modiftime
	 * @param oRow HTMLElement Row
	 * @param nColumn Integer
	 * @returns Integer
	 */
	sortTimes : function(oRow, nColumn){
        if(oRow.ajxpNode && oRow.ajxpNode.getMetadata().get("ajxp_modiftime")){
            return parseInt(oRow.ajxpNode.getMetadata().get("ajxp_modiftime"));
        }
        return 0;
	}

});