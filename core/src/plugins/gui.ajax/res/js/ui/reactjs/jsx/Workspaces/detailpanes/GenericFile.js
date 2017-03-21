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
            {key:'size',label:'File Size',value:hSize},
            {key:'date',label:'Modified on',value:formattedDate}
        ];

        return (
            <span>
                <InfoPanelCard {...this.props}
                               primaryToolbars={["info_panel", "info_panel_share"]}>
                    <FilePreview
                        key={this.props.node.getPath()}
                        style={{height:200}}
                        node={this.props.node}
                        loadThumbnail={true}
                        richPreview={true}
                    />
                </InfoPanelCard>
                <InfoPanelCard title="File Information" standardData={stdData} contentStyle={{paddingBottom: 10}}/>
            </span>
        );
    }

});

export {GenericFile as default}