(function(global){

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
            console.log(this.state.gpsData);
        },

        render:function(){

            let content, actions=null;
            if(this.state.loaded && !this.state.error){
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
                if(this.state.gpsData){
                    actions = [
                        <MaterialUI.ToolbarGroup firstChild={true}>
                            <MaterialUI.FlatButton label="Locate" onClick={this.openGpsLocator}/>
                        </MaterialUI.ToolbarGroup>
                    ];
                }

            }else if(this.state.loaded && this.state.error){
                content = <div>{this.state.error}</div>
            }else{
                content = <PydioReactUI.Loader/>;
            }


            return (
                <PydioComponents.AbstractEditor {...this.props} actions={actions}>{content}</PydioComponents.AbstractEditor>
            );

        }

    });


    window.PydioExif = {
        Editor: Editor
    };

})(window);