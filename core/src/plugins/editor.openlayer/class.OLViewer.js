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

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.actions.get("downloadFileButton").observe('click', function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'?action=download&file='+this.currentFile);
			return false;
		}.bind(this));
		this.element.observe('editor:enterFS', function(){this.fullScreenMode = true;}.bind(this) );
	},
	
	
	open : function($super, userSelection){
		$super(userSelection);
		var ajxpNode = userSelection.getUniqueNode();
		this.mapDiv = new Element('div', {id:'openlayer_map', style:'width:100%'});
		this.contentMainContainer = this.mapDiv;
		this.initFilterBar();
		this.element.insert(this.mapDiv);
		fitHeightToBottom($(this.mapDiv), $(modal.elementName));

		if(ajxpNode.getMetadata().get('layer_type') == 'Google' && (!google || !google.maps)){
			alert('Warning, you must add the line \n<script src="http://maps.google.com/maps/api/js?sensor=false"/> \n inside the main application template (client/gui.html) to enable Google Maps.');
			hideLightBox(true);
			return;
		}
		
		var result = this.createOLMap(ajxpNode, 'openlayer_map', false, true);
		this.olMap = result.MAP;
		this.layers = result.LAYERS;
        this.olMap.addControl(new OpenLayers.Control.PanZoomBar({
            position: new OpenLayers.Pixel(5, 5)
        }));		
		this.olMap.addControl(new OpenLayers.Control.Navigation());
		this.olMap.addControl(new OpenLayers.Control.ScaleLine());
		this.olMap.addControl(new OpenLayers.Control.MousePosition({element:this.element.down('span[id="location"]'), numDigits:4}));
	},
			
	resize : function ($super, size){
		$super(size);
		if(!this.fullScreenMode){
			fitHeightToBottom($(this.mapDiv), $(modal.elementName));
		}
		if(this.olMap){
			this.olMap.updateSize();
		}
	},
	
	initFilterBar : function(){
		var bar = this.element.down('div.filter');
		var button = this.element.down('div#filterButton');
		bar.select('select').invoke('setStyle', {width:'80px',height:'18px',fontSize:'11px',marginRight:'5px',border:'1px solid #AAAAAA'});
		bar.select('input').invoke('setStyle', {height:'18px',fontSize:'11px',border:'1px solid #AAAAAA',backgroundImage:'url('+ajxpResourcesFolder+'"/images/locationBg.gif")', backgroundPosition:'left top', backgroundRepeat:'no-repeat'});
		bar.hide();
		this.filterBar = bar;
		button.observe("click", function(e){
			this.toggleFilterBar();
		}.bind(this));
		bar.down('select#tilingModeSelector').observe('change', function(e){
			var tilingMode = e.findElement().getValue();
			var tiled = this.layers[0];
			var untiled = this.layers[1];
	        if (tilingMode == 'tiled') {
	            untiled.setVisibility(false);
	            tiled.setVisibility(true);
	            this.olMap.setBaseLayer(tiled);
	        }
	        else {
	            untiled.setVisibility(true);
	            tiled.setVisibility(false);
	            this.olMap.setBaseLayer(untiled);
	        }
		}.bind(this) );
		bar.down('select#antialiasSelector').observe('change', function(e){
			this.layers.invoke('mergeNewParams', {format_options:'antialias:' + e.findElement().getValue()});
		}.bind(this) );
		bar.down('select#imageFormatSelector').observe('change', function(e){
			this.layers.invoke('mergeNewParams', {format:e.findElement().getValue()});
		}.bind(this) );
		bar.down('select#imageStyleSelector').observe('change', function(e){
			this.layers.invoke('mergeNewParams', {format:e.findElement().getValue()});
		}.bind(this) );
		
		bar.down('img#submitFilter').observe('click', function(e){
			this.updateFilter(bar);
		}.bind(this));
		
		bar.down('img#resetFilter').observe('click', function(e){
			bar.down('input#filter').setValue('');
			this.updateFilter(bar);
		}.bind(this));
		
	},
	
	toggleFilterBar : function(){
		if(this.filterBarShown){
			new Effect.BlindUp(this.filterBar, {duration:0.1,scaleMode:'contents',afterFinish : function(){
				fitHeightToBottom($(this.mapDiv), $(modal.elementName));
				this.resize();			
			}.bind(this) });
			this.filterBarShown = false;
		}else{
			new Effect.BlindDown(this.filterBar, {duration:0.1,scaleMode:'contents',afterFinish : function(){
				fitHeightToBottom($(this.mapDiv), $(modal.elementName));
				this.resize();			
			}.bind(this) });
			this.filterBarShown = true;
		}
	},
		
	updateFilter : function(bar){

		var filterType = bar.down('select#filterType').getValue();
		var filter = bar.down('input#filter').getValue();

		// by default, reset all filters
		var filterParams = {
			filter: null,
			cql_filter: null,
			featureId: null
		};
		if (OpenLayers.String.trim(filter) != "") {
			if (filterType == "cql")
			filterParams["cql_filter"] = filter;
			if (filterType == "ogc")
			filterParams["filter"] = filter;
			if (filterType == "fid")
			filterParams["featureId"] = filter;
		}
		// merge the new filter definitions
		this.layers.invoke('mergeNewParams', filterParams);
	},
	
	createOLMap : function(ajxpNode, targetId, useDefaultControls, dualTileMode){
		var metadata = ajxpNode.getMetadata();
        var map, bound, srs;
        var options;
        if(metadata.get('bbox_minx') && metadata.get('bbox_miny') && metadata.get('bbox_maxx') && metadata.get('bbox_maxy')){
        	bound = new OpenLayers.Bounds(
        		metadata.get('bbox_minx'), 
        		metadata.get('bbox_miny'), 
        		metadata.get('bbox_maxx'), 
        		metadata.get('bbox_maxy')
        	);
        	if(metadata.get('bbox_SRS')){
        		srs = metadata.get('bbox_SRS');
        	}
        	options = {
	        	maxExtent : bound,
	        	projection: srs,
	        	maxResolution: 1245.650390625	
        	};
        }else if(metadata.get('center_lat') && metadata.get('center_long')){        	
        	var center = new OpenLayers.LonLat(parseFloat(metadata.get('center_long')), parseFloat(metadata.get('center_lat')));
        	console.log(center, metadata);
        	if(metadata.get('center_srs')){
        		srs = metadata.get('center_SRS');
        	}
        	options = {
        		projection:srs
        	}
        }
        
        /*
        var options = {
        	maxExtent : bound,
        	projection: srs,
        	maxResolution: 1245.650390625	            	
        };
        */
        if(!useDefaultControls){
        	options.controls = [];
        }
        map = new OpenLayers.Map( targetId, options);
        var layers = $A();
        if(!metadata.get('layer_type') || metadata.get('layer_type') == 'WMS'){
	        var layer = new OpenLayers.Layer.WMS( "AjaXplorer (tiled)",
	                metadata.get('wms_url'), 
	                {
	                	layers: metadata.get('name'), 
	                	styles: metadata.get('style'),
	                	tiled:'true', 
	                	tilesOrigin : map.maxExtent.left + ',' + map.maxExtent.bottom
	                }, 
	                {
	                	buffer:0,
	                	displayOutsideMaxExtent:true
	                }
				);
			layers.push(layer);
			map.addLayer(layer);
			if(dualTileMode){
				var untiled = new OpenLayers.Layer.WMS( "AjaXplorer (untiled)", 
					metadata.get('wms_url'), 
					{
	                	layers: metadata.get('name'), 
	                	styles: metadata.get('style')
					},
					{
						singleTile:true, ratio:1
					}
				);
				layers.push(untiled);
				map.addLayer(untiled);
				untiled.setVisibility(false);
			}       
        }else if(metadata.get('layer_type') == 'Google'){
        	switch(metadata.get('google_type')){
        		case 'physical':
		            var layer = new OpenLayers.Layer.Google(
	                	"Google Physical",
	                	{type: google.maps.MapTypeId.TERRAIN}
		            );
				break;

        		case 'streets':
		            var layer = new OpenLayers.Layer.Google(
		                "Google Streets", // the default
		                {numZoomLevels: 20}
		            );
				break;

        		case 'hybrid':
		            var layer = new OpenLayers.Layer.Google(
		                "Google Hybrid",
		                {type: google.maps.MapTypeId.HYBRID, numZoomLevels: 20}
		            );
				break;

        		case 'satellite':
        		default:
		            var layer = new OpenLayers.Layer.Google(
		                "Google Satellite",
		                {type: google.maps.MapTypeId.SATELLITE, numZoomLevels: 22}
		            );
				break;			
        	}
        	if(layer){
				layers.push(layer);
				map.addLayer(layer);
        	}
        }
        
		if(bound){
			map.zoomToExtent(bound);	        
		}
		else if(center){
			
			var projectedCenter = center.transform(new OpenLayers.Projection("EPSG:4326"),map.getProjectionObject());
			// Add Marker for center!
            var markers = new OpenLayers.Layer.Markers( "Markers" );
            map.addLayer(markers);
            var size = new OpenLayers.Size(22,22);
            var offset = new OpenLayers.Pixel(0, -size.h);
            var icon = new OpenLayers.Icon('plugins/editor.openlayer/services.png',size,offset);
            markers.addMarker(new OpenLayers.Marker(projectedCenter,icon));
			
			map.setCenter(projectedCenter, 10);
		}
		//map.addControl( new OpenLayers.Control.LayerSwitcher() );
		return {MAP: map, LAYERS:layers};
	},		
	
	getPreview : function(ajxpNode, rich){		
		if(rich){
			
			var metadata = ajxpNode.getMetadata();			
			var div = new Element('div', {id:"ol_map", style:"width:100%;height:200px;"});
			div.resizePreviewElement = function(dimensionObject){
				// do nothing;
				div.setStyle({height:'200px'});
				if(div.initialized) return;				
				OLViewer.prototype.createOLMap(ajxpNode, 'ol_map', true);
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