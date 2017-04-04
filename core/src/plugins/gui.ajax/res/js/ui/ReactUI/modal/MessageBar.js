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
        return (
            <Snackbar
                open={this.state.open}
                message={this.state.message || ''}
                onRequestClose={this.handleClose}
                autoHideDuration={4000}
                action={this.state.actionLabel}
                onActionTouchTap={this.state.actionCallback}
            />
        );
    }
});

MessageBar = PydioContextConsumer(MessageBar);

export {MessageBar as default}
