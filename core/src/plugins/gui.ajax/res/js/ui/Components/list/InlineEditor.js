const React = require('react')
const Pydio = require('pydio')
const AjxpNode = require('pydio/model/node')
const {PydioContextConsumer} = Pydio.requireLib('boot')
const {Paper, TextField, FlatButton} = require('material-ui')

let InlineEditor = React.createClass({

    propTypes:{
        node        : React.PropTypes.instanceOf(AjxpNode),
        callback    : React.PropTypes.func,
        onClose     : React.PropTypes.func,
        detached    : React.PropTypes.bool
    },

    submit: function(){
        if(!this.state || !this.state.value || this.state.value === this.props.node.getLabel()){
            this.setState({errorString: 'Please use a different value for renaming!'});
            this.props.getPydio().displayMessage('ERROR', 'Please use a different value for renaming!');
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
            <Paper className={"inline-editor" + (this.props.detached ? " detached" : "")} style={{padding: 8}} zDepth={2}>
                <TextField
                    ref="text"
                    defaultValue={this.props.node.getLabel()}
                    onChange={(e, value)=>{this.setState({value:value})}}
                    onClick={this.catch} onDoubleClick={this.catchClicks}
                    tabIndex="0" onKeyDown={this.onKeyDown}
                    errorText={this.state ? this.state.errorString : null}
                />
                <div style={{textAlign:'right', paddingTop: 8}}>
                    <FlatButton label="Cancel" onClick={this.props.onClose}/>
                    <FlatButton label="Submit" onClick={this.submit}/>
                </div>
            </Paper>
        );
    }

});

InlineEditor = PydioContextConsumer(InlineEditor)

export {InlineEditor as default}