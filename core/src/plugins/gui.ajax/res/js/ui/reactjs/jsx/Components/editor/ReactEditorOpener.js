/**
 * Opens an oldschool Pydio editor in React context, based on node mime type.
 * @type {*|Function}
 */
export default React.createClass({

    propTypes:{
        node:React.PropTypes.instanceOf(AjxpNode),
        registry:React.PropTypes.instanceOf(Registry).isRequired,
        closeEditorContainer:React.PropTypes.func.isRequired,
        editorData:React.PropTypes.object,
        registerCloseCallback:React.PropTypes.func,
        onRequestTabTitleUpdate:React.PropTypes.func
    },

    getInitialState: function(){
        return {editorData: null};
    },

    _getEditorData: function(node){
        var selectedMime = PathUtils.getAjxpMimeType(node);
        var editors = this.props.registry.findEditorsForMime(selectedMime, false);
        if(editors.length && editors[0].openable){
            return editors[0];
        }
    },

    closeEditor: function(){
        if(this.editor){
            var el = this.editor.element;
            this.editor.destroy();
            try{el.remove();}catch(e){}
            this.editor = null;
        }
        if(this.props.closeEditorContainer() !== false){
            this.setState({editorData: null, node:null});
        }
    },

    loadEditor: function(node, editorData){
        this._blockUpdates = false;

        if(this.editor){
            this.closeEditor();
        }
        if(!editorData){
            editorData = this._getEditorData(node);
        }
        if(editorData) {
            this.props.registry.loadEditorResources(editorData.resourcesManager, function(){
                this.setState({editorData: editorData, node:node}, this._loadPydioEditor);
            }.bind(this));
        }else{
            this.setState({editorData: null, node:null}, this._loadPydioEditor);
        }
    },

    componentDidMount:function(){
        if(this.props.node) {
            this.loadEditor(this.props.node, this.props.editorData);
        }
    },

    componentWillReceiveProps:function(newProps){
        this._blockUpdates = false;
        if(newProps.node && newProps.node !== this.props.node) {
            this.loadEditor(newProps.node, newProps.editorData);
        }else if(newProps.node && newProps.node === this.props.node){
            this._blockUpdates = true;
        }
    },

    componentDidUpdate:function(){
        if(this.editor && this.editor.resize){
            this.editor.resize();
        }
    },

    componentWillUnmount:function(){
        if(this.editor){
            this.editor.destroy();
            this.editor = null;
        }
    },

    shouldComponentUpdate:function(){
        if(this._blockUpdates){
            return false;
        }else{
            return true;
        }
    },

    _loadPydioEditor: function(){
        if(this.editor){
            this.editor.destroy();
            this.editor = null;
        }
    },

    render: function(){
        var editor;

        if(this.state.editorData){
            let className = this.state.editorData.editorClass;
            if(FuncUtils.getFunctionByName(className, window)){
                editor = React.createElement(FuncUtils.getFunctionByName(className, window), {
                    pydio       : this.props.pydio,
                    node        : this.props.node,
                    registerCloseCallback:this.props.registerCloseCallback,
                    onRequestTabClose:this.props.onRequestTabClose,
                    onRequestTabTitleUpdate:this.props.onRequestTabTitleUpdate
                });
            }else{
                editor = <div>{"Cannot find editor component (" + className + ")!"}</div>
            }
        }
        return editor || null;
    }
});
