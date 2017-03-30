(function(global){

    const OLMap = React.createClass({

        propTypes: {
            centerPoint:React.PropTypes.object,
            centerNode:React.PropTypes.instanceOf(AjxpNode),
            centerSRS:React.PropTypes.string,
            defaultControls:React.PropTypes.bool,

            onMapLoaded:React.PropTypes.func
        },

        getDefaultProps: function(){
            return {
                centerSRS: 'EPSG:4326',
                defaultControls: true
            }
        },

        attachMap : function(){

            // PARSE METADATA
            if(this.state && this.state.map){
                this.state.map.destroy();
            }

            let layersDefinitions = [{type:'OSM'}], latitude, longitude;

            if(this.props.centerPoint){
                latitude = this.props.centerPoint['latitude'];
                longitude = this.props.centerPoint['longitude'];
            }else if(this.props.centerNode){
                let meta = this.props.centerNode.getMetadata();
                if(meta.has("COMPUTED_GPS-GPS_Latitude") && meta.has("COMPUTED_GPS-GPS_Longitude")) {
                    latitude = parseFloat(meta.get("COMPUTED_GPS-GPS_Latitude").split('--').pop());
                    longitude = parseFloat(meta.get("COMPUTED_GPS-GPS_Longitude").split('--').pop());
                }
            }
            if(!latitude || !longitude){
                if(this.props.onMapLoaded){
                    this.props.onMapLoaded(null, 'Could not find latitude / longitude');
                }
                return;
            }
            let meta_center = new OpenLayers.LonLat(longitude, latitude);
            let meta_srs = this.props.centerSRS;

            // Check Google layer
            var mapsFound = (global.google && global.google.maps?true:false);
            var googleRejected = false;
            layersDefinitions.map(function(definition, key){
                if(definition.type=='Google'){
                    if(!mapsFound){
                        layersDefinitions = LangUtils.arrayWithout(layersDefinitions, key);
                        googleRejected = true;
                        return;
                    }
                    meta_srs = 'EPSG:900913';
                }
            });
            if(googleRejected){
                var remainingLength = layersDefinitions.length;
                if(!remainingLength){ // Switch to OSM by default.
                    layersDefinitions.push({type:'OSM'});
                    meta_srs = 'EPSG:4326';
                }
            }


            var options = {projection:meta_srs};
            if(!this.props.useDefaultControls){
                options.controls = [];
            }
            var map = new OpenLayers.Map(this.refs.target, options);

            var layers = [];
            layersDefinitions.map(function(definition){
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
                                {type: global.google.maps.MapTypeId.TERRAIN}
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
                                {type: global.google.maps.MapTypeId.HYBRID, numZoomLevels: 20}
                            );
                            break;

                        case 'satellite':
                        default:
                            layer = new OpenLayers.Layer.Google(
                                "Google Satellite",
                                {type: global.google.maps.MapTypeId.SATELLITE, numZoomLevels: 22}
                            );
                            break;
                    }
                }
                if(layer){
                    map.addLayer(layer);
                    layers.push(layer);
                }
            });


            let projectedCenter = meta_center.transform(new OpenLayers.Projection("EPSG:4326"),map.getProjectionObject());
            // Add Marker for center!
            var markers = new OpenLayers.Layer.Markers( "Markers" );
            map.addLayer(markers);
            var size = new OpenLayers.Size(22,22);
            var offset = new OpenLayers.Pixel(0, -size.h);
            var icon = new OpenLayers.Icon('plugins/editor.openlayer/res/services.png',size,offset);
            markers.addMarker(new OpenLayers.Marker(projectedCenter,icon));
            try{
                map.setCenter(projectedCenter, 10);
            }catch(e){
                if(console) console.error(e);
            }

            this.setState({map: map, layers: layers});
            global.setTimeout(function(){map.updateSize()}, 300);
            if(this.props.onMapLoaded){
                this.props.onMapLoaded(map);
            }
        },

        componentDidMount: function(){
            this.attachMap();
        },

        componentWillReceiveProps: function(nextProps){
            if(nextProps.centerNode !== this.props.centerNode){
                this.attachMap();
            }
        },

        componentWillUnmount: function(){
            if(this.state && this.state.map){
                this.state.map.destroy();
            }
        },

        render: function(){

            let style = {width:'100%',height:'100%'};
            if(this.props.style){
                style = Object.assign(style, this.props.style);
            }
            return <div style={style} ref="target"></div>;

        }

    });

    const Viewer = React.createClass({

        onMapLoaded: function(map, error = null){

            if(error){
                this.setState({error: error});
            }else{
                let location = this.refs.location;
                map.addControl(new OpenLayers.Control.PanZoomBar({
                    position: new OpenLayers.Pixel(5, 5)
                }));
                map.addControl(new OpenLayers.Control.Navigation());
                map.addControl(new OpenLayers.Control.ScaleLine());
                map.addControl(new OpenLayers.Control.MousePosition({element:location, numDigits:4}));
            }
        },

        render: function(){

            let errorString = null
            let actions = null

            if(this.state && this.state.error) {
                error = "No GPS data found"
            } else {
                actions = [
                    <div>{this.props.pydio.MessageHash['openlayer.3']}: <span ref="location"/></div>
                ];
            }

            return (
                <ExtendedOLMap ref="mapObject" actions={actions} error={errorString} centerNode={this.props.node} onMapLoaded={this.onMapLoaded}/>
            );
        }
    });

    const ExtendedOLMap = PydioHOCs.withActions(PydioHOCs.withErrors(OLMap))

    global.PydioMaps = {
        Viewer: Viewer,
        OLMap: OLMap
    };


})(window);
