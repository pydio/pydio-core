const {Component} = require('react');
import AsyncModal from './AsyncModal'

class Modal extends Component{

    constructor(props, context){
        super(props, context);
        this.state = {open: false};
    }

    activityObserver(activityState){
        if(activityState.activeState === 'warning'){
            if(this.state.open && this.state.modalData && this.state.modalData.compName === 'ActivityWarningDialog'){
                return;
            }
            this.open('PydioReactUI', 'ActivityWarningDialog', {activityState:activityState});
        }else{
            this.setState({open: false, modalData:null});
        }
    }

    componentDidMount(){
        const {pydio} = this.props;
        pydio.UI.registerModalOpener(this);
        this._activityObserver = this.activityObserver.bind(this);
        pydio.observe('activity_state_change', this._activityObserver);
    }

    componentWillUnmount(){
        const {pydio} = this.props;
        pydio.UI.unregisterModalOpener();
        pydio.stopObserving('activity_state_change', this._activityObserver);
    }

    open(namespace, component, props) {
        this.setState({
            open: true,
            modalData:{
                namespace: namespace,
                compName: component,
                payload: props
            }
        });
    }

    handleLoad() {
        this.setState({open: true})
    }

    handleClose() {
        if(this.state.open && this.state.modalData && this.state.modalData.compName === 'ActivityWarningDialog'){
            this.props.pydio.notify('user_activity');
        }
        this.setState({open: false});
    }

    render(){
        return (
            <AsyncModal
                ref="modal"
                open={this.state.open}
                componentData={this.state.modalData}
                onLoad={this.handleLoad.bind(this)}
                onDismiss={this.handleClose.bind(this)}
            />
        );
    }
}

export {Modal as default}