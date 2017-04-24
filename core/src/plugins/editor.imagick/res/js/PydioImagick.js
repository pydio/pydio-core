class UrlProvider extends PydioDiaporama.UrlProvider {

    getHiResUrl(node, time_seed = '') {
        let url;

        if(node.getMetadata().get('thumb_file_id')){
            url = baseUrl + '&get_action=get_extracted_page' +
                '&file=' + encodeURIComponent(node.getMetadata().get('thumb_file_id')) +
                '&src_file=' + encodeURIComponent(node.getPath());
        }

        return url
    }

    getLowResUrl(node, time_seed = '') {
        return this.getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed);
    }
}

class SelectionModel extends PydioDiaporama.SelectionModel {

    buildSelection() {
        let initialNodePath = this.currentNode.getPath();
        let initialNodeLabel = this.currentNode.getLabel();

        PydioApi.getClient().request({
            get_action:'imagick_data_proxy',
            all:'true',
            file:initialNodePath
        }, ({responseJSON}) => {
            let page = 1;
            this.selection = responseJSON.map(({width, height, file}) => {

                let node = new AjxpNode(initialNodePath, true, initialNodeLabel+' ('+page+')');

                node.getMetadata().set('image_width', width);
                node.getMetadata().set('image_height', height);
                node.getMetadata().set('thumb_file_id', file);

                page++;
                return node;
            });

            this.currentIndex = 0;
            this.notify('selectionChanged');
        });

    }
}

import React, {Component} from 'react'
import {compose} from 'redux'
import {FlatButton, IconButton, Slider, ToolbarGroup, ToolbarSeparator} from 'material-ui'

const baseURL = pydio.Parameters.get('ajxpServerAccess')
const conf = pydio.getPluginConfigs('editor.diaporama')
const sizes = conf && conf.get("PREVIEWER_LOWRES_SIZES").split(",") || [300, 700, 1000, 1300]

const {ContainerSizeProvider, ImageSizeProvider, withResolution, withSelection, withResize, withMenu, withLoader, withErrors, withControls} = PydioHOCs;

class Image extends Component {
    static get propTypes() {
        scale: React.PropTypes.number
    }

    static get styles() {
        return {
            transformOrigin: "50% 0",
            boxShadow: DOMUtils.getBoxShadowDepth(1)
        }
    }

    render() {
        const {scale, ...remainingProps} = this.props
        
        return (
            <img src={url} className={imageClassName} style={{...ImagePanel.styles, transform: `scale(${scale})`}} />
        )
    }
}

class ImagePanel extends Component {
    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            url: React.PropTypes.string.isRequired,

        }
    }

    static get IMAGE_PANEL_MARGIN() {
        return 10
    }

    static get styles() {
        return {
            container: {
                display: "flex",
                flex: 1,
                justifyContent: 'center',
                padding: ImagePanel.IMAGE_PANEL_MARGIN,
                overflow: 'auto'
            },

            img: {
                boxShadow: DOMUtils.getBoxShadowDepth(1)
            }
        }
    }

    render() {
        const {url, imgWidth, imgHeight, imageClassName, scale} = this.props

        return (
            <div style={ImagePanel.styles.container}>

            </div>
        )
    }
}

let ExtendedImagePanel = compose(
    withResize,
    withResolution(sizes,
        (node) => `${baseURL}&action=preview_data_proxy&file=${encodeURIComponent(node.getPath())}`,
        (node, dimension) => `${baseURL}&action=preview_data_proxy&get_thumb=true&dimension=${dimension}&file=${encodeURIComponent(node.getPath())}`
    ),
    withMenu,
    withLoader,
    withErrors
)(ImagePanel)

class Editor extends Component {

    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            pydio: React.PropTypes.instanceOf(Pydio).isRequired,
        }
    }

    /*static getCoveringBackgroundSource(ajxpNode) {
        return this.getThumbnailSource(ajxpNode);
    }

    static getThumbnailSource(ajxpNode) {
        var repoString = "";
        if(pydio.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != pydio.repositoryId){
            repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
        }
        var mtimeString = ajxpNode.buildRandomSeed();
        return pydio.Parameters.get('ajxpServerAccess') + repoString + mtimeString + "&get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
    }

    static getOriginalSource(ajxpNode) {
        return pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy'+ajxpNode.buildRandomSeed()+'&file='+encodeURIComponent(ajxpNode.getPath());
    }

    static getSharedPreviewTemplate(node, link) {
        // Return string
        return '<img src="' + link + '"/>';
    }

    static getRESTPreviewLinks(node) {
        return {
            "Original image": "",
            "Thumbnail (200px)": "&get_thumb=true&dimension=200"
        };
    }*/

    componentDidMount() {
        this.state.selectionModel.observe('selectionChanged', () => this.setState({selectionLoaded:true}));
    }

    componentWillUnmount(){
        let node = this.state.selectionModel.first()

        if (!node) {
            return
        }

        let fileId = this.state.selectionModel.first().getMetadata().get('thumb_file_id');
        var prefix = fileId.replace("-0.jpg", "").replace(".jpg", "");
        PydioApi.getClient().request({get_action:'delete_imagick_data', file:prefix});
    }

    componentWillReceiveProps(nextProps) {
        if (this.props.selectionPlaying !== nextProps.selectionPlaying)  {
            if (nextProps.selectionPlaying) {
                this.pe = new PeriodicalExecuter(nextProps.onRequestSelectionPlay, 3);
            } else {
                this.pe && this.pe.stop()
            }
        }
    }

    render() {
        const {node, url, controls, ...remainingProps} = this.props;
        const {playing} = this.state || {};

        return (
            <ContainerSizeProvider>
            {({containerWidth, containerHeight}) =>
                <ImageSizeProvider
                    url={url}
                    node={node}
                >
                {({imgWidth, imgHeight}) =>
                    <ExtendedImagePanel
                        node={node}
                        url={url}

                        containerWidth={containerWidth}
                        containerHeight={containerHeight}
                        imgWidth={imgWidth}
                        imgHeight={imgHeight}

                        controls={controls}
                    />
                }
                </ImageSizeProvider>
            }
            </ContainerSizeProvider>
        )
    }
}

export default compose(
    withSelection((node) => node.getMetadata().get('is_image') === '1')
)(Editor)

/*
class Editor extends React.Component {
    static get config() {
        return {
            baseUrl: pydio.Parameters.get('ajxpServerAccess'),
            editorConfigs: pydio.getPluginConfigs('editor.imagick')
        }
    }

    static getCoveringBackgroundSource(ajxpNode) {
        return Editor.getThumbnailSource(ajxpNode);
    }

    static getThumbnailSource(ajxpNode) {
        var repoString = "";
        if(pydio.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != pydio.repositoryId){
            repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
        }
        var mtimeString = UrlProvider.buildRandomSeed(ajxpNode);
        return pydio.Parameters.get('ajxpServerAccess') + "&get_action=imagick_data_proxy"+repoString + mtimeString +"&file="+encodeURIComponent(ajxpNode.getPath());
    }

    static getSharedPreviewTemplate(node, link){
            return `<img src="${link}"/>`;
    }

    static getRESTPreviewLinks(node){
        return {
            "First Page Thumbnail": ""
        };
    }

    constructor(props) {
        super(props)

        this.state = {
            selectionLoaded: false,
            selectionModel: new SelectionModel(props.node),
            urlProvider: new UrlProvider(Editor.config.baseUrl, Editor.config.editorConfigs)
        };
    }



    render() {
        return (
            <PydioDiaporama.Editor
                {...this.props}
                selectionModel={this.state.selectionModel}
                urlProvider={this.state.urlProvider}
                showResolutionToggle={false}
                showLoader={!this.state.selectionLoaded}
            />
        )
    }
}

window.PydioImagick = {
    Editor: Editor
}*/
