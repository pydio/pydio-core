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
 * Description : Static class for renderers
 */
Class.create("ExifCellRenderer", {	
	initialize: function(){
	},
	
	infoPanelModifier : function(htmlElement){
		var latiCell = htmlElement.down('#ip_COMPUTED_GPS-GPS_Latitude');
		var longiCell = htmlElement.down('#ip_COMPUTED_GPS-GPS_Longitude');
		if(latiCell && longiCell && latiCell.innerHTML && longiCell.innerHTML){
			var object = new ExifCellRenderer();
			object.transformGeoCells(latiCell, longiCell);
		}		
	},
	
	transformGeoCells : function(latiCell, longiCell){
		var split = latiCell.innerHTML.split('--');
		latiCell.update(split[0]);
		latiCell.setAttribute("latiDegree", split[1]);
		split = longiCell.innerHTML.split('--');
		longiCell.update(split[0]);
		longiCell.setAttribute("longiDegree", split[1]);
		var button = new Element('div', {
			className:'fakeUploadButton',
			style:'padding-top:3px;width:50px;margin-bottom:0px;padding-bottom:3px;'
		}).update('<img src="plugins/meta.exif/world.png"><br>'+MessageHash['meta.exif.2']);
		var buttonCell = new Element('td', {
			rowspan:2,
			align:'center',
			valign:'center',
			style:'padding:2px;width:60px;'
		}).update(button);
		latiCell.insert({after:buttonCell});		
		// Set all other cells colspan to 2.
		latiCell.up().nextSiblings().each(function(tr){
			tr.down('td.infoPanelValue').setAttribute('colspan', 2);
		});
		button.observe("click", function(){
			this.openLocator(latiCell.getAttribute('latiDegree'), longiCell.getAttribute("longiDegree"));
		}.bind(this) );		
	},
	
	openLocator : function(latitude, longitude){
		// console.log(latitude, longitude);
		// Call openLayer editor!
		// TEST : WestHausen : longitude=10.2;latitude = 48.9;
		var editors = ajaxplorer.findEditorsForMime("ol_layer");
		if(editors.length){
			editorData = editors[0];							
		}					
		if(editorData){
			// Update ajxpNode with Google Layer!
			var ajxpNode = ajaxplorer.getUserSelection().getUniqueNode();
			var metadata = ajxpNode.getMetadata();
			ajxpNode.setMetadata(metadata.merge({
				'ol_layers' : [{type:'Google', google_type:'hybrid'}, {type:'Google', google_type:'streets'}, {type:'OSM'}],
				'ol_center' : {latitude:parseFloat(latitude),longitude:parseFloat(longitude)}
			}));
			ajaxplorer.loadEditorResources(editorData.resourcesManager);
			modal.openEditorDialog(editorData);
		}
		
	}
});