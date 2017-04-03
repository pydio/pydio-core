/**
 * Search input building a set of query parameters and calling
 * the callbacks to display / hide results
 */
export default React.createClass({

    propTypes:{
        // Required
        parameters:React.PropTypes.object.isRequired,
        queryParameterName:React.PropTypes.string.isRequired,
        // Other
        textLabel:React.PropTypes.string,
        displayResults:React.PropTypes.func,
        hideResults:React.PropTypes.func,
        displayResultsState:React.PropTypes.bool,
        limit:React.PropTypes.number
    },

    getInitialState: function(){
        return {
            displayResult:this.props.displayResultsState?true:false
        };
    },

    getDefaultProps: function(){
        var dm = new PydioDataModel();
        dm.setRootNode(new AjxpNode());
        return {dataModel: dm};
    },

    displayResultsState: function(){
        this.setState({
            displayResult:true
        });
    },

    hideResultsState: function(){
        this.setState({
            displayResult:false
        });
        this.props.hideResults();
    },

    onClickSearch: function(){
        var value = this.refs.query.getValue();
        var dm = this.props.dataModel;
        var params = this.props.parameters;
        params[this.props.queryParameterName] = value;
        params['limit'] = this.props.limit || 100;
        dm.getRootNode().setChildren([]);
        PydioApi.getClient().request(params, function(transport){
            var remoteNodeProvider = new RemoteNodeProvider({});
            remoteNodeProvider.parseNodes(dm.getRootNode(), transport);
            dm.getRootNode().setLoaded(true);
            this.displayResultsState();
            this.props.displayResults(value, dm);
        }.bind(this));
    },

    keyDown: function(event){
        if(event.key == 'Enter'){
            this.onClickSearch();
        }
    },

    render: function(){
        return (
            <div className={(this.props.className?this.props.className:'')}>
                <div style={{paddingTop:22, float:'right', opacity:0.3}}>
                    <ReactMUI.IconButton
                        ref="button"
                        onClick={this.onClickSearch}
                        iconClassName="icon-search"
                        tooltip="Search"
                    />
                </div>
                <div className="searchbox-input-fill" style={{width: 220, float:'right'}}>
                    <ReactMUI.TextField ref="query" onKeyDown={this.keyDown} floatingLabelText={this.props.textLabel}/>
                </div>
            </div>
        );
    }

});

