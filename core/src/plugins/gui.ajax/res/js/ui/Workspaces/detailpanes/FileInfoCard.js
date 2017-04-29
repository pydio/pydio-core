import React from 'react'
import InfoPanelCard from './InfoPanelCard'

class FileInfoCard extends React.Component {
    constructor(props) {
        super(props)

        const meta = props.node.getMetadata();

        let size = meta.get('bytesize');
        let hSize = PathUtils.roundFileSize(parseInt(size));
        let time = meta.get('ajxp_modiftime');
        var date = new Date();
        date.setTime(parseInt(meta.get('ajxp_modiftime')) * 1000);

        let formattedDate = PathUtils.formatModifDate(date);

        let data = [
            {key:'size',label:'File Size',value:hSize},
            {key:'date',label:'Modified on',value:formattedDate}
        ];

        let w = meta.get('image_width');
        let h = meta.get('image_height');
        if(w && h){
            data = [
                ...data,
                {key:'image', label:'Image Dimension', value:w + 'px X ' + h + 'px'}
            ]
        }

        this.state = {
            data: data
        }
    }

    render() {
        return (
            <InfoPanelCard {...this.props} title="File Information" standardData={this.state.data} contentStyle={{paddingBottom: 10}} icon="information-outline" iconColor="#2196f3"/>
        );
    }
}

export {FileInfoCard as default}
