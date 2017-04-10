const React = require('react');
const ResourcesManager = require('pydio/http/resources-manager');
/********************/
/* ASYNC COMPONENTS */
/********************/
/**
 * Load a component from server (if not already loaded) based on its namespace.
 */
class AsyncComponent extends React.Component {

    constructor(props) {
        super(props)

        this.state = {
            loaded: false
        }
    }

    _asyncLoad() {
        ResourcesManager.loadClassesAndApply([this.props.namespace], function() {
            this.setState({loaded:true});

            /*if(this.refs['component'] && this.props.onLoad && !this.loadFired){
                this.props.onLoad(this.refs['component']);
                this.loadFired = true;
            }*/
        }.bind(this));
    }

    componentDidMount() {
        this._asyncLoad();
    }

    componentWillReceiveProps(newProps) {
        if (this.props.namespace != newProps.namespace) {
            this.loadFired = false;
            this.setState({loaded:false});
        }
    }

    componentDidUpdate() {
        if (!this.state.loaded) {
            this._asyncLoad();
        /*}else{
            if(this.refs['component'] && this.props.onLoad && !this.loadFired){
                this.props.onLoad(this.refs['component']);
                this.loadFired = true;
            }*/
        }
    }

    render() {

        if (!this.state.loaded) return null

        let props = this.props
        const {namespace, componentName, modalData} = props
        const nsObject = window[this.props.namespace];
        const component = FuncUtils.getFunctionByName(this.props.componentName, window[this.props.namespace]);

        if (component) {
            if (modalData && modalData.payload) {
                props = {
                    ...props,
                    ...modalData.payload
                }
            }

            return React.createElement(component, {...props});

        } else {
            return <div>Component {namespace}.{componentName} not found!</div>;
        }
    }
}

AsyncComponent.propTypes = {
    namespace: React.PropTypes.string.isRequired,
    componentName: React.PropTypes.string.isRequired
}

// AsyncComponent = PydioHOCs.withLoader(AsyncComponent)

export {AsyncComponent as default}
