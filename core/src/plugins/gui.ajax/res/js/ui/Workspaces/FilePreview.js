const React = require('react');
const {muiThemeable} = require('material-ui/styles')

let FilePreview = React.createClass({

    propTypes: {
        node            : React.PropTypes.instanceOf(AjxpNode),
        loadThumbnail   : React.PropTypes.bool,
        richPreview     : React.PropTypes.bool,
        // This will apply default styles and mimefontStyles
        rounded         : React.PropTypes.bool,
        roundedSize     : React.PropTypes.number,
        // Additional styling
        style           : React.PropTypes.object,
        mimeFontStyle   : React.PropTypes.object
    },

    getStyles: function(){

        const color = this.props.muiTheme.palette.primary1Color;
        let roundedStyle = {
            root: {
                backgroundColor: '#ECEFF1',
                borderRadius: '50%',
                margin: 15,
                height: 40,
                width: 40,
                lineHeight: '40px',
                display: 'flex',
                alignItems: 'center',
                justifyContent:'center'
            },
            mimefont: {
                color: color,
                fontSize: 24,
                textAlign: 'center',
                flex: 1
            }
        };

        let rootStyle = this.props.rounded ? roundedStyle.root : {};
        let mimefontStyle = this.props.rounded ? roundedStyle.mimefont : {};

        if(this.props.rounded && this.props.roundedSize){
            rootStyle.height = rootStyle.width = rootStyle.lineHeight = this.props.roundedSize;
            rootStyle.lineHeight = this.props.roundedSize + 'px';
        }
        if(this.props.style){
            rootStyle = {...rootStyle, ...this.props.style};
        }
        if(this.props.mimeFontStyle){
            mimefontStyle = {...mimefontStyle, ...this.props.mimeFontStyle};
        }

        return {rootStyle: rootStyle, mimeFontStyle: mimefontStyle};

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

        console.log(this.props.richPreview)
        this.setState({
            preview: this.props.richPreview ? editorClass.Panel : editorClass.Badge
        })
        /*if(editorClass.getCoveringBackgroundSource){
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

                const {rootStyle} = this.getStyles();
                let element = (<div className="covering-bg-preview" style={{...style, ...rootStyle}}></div>);

                this.setState({loading: false, element: element});
            }.bind(this);

            this.setState({loading: true});

            image.src = bgUrl;
            if(image.readyState && image.readyState === 'complete'){
                loader();
            }else{
                image.onload = loader();
            }
        } else if (editorClass.getPreviewComponent) {
            const promise = editorClass.getPreviewComponent(node, this.props.richPreview)

            Promise.resolve(promise).then(function (component) {
                this.setState({
                    preview: component
                })
            }.bind(this))
        }*/

    },

    shouldComponentUpdate: function(){
        return !!!this._previewNode;
    },

    render: function(){

        const {preview: EditorClass, element} = this.state;

        if (!EditorClass) return null

        return (
            <EditorClass {...this.props} />
        )

        if (preview) {
            return (
                <preview.element {...preview.props} pydio={window.pydio} preview={true} />
            )
        }else if(element){
            return element;
        }

        const {node} = this.props;
        if (!node) {
            return null
        }

        const {rootStyle, mimeFontStyle} = this.getStyles();
        let svg = node.getSvgSource();
        if(!svg){
            svg = (node.isLeaf() ? 'file' : 'folder');
        }
        return (
            <div ref="container" style={rootStyle} className='mimefont-container'>
                <div key="icon" className={"mimefont mdi mdi-" + svg} style={mimeFontStyle}/>
            </div>
        );
    }
});

FilePreview = muiThemeable()(FilePreview);

export {FilePreview as default}
