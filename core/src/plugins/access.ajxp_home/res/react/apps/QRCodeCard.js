const React = require('react')
const ReactQRCode = require('qrcode.react')
const {asGridItem} = require('pydio').requireLib('components')

import Palette from '../board/Palette'
import ColorPaper from '../board/ColorPaper'

let QRCodeCard = React.createClass({

    render: function(){

        let jsonData = {
            "server"    : window.location.href.split('welcome').shift(),
            "user"      : this.props.pydio.user ? this.props.pydio.user.id : null
        }

        return (
            <ColorPaper {...this.props} style={{...this.props.style,display:'flex'}} paletteIndex={2} closeButton={this.props.closeButton}>
                <div style={{padding: 16, fontSize: 16, paddingRight: 8, overflow:'hidden'}}>{this.props.pydio.MessageHash['user_home.74']}</div>
                <div className="home-qrCode" style={{display:'flex', justifyContent:'center', alignItems:'center', marginRight:16}}>
                    <ReactQRCode bgColor={Palette[2]} fgColor={'#ffffff'} value={JSON.stringify(jsonData)} size={80}/>
                </div>
            </ColorPaper>
        );

    }

});

QRCodeCard = asGridItem(QRCodeCard,global.pydio.MessageHash['user_home.72'],{gridWidth:2,gridHeight:10},[]);

export {QRCodeCard as default}