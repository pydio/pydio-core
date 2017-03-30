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

        const style = {
            ...this.props.style,
            backgroundColor: this.props.tint,
            color: 'white',
            display:'flex'
        };

        return (
            <MaterialUI.Paper zDepth={1} {...this.props} transitionEnabled={false} style={style}>
                {this.getCloseButton()}
                <div style={{padding: 16, fontSize: 16}}>{this.props.pydio.MessageHash['user_home.74']}</div>
                <div className="home-qrCode" style={{display:'flex', justifyContent:'center', alignItems:'center', marginRight:16}}>
                    <ReactQRCode bgColor={style.backgroundColor} fgColor={style.color} value={JSON.stringify(jsonData)} size={80}/>
                </div>
            </MaterialUI.Paper>
        );

    }

});