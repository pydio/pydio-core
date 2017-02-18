(function(global){

    let pydio = global.pydio;

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

        render: function(){
            return (
                <div style={{widht:'100%',height:'100%', textAlign:'center'}}>
                    <img style={{height:'98%', maxWidth:'98%', margin:'1%'}} src={Editor.getOriginalSource(this.props.node)}/>
                </div>
            );
        }

    });


    global.PydioDiaporama = {
        Editor: Editor
    };

})(window)