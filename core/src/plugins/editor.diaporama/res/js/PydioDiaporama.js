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

import React, {Component} from 'react'
import {compose} from 'redux'
import {FlatButton, IconButton, Slider, ToolbarGroup, ToolbarSeparator} from 'material-ui'

const baseURL = pydio.Parameters.get('ajxpServerAccess')
const conf = pydio.getPluginConfigs('editor.diaporama')
const sizes = conf && conf.get("PREVIEWER_LOWRES_SIZES").split(",") || [300, 700, 1000, 1300]

const {SizeProviders, withResolution, withSelection, withResize, withMenu, withLoader, withErrors, withControls} = PydioHOCs;
const {ImageSizeProvider, ContainerSizeProvider} = SizeProviders

class Image extends Component {
    static get propTypes() {
        return {
            scale: React.PropTypes.number
        }
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
        const {playing} = this.state || {};

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

export default compose(
    withSelection((node) => node.getMetadata().get('is_image') === '1'),
    withResolution(sizes,
        (node) => `${baseURL}&action=preview_data_proxy&file=${encodeURIComponent(node.getPath())}`,
        (node, dimension) => `${baseURL}&action=preview_data_proxy&get_thumb=true&dimension=${dimension}&file=${encodeURIComponent(node.getPath())}`
    )
)(Editor)
