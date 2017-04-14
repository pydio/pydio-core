import Palette from '../board/Palette'
import ColorPaper from '../board/ColorPaper'

export default React.createClass({

    mixins: [PydioComponents.DynamicGridItemMixin],

    statics:{
        gridWidth:2,
        gridHeight:10,
        builderDisplayName:'Qr Code',
        builderFields:[]
    },


    render: function(){

        let jsonData = {
            "server"    : window.location.href.split('welcome').shift(),
            "user"      : this.props.pydio.user ? this.props.pydio.user.id : null
        }

        return (
            <ColorPaper {...this.props} style={{...this.props.style,display:'flex'}} paletteIndex={2} getCloseButton={this.getCloseButton}>
                <div style={{padding: 16, fontSize: 16, paddingRight: 8, overflow:'hidden'}}>{this.props.pydio.MessageHash['user_home.74']}</div>
                <div className="home-qrCode" style={{display:'flex', justifyContent:'center', alignItems:'center', marginRight:16}}>
                    <ReactQRCode bgColor={Palette[2]} fgColor={'#ffffff'} value={JSON.stringify(jsonData)} size={80}/>
                </div>
            </ColorPaper>
        );

    }

});
