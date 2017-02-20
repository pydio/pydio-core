import AsyncModal from './AsyncModal'

let Modal = React.createClass({

    componentDidMount: function(){
        this.props.pydio.UI.registerModalOpener(this);
    },

    componentWillUnmount: function(){
        this.props.pydio.UI.unregisterModalOpener();
    },

    open:function(namespace, component, props){
        this.setState({
            open: true,
            modalData:{
                namespace:namespace,
                compName: component,
                payload: props
            }
        });
    },

    getInitialState:function(){
        return {open: false};
    },

    handleClose: function(){
        this.setState({open: false});
    },

    render: function(){
        return (
            <MaterialUI.MuiThemeProvider>
                <AsyncModal
                    ref="modal"
                    open={this.state.open}
                    componentData={this.state.modalData}
                    onDismiss={this.handleClose}
                />
            </MaterialUI.MuiThemeProvider>

        );
    }

});

export {Modal as default}