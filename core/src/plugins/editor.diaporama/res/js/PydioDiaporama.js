(function(global){

    let pydio = global.pydio;

    class UrlProvider{

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

        getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed){

            var h = parseInt(imageDimensions.height);
            var w = parseInt(imageDimensions.width);

            return {url: baseUrl + time_seed + "&file=" + encodeURIComponent(node.getPath()), width: w, height: h};

        }

        getLowResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed){

            var h = parseInt(imageDimensions.height);
            var w = parseInt(imageDimensions.width);
            var sizes = [300, 700, 1000, 1300];
            if(editorConfigs && editorConfigs.get("PREVIEWER_LOWRES_SIZES")){
                sizes = editorConfigs.get("PREVIEWER_LOWRES_SIZES").split(",");
            }
            var reference = Math.max(h, w);
            var viewportRef = (DOMUtils.getViewportHeight() + DOMUtils.getViewportWidth()) / 2;
            var thumbLimit = 0;
            for(var i=0;i<sizes.length;i++){
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
                    height:crtHeight
                };
            }else{
                return this.getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed);
            }
        }
    }

    class SizeComputer{

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

    const IMAGE_PANEL_MARGIN = 10;

    let ImagePanel = React.createClass({

        propTypes: {
            url: React.PropTypes.string,
            width:React.PropTypes.number,
            height:React.PropTypes.number,
            imageClassName:React.PropTypes.string,

            fit:React.PropTypes.bool,
            zoomFactor:React.PropTypes.number
        },

        getInitialState: function(){
            return {...this.props};
        },

        componentDidMount: function(){
            this._observer = function(e){this.resize()}.bind(this);
            DOMUtils.observeWindowResize(this._observer);
            this.resize();
            setTimeout(this.resize, 1000);
        },

        componentWillReceiveProps: function(nextProps){
            this.setState({url: nextProps.url}, function(){
                this.resize(nextProps);
            });
        },

        componentWillUnmount: function(){
            DOMUtils.stopObservingWindowResize(this._observer);
        },

        resize: function(props = null){
            if(!this.refs.container) return;
            if(!props) props = this.props;
            let w = this.refs.container.clientWidth - 2 * IMAGE_PANEL_MARGIN;
            let h = this.refs.container.clientHeight - 2 * IMAGE_PANEL_MARGIN;
            let imgW = props.width;
            let imgH = props.height;
            if((imgW === -1 && imgH === -1) || h < 0 || w < 0){
                this.setState({width: null,height: '98%'});
                return;
            }
            let newW, newH = imgH;
            if((imgW < w && imgH < h) || !this.props.fit){
                let zoomFactor = this.props.zoomFactor || 1;
                this.setState({width: imgW * zoomFactor,height: imgH * zoomFactor});
                return;
            }
            if(imgW >= w){
                this.setState({width: w,height: imgH * w / imgW});
                newH = imgH * w / imgW;
            }
            if(newH >= h){
                this.setState({height: h,width: imgW * h / imgH});
            }
        },

        render: function(){
            if(!this.props.url) return null;

            let style = {
                boxShadow: DOMUtils.getBoxShadowDepth(1),
                margin:IMAGE_PANEL_MARGIN,
                transition:DOMUtils.getBeziersTransition()
            }

            if (this.props.fit) {
                style = {
                    ...style,
                    maxWidth: "90%",
                    maxHeight: "90%",
                    minHeight: "90%"
                }
            } else {
                style= {
                    ...style,
                    height:this.state.height,
                    width:this.state.width
                }
            }

            return (
                <div ref="container" style={{textAlign:'center', overflow:(!this.props.fit?'auto':null), flex: 1}}>
                    <img className={this.props.imageClassName} style={style} src={this.props.url}/>
                </div>
            );
        }


    });

    let Editor = React.createClass({

        propTypes:{
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,

            urlProvider: React.PropTypes.instanceOf(UrlProvider),
            selectionModel:React.PropTypes.instanceOf(SelectionModel),
            editorConfigs:React.PropTypes.instanceOf(Map),
            baseUrl: React.PropTypes.string,
            showResolutionToggle: React.PropTypes.bool
        },

        statics:{
            getCoveringBackgroundSource: function(ajxpNode){
                return this.getThumbnailSource(ajxpNode);
            },

            getThumbnailSource : function(ajxpNode){
                var repoString = "";
                if(pydio.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != pydio.repositoryId){
                    repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
                }
                var mtimeString = UrlProvider.buildRandomSeed(ajxpNode);
                return pydio.Parameters.get('ajxpServerAccess') + repoString + mtimeString + "&get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
            },

            getOriginalSource : function(ajxpNode) {
                return pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy'+UrlProvider.buildRandomSeed(ajxpNode)+'&file='+encodeURIComponent(ajxpNode.getPath());
            },

            getSharedPreviewTemplate : function(node, link){
                // Return string
                return '<img src="' + link + '"/>';
            },

            getRESTPreviewLinks:function(node){
                return {
                    "Original image": "",
                    "Thumbnail (200px)": "&get_thumb=true&dimension=200"
                };
            }
        },

        getDefaultProps: function(){
            let baseURL = pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy';
            let editorConfigs = pydio.getPluginConfigs('editor.diaporama');
            return {
                baseUrl: baseURL,
                editorConfigs: editorConfigs,
                urlProvider:new UrlProvider(),
                showResolutionToggle: true
            }
        },

        imageSizeCallback: function(node, dimension){
            if(this.state.currentNode === node){
                this.setState({imageDimension: dimension});
            }
        },

        computeStateFromProps: function(props){
            this.selectionModel = props.selectionModel || new SelectionModel(props.node);
            this.selectionModel.observe('selectionChanged', function(){
                this.updateStateNode(this.selectionModel.current());
            }.bind(this));

            let {url} = this.computeImageData(props.node);
            SizeComputer.loadImageSize(url, props.node, this.imageSizeCallback);

            return {
                currentNode: this.selectionModel.current(),
                displayOriginal: false,
                fitToScreen:true,
                zoomFactor: 1
            };
        },

        componentWillReceiveProps: function(nextProps){
            if(nextProps.node !== this.props.node){
                this.setState(this.computeStateFromProps(nextProps));
            }
        },

        getInitialState: function(){

            return this.computeStateFromProps(this.props);

        },

        updateStateNode: function(node){
            let {url} = this.computeImageData(node);
            SizeComputer.loadImageSize(url, node, this.imageSizeCallback);

            if(this.props.onRequestTabTitleUpdate){
                this.props.onRequestTabTitleUpdate(node.getLabel());
            }
            this.setState({
                currentNode: node
            });
        },

        onSliderChange: function(event, newValue){
            this.setState({zoomFactor:newValue});
        },

        play: function(){
            this.pe = new PeriodicalExecuter(function(){
                this.updateStateNode(this.selectionModel.nextOrFirst());
            }.bind(this), 3);
            this.setState({playing:true});
        },

        stop: function(){
            if(this.pe) {
                this.pe.stop();
            }
            this.setState({playing:false});
        },

        computeImageData(node){
            let baseURL = this.props.baseUrl;
            let editorConfigs = this.props.editorConfigs;
            let data = {};
            let dimension = {width: -1, height: -1};
            if(this.state && this.state.imageDimension){
                dimension = this.state.imageDimension;
            }
            if(!this.state || this.state.displayOriginal){
                data = this.props.urlProvider.getHiResUrl(baseURL, editorConfigs, node, dimension, '');
                if(node.getMetadata().get("image_exif_orientation")){
                    data['imageClassName'] = 'ort-rotate-' + node.getMetadata().get("image_exif_orientation");
                }
            }else{
                data = this.props.urlProvider.getLowResUrl(baseURL, editorConfigs, node, dimension, '');
            }
            return data;
        },

        buildActions: function(){
            let actions = [];
            let mess = this.props.pydio.MessageHash;
            if(this.selectionModel.length() > 1){

                actions.push(
                    <MaterialUI.ToolbarGroup
                        firstChild={true}
                        key="left"
                    >
                        <MaterialUI.FlatButton label={mess[178]} disabled={!this.selectionModel.hasPrevious()} onClick={()=>{this.updateStateNode(this.selectionModel.previous())}}/>
                        <MaterialUI.FlatButton label={this.state.playing?mess[232]:mess[230]} onClick={()=>{this.state.playing?this.stop():this.play()}}/>
                        <MaterialUI.FlatButton label={mess[179]} disabled={!this.selectionModel.hasNext()} onClick={()=>{this.updateStateNode(this.selectionModel.next())}}/>
                    </MaterialUI.ToolbarGroup>
                );
            }

            let rightActions = [];
            if(this.props.showResolutionToggle){
                rightActions.push(<MaterialUI.FlatButton key="resolution" label={this.state.displayOriginal?mess[526]:mess[525]} onClick={()=>{this.setState({displayOriginal:!this.state.displayOriginal})}}/>);
                rightActions.push(<MaterialUI.ToolbarSeparator key="separator"/>);
            }

            if(this.state.fitToScreen){
                rightActions.push(<MaterialUI.FlatButton key="fit" label={mess[326]} onClick={()=>{this.setState({fitToScreen:!this.state.fitToScreen})}}/>);
            }else{
                rightActions.push(<MaterialUI.FlatButton key="fit" label={mess[325]} onClick={()=>{this.setState({fitToScreen:!this.state.fitToScreen})}}/>);
                rightActions.push(
                    <div key="zoom" style={{display:'flex', height:56}}>
                        <MaterialUI.Slider style={{width:150, marginTop:-4}} min={0.25} max={4} defaultValue={1} value={this.state.zoomFactor} onChange={this.onSliderChange}/>
                        <span style={{padding:18,fontSize: 16}}>{Math.round(this.state.zoomFactor * 100)} %</span>
                    </div>
                );
            }

            actions.push(
                <MaterialUI.ToolbarGroup key="right" lastChild={true}>
                    {rightActions}
                </MaterialUI.ToolbarGroup>
            );
            return actions;
        },

        render: function(){

            if(!this.state.currentNode || this.props.showLoader){
                return (
                    <PydioComponents.AbstractEditor {...this.props} actions={[]}>
                        <PydioReactUI.Loader/>
                    </PydioComponents.AbstractEditor>
                );
            }else{
                let imgProps = this.computeImageData(this.state.currentNode);
                return (
                    <PydioComponents.AbstractEditor {...this.props} actions={this.buildActions()}>
                        <ImagePanel
                            {...imgProps}
                            fit={this.state.fitToScreen}
                            zoomFactor={this.state.zoomFactor}
                        />
                    </PydioComponents.AbstractEditor>
                );
            }

        }

    });


    global.PydioDiaporama = {
        Editor: Editor,
        SelectionModel: SelectionModel,
        UrlProvider: UrlProvider
    };

})(window)
