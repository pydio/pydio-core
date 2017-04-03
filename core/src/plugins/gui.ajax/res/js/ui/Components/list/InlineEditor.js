export default React.createClass({

    mixins:[PydioReactUI.PydioContextConsumerMixin],

    propTypes:{
        node: React.PropTypes.instanceOf(AjxpNode),
        callback:React.PropTypes.func,
        onClose:React.PropTypes.func,
        detached:React.PropTypes.bool
    },

    submit: function(){
        if(!this.state || !this.state.value || this.state.value === this.props.node.getLabel()){
            this.setState({errorString: 'Please use a different value for renaming!'});
            this.context.getPydio().displayMessage('ERROR', 'Please use a different value for renaming!');
        }else{
            this.props.callback(this.state.value);
            this.props.onClose();
        }
    },

    componentDidMount:function(){
        this.refs.text.focus();
    },

    catchClicks: function(e){
        e.stopPropagation();
    },

    onKeyDown: function(e){
        if(e.key === 'Enter') {
            this.submit();
        }
        this.setState({errorString: ''});
        e.stopPropagation();
    },

    render: function(){
        return (
            <ReactMUI.Paper className={"inline-editor" + (this.props.detached ? " detached" : "")} zDepth={2}>
                <MaterialUI.TextField
                    ref="text"
                    defaultValue={this.props.node.getLabel()}
                    onChange={(e, value)=>{this.setState({value:value})}}
                    onClick={this.catch} onDoubleClick={this.catchClicks}
                    tabIndex="0" onKeyDown={this.onKeyDown}
                    errorText={this.state ? this.state.errorString : null}
                />
                <div className="modal-buttons">
                    <ReactMUI.FlatButton label="Cancel" onClick={this.props.onClose}/>
                    <ReactMUI.FlatButton label="Submit" onClick={this.submit}/>
                </div>
            </ReactMUI.Paper>
        );
    }

});

