/*
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
 */
/**
 * AjaXplorer Encapsulation of the Sortable Table
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
		this.addSortType( "StringDirFile", this.toUpperCase, false, this.splitDirsAndFiles.bind(this) );		
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
			if(columnsDefs[i]['field_name'] == crtOrderName){
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
			params.set('order_column', column['field_name'] || cellColumn);
			params.set('order_direction', (this.descending?'desc':'asc'));
			this.paginationLoaderFunc(params);
		}else{
			this.sort(cellColumn);
		}
	},
	
	/**
	 * Function for handling Bytes / Binary
	 * @param str String
	 * @returns String
	 */
	replace8a8: function(str) {
		str = str.toUpperCase();
		var splitstr = "____";
		var ar = str.replace(
			/(([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?)(.*)/,
		 "$1"+splitstr+"$4").split(splitstr);
		var num = Number(ar[0]).valueOf();
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
	 * @returns String
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
		if (typeof c.innerText != "undefined")
			s = c.innerText;
		else
			s = this.getInnerText(c);
		if(s[0] == ' ') s = s.substr(1, (s.length-1));	
		if(oRow.getAttribute('is_file') == '0' || oRow.getAttribute('is_file') == 'false'){		
			s = '000'+s;
		}
		return s.toUpperCase();
	},

	/**
	 * If the cell has a sorter_value attribute, use this as sorting
	 * @param oRow HTMLElement Row
	 * @param nColumn Integer
	 * @returns String
	 */
	cellSorterValue : function(oRow, nColumn){
		var tds = oRow.select('td');
		if(tds[nColumn] && tds[nColumn].readAttribute('sorter_value')){
			return parseInt(tds[nColumn].readAttribute('sorter_value'));
		}
	},
	
	/**
	 * Sort by ajxp_modiftime
	 * @param oRow HTMLElement Row
	 * @param nColumn Integer
	 * @returns String
	 */
	sortTimes : function(oRow, nColumn){
		if(oRow.ajxp_modiftime){
			return oRow.ajxp_modiftime;
		}
	}
	
});