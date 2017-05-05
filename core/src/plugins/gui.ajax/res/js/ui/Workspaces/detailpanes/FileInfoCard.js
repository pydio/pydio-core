import React from 'react'
import Pydio from 'pydio'
import InfoPanelCard from './InfoPanelCard'
const {PydioContextConsumer} = Pydio.requireLib('boot')

class FileInfoCard extends React.Component {

    render() {

        const {node, getMessage} = this.props;
        const meta = node.getMetadata();

        let size = meta.get('bytesize');
        let hSize = PathUtils.roundFileSize(parseInt(size));
        let time = meta.get('ajxp_modiftime');
        var date = new Date();
        date.setTime(parseInt(meta.get('ajxp_modiftime')) * 1000);
        let formattedDate = PathUtils.formatModifDate(date);

        let data = [
            {key:'size',label:getMessage('2'),value:hSize},
            {key:'date',label:getMessage('4'),value:formattedDate}
        ];

        let w = meta.get('image_width');
        let h = meta.get('image_height');
        if(w && h){
            data = [
                ...data,
                {key:'image', label:getMessage('135'), value:w + 'px X ' + h + 'px'}
            ]
        }

        return (
            <InfoPanelCard
                {...this.props}
                title={getMessage('341')}
                standardData={data}
                contentStyle={{paddingBottom: 10}}
                icon="information-outline"
                iconColor="#2196f3"
            />
        );
    }
}

FileInfoCard = PydioContextConsumer(FileInfoCard);
export {FileInfoCard as default}
