/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */


import React, {PureComponent} from 'react'
import { connect } from 'react-redux'
import { compose } from 'redux'
import { ImageContainer } from './components'
import PydioApi from 'pydio/http/api'

const baseURL = pydio.Parameters.get('ajxpServerAccess')
const conf = pydio.getPluginConfigs('editor.diaporama')
const sizes = conf && conf.get("PREVIEWER_LOWRES_SIZES").split(",") || [300, 700, 1000, 1300]

const { SizeProviders, URLProvider, withResolution, withSelection, withResize } = PydioHOCs;
const { ImageSizeProvider, ContainerSizeProvider } = SizeProviders
const ExtendedImageContainer = withResize(ImageContainer)

class Editor extends PureComponent {

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
        PydioApi.getClient().request({get_action:'delete_imagick_data', file: fileId});
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
        const {node, src, editorData, scale} = this.props;

        if (!node) return null

        return (
            <ContainerSizeProvider>
            {({containerWidth, containerHeight}) =>
                <ImageSizeProvider url={src} node={node}>
                {({imgWidth, imgHeight}) =>
                    <ExtendedImageContainer
                        editorData={editorData}
                        node={node}
                        src={src}
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
    )
)(Editor)
