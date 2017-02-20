/**
 * Get info from Pydio controller an build an
 * action bar with active actions.
 * TBC
 */
export default React.createClass({

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        node:React.PropTypes.instanceOf(AjxpNode).isRequired,
        actions:React.PropTypes.array
    },

    clickAction: function(event){
        var actionName = event.currentTarget.getAttribute("data-action");
        this.props.dataModel.setSelectedNodes([this.props.node]);
        var a = window.pydio.Controller.getActionByName(actionName);
        a.fireContextChange(this.props.dataModel, true, window.pydio.user);
        //a.fireSelectionChange(this.props.dataModel);
        a.apply([this.props.dataModel]);
        event.stopPropagation();
        event.preventDefault();
    },

    render: function(){
        var actions = this.props.actions.map(function(a){
            return(
                <div
                    key={a.options.name}
                    className={a.options.icon_class+' material-list-action-inline' || ''}
                    title={a.options.title}
                    data-action={a.options.name}
                    onClick={this.clickAction}></div>
            );
        }.bind(this));
        return(
            <span>
                    {actions}
                </span>
        );

    }
});

