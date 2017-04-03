import PydioContextConsumerMixin from '../PydioContextConsumerMixin'

let MessageBar = React.createClass({

    mixins:[PydioContextConsumerMixin],

    componentDidMount: function(){
        this.context.getPydio().UI.registerMessageBar(this);
    },

    componentWillUnmount: function(){
        this.context.getPydio().UI.unregisterMessageBar();
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
            <MaterialUI.Snackbar
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

export {MessageBar as default}
