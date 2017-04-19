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

const {withResize, withMenu, withLoader, withErrors, withControls} = PydioHOCs;

class UrlProvider {

    static buildRandomSeed(ajxpNode) {
        var mtimeString = "&time_seed=" + ajxpNode.getMetadata().get("ajxp_modiftime");
        if(ajxpNode.getParent()){
            var preview_seed = ajxpNode.getParent().getMetadata().get('preview_seed');
            if(preview_seed){
                mtimeString += "&rand="+preview_seed;
            }
        }
        return mtimeString;
    }

    constructor(baseUrl, editorConfigs) {
        this.baseUrl = baseUrl

        this.sizes = editorConfigs && editorConfigs.get("PREVIEWER_LOWRES_SIZES").split(",") || [300, 700, 1000, 1300];
    }

    getHiResUrl(node, time_seed = '') {
        return `${this.baseUrl}${time_seed}&file=${encodeURIComponent(node.getPath())}`
    }

    getLowResUrl(node, time_seed = '') {
        const viewportRef = (DOMUtils.getViewportHeight() + DOMUtils.getViewportWidth()) / 2;
        const thumbLimit = this.sizes.reduce((current, size) => {
            return viewportRef > parseInt(size) && parseInt(size) || current
        }, 0);

        if (thumbLimit > 0) {
            return `${this.baseUrl}${time_seed}&get_thumb=true&dimension=${thumbLimit}&file=${encodeURIComponent(node.getPath())}`
        }

        return this.getHiResUrl(node, time_seed);
    }
}

class SizeComputer {
    static loadImageSize(loadUrl, node, callback){
        let getDimFromNode = function(n){
            return {
                width: parseInt(n.getMetadata().get('image_width')),
                height: parseInt(n.getMetadata().get('image_height'))
            };
        };

        DOMUtils.imageLoader(loadUrl, () => {
            if(!node.getMetadata().has('image_width')){
                node.getMetadata().set("image_width", this.width);
                node.getMetadata().set("image_height", this.height);
            }

            callback(node, getDimFromNode(node))
        }, () => {
            let dim = {width:200, height: 200, default: true};

            if(node.getMetadata().has('image_width')){
                let dim = getDimFromNode(node);
            }

            callback(node, dim);
        });
    }
}

class SelectionModel extends Observable{

    constructor(node){
        super();
        this.currentNode = node;
        this.selection = [];
        this.buildSelection();
    }

    buildSelection(){
        let currentIndex;
        let child;
        let it = this.currentNode.getParent().getChildren().values();
        while(child = it.next()){
            if(child.done) break;
            let node = child.value;
            if(node.getMetadata().get('is_image') === '1'){
                this.selection.push(node);
                if(node === this.currentNode){
                    this.currentIndex = this.selection.length - 1;
                }
            }
        }
    }

    length(){
        return this.selection.length;
    }

    hasNext(){
        return this.currentIndex < this.selection.length - 1;
    }

    hasPrevious(){
        return this.currentIndex > 0;
    }

    current(){
        return this.selection[this.currentIndex];
    }

    next(){
        if(this.hasNext()){
            this.currentIndex ++;
        }
        return this.current();
    }

    previous(){
        if(this.hasPrevious()){
            this.currentIndex --;
        }
        return this.current();
    }

    first(){
        return this.selection[0];
    }

    last(){
        return this.selection[this.selection.length -1];
    }

    nextOrFirst(){
        if(this.hasNext()) this.currentIndex ++;
        else this.currentIndex = 0;
        return this.current();
    }
}

class ImagePanel extends Component {

    static get propTypes() {
        return {
            url: React.PropTypes.string,
            width: React.PropTypes.number,
            height: React.PropTypes.number,
            imageClassName: React.PropTypes.string,

            fit: React.PropTypes.bool,
            zoomFactor: React.PropTypes.number
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
                boxShadow: DOMUtils.getBoxShadowDepth(1),
                transition: DOMUtils.getBeziersTransition()
            }
        }
    }

    render() {
        const {url, imageClassName, width, height, fit, zoomFactor} = this.props

        return (
            <div style={ImagePanel.styles.container}>
                {fit &&
                    <img src={url} className={imageClassName} style={{...ImagePanel.styles.img, flex: "0 1", minHeight: "100%", maxHeight: "100%"}} /> ||
                    <img src={url} className={imageClassName} style={{...ImagePanel.styles.img, width: width, height: height, transform: `scale(${zoomFactor})`, transformOrigin: "50% 0"}} />
                }
            </div>
        )
    }
}

class ImageNode extends Component {

    static get config() {
        return {
            baseUrl: pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy',
            editorConfigs: pydio.getPluginConfigs('editor.diaporama')
        }
    }

    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            pydio: React.PropTypes.instanceOf(Pydio).isRequired,

            urlProvider: React.PropTypes.instanceOf(UrlProvider),
            baseUrl: React.PropTypes.string,
            editorConfigs: React.PropTypes.instanceOf(Map),

            displayOriginal: React.PropTypes.bool
        }
    }

    static get defaultProps(){
        return {
            urlProvider: new UrlProvider(ImageNode.config.baseUrl, ImageNode.config.editorConfigs),
            displayOriginal: true
        }
    }

    constructor(props) {
        super(props)

        this.state = {}
    }

    componentWillReceiveProps(nextProps) {

        const {node, displayOriginal, urlProvider, baseUrl, editorConfigs} = nextProps

        if (!node) return

        const url = displayOriginal ?
            urlProvider.getHiResUrl(node) :
            urlProvider.getLowResUrl(node)

        SizeComputer.loadImageSize(url, node, (node, dimension) => this.setState({
            url,
            imageClassName: `ort-rotate-${node.getMetadata().get("image_exif_orientation")}`,
            imgWidth: dimension.width,
            imgHeight: dimension.height
        }))
    }

    render() {
        const {node, fit, zoomFactor} = this.props
        const {url, imgWidth, imgHeight, imageClassName} = this.state

        return (
            <ImagePanel
                url={url}
                width={imgWidth}
                height={imgHeight}
                imageClassName={imageClassName}
                fit={fit}
                zoomFactor={zoomFactor}
            />
        )
    }
}

let ExtendedImageNode = compose(
    withMenu,
    withLoader,
    withErrors,
    withResize
)(ImageNode)

class Editor extends Component {

    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            pydio: React.PropTypes.instanceOf(Pydio).isRequired,

            showResolutionToggle: React.PropTypes.bool,

            urlProvider: React.PropTypes.instanceOf(UrlProvider),
            selectionModel: React.PropTypes.instanceOf(SelectionModel),
            editorConfigs: React.PropTypes.instanceOf(Map),
            baseUrl: React.PropTypes.string
        }
    }

    static get defaultProps() {
        return {
            showResolutionToggle: true
        }
    }

    static getCoveringBackgroundSource(ajxpNode) {
        return this.getThumbnailSource(ajxpNode);
    }

    static getThumbnailSource(ajxpNode) {
        var repoString = "";
        if(pydio.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != pydio.repositoryId){
            repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
        }
        var mtimeString = UrlProvider.buildRandomSeed(ajxpNode);
        return pydio.Parameters.get('ajxpServerAccess') + repoString + mtimeString + "&get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
    }

    static getOriginalSource(ajxpNode) {
        return pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy'+UrlProvider.buildRandomSeed(ajxpNode)+'&file='+encodeURIComponent(ajxpNode.getPath());
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
    }

    constructor(props) {
        super(props)

        const {selectionModel, node} = props

        this.state = {
            selectionModel: selectionModel || new SelectionModel(node),
            displayOriginal: false,
            fitToScreen: true,
            zoomFactor: 1
        }
    }

    componentWillReceiveProps(nextProps) {

        const {node, selectionModel = new SelectionModel(node)} = nextProps

        this.state = {
            selectionModel,
            currentNode: selectionModel.currentNode,
            displayOriginal: false,
            fitToScreen: true,
            zoomFactor: 1
        }
    }

    play() {
        this.pe = new PeriodicalExecuter(() => this.setState({currentNode: this.state.selectionModel.nextOrFirst()}), 3);

        this.setState({playing:true});
    }

    stop() {
        if(this.pe) this.pe.stop();
        this.setState({playing:false});
    }

    buildActions() {
        const {MessageHash} = this.props.pydio
        const {selectionModel: sel, playing, displayOriginal, fitToScreen, zoomFactor} = this.state

        return [
            sel && sel.length() > 1 &&
            <ToolbarGroup firstChild={true}>
                <IconButton disabled={!sel.hasPrevious()} iconClassName="mdi mdi-arrow-left" tooltip={MessageHash[178]} onClick={() => this.setState({currentNode: sel.previous()})} />
                <IconButton iconClassName={playing ? "mdi mdi-pause" : "mdi mdi-play"} tooltip={playing ? MessageHash[232] : MessageHash[230]} onClick={()=>{playing ? this.stop() : this.play()}} />
                <IconButton disabled={!sel.hasNext()} iconClassName="mdi mdi-arrow-right" tooltip={MessageHash[179]} onClick={() => this.setState({currentNode: sel.next()})} />
            </ToolbarGroup>,

            this.props.showResolutionToggle &&
            <ToolbarGroup>
                <IconButton iconClassName={displayOriginal ? "mdi mdi-image-filter" : "mdi mdi-image-filter-none"} tooltip={displayOriginal ? MessageHash[525] : MessageHash[526]} onClick={()=>{this.setState({displayOriginal: !displayOriginal})}} />
            </ToolbarGroup>,

            this.state.fitToScreen &&
                <ToolbarGroup>
                    <FlatButton key="fit" label={MessageHash[326]} onClick={() => this.setState({fitToScreen:!fitToScreen})} />
                </ToolbarGroup> ||
                <ToolbarGroup>
                    <FlatButton key="fit" label={MessageHash[325]} onClick={() => this.setState({fitToScreen:!fitToScreen})} />
                    <div key="zoom" style={{display:'flex', height:56}}>
                        <Slider style={{width:150, marginTop:-4}} min={0.25} max={4} defaultValue={1} value={zoomFactor} onChange={(_, zoomFactor) => this.setState({zoomFactor})} />
                        <span style={{padding:18,fontSize: 16}}>{Math.round(zoomFactor * 100)} %</span>
                    </div>
                </ToolbarGroup>
        ]
    }

    render() {
        const {currentNode, zoomFactor, fitToScreen, displayOriginal} = this.state;

        if (!currentNode) return null

        return (
            <ExtendedImageNode
                pydio={pydio}
                node={currentNode}
                displayOriginal={displayOriginal}
                fit={fitToScreen}
                zoomFactor={zoomFactor}

                controls={this.buildActions()}
            />
        )
    }
}

window.PydioDiaporama = {
    Editor: Editor,
    SelectionModel: SelectionModel,
    UrlProvider: UrlProvider
}
