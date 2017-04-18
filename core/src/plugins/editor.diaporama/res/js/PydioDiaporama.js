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

import {FlatButton, Slider, ToolbarGroup, ToolbarSeparator} from 'material-ui'

class UrlProvider {
    static buildRandomSeed(ajxpNode){
        var mtimeString = "&time_seed=" + ajxpNode.getMetadata().get("ajxp_modiftime");
        if(ajxpNode.getParent()){
            var preview_seed = ajxpNode.getParent().getMetadata().get('preview_seed');
            if(preview_seed){
                mtimeString += "&rand="+preview_seed;
            }
        }
        return mtimeString;
    }

    getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed) {

        var h = parseInt(imageDimensions.height);
        var w = parseInt(imageDimensions.width);

        return {url: baseUrl + time_seed + "&file=" + encodeURIComponent(node.getPath()), width: w, height: h};
    }

    getLowResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed) {

        var h = parseInt(imageDimensions.height);
        var w = parseInt(imageDimensions.width);
        var sizes = [300, 700, 1000, 1300];
        if(editorConfigs && editorConfigs.get("PREVIEWER_LOWRES_SIZES")){
            sizes = editorConfigs.get("PREVIEWER_LOWRES_SIZES").split(",");
        }
        var reference = Math.max(h, w);
        var viewportRef = (DOMUtils.getViewportHeight() + DOMUtils.getViewportWidth()) / 2;
        var thumbLimit = 0;
        for(var i=0;i < sizes.length;i++) {
            if(viewportRef > parseInt(sizes[i])) {
                if(sizes[i+1]) thumbLimit = parseInt(sizes[i+1]);
                else thumbLimit = parseInt(sizes[i]);
            }
            else break;
        }
        var hasThumb = thumbLimit && (reference > thumbLimit);
        var time_seed_string = time_seed?time_seed:'';
        let crtHeight, crtWidth;
        if(hasThumb){
            if(h>w){
                crtHeight = thumbLimit;
                crtWidth = parseInt( w * thumbLimit / h );
            }else{
                crtWidth = thumbLimit;
                crtHeight = parseInt( h * thumbLimit / w );
            }
            return {
                url: baseUrl + time_seed_string + "&get_thumb=true&dimension="+thumbLimit+"&file="+encodeURIComponent(node.getPath()),
                width: crtWidth,
                height: crtHeight
            };
        }else{
            return this.getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed);
        }
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

        DOMUtils.imageLoader(loadUrl, function(){
            if(!node.getMetadata().has('image_width')){
                node.getMetadata().set("image_width", this.width);
                node.getMetadata().set("image_height", this.height);
            }
            callback(node, getDimFromNode(node))
        }, function(){
            let dim = {width:200, height: 200, default:true};
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
            width:React.PropTypes.number,
            height:React.PropTypes.number,
            imageClassName:React.PropTypes.string,

            fit:React.PropTypes.bool,
            zoomFactor:React.PropTypes.number
        }
    }

    static get IMAGE_PANEL_MARGIN() {
        return 10
    }

    static get styles() {
        return {
            container: {
                flex: 1,
                justifyContent: 'center'
            },

            img: {
                boxShadow: DOMUtils.getBoxShadowDepth(1),
                margin: ImagePanel.IMAGE_PANEL_MARGIN,
                transition: DOMUtils.getBeziersTransition()
            }
        }
    }

    constructor(props) {
        super(props)
        this.state = {
            ...this.props
        }
    }

    componentDidMount() {
        this._observer = (e) => this.resize();
        DOMUtils.observeWindowResize(this._observer);
        this.resize();
        setTimeout(this.resize, 1000);
    }

    componentWillReceiveProps(nextProps){
        this.setState({url: nextProps.url}, () => this.resize(nextProps));
    }

    componentWillUnmount() {
        DOMUtils.stopObservingWindowResize(this._observer);
    }

    resize(props = null) {

        if(!this.container) return;
        if(!props) props = this.props;
        let w = this.container.clientWidth - 2 * ImagePanel.IMAGE_PANEL_MARGIN;
        let h = this.container.clientHeight - 2 * ImagePanel.IMAGE_PANEL_MARGIN;
        let imgW = props.width;
        let imgH = props.height;

        if ((imgW === -1 && imgH === -1) || h < 0 || w < 0) {
            this.setState({width: null,height: '98%'});
            return;
        }

        let newW, newH = imgH;
        if((imgW < w && imgH < h) || !this.props.fit) {
            let zoomFactor = this.props.zoomFactor || 1;
            this.setState({width: imgW * zoomFactor, height: imgH * zoomFactor});
            return;
        }

        if (imgW >= w) {
            this.setState({width: w, height: imgH * w / imgW});
            newH = imgH * w / imgW;
        }

        if(newH >= h) {
            this.setState({height: h, width: imgW * h / imgH});
        }
    }

    render() {

        const {fit, url, imageClassName} = this.props

        if(!url) return null;

        if (fit) {
            return (
                <div ref={(container) => this.container = container} style={{...ImagePanel.styles.container, display: 'flex'}}>
                    <img src={url} className={imageClassName} style={{...ImagePanel.styles.img, flex: "0 1", minHeight: "90%", maxHeight: "90%"}} />
                </div>
            )
        }

        const {width, height} = this.state

        return (
            <div ref={(container) => this.container = container} style={{...ImagePanel.styles.container, overflow: 'auto'}}>
                <img src={url} className={imageClassName} style={{...ImagePanel.styles.img, width: width, height: height}} />
            </div>
        )
    }
}

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

let ExtendedImagePanel = compose(
    withMenu,
    withLoader,
    withErrors
)(ImagePanel)

class Editor extends Component {

    static get propTypes() {
        return {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            pydio: React.PropTypes.instanceOf(Pydio).isRequired,

            urlProvider: React.PropTypes.instanceOf(UrlProvider),
            selectionModel: React.PropTypes.instanceOf(SelectionModel),
            editorConfigs: React.PropTypes.instanceOf(Map),
            baseUrl: React.PropTypes.string,
            showResolutionToggle: React.PropTypes.bool
        }
    }

    static get defaultProps(){
        let baseURL = pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy';
        let editorConfigs = pydio.getPluginConfigs('editor.diaporama');
        return {
            baseUrl: baseURL,
            editorConfigs: editorConfigs,
            urlProvider: new UrlProvider(),
            showResolutionToggle: true,
            onLoad: () => {}
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
            imageDimension: {
                width: -1,
                height: -1
            },
            displayOriginal: false,
            fitToScreen: true,
            zoomFactor: 1
        }
    }

    componentDidMount() {
        this.setState({
            currentNode: this.state.selectionModel.nextOrFirst()
        })
    }

    componentDidUpdate() {
        const {baseUrl, editorConfigs, urlProvider} = this.props
        const {currentNode, selectionModel, displayOriginal, imageDimension} = this.state;

        const {url, width, height} = displayOriginal ?
            urlProvider.getHiResUrl(baseUrl, editorConfigs, currentNode, imageDimension, '') :
            urlProvider.getLowResUrl(baseUrl, editorConfigs, currentNode, imageDimension, '')

        const imageClassName = 'ort-rotate-' + currentNode.getMetadata().get("image_exif_orientation")

        SizeComputer.loadImageSize(url, currentNode, (node, dimension) => this.setState({
            currentNode,
            url,
            ...dimension,
            imageClassName
        }));
    }

    // componentWillReceiveProps(nextProps) {
    //     const {selectionModel} = nextProps || {selectionModel: new SelectionModel(nextProps.node)}
    //
    //     this.setState({
    //         currentNode: selectionModel && selectionModel.nextOrFirst()
    //     })
    // }

    // imageSizeCallback(node, dimension) {
    //     if (this.state.currentNode === node) {
    //         this.timeout = setTimeout(() => this.setState({imageDimension: dimension}), 0);
    //     }
    // }

    // updateStateNode(node) {
    //     //let {url} = this.computeImageData(node);
    //     //SizeComputer.loadImageSize(url, node, () => this.imageSizeCallback());
    //
    //     if(this.props.onRequestTabTitleUpdate){
    //         this.props.onRequestTabTitleUpdate(node.getLabel());
    //     }
    //     this.setState({
    //         currentNode: node
    //     });
    // }

    play() {
        this.pe = new PeriodicalExecuter(() => this.setState({currentNode: this.state.selectionModel.nextOrFirst()}), 3);

        this.setState({playing:true});
    }

    stop() {
        if(this.pe) this.pe.stop();
        this.setState({playing:false});
    }

    buildActions() {
        const {MessageHash: mess} = this.props.pydio
        const {selectionModel: sel, playing, displayOriginal, fitToScreen, zoomFactor} = this.state

        return [
            sel && sel.length() > 1 &&
            <ToolbarGroup firstChild={true}>
                <FlatButton label={mess[178]} disabled={!sel.hasPrevious()} onClick={() => this.setState({currentNode: sel.previous()})} />
                <FlatButton label={playing ? mess[232] : mess[230]} onClick={()=>{playing ? this.stop() : this.play()}}/>
                <FlatButton label={mess[179]} disabled={!sel.hasNext()} onClick={() => this.setState({currentNode: sel.next()})} />
            </ToolbarGroup>,

            this.props.showResolutionToggle &&
            <ToolbarGroup>
                <FlatButton key="resolution" label={displayOriginal?mess[526]:mess[525]} onClick={()=>{this.setState({displayOriginal:!displayOriginal})}}/>
                <ToolbarSeparator key="separator"/>
            </ToolbarGroup>,

            this.state.fitToScreen &&
                <ToolbarGroup>
                    <FlatButton key="fit" label={mess[326]} onClick={() => this.setState({fitToScreen:!fitToScreen})} />
                </ToolbarGroup> ||
                <ToolbarGroup>
                    <FlatButton key="fit" label={mess[325]} onClick={() => this.setState({fitToScreen:!fitToScreen})} />
                    <div key="zoom" style={{display:'flex', height:56}}>
                        <Slider style={{width:150, marginTop:-4}} min={0.25} max={4} defaultValue={1} value={zoomFactor} onChange={(_, zoomFactor) => this.setState({zoomFactor})} />
                        <span style={{padding:18,fontSize: 16}}>{Math.round(zoomFactor * 100)} %</span>
                    </div>
                </ToolbarGroup>
        ]
    }

    render() {
        const {url, width, height, imageClassName, zoomFactor, fitToScreen} = this.state;

        if (!url) return null

        return (
            <ExtendedImagePanel
                url={url}
                width={width}
                height={height}
                imageClassName={imageClassName}
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
