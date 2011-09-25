/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
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
			style:'padding:2px;width:60px;',
            className:'ip_geo_cell'
		}).update(button);
		latiCell.insert({after:buttonCell});		
		// Set all other cells colspan to 2.
		latiCell.up().nextSiblings().each(function(tr){
			tr.down('td.infoPanelValue').setAttribute('colspan', 2);
		});
		longiCell.setAttribute("colspan", "1");
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