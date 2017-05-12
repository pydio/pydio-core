/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

const baseURL = pydio.Parameters.get('ajxpServerAccess')
const conf = pydio.getPluginConfigs('editor.diaporama')
const sizes = conf && conf.get("PREVIEWER_LOWRES_SIZES").split(",") || [300, 700, 1000, 1300]

const { SizeProviders, URLProvider, withResolution, withSelection, withResize } = PydioHOCs;
const { ImageSizeProvider, ContainerSizeProvider } = SizeProviders
const ExtendedImageContainer = withResize(ImageContainer)

class Editor extends PureComponent {

    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired
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
        const {node, src, editorData} = this.props;

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
                        imgClassName="diaporama-image-main-block"
                        style={{backgroundColor:'#424242'}}
                        imgStyle={{boxShadow: 'rgba(0, 0, 0, 0.117647) 0px 1px 6px, rgba(0, 0, 0, 0.117647) 0px 1px 4px'}}
                    />
                }
                </ImageSizeProvider>
            }
            </ContainerSizeProvider>
        )
    }
}

const getSelectionFilter = (node) => node.getMetadata().get('is_image') === '1'
const getSelection = (node) => new Promise((resolve, reject) => {
    let selection = [];

    node.getParent().getChildren().forEach((child) => selection.push(child));
    selection = selection.filter(getSelectionFilter)

    resolve({
        selection,
        currentIndex: selection.reduce((currentIndex, current, index) => current === node && index || currentIndex, 0)
    })
})

export default compose(
    withSelection(getSelection, getSelectionFilter),
    withResolution(sizes,
        (node) => `${baseURL}&action=preview_data_proxy&file=${encodeURIComponent(node.getPath())}`,
        (node, dimension) => `${baseURL}&action=preview_data_proxy&get_thumb=true&dimension=${dimension}&file=${encodeURIComponent(node.getPath())}`
    )
)(Editor)
