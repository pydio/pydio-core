import InfoPanelCard from './InfoPanelCard'

let GenericDir = React.createClass({

    render: function(){

        const themePalette = this.props.muiTheme.palette;
        const tBarStyle = {
            backgroundColor: themePalette.primary1Color
        };

        return (
            <InfoPanelCard {...this.props} primaryToolbars={["info_panel", "info_panel_share"]}>
                <div className="mimefont-container" style={{width:'100%', height:200}}>
                    <div className={"mimefont mdi mdi-" + this.props.node.getMetadata().get('fonticon')}></div>
                </div>
            </InfoPanelCard>
        );
    }

});

GenericDir = MaterialUI.Style.muiThemeable()(GenericDir);

export {GenericDir as default}