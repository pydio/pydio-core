let AbstractEditor = React.createClass({

    propTypes: {
        node: React.PropTypes.instanceOf(AjxpNode),
        pydio: React.PropTypes.instanceOf(Pydio),

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

        return <div className="vertical_fit vertical_layout">{actionBar}{this.props.children}</div>

    }

})

export {AbstractEditor as default}