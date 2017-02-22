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
        if(this.props.actions){
            actionBar = (
                <MaterialUI.Toolbar>
                    <MaterialUI.ToolbarGroup>
                        {this.props.actions}
                    </MaterialUI.ToolbarGroup>
                </MaterialUI.Toolbar>
            );
        }

        return <div className="vertical_fit vertical_layout">{actionBar}{this.props.children}</div>

    }

})

export {AbstractEditor as default}