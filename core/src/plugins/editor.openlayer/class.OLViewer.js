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
Class.create("OLViewer", AbstractEditor, {

	initialize: function($super, oFormObject, options)
	{
		$super(oFormObject, options);
		this.element.observe('editor:enterFS', function(){this.fullScreenMode = true;}.bind(this) );
	},
	
	
	open : function($super, node){
		$super(node);
        this.updateTitle(getBaseName(node.getPath()));
		this.mapDiv = new Element('div', {id:'openlayer_map', style:'width:100%'});
		this.contentMainContainer = this.mapDiv;
		this.initFilterBar((node.getAjxpMime() == 'wms_layer'));
		this.element.insert(this.mapDiv);
		fitHeightToBottom($(this.mapDiv), $(modal.elementName));
        
		var result = this.createOLMap(node, 'openlayer_map', false, true);
		this.olMap = result.MAP;
		this.layers = result.LAYERS;
		this.refreshLayersSwitcher();
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
			fitHeightToBottom($(this.element));
			fitHeightToBottom($(this.mapDiv), $(this.editorOptions.context.elementName));
		}
		if(this.olMap){
			this.olMap.updateSize();
		}
	},
	
	initFilterBar : function(wmsFiltersActive){
		var bar = this.element.down('div.filter');
		var button = this.element.down('div#filterButton');
		bar.select('select').invoke('setStyle', {width:'80px',height:'18px',fontSize:'11px',marginRight:'5px',border:'1px solid #AAAAAA'});
		bar.select('input').invoke('setStyle', {height:'18px',fontSize:'11px',border:'1px solid #AAAAAA',backgroundImage:'url('+ajxpResourcesFolder+'"/images/locationBg.gif")', backgroundPosition:'left top', backgroundRepeat:'no-repeat'});
		bar.hide();
		this.filterBar = bar;
		button.observe("click", function(e){
			this.toggleFilterBar();
		}.bind(this));
		bar.down('select#layerSelector').observe('change', function(e){
			var selected = e.findElement().getValue();
			var baseLayer='';
			this.layers.each(function(layer){
				if(layer.name == selected){
					baseLayer = layer;
					layer.setVisibility(true);
				}else{
					layer.setVisibility(false);
				}
			});
			this.olMap.setBaseLayer(baseLayer);
		}.bind(this) );
		if(!wmsFiltersActive){
			bar.down('#wms_filters').hide();
			bar.setStyle({textAlign:'right'});
			return;
		}
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
	
	refreshLayersSwitcher : function(){
		var selector = this.filterBar.down('select#layerSelector');
		if(this.layers.length < 2){
			selector.disabled = true;
			return;
		}
		this.layers.each(function(layer){
			selector.insert(new Element('option', {value:layer.name}).update(layer.name));
		});
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
		
		// PARSE METADATA
		var metadata = ajxpNode.getMetadata();
		var layersDefinitions;
		if(metadata.get('ajxp_mime') == 'wms_layer'){			
			layersDefinitions = $A([
				{type:'WMS',tile:true,wms_url:metadata.get('wms_url'),name:metadata.get('name'),style:metadata.get('style')}
				]);
			if(dualTileMode){
				layersDefinitions.push({
					type:'WMS',tile:false,wms_url:metadata.get('wms_url'),name:metadata.get('name'),style:metadata.get('style')
				});
			}
		}else{
			layersDefinitions = $A(metadata.get('ol_layers'));
		}
		var meta_srs,meta_bound,meta_center;
        if(metadata.get('bbox_minx') && metadata.get('bbox_miny') && metadata.get('bbox_maxx') && metadata.get('bbox_maxy')){
        	meta_bound = new OpenLayers.Bounds(
        		metadata.get('bbox_minx'), 
        		metadata.get('bbox_miny'), 
        		metadata.get('bbox_maxx'), 
        		metadata.get('bbox_maxy')
        	);
        	if(metadata.get('bbox_SRS')){
        		meta_srs = metadata.get('bbox_SRS');
        	}
        }else if(metadata.get('ol_center')){
        	var ol_center = metadata.get('ol_center');
        	meta_center = new OpenLayers.LonLat(ol_center.longitude, ol_center.latitude);
        	if(metadata.get('center_srs')){
        		meta_srs = metadata.get('center_SRS');
        	}else{
        		meta_srs = 'EPSG:4326'; // Default SRS
        	}
        }
        	
        // Check Google layer
        var mapsFound = (window.google && window.google.maps?true:false);
        var googleRejected = false;
        layersDefinitions.each(function(definition){        	
        	if(definition.type=='Google'){
        		if(!mapsFound){        		
        			layersDefinitions = layersDefinitions.without(definition);
        			googleRejected = true;
        			return;
        		}
        		meta_srs = 'EPSG:900913';
        	}
        });                
		if(googleRejected){
			var remainingLength = layersDefinitions.size();
			if(!remainingLength){ // Switch to OSM by default.
				layersDefinitions.push({type:'OSM'});
				meta_srs = 'EPSG:4326';
			}
		}
		
		
        var options = {projection:meta_srs};
        if(meta_bound){
        	options.maxExtent = meta_bound;
        	options.maxResolution =  1245.650390625;
        }
        if(!useDefaultControls){
        	options.controls = [];
        }
        var map = new OpenLayers.Map(targetId, options);
        var layers = $A();
        layersDefinitions.each(function(definition){
        	var layer;
        	if(definition.type == 'WMS'){
        		var title;
        		var layerDef = {
        			layers : definition.name,
        			styles : definition.style
        		};
        		var layerUrl = definition.wms_url;
        		if(definition.tile){
	        		title = "Tiled";
	        		layerDef.tiled = true;
	        		if(meta_bound) layerDef.tilesOrigin = map.maxExtent.left + ',' + map.maxExtent.bottom;
	                options = {buffer:0,displayOutsideMaxExtent:true};	        		
        		}else{
        			title = "Single Tile";
        			options = {singleTile:true, ratio:1};
        		}
        		layer = new OpenLayers.Layer.WMS(title, layerUrl, layerDef, null);
        	}else if(definition.type == 'OSM'){
	        	layer = new OpenLayers.Layer.OSM();
        	}else if(definition.type == 'Google'){
	        	switch(definition.google_type){
	        		case 'physical':
			            layer = new OpenLayers.Layer.Google(
		                	"Google Physical",
		                	{type: google.maps.MapTypeId.TERRAIN}
			            );
					break;
	
	        		case 'streets':
			            layer = new OpenLayers.Layer.Google(
			                "Google Streets", // the default
			                {numZoomLevels: 20}
			            );
					break;
	
	        		case 'hybrid':
			            layer = new OpenLayers.Layer.Google(
			                "Google Hybrid",
			                {type: google.maps.MapTypeId.HYBRID, numZoomLevels: 20}
			            );
					break;
	
	        		case 'satellite':
	        		default:
			            layer = new OpenLayers.Layer.Google(
			                "Google Satellite",
			                {type: google.maps.MapTypeId.SATELLITE, numZoomLevels: 22}
			            );
					break;			
	        	}
        	}
        	if(layer){
	        	map.addLayer(layer);
	        	layers.push(layer);
        	}
        });
        
		if(meta_bound){
			map.zoomToExtent(meta_bound);	        
		}
		else if(meta_center){			
			var projectedCenter = meta_center.transform(new OpenLayers.Projection("EPSG:4326"),map.getProjectionObject());
			// Add Marker for center!
            var markers = new OpenLayers.Layer.Markers( "Markers" );
            map.addLayer(markers);
            var size = new OpenLayers.Size(22,22);
            var offset = new OpenLayers.Pixel(0, -size.h);
            var icon = new OpenLayers.Icon('plugins/editor.openlayer/services.png',size,offset);
            markers.addMarker(new OpenLayers.Marker(projectedCenter,icon));
			
			map.setCenter(projectedCenter, 10);
		}		
		return {MAP: map, LAYERS:layers};
	},		
	
	getPreview : function(ajxpNode, rich){		
		if(rich){
			
			var div = new Element('div', {id:"ol_map", style:"width:100%;height:200px;"});
			div.resizePreviewElement = function(dimensionObject){
				div.setStyle({height:'200px'});
				if(div.initialized) return;				
				OLViewer.prototype.createOLMap(ajxpNode, 'ol_map', true);
				div.initialized = true;
			};
			return div;
		}else{
			return new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64)});
		}
	},
	
	getThumbnailSource : function(ajxpNode){
		return resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
	}
	
});