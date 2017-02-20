export default React.createClass({

    propTypes:{
        node: React.PropTypes.instanceOf(AjxpNode),
        callback:React.PropTypes.func,
        onClose:React.PropTypes.func,
        detached:React.PropTypes.bool
    },

    submit: function(){
        if(!this.state || !this.state.value || this.state.value === this.props.node.getLabel()){
            alert('Please use a different value for renaming!');
        }else{
            this.props.callback(this.state.value);
            this.props.onClose();
        }
    },

    focused: function(){
        pydio.UI.disableAllKeyBindings();
    },

    blurred: function(){
        pydio.UI.enableAllKeyBindings();
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
        e.stopPropagation();
    },

    render: function(){
        return (
            <ReactMUI.Paper className={"inline-editor" + (this.props.detached ? " detached" : "")} zDepth={2}>
                <ReactMUI.TextField
                    ref="text"
                    defaultValue={this.props.node.getLabel()}
                    onChange={(e)=>{this.setState({value:e.target.getValue()})}}
                    onFocus={this.focused}
                    onBlur={this.blurred}
                    onClick={this.catch} onDoubleClick={this.catchClicks}
                    tabIndex="0" onKeyDown={this.onKeyDown}
                />
                <div className="modal-buttons">
                    <ReactMUI.FlatButton label="Cancel" onClick={this.props.onClose}/>
                    <ReactMUI.FlatButton label="Submit" onClick={this.submit}/>
                </div>
            </ReactMUI.Paper>
        );
    }

});

