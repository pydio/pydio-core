let FilePreview = React.createClass({

    propTypes: {
        node            : React.PropTypes.instanceOf(AjxpNode),
        loadThumbnail   : React.PropTypes.bool,
        richPreview     : React.PropTypes.bool,
        style           : React.PropTypes.object,
        mimeFontStyle   : React.PropTypes.object
    },

    getInitialState: function(){
        return {loading: false, element: null}
    },

    getDefaultProps: function(){
        return {richPreview: false}
    },

    insertPreviewNode: function(previewNode){
        this._previewNode = previewNode;
        let containerNode = this.refs.container;
        containerNode.innerHTML = '';
        containerNode.className='richPreviewContainer';
        containerNode.appendChild(this._previewNode);
    },

    destroyPreviewNode: function(){
        if(this._previewNode) {
            this._previewNode.destroyElement();
            if(this._previewNode.parentNode) {
                this._previewNode.parentNode.removeChild(this._previewNode);
            }
            this._previewNode = null;
        }
    },

    componentDidMount: function(){
        this.loadCoveringImage();
    },

    componentWillUnmount: function(){
        this.destroyPreviewNode();
    },

    componentWillReceiveProps: function(nextProps){
        if(nextProps.node.getPath() !== this.props.node.getPath()){
            this.destroyPreviewNode();
            this.loadCoveringImage();
            return;
        }
        if(this._previewNode){
            return;
        }
        if(nextProps.loadThumbnail !== this.props.loadThumbnail && nextProps.loadThumbnail){
            this.loadCoveringImage(true);
        }
    },

    loadCoveringImage: function(force = false){
        if(!this.props.loadThumbnail && !force){
            return;
        }
        let pydio = window.pydio, node = this.props.node;
        let editors = window.pydio.Registry.findEditorsForMime((node.isLeaf()?node.getAjxpMime():"mime_folder"), true);
        if(!editors || !editors.length) {
            return;
        }
        let editor = editors[0];
        let editorClassName = editors[0].editorClass;

        pydio.Registry.loadEditorResources(editors[0].resourcesManager, function(){
            let component = FuncUtils.getFunctionByName(editorClassName, global);
            if(component && this.isMounted()){
                this.loadPreviewFromEditor(component, node);
            }
        }.bind(this));

    },

    loadPreviewFromEditor: function(editorClass, node){

        if(editorClass.getCoveringBackgroundSource){
            let image = new Image();
            let bgUrl = editorClass.getCoveringBackgroundSource(node);

            let loader = function(){
                if(!this.isMounted()) return;
                bgUrl = bgUrl.replace('(', '\\(').replace(')', '\\)').replace('\'', '\\\'');
                let style = {
                    backgroundImage:'url(' + bgUrl + ')',
                    backgroundSize : 'cover',
                    backgroundPosition: 'center center'
                };
                if(this.props.style){
                    style = Object.assign(style, this.props.style);
                }
                let element = (<div className="covering-bg-preview" style={style}></div>);

                this.setState({loading: false, element: element});
            }.bind(this);

            this.setState({loading: true});

            image.src = bgUrl;
            if(image.readyState && image.readyState === 'complete'){
                loader();
            }else{
                image.onload = loader();
            }
        }  else if (editorClass.getPreviewComponent) {
            var promise = editorClass.getPreviewComponent(node, this.props.richPreview)

            Promise.resolve(promise).then(function (component) {
                this.setState({
                    preview: component
                })
            }.bind(this))
        }

    },

    shouldComponentUpdate: function(){
        return !!!this._previewNode;
    },

    render: function(){

        if (this.state.preview) {
            return (
                <this.state.preview.element {...this.state.preview.props} pydio={window.pydio} preview={true} />
            )
        }else if(this.state.element){
            return this.state.element;
        }

        let node  = this.props.node;
        let svg = PydioComponents.AbstractEditor.getSvgSource(node);
        let object, className;
        if(svg){
            object = <div key="icon" className={"mimefont mdi mdi-" + svg} style={this.props.mimeFontStyle}></div>;
            className = 'mimefont-container';
        }else{
            var src = ResourcesManager.resolveImageSource(node.getIcon(), "mimes/ICON_SIZE", 64);
            if(!src){
                if(!node.isLeaf()) src = ResourcesManager.resolveImageSource('folder.png', "mimes/ICON_SIZE", 64);
                else src = ResourcesManager.resolveImageSource('mime_empty.png', "mimes/ICON_SIZE", 64);
            }
            object = <img key="image" src={src}/>;
        }

        return (
            <div ref="container" style={this.props.style} className={className}>{object}</div>
        );
    }
});

export {FilePreview as default}
