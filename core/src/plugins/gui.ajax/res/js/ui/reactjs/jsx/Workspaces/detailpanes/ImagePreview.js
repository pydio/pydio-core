import InfoPanelCard from './InfoPanelCard'

export default React.createClass({

    render: function(){
        const meta = this.props.node.getMetadata();
        let w = meta.get('image_width');
        let h = meta.get('image_height');
        let dimDiv;
        if(w & h){
            return (
                <InfoPanelCard title="Image Dimension">
                    <div className={"img-dimension-div " + (w > h ? "landscape":(w===h ?"square":"portrait"))} data-width={w + ' px'} data-height={h + ' px'} style={{marginBottom:16}}/>
                </InfoPanelCard>
            );
        }else{
            return null;
        }
    }

});
