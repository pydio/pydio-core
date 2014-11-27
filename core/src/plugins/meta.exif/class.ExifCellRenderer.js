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
Class.create("ExifCellRenderer", {	
	initialize: function(){
	},
	
	infoPanelModifier : function(htmlElement){
		var latiCell = htmlElement.down('#ip_COMPUTED_GPS-GPS_Latitude');
		var longiCell = htmlElement.down('#ip_COMPUTED_GPS-GPS_Longitude');
		if(latiCell && longiCell && latiCell.innerHTML && longiCell.innerHTML){
			var object = new ExifCellRenderer();
			object.transformGeoCells(latiCell, longiCell);
		}else if(latiCell && longiCell){
            latiCell.up('div.infoPanelTable').up('div').hide();
        }
	},
	
	transformGeoCells : function(latiCell, longiCell){
		var split = latiCell.innerHTML.split('--');
		latiCell.update(split[0]);
		latiCell.setAttribute("latiDegree", split[1]);
		split = longiCell.innerHTML.split('--');
		longiCell.update(split[0]);
		longiCell.setAttribute("longiDegree", split[1]);
        var decorator = '<img src="plugins/meta.exif/world.png" style="margin-bottom: 0;">';
        if(ajaxplorer.currentThemeUsesIconFonts){
            decorator = '<span class="icon-map-marker" style="font-size: 2em;"></span>';
        }
		var button = new Element('div', {
			className:'fakeUploadButton',
			style:'padding-top:6px;width:50px;margin-bottom:0px;padding-bottom:3px;text-align:center; font-size: 11px;'
		}).update( decorator + '<br>'+ MessageHash['meta.exif.2']);
		var buttonCell = new Element('div', {
			rowspan:2,
			align:'center',
			valign:'center',
			style:'padding:2px;width:60px;',
            className:'ip_geo_cell'
		}).update(button);
		latiCell.insert({after:buttonCell});
        latiCell.up('div').setStyle({position: 'relative'});
		// Set all other cells colspan to 2.
		latiCell.up().nextSiblings().each(function(tr){
			tr.down('div.infoPanelValue').setAttribute('colspan', 2);
		});
		longiCell.setAttribute("colspan", "1");
        var clicker = function(){
            this.openLocator(latiCell.getAttribute('latiDegree'), longiCell.getAttribute("longiDegree"));
        }.bind(this);
		button.observe("click", clicker);
        try{
            var userMetaButton = latiCell.up('div.infoPanelTable').previous('div.infoPanelGroup').down('span.user_meta_change');
            userMetaButton.observe("click", clicker);
        }catch(e){

        }

        var editors = ajaxplorer.findEditorsForMime("ol_layer");
        var editorData;
        if(editors.length){
            editorData = editors[0];
        }
        if(editorData){
            var ajxpNode = ajaxplorer.getUserSelection().getUniqueNode();
            var metadata = ajxpNode.getMetadata();
            ajxpNode.setMetadata(metadata.merge({
                'ol_layers' : [{type:'Google', google_type:'hybrid'}, {type:'Google', google_type:'streets'}, {type:'OSM'}],
                'ol_center' : {latitude:parseFloat(latiCell.getAttribute('latiDegree')),longitude:parseFloat(longiCell.getAttribute("longiDegree"))}
            }));
            var  id = "small_map_" + Math.random();
            latiCell.up('div.infoPanelTable').insert({top:'<div id="'+id+'" style="height: 250px;"></div>'});
            ajaxplorer.loadEditorResources(editorData.resourcesManager);
            OLViewer.prototype.createOLMap(ajxpNode, id, false, false);
        }

	},
	
	openLocator : function(latitude, longitude){
		// console.log(latitude, longitude);
		// Call openLayer editor!
		// TEST : WestHausen : longitude=10.2;latitude = 48.9;
		var editors = ajaxplorer.findEditorsForMime("ol_layer");
        var editorData;
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
            ajaxplorer.openCurrentSelectionInEditor(editorData);
		}
		
	}
});