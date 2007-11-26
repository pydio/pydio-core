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
| Dependencies:  This file requires that SelectableElements is first defined. |
| This class can  be found in the file selectableelements.js at WebFX.        |
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

function SelectableTableRows(oTableElement, bMultiple) {
	SelectableElements.call(this, oTableElement, bMultiple);
}
SelectableTableRows.prototype = new SelectableElements;

SelectableTableRows.prototype.isItem = function (node) {
	return node != null && ( node.tagName == "TR" || node.tagName == "tr") &&
		( node.parentNode.tagName == "TBODY" || node.parentNode.tagName == "tbody" )&&
		node.parentNode.parentNode == this._htmlElement;
};

/* Indexable Collection Interface */

SelectableTableRows.prototype.getItems = function () {
	return this._htmlElement.rows;
};

SelectableTableRows.prototype.getItemIndex = function (el) {
	return el.rowIndex;
};

SelectableTableRows.prototype.getItem = function (i) {
	return this._htmlElement.rows[i];
};

/* End Indexable Collection Interface */