import ColorPaper from '../board/ColorPaper'

export default React.createClass({

    mixins: [PydioComponents.DynamicGridItemMixin],

    statics:{
        gridWidth:2,
        gridHeight:10,
        builderDisplayName:'Quick Upload',
        builderFields:[]
    },

    render: function(){
        const title = <MaterialUI.CardTitle title="Quick Upload"/>;

        return (
            <ColorPaper zDepth={1} {...this.props} paletteIndex={0} getCloseButton={this.getCloseButton.bind(this)} >
                <div style={{display:'flex'}}>
                    <div style={{padding: 16, fontSize: 16}}>Drop a file here from your desktop</div>
                    <div style={{textAlign:'center', padding:18}}><span style={{borderRadius:'50%', border: '4px solid white', fontSize:56, padding: 20}} className="mdi mdi-cloud-upload"></span></div>
                </div>
            </ColorPaper>
        );
    }

});