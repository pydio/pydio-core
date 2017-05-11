const {Component} = require('react');
import AsyncModal from '../modal/AsyncModal'

class CompatModal extends Component{

    constructor(props, context){
        super(props, context);
        this.state = {open: false};
    }

    componentDidMount(){
        const {pydio} = this.props;
        pydio.UI.registerModalOpener(this);
    }

    componentWillUnmount(){
        const {pydio} = this.props;
        pydio.UI.unregisterModalOpener();
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
        this.setState({open: false});
    }

    render(){
        return (
            <AsyncModal
                ref="modal"
                open={this.state.open}
                componentData={{namespace:'PydioReactUI', compName:'CompatMigrationDialog'}}
                onLoad={this.handleLoad.bind(this)}
                onDismiss={this.handleClose.bind(this)}
            />
        );
    }
}

export {CompatModal as default}