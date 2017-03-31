(function(global) {

    let pydio = global.pydio;

    class UrlProvider extends PydioDiaporama.UrlProvider{

        getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed){
            let url;
            if(node.getMetadata().get('thumb_file_id')){
                url = baseUrl + '&get_action=get_extracted_page' +
                    '&file=' + encodeURIComponent(node.getMetadata().get('thumb_file_id')) +
                    '&src_file=' + encodeURIComponent(node.getPath());
            }
            return Object.assign({url:url}, imageDimensions);
        }

        getLowResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed){
            return this.getHiResUrl(baseUrl, editorConfigs, node, imageDimensions, time_seed);
        }

    }

    class SelectionModel extends PydioDiaporama.SelectionModel{

        buildSelection(){

            let initialNodePath = this.currentNode.getPath();
            let initialNodeLabel = this.currentNode.getLabel();

            PydioApi.getClient().request({
                get_action:'imagick_data_proxy',
                all:'true',
                file:initialNodePath
            }, function(transport){
                let page = 1;
                this.selection = transport.responseJSON.map(function(result){
                    let node = new AjxpNode(initialNodePath, true, initialNodeLabel+' ('+page+')');
                    node.getMetadata().set('image_width', result.width);
                    node.getMetadata().set('image_height', result.height);
                    node.getMetadata().set('thumb_file_id', result.file);
                    page++;
                    return node;
                });
                this.currentIndex = 0;
                this.notify('selectionChanged');
            }.bind(this));

        }

    }

    const Editor = React.createClass({

        statics: {
            getCoveringBackgroundSource: function (ajxpNode) {
                return this.getThumbnailSource(ajxpNode);
            },

            getThumbnailSource: function (ajxpNode) {
                var repoString = "";
                if(pydio.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != pydio.repositoryId){
                    repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
                }
                var mtimeString = UrlProvider.buildRandomSeed(ajxpNode);
                return pydio.Parameters.get('ajxpServerAccess') + "&get_action=imagick_data_proxy"+repoString + mtimeString +"&file="+encodeURIComponent(ajxpNode.getPath());
            },

            getSharedPreviewTemplate : function(node, link){
                return '<img src="' + link + '"/>';
            },

            getRESTPreviewLinks:function(node){
                return {
                    "First Page Thumbnail": ""
                };
            }
        },

        getInitialState: function(){
            let s = new SelectionModel(this.props.node);
            s.observe('selectionChanged', ()=>{this.setState({selectionLoaded:true})});
            return {
                selectionLoaded: false,
                selectionModel: s,
                urlProvider:new UrlProvider()
            };
        },

        componentWillUnmount: function(){
            let node = this.state.selectionModel.first()

            if (!node) {
                return
            }
            
            let fileId = this.state.selectionModel.first().getMetadata().get('thumb_file_id');
            var prefix = fileId.replace("-0.jpg", "").replace(".jpg", "");
            PydioApi.getClient().request({get_action:'delete_imagick_data', file:prefix});
        },

        render: function(){

            return (
                <PydioDiaporama.Editor
                    {...this.props}
                    selectionModel={this.state.selectionModel}
                    urlProvider={this.state.urlProvider}
                    baseUrl={pydio.Parameters.get('ajxpServerAccess')}
                    showResolutionToggle={false}
                    showLoader={!this.state.selectionLoaded}
                />
            )
        }

    });

    window.PydioImagick = {
        Editor: Editor
    }

})(window);
