import {PureComponent, PropTypes} from 'react';
import {muiThemeable} from 'material-ui/styles';
import Color from 'color'

class FilePreview extends PureComponent {
    static get propTypes() {
        return {
            node            : PropTypes.instanceOf(AjxpNode).isRequired,
            loadThumbnail   : PropTypes.bool,
            richPreview     : PropTypes.bool,
            // Additional styling
            style           : PropTypes.object,
            mimeFontStyle   : PropTypes.object,
            mimeClassName   : PropTypes.string
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
        const {style, mimeFontStyle} = this.props

        const color = new Color(this.props.muiTheme.palette.primary1Color).saturationl(18).lightness(44).toString();
        const light = new Color(this.props.muiTheme.palette.primary1Color).saturationl(15).lightness(94).toString();

        const rootStyle = {
            backgroundColor: light,
            alignItems: "initial",
            ...style
        };

        const mimefontStyle = {
            color: color,
            ...mimeFontStyle
        };

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
        const {rootStyle, mimeFontStyle} = this.getStyles();

        const {node, mimeClassName} = this.props;
        const {EditorClass} = this.state;

        let element;

        if (EditorClass) {
            element = <EditorClass pydio={pydio} {...this.props} preview={true} />
        } else {
            const svg = node.getSvgSource()
            const isLeaf = node.isLeaf()
            element = <div key="icon" className={mimeClassName || `mimefont mdi mdi-${svg || (isLeaf ? 'file' : 'folder')}`} style={mimeFontStyle}/>
        }

        return (
            <div ref="container" style={rootStyle} className={'mimefont-container' + (EditorClass?' with-editor-badge':'')} onTouchTap={this.props.onTouchTap}>
                {element}
            </div>
        );
    }
}

export default muiThemeable()(FilePreview)
