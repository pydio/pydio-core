(function(global){

    let DetailPanel = React.createClass({

        parseValues: function(node){
            
            let configs = this.props.pydio.getPluginConfigs('meta.exif');
            if(!configs.has('meta_definitions')){
                return;
            }
            let nodeMeta = node.getMetadata();
            let definitions = configs.get('meta_definitions');
            let items = [];
            let gpsData = {'COMPUTED_GPS-GPS_Latitude':null, 'COMPUTED_GPS-GPS_Longitude':null};
            for(let key in definitions){
                if(!definitions.hasOwnProperty(key)) continue;
                if(nodeMeta.has(key)){
                    let value = nodeMeta.get(key);
                    if(gpsData[key] !== undefined){
                        gpsData[key] = nodeMeta.get(key);
                        value = value.split('--').shift();
                    }
                    items.push({
                        key: key,
                        label:definitions[key],
                        value:value
                    });
                }
            }
            if(gpsData['COMPUTED_GPS-GPS_Longitude'] && gpsData['COMPUTED_GPS-GPS_Latitude']){
                // Special Case
                ResourcesManager.loadClassesAndApply(['OpenLayers', 'PydioMaps'], function(){
                    this.setState({gpsData:gpsData});
                }.bind(this));
            }
            this.setState({items:items});
        },
        
        componentDidMount: function(){
            this.parseValues(this.props.node);
        },
        
        componentWillReceiveProps: function(nextProps){
            if(nextProps.node !== this.props.node){
                this.setState({gpsData:null});
                this.parseValues(nextProps.node);
            }
        },
        
        mapLoaded: function(map, error){
            if(error && console) console.log(error);
        },
        
        openInExifEditor: function(){
            const editor = global.pydio.Registry.findEditorById("editor.exif");
            if(editor){
                global.pydio.UI.openCurrentSelectionInEditor(editor, this.props.node);
            }
        },

        openInMapEditor: function(){
            const editors = global.pydio.Registry.findEditorsForMime("ol_layer");
            if(editors.length){
                global.pydio.UI.openCurrentSelectionInEditor(editors[0], this.props.node);
            }
        },

        render: function(){
            
            let items = [];
            let actions = [];
            if(this.state && this.state.items){
                items = this.state.items.map(function(object){
                    return (
                        <div key={object.key} className="infoPanelRow">
                            <div className="infoPanelLabel">{object.label}</div>
                            <div className="infoPanelValue">{object.value}</div>
                        </div>
                    )
                });

                actions.push(
                    <MaterialUI.FlatButton onClick={this.openInExifEditor} label="More Exif" />
                );
            }
            if(this.state && this.state.gpsData){
                items.push(
                    <PydioReactUI.AsyncComponent
                        namespace="PydioMaps"
                        componentName="OLMap"
                        key="map"
                        style={{height: 170,marginBottom:16}}
                        centerNode={this.props.node}
                        mapLoaded={this.mapLoaded}
                    />
                );
                actions.push(
                    <MaterialUI.FlatButton onClick={this.openInMapEditor} label="Open Map" />
                )
            }
            
            if(!items.length){
                return null;
            }
            return (
                <PydioDetailPanes.InfoPanelCard title="Exif Data" actions={actions}>
                    {items}
                </PydioDetailPanes.InfoPanelCard>
            );

        }

    });

    let Editor = React.createClass({

        componentDidMount: function(){
            this.loadData();
        },

        getInitialState:function(){
            return {loaded: false};
        },
        
        loadData:function(){
            PydioApi.getClient().request({
                get_action:'extract_exif',
                file:this.props.node.getPath(),
                format:'json'
            }, function(transp){
                if(!transp.responseJSON){
                    this.setState({loaded: true, error: 'Could not load JSON'});
                }else{
                    let gpsData;
                    if(transp.responseJSON['COMPUTED_GPS']){
                        let {GPS_Latitude, GPS_Longitude} = transp.responseJSON['COMPUTED_GPS'];
                        gpsData = {
                            latitude:GPS_Latitude.split('--').pop(),
                            longitude:GPS_Longitude.split('--').pop()
                        };
                    }
                    this.setState({loaded: true, data: transp.responseJSON, gpsData:gpsData});
                }
            }.bind(this), function(){
                this.setState({loaded: true, error: 'Could not load data'});
            });
        },

        openGpsLocator: function(){
            const editors = global.pydio.Registry.findEditorsForMime("ol_layer");
            if(editors.length){
                global.pydio.UI.openCurrentSelectionInEditor(editors[0], this.props.node);
            }
        },

        render:function(){

            let content, actions=null, error;
            if(this.state.loaded && !this.state.error) {

                const data = this.state.data;
                let sectionItems = [];
                for(let section in data){
                    if(!data.hasOwnProperty(section)) continue;
                    let items = [];
                    items.push(<MaterialUI.Subheader key={section+'-head'}>{section}</MaterialUI.Subheader>);
                    for(let keyName in data[section]){
                        if(!data[section].hasOwnProperty(keyName)) continue;
                        let label = <span><span style={{fontWeight:500}}>{keyName}</span> : {data[section][keyName]}</span>;
                        items.push(<MaterialUI.ListItem key={section + '-' +keyName} primaryText={label}/>);
                    }
                    sectionItems.push(<MaterialUI.List key={section}>{items}</MaterialUI.List>);
                    sectionItems.push(<MaterialUI.Divider/>);
                }
                content = (
                    <div className="vertical_fit" style={{overflowY:'auto'}}>
                        {sectionItems}
                    </div>
                );

            }else if(this.state.loaded && this.state.error){

                error = <div>{this.state.error}</div>

            }else{

                content = <PydioReactUI.Loader/>;

            }

            if(this.state.gpsData){
                actions = [
                    <MaterialUI.ToolbarGroup firstChild={true}>
                        <MaterialUI.FlatButton label={"Locate on a map"} onClick={this.openGpsLocator}/>
                    </MaterialUI.ToolbarGroup>
                ];
            }

            return (
                <PydioComponents.AbstractEditor
                    {...this.props}
                    actions={actions}
                    errorString={error}>
                    {content}
                </PydioComponents.AbstractEditor>
            );

        }

    });


    window.PydioExif = {
        Editor: Editor,
        DetailPanel:DetailPanel
    };

})(window);