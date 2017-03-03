export default React.createClass({

    componentDidMount: function(){
        this.props.pydio.observe('connection-start', () => { this.setState({show: true}) });
        this.props.pydio.observe('connection-end', () => { this.setState({show: false}) });
    },

    getInitialState: function(){
        return {show: false};
    },

    render: function(){
        const style = {
            display: this.state.show?'block':'none'
        };
        return (
            <div className="indeterminate-loader" style={style}/>
        );

    }

});