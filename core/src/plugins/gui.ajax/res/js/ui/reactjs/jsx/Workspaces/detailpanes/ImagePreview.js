import InfoPanelCard from './InfoPanelCard'

export default React.createClass({

    render: function(){
        const meta = this.props.node.getMetadata();
        let w = meta.get('image_width');
        let h = meta.get('image_height');
        let dimDiv;
        if(w & h){
            return (
                <InfoPanelCard title="Image Dimension" icon="image" iconColor="#D32F2F">
                    <div style={{padding:'20px 20px 40px',fontSize:20, color:'rgba(0,0,0,0.53)', textAlign:'center'}}>{w} px X {h} px</div>
                </InfoPanelCard>
            );
        }else{
            return null;
        }
    }

});
