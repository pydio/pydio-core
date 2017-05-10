const React = require('react')
const {Snackbar} = require('material-ui')

import PydioContextConsumer from '../PydioContextConsumer'

let MessageBar = React.createClass({

    componentDidMount: function(){
        this.props.getPydio().UI.registerMessageBar(this);
    },

    componentWillUnmount: function(){
        this.props.getPydio().UI.unregisterMessageBar();
    },

    error:function(message, actionLabel, actionCallback) {
        this.setState({
            open: true,
            errorStatus: true,
            message: message,
            actionLabel: actionLabel,
            actionCallback: actionCallback
        });
    },

    info:function(message, actionLabel, actionCallback) {
        this.setState({
            open: true,
            errorStatus: false,
            message: message,
            actionLabel: actionLabel,
            actionCallback: actionCallback
        });
    },

    getInitialState:function(){
        return {open: false};
    },

    handleClose: function() {
        this.setState({open: false});
    },

    render: function(){
        let message = this.state.message || '';
        if(message.split('\n').length){
            message = (
                <span>{message.split('\n').map((m) => {
                    return <div>{m}</div>;
                })}</span>
            );
        }
        return (
            <Snackbar
                open={this.state.open}
                message={message}
                onRequestClose={this.handleClose}
                autoHideDuration={4000}
                action={this.state.actionLabel}
                onActionTouchTap={this.state.actionCallback}
                bodyStyle={{padding:'16px 24px', height:'auto', maxHeight:200, overflowY:'auto', lineHeight:'inherit'}}
            />
        );
    }
});

MessageBar = PydioContextConsumer(MessageBar);

export {MessageBar as default}
