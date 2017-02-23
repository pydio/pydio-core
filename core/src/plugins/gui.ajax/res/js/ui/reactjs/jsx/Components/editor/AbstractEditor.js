let AbstractEditor = React.createClass({

    propTypes: {
        node: React.PropTypes.instanceOf(AjxpNode),
        pydio: React.PropTypes.instanceOf(Pydio),

        errorString: React.PropTypes.string,
        onRequestTabTitleUpdate: React.PropTypes.func,
        onRequestTabClose: React.PropTypes.func,
        actions:React.PropTypes.array
    },

    statics:{
        getSvgSource: function(ajxpNode){
            return ajxpNode.getMetadata().get("fonticon");
        }
    },


    render: function(){

        let actionBar;
        if(this.props.actions !== null){
            actionBar = (
                <MaterialUI.Toolbar>{this.props.actions}</MaterialUI.Toolbar>
            );
        }
        let error;
        if(this.props.errorString){
            error = (
                <div style={{display:'flex',alignItems:'center',width:'100%',height:'100%'}}>
                    <div style={{flex:1,textAlign:'center',fontSize:20}}>{this.props.errorString}</div>
                </div>
            );
        }
        return <div className="vertical_fit vertical_layout">{actionBar}{error}{this.props.children}</div>

    }

})

export {AbstractEditor as default}