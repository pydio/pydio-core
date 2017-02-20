(function(global){

    export default React.createClass({

        getDefaultProps: function(){
            return {
                toolbarGroups: ['mfb'],
                highlight: 'upload'
            };
        },

        getInitialState: function(){
            return {actions: []};
        },

        actionsChange: function(){
            let controller = global.pydio.Controller;
            let actions = controller.getContextActions('genericContext', null, this.props.toolbarGroups);
            this.setState({actions: actions});
        },

        componentDidMount: function(){
            this._listener = this.actionsChange.bind(this);
            global.pydio.observe("context_changed", this._listener);
        },

        componentWillUnmount: function(){
            global.pydio.stopObserving("context_changed", this._listener);
        },

        close: function(e){
            this.refs.menu.toggleMenu(e);
        },

        render: function(){

            if(!this.state.actions.length){
                return null;
            }

            let children = [];
            let close = this.close.bind(this);
            let hl = null, hlName = this.props.highlight;
            this.state.actions.map(function(a){
                if(a.separator) return;
                let cb = function(e){
                    close(e);
                    a.callback();
                };
                if(hlName && a.action_id === hlName){
                    hl = <ReactMFB.ChildButton icon={a.icon_class} label={a.alt} onClick={cb}/>;
                } else{
                    children.push(<ReactMFB.ChildButton icon={a.icon_class} label={a.alt} onClick={cb}/>);
                }
            });
            if(hl) children.push(hl);

            return (
                <ReactMFB.Menu effect="slidein" position="tl" icon="mdi mdi-file" ref="menu">
                    <ReactMFB.MainButton iconResting="mdi mdi-plus" iconActive="mdi mdi-close"/>
                    {children}
                    <span className="hiddenOverlay"/>
                </ReactMFB.Menu>
            );
        }
    });


})(window);