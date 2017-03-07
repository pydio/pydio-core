import InfoPanelCard from './InfoPanelCard'
import FilePreview from '../FilePreview'

let GenericFile = React.createClass({

    render: function(){

        const meta = this.props.node.getMetadata();
        let size = meta.get('bytesize');
        let hSize = PathUtils.roundFileSize(parseInt(size));
        let time = meta.get('ajxp_modiftime');
        var date = new Date();
        date.setTime(parseInt(meta.get('ajxp_modiftime')) * 1000);
        let formattedDate = PathUtils.formatModifDate(date);

        let stdData = [
            {key:'size',label:'Imgae Size',value:hSize},
            {key:'date',label:'Modified on',value:formattedDate}
        ];

        const themePalette = this.props.muiTheme.palette;
        const tBarStyle = {
            backgroundColor: themePalette.primary1Color
        };

        return (
            <span>
                <InfoPanelCard>
                    <FilePreview
                        key={this.props.node.getPath()}
                        style={{height:200}}
                        node={this.props.node}
                        loadThumbnail={true}
                        richPreview={true}
                    />
                    <PydioMenus.Toolbar
                        toolbarStyle={tBarStyle}
                        className="primaryToolbar"
                        renderingType="button-icon"
                        toolbars={["info_panel", "info_panel_share"]}
                        controller={this.props.pydio.getController()}
                    />
                </InfoPanelCard>
                <InfoPanelCard title="File Information" standardData={stdData}/>
            </span>
        );
    }

});

GenericFile = MaterialUI.Style.muiThemeable()(GenericFile);

export {GenericFile as default}