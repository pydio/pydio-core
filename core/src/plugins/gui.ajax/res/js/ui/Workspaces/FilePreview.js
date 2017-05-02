import {PureComponent, PropTypes} from 'react';
import {muiThemeable} from 'material-ui/styles';
import Color from 'color'

class FilePreview extends PureComponent {
    static get propTypes() {
        return {
            node            : PropTypes.instanceOf(AjxpNode).isRequired,
            loadThumbnail   : PropTypes.bool,
            richPreview     : PropTypes.bool,
            // This will apply default styles and mimefontStyles
            rounded         : PropTypes.bool,
            roundedSize     : PropTypes.number,
            // Additional styling
            style           : PropTypes.object,
            mimeFontStyle   : PropTypes.object
        }
    }

    static get defaultProps() {
        return {richPreview: false}
    }

    constructor(props) {
        super(props)

        this.state = {
            loading: false
        }
    }

    getStyles() {
        const color = new Color(this.props.muiTheme.palette.primary1Color).saturationl(18).lightness(44).toString();
        const light = new Color(this.props.muiTheme.palette.primary1Color).saturationl(15).lightness(94).toString();

        let roundedStyle = {
            root: {
                backgroundColor: light,
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

        let rootStyle = this.props.rounded ? roundedStyle.root : {backgroundColor: light};
        let mimefontStyle = this.props.rounded ? roundedStyle.mimefont : {color: color};

        if (this.props.rounded && this.props.roundedSize) {
            rootStyle.height = rootStyle.width = rootStyle.lineHeight = this.props.roundedSize;
            rootStyle.lineHeight = this.props.roundedSize + 'px';
        }
        if (this.props.style) {
            rootStyle = {...rootStyle, ...this.props.style};
        }
        if (this.props.mimeFontStyle) {
            mimefontStyle = {...mimefontStyle, ...this.props.mimeFontStyle};
        }

        return {rootStyle: rootStyle, mimeFontStyle: mimefontStyle};
    }

    insertPreviewNode(previewNode) {
        this._previewNode = previewNode;
        let containerNode = this.refs.container;
        containerNode.innerHTML = '';
        containerNode.className='richPreviewContainer';
        containerNode.appendChild(this._previewNode);
    }

    destroyPreviewNode() {
        if(this._previewNode) {
            this._previewNode.destroyElement();
            if(this._previewNode.parentNode) {
                this._previewNode.parentNode.removeChild(this._previewNode);
            }
            this._previewNode = null;
        }
    }

    componentDidMount() {
        this.loadCoveringImage();
    }

    componentWillUnmount() {
        this.destroyPreviewNode();
    }

    componentWillReceiveProps(nextProps) {
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
    }

    loadCoveringImage(force = false) {
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
            let component = FuncUtils.getFunctionByName(editorClassName, window);

            if(component){
                this.loadPreviewFromEditor(component, node);
            }
        }.bind(this));
    }

    loadPreviewFromEditor(editorClass, node) {
        this.setState({
            EditorClass: this.props.richPreview ? editorClass.Panel : editorClass.Badge
        })
    }

    render() {
        const {node} = this.props;
        const {EditorClass, element} = this.state;

        if (EditorClass) {
            return (
                <EditorClass pydio={pydio} {...this.props} preview={true} />
            )
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
}

export default muiThemeable()(FilePreview)
