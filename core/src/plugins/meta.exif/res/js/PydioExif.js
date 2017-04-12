import React, {Component} from 'react'

import {ToolbarGroup, FlatButton, Card, CardTitle, CardText, Table, TableBody, TableRow, TableRowColumn} from 'material-ui'

class DetailPanel extends Component {

    parseValues(node){
        const configs = this.props.pydio.getPluginConfigs('meta.exif');
        if(!configs.has('meta_definitions')){
            return;
        }

        const nodeMeta = node.getMetadata();
        const definitions = configs.get('meta_definitions');

        let items = Object.keys(definitions)
            .filter(key => nodeMeta.has(key))
            .map(key => ({key, label: definitions[key], value: nodeMeta.get(key).split('--').shift()}))

        let gpsData = ["COMPUTED_GPS-GPS_Latitude", "COMPUTED_GPS-GPS_Longitude"]
            .filter(key => nodeMeta.has(key))
            .map((key) => ({key, value: nodeMeta.get(key)}))
            .reduce((obj, cur) => ({...obj, [cur.key]: cur.value }), {});

        if(gpsData['COMPUTED_GPS-GPS_Longitude'] && gpsData['COMPUTED_GPS-GPS_Latitude']){
            // Special Case
            ResourcesManager.loadClassesAndApply(['OpenLayers', 'PydioMaps'], () => this.setState({gpsData}));
        }

        this.setState({items});
    }

    componentDidMount() {
        this.parseValues(this.props.node);
    }

    componentWillReceiveProps(nextProps){
        if(nextProps.node !== this.props.node){
            this.setState({gpsData:null});
            this.parseValues(nextProps.node);
        }
    }

    mapLoaded(map, error){
        if (error && console) console.log(error);
    }

    openInExifEditor() {
        const {pydio, node} = this.props

        const editor = pydio.Registry.findEditorById("editor.exif");
        if (editor) {
            pydio.UI.openCurrentSelectionInEditor(editor, node);
        }
    }

    openInMapEditor() {
        const {pydio, node} = this.props

        const editors = pydio.Registry.findEditorsForMime("ol_layer");
        if (editors.length) {
            pydio.UI.openCurrentSelectionInEditor(editors[0], node);
        }
    }

    render(){

        let items = [];
        let actions = [];
        if (this.state && this.state.items) {

            const fields = this.state.items.map(function(object){
                return (
                    <div key={object.key} className="infoPanelRow" style={{float:'left', width: '50%', padding: '0 4px 12px', whiteSpace:'nowrap'}}>
                        <div className="infoPanelLabel">{object.label}</div>
                        <div className="infoPanelValue">{object.value}</div>
                    </div>
                )
            });
            items.push(<div style={{padding: '0 12px'}}>{fields}</div>)
            items.push(<div style={{clear:'left'}}></div>)

            actions.push(
                <MaterialUI.FlatButton onClick={() => this.openInExifEditor()} label="More Exif" />
            );
        }
        if (this.state && this.state.gpsData) {
            items.push(
                <PydioReactUI.AsyncComponent
                    namespace="PydioMaps"
                    componentName="OLMap"
                    key="map"
                    style={{height: 170,marginBottom:0, padding:0}}
                    centerNode={this.props.node}
                    mapLoaded={this.mapLoaded}
                />
            );
            actions.push(
                <MaterialUI.FlatButton onClick={() => this.openInMapEditor()} label="Open Map" />
            )
        }

        if (!items.length) {
            return null;
        }
        return (
            <PydioWorkspaces.InfoPanelCard style={this.props.style} title="Exif Data" actions={actions} icon="camera" iconColor="#607d8b">
                {items}
            </PydioWorkspaces.InfoPanelCard>
        );

    }
}

class Editor extends Component {

    constructor(props) {
        super(props)

        this.state = {
            data: [],
            error: ""
        }
    }

    componentDidMount() {
        const callback = (object) => {
            this.setState(object)
            typeof this.props.onLoad === 'function' && this.props.onLoad()
        }

        PydioApi.getClient().request({
            get_action:'extract_exif',
            file:this.props.node.getPath(),
            format:'json'
        },
        ({responseJSON}) => responseJSON ? callback({data: responseJSON}) : callback({error: 'Could not load JSON'}),
        () => callback({error: 'Could not load data'}));
    }

    openGpsLocator() {
        const {pydio, node} = this.props

        const editors = pydio.Registry.findEditorsForMime("ol_layer");
        if (editors.length) {
            pydio.UI.openCurrentSelectionInEditor(editors[0], this.props.node);
        }
    }

    render() {
        let content;

        const {data, error} = this.state;

        return (
            <Viewer
                {...this.props}
                actions={
                    <ToolbarGroup firstChild={true}>
                        <FlatButton label={"Locate on a map"} onClick={() => this.openGpsLocator()}/>
                    </ToolbarGroup>
                }
                error={error}
                style={{display: "flex", justifyContent: "space-around", flexFlow: "row wrap"}}
            >
                {Object.keys(data).map(key =>
                    <Card style={{width: "calc(50% - 20px)", margin: 10, overflow: "auto"}}>
                        <CardTitle key={key+'-head'}>{key}</CardTitle>

                        <CardText>
                            <Table selectable={false}>
                                <TableBody displayRowCheckbox={false}>
                                {Object.keys(data[key]).map(itemKey =>
                                    <TableRow key={`${key}-${itemKey}`}>
                                        <TableRowColumn>{itemKey}</TableRowColumn>
                                        <TableRowColumn>{data[key][itemKey]}</TableRowColumn>
                                    </TableRow>
                                )}
                                </TableBody>
                            </Table>
                        </CardText>
                    </Card>
                )}
            </Viewer>
        );
    }
}

let Viewer = (props) => {
    return (
        <div {...props} />
    )
}

// Define HOCs
Viewer = PydioHOCs.withActions(Viewer);
Viewer = PydioHOCs.withLoader(Viewer)
Viewer = PydioHOCs.withErrors(Viewer)

window.PydioExif = {
    Editor: Editor,
    DetailPanel:DetailPanel
};
