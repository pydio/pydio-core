export default {

    attach: function(node){
        this._nodeListener = function(){
            if(!this.isMounted()) {
                this.detach(node);
                return;
            }
            this.forceUpdate();
        }.bind(this);
        this._actionListener = function(eventMemo){
            if(!this.isMounted()) {
                this.detach(node);
                return;
            }
            if(eventMemo && eventMemo.type === 'prompt-rename' && eventMemo.callback){
                this.setState({inlineEdition:true, inlineEditionCallback:eventMemo.callback});
            }
            return true;
        }.bind(this);
        node.observe("node_replaced", this._nodeListener);
        node.observe("node_action", this._actionListener);
    },

    detach: function(node){
        if(this._nodeListener){
            node.stopObserving("node_replaced", this._nodeListener);
            node.stopObserving("node_action", this._actionListener);
        }
    },

    componentDidMount: function(){
        this.attach(this.props.node);
    },

    componentWillUnmount: function(){
        this.detach(this.props.node);
    },

};

