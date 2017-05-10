import ActionRunnerMixin from '../mixins/ActionRunnerMixin'
const React = require('react')

export default React.createClass({

    mixins:[ActionRunnerMixin],

    getInitialState:function(){
        let loadingMessage = 'Loading';
        if(this.context && this.context.getMessage){
            loadingMessage = this.context.getMessage(466, '');
        }else if(global.pydio && global.pydio.MessageHash){
            loadingMessage = global.pydio.MessageHash[466];
        }
        return {status:loadingMessage};
    },

    componentDidMount:function(){
        const callback = function(transport){
            this.setState({status:transport.responseText});
        }.bind(this);
        this._poller = function(){
            this.applyAction(callback);
        }.bind(this);
        this._poller();
        this._pe = global.setInterval(this._poller, 10000);
    },

    componentWillUnmount:function(){
        if(this._pe){
            global.clearInterval(this._pe);
        }
    },

    render: function(){
        return (<div>{this.state.status}</div>);
    }

});