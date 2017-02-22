(function(global){

    let pydio = global.pydio;
    
    class SizeComputer{

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

        static loadImageSize(node, callback){

            if(node.getMetadata().get('image_width')){
                return {
                    width:parseInt(node.getMetadata().get('image_width')),
                    height: parseInt(node.getMetadata().get('image_height'))
                };
            }else{
                var sizeLoader = document.createElement('image');
                var tmpThis = this;
                sizeLoader.onload = function(){
                    node.getMetadata().set("image_width", this.width);
                    node.getMetadata().set("image_height", this.height);
                    callback(node, {
                        width: this.width,
                        height: this.height
                    })
                }.bind(sizeLoader);
                sizeLoader.onerror = function(){
                    callback(node, {width:200, height: 200, default:true});
                }
                sizeLoader.src = this.baseUrl + SizeComputer.buildRandomSeed(node) + "&file=" + encodeURIComponent(node.getPath());
                return {width:200, height: 200, default:true};
            }

        }

        static getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed){

            var h = parseInt(imageDimensions.height);
            var w = parseInt(imageDimensions.width);

            return {url: baseUrl + time_seed + "&file=" + encodeURIComponent(node.getPath()), width: w, height: h};

        }

        static getLowResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed){

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
                return SizeComputer.getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed);
            }
        }



    }

    class SelectionModel{

        constructor(node){
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

    }

    let Editor = React.createClass({

        propTypes:{
            node: React.PropTypes.instanceOf(AjxpNode)
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
                var mtimeString = Editor.buildRandomSeed(ajxpNode);
                return pydio.Parameters.get('ajxpServerAccess') + repoString + mtimeString + "&get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
            },

            getOriginalSource : function(ajxpNode) {
                return pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy'+Editor.buildRandomSeed(ajxpNode)+'&file='+encodeURIComponent(ajxpNode.getPath());
            },

            buildRandomSeed : function(ajxpNode){
                var mtimeString = "&time_seed=" + ajxpNode.getMetadata().get("ajxp_modiftime");
                if(ajxpNode.getParent()){
                    var preview_seed = ajxpNode.getParent().getMetadata().get('preview_seed');
                    if(preview_seed){
                        mtimeString += "&rand="+preview_seed;
                    }
                }
                return mtimeString;
            }
        },

        getInitialState: function(){
            this.selectionModel = new SelectionModel(this.props.node);
            let initDimension = SizeComputer.loadImageSize(this.props.node, function(node, dimension){
                if(this.state.currentNode === node){
                    this.setState({imageDimension: dimension});
                }
            }.bind(this));
            return {
                currentNode: this.selectionModel.current(),
                imageDimension: initDimension
            };
        },

        updateStateNode: function(node){
            let initDimension = SizeComputer.loadImageSize(node, function(passedNode, dimension){
                if(this.state.currentNode === passedNode){
                    this.setState({imageDimension: dimension});
                }
            }.bind(this));
            this.setState({
                currentNode: node,
                imageDimension:initDimension
            });
        },

        buildActions: function(){
            let actions = [];
            actions.push(<MaterialUI.FlatButton label="Previous" disabled={!this.selectionModel.hasPrevious()} onClick={()=>{this.updateStateNode(this.selectionModel.previous())}}/>);
            actions.push(<MaterialUI.FlatButton label="Next" disabled={!this.selectionModel.hasNext()} onClick={()=>{this.updateStateNode(this.selectionModel.next())}}/>);
            return actions;
        },

        render: function(){

            let baseURL = pydio.Parameters.get('ajxpServerAccess')+'&action=preview_data_proxy';
            let editorConfigs = pydio.getPluginConfigs('editor.diaporama');
            let data = SizeComputer.getHiResUrl(baseURL, editorConfigs, this.state.currentNode, this.state.imageDimension, '');

            console.log(data);

            return (
                <PydioComponents.AbstractEditor {...this.props} actions={this.buildActions()}>
                    <div className="vertical_fit" style={{textAlign:'center'}}>
                        <img style={{height:data.height, width:data.width}} src={data.url}/>
                    </div>
                </PydioComponents.AbstractEditor>
            );
        }

    });


    global.PydioDiaporama = {
        Editor: Editor
    };

})(window)