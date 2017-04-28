import React, {Component} from 'react'
import { connect } from 'react-redux'
import { compose } from 'redux'
import PydioApi from 'pydio/http/api'

const baseURL = pydio.Parameters.get('ajxpServerAccess')
const conf = pydio.getPluginConfigs('editor.diaporama')
const sizes = conf && conf.get("PREVIEWER_LOWRES_SIZES").split(",") || [300, 700, 1000, 1300]

const {SizeProviders, SelectionProviders, withResolution, withSelection, withResize, withMenu, withLoader, withErrors, withControls} = PydioHOCs;
const {ImageSizeProvider, ContainerSizeProvider} = SizeProviders
const {SelectionProvider} = SelectionProviders

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
        const {width, height, scale, ...remainingProps} = this.props

        return <img {...remainingProps} style={{...Image.styles, width, height, transform: `scale(${scale})`}} />
    }
}

class ImagePanel extends Component {
    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            src: React.PropTypes.string.isRequired,
            imgClassName: React.PropTypes.string
        }
    }

    static get IMAGE_PANEL_MARGIN() {
        return 10
    }

    static get styles() {
        return {
            display: "flex",
            flex: 1,
            justifyContent: 'center',
            padding: ImagePanel.IMAGE_PANEL_MARGIN,
            overflow: 'auto'
        }
    }

    render() {
        const {src, width, height, imgClassName, scale} = this.props

        return (
            <div style={ImagePanel.styles}>
                <Image
                    src={src}
                    width={width}
                    height={height}
                    className={imgClassName}
                    scale={scale}
                />
            </div>
        )
    }
}

const ExtendedImagePanel = compose(
    withResize
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

    componentWillUnmount(){
        const {selection} = this.props
        const node = selection && selection.first()

        if (!node) {
            return
        }

        const fileId = node.getMetadata().get('thumb_file_id').replace("-0.jpg", "").replace(".jpg", "");
        PydioApi.getClient().request({get_action:'delete_imagick_data', file: prefix});
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
        const {node, src, editorData, scale, ...remainingProps} = this.props;

        if (!node) return null

        return (
            <ContainerSizeProvider>
            {({containerWidth, containerHeight}) =>
                <ImageSizeProvider
                    url={src}
                    node={node}
                >
                {({imgWidth, imgHeight}) =>
                    <ExtendedImagePanel
                        editorData={editorData}
                        node={node}
                        src={src}
                        scale={scale}
                        width={imgWidth}
                        height={imgHeight}
                        containerWidth={containerWidth}
                        containerHeight={containerHeight}
                    />
                }
                </ImageSizeProvider>
            }
            </ContainerSizeProvider>
        )
    }
}

const getSelection = (node) => {
    const path = node.getPath();
    const label = node.getLabel();

    return new Promise((resolve, reject) => {
        PydioApi.getClient().request({
            get_action: 'imagick_data_proxy',
            all: 'true',
            file: path
        }, ({responseJSON}) => {
            resolve({
                selection: responseJSON.map(({width, height, file}, page) => {
                    let node = new AjxpNode(path, true, `${label} (${page + 1})`);

                    node.getMetadata().set('image_width', width);
                    node.getMetadata().set('image_height', height);
                    node.getMetadata().set('thumb_file_id', file);

                    return node;
                }),
                currentIndex: 0
            })
        }, reject)
    })
}

const getThumbnailURL = (baseURL, node) => {
    const path = encodeURIComponent(node.getPath())
    const file = encodeURIComponent(node.getMetadata().get('thumb_file_id'))

    return `${baseURL}&get_action=get_extracted_page&file=${file}&src_file=${path}`
}

export default compose(
    withSelection(getSelection),
    withResolution(sizes,
        (node) => getThumbnailURL(baseURL, node),
        (node) => getThumbnailURL(baseURL, node)
    ),
    connect()
)(Editor)
