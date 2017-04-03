import MessagesConsumerMixin from '../MessagesConsumerMixin'

/**
 * Pagination component reading metadata "paginationData" from current node.
 */
export default React.createClass({

    mixins:[MessagesConsumerMixin],

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        node:React.PropTypes.instanceOf(AjxpNode)
    },

    componentDidMount: function(){
        if(!this.props.node){
            let dm = this.props.dataModel;
            this._dmObserver = function(){
                this.setState({node: dm.getContextNode()});
            }.bind(this);
            dm.observe("context_changed", this._dmObserver);
            this.setState({node: dm.getContextNode()});
        }
    },

    componentWillUnmount: function(){
        if(this._dmObserver){
            this.props.dataModel.stopObserving("context_changed", this._dmObserver);
        }
    },

    getInitialState: function(){
        return { node: this.props.node };
    },

    changePage: function(event){
        this.state.node.getMetadata().get("paginationData").set("new_page", event.currentTarget.getAttribute('data-page'));
        this.props.dataModel.requireContextChange(this.state.node);
    },

    onMenuChange:function(event, index, item){
        this.state.node.getMetadata().get("paginationData").set("new_page", item.payload);
        this.props.dataModel.requireContextChange(this.state.node);
    },

    render: function(){
        if(!this.state.node || !this.state.node.getMetadata().get("paginationData")) {
            return null;
        }
        var pData = this.state.node.getMetadata().get("paginationData");
        var current = parseInt(pData.get("current"));
        var total = parseInt(pData.get("total"));
        var pages = [], next, last, previous, first;
        var pageWord = this.context.getMessage ? this.context.getMessage('331', '') : this.props.getMessage('331', '');
        for(var i=1; i <= total; i++){
            pages.push({payload:i, text:pageWord + ' ' +i + (i == current?(' / ' + total ): '')});
        }
        if(pages.length <= 1){
            return null;
        }
        let sep;
        if(this.props.toolbarDisplay){
            if(current > 1) previous = <span className="toolbars-button-menu"><ReactMUI.IconButton onClick={this.changePage} data-page={current-1} iconClassName="icon-caret-left" /></span>;
            if(current < total) next = <span className="toolbars-button-menu"><ReactMUI.IconButton onClick={this.changePage} data-page={current+1} iconClassName="icon-caret-right" /></span>;
        }else{
            if(current > 1) previous = <ReactMUI.FontIcon onClick={this.changePage} data-page={current-1} className="icon-angle-left" />;
            if(current < total) next = <ReactMUI.FontIcon onClick={this.changePage} data-page={current+1} className="icon-angle-right" />;
            sep = <span className="mui-toolbar-separator">&nbsp;</span>;
        }
        return (
            <span id={this.props.id} style={this.props.style}>
                    {first}
                {previous}
                <ReactMUI.DropDownMenu onChange={this.onMenuChange} menuItems={pages} selectedIndex={current-1} />
                {next}
                {last}
                {sep}
            </span>
        );
    }

});

