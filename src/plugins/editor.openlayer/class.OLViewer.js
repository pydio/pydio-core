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
 * Description : OpenLayer implementation
 */
Class.create("OLViewer", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject){		
	},
		
	getPreview : function(ajxpNode, rich){		
		if(rich){
			mapName = ajxpNode.getMetadata().get('name');
			
			var div = new Element('div', {id:"ol_map", style:"width:100%;height:200px;"});
			div.resizePreviewElement = function(dimensionObject){
				// do nothing;
				if(div.initialized) return;
		        var lon = 5;
		        var lat = 40;
		        var zoom = 5;
		        var map, layer;
		
	            map = new OpenLayers.Map( 'ol_map' );
	            layer = new OpenLayers.Layer.WMS( "Argeo",
	                    ajxpNode.getMetadata().get('wms_url'), 
	                    {layers: ajxpNode.getMetadata().get('name')} );
	            map.addLayer(layer);
	
	            map.setCenter(new OpenLayers.LonLat(lon, lat), zoom);
	            map.addControl( new OpenLayers.Control.LayerSwitcher() );
				div.initialized = true;
			}
			return div;
		}else{
			return new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64)});
		}
	},
	
	getThumbnailSource : function(ajxpNode){
		return resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
	}
	
});