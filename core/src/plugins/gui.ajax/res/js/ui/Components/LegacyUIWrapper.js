export default React.createClass({
    propTypes:{
        componentName:React.PropTypes.string.isRequired,
        componentOptions:React.PropTypes.object,
        onLoadCallback:React.PropTypes.func
    },

    componentDidMount(){
        if(window[this.props.componentName]){
            var element = this.refs.wrapper;
            var options = this.props.componentOptions;
            this.legacyComponent = new window[this.props.componentName](element, options);
            if(this.props.onLoadCallback){
                this.props.onLoadCallback(this.legacyComponent);
            }
        }
    },

    componentWillUnmount(){
        if(this.legacyComponent){
            this.legacyComponent.destroy();
        }
    },

    shouldComponentUpdate: function() {
        // Let's just never update this component again.
        return false;
    },

    render: function(){
        return <div id={this.props.id} className={this.props.className} style={this.props.style} ref="wrapper"></div>;
    }
});
