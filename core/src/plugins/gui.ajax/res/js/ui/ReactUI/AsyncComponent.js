import React, {Component} from 'react';
import ResourcesManager from 'pydio/http/resources-manager';

import _ from 'lodash';

/********************/
/* ASYNC COMPONENTS */
/********************/
/**
 * Load a component from server (if not already loaded) based on its namespace.
 */
class AsyncComponent extends Component {

    constructor(props) {
        super(props)

        this.state = {
            loaded: false
        }

        this._handleLoad = _.debounce(this._handleLoad, 100)
    }

    _handleLoad() {
        const callback = () => {
            if (this.instance && !this.loadFired && typeof this.props.onLoad === 'function') {
                this.props.onLoad(this.instance)
                this.loadFired = true
            }
        }

        if (!this.state.loaded) {
            // Loading the class asynchronously
            ResourcesManager.loadClassesAndApply([this.props.namespace], () => {
                this.setState({loaded:true});
                callback();
            })
        } else {
            // Class is already available, just doing the callback
            callback();
        }
    }

    componentDidMount() {
        this._handleLoad();
    }

    componentWillReceiveProps(newProps) {
        if (this.props.namespace != newProps.namespace) {
            this.loadFired = false;
            this.setState({loaded:false});
        }
    }

    componentDidUpdate() {
        this._handleLoad();
    }

    render() {
        if (!this.state.loaded) return null

        let props = this.props
        const {namespace, componentName, modalData} = props
        const nsObject = window[this.props.namespace];
        const Component = FuncUtils.getFunctionByName(this.props.componentName, window[this.props.namespace]);

        if (Component) {
            if (modalData && modalData.payload) {
                props = {
                    ...props,
                    ...modalData.payload
                }
            }

            return <Component {...props} ref={(instance) => { this.instance = instance; }} />;

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
