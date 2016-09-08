(function(global){

    var DLTemplate = React.createClass({

        triggerDL: function(){

            this.setState({downloadStarted: true});
            global.setTimeout(function(){
                this.props.pydio.Controller.fireAction("download");
                    global.setTimeout(function(){
                        this.setState({downloadStarted: false});
                    }.bind(this), 1500);
            }.bind(this), 100);

        },

        detectFirstNode: function(){
            let dm = this.props.pydio.getContextHolder();
            if(!dm.getSelectedNodes().length) {
                let first = dm.getRootNode().getFirstChildIfExists();
                if (first) {
                    dm.setSelectedNodes([first], "dataModel");
                    this.setState({node: first});
                }else{
                    global.setTimeout(this.detectFirstNode.bind(this), 1000);
                }
            }else{
                if(!this.state || !this.state.node){
                    this.setState({node: dm.getSelectedNodes()[0]});
                }
            }
        },

        componentDidMount: function(){
            this.detectFirstNode();
            let pydio = this.props.pydio;
            if(pydio.user && pydio.user.activeRepository){
                this.setState({
                    repoObject:pydio.user.repositories.get(pydio.user.activeRepository)
                });
            }else{
                global.document.observe("ajaxplorer:repository_list_refreshed", function(e){
                    let repositoryList = e.memo.list;
                    let repositoryId = e.memo.active;
                    if(repositoryList && repositoryList.has(repositoryId)){
                        var repoObject = repositoryList.get(repositoryId);
                        this.setState({repoObject: repoObject});
                    }
                }.bind(this));
            }
        },

        render: function(){

            if(!this.props.pydio.user){
                return <div></div>;
            }
            let name1, name2, name3, owner;
            let classNames = ['download-block'];
            if(this.state && this.state.repoObject){
                owner = this.state.repoObject.getOwner();
                name1 = '%1 shared'.replace('%1', owner);
                name2 = this.state.repoObject.getLabel();
                name3 = 'with you';
            }
            let click = null;
            let fileDetails = <div className="dl-details">{this.props.pydio.MessageHash[466]}</div> ;
            if(this.state && this.state.node){
                click = this.triggerDL.bind(this);
                fileDetails = (
                    <div className="dl-details">
                        <div className="row">
                            <span className="label">{this.props.pydio.MessageHash[503]}</span>
                            <span className="value">{this.state.node.getMetadata().get('filesize')}</span>
                        </div>
                        <div className="click-legend">{this.props.pydio.MessageHash['share_center.231']}</div>
                    </div>
                );
            }else{
                classNames.push('not-ready');
            }
            if(this.state && this.state.downloadStarted){
                classNames.push('dl-started');
            }
            return (
                <div className={classNames.join(' ')} onClick={click}>
                    <span className="dl-filename">{name2}</span>
                    <div className="dl-icon">
                        <span className="mdi mdi-file"/>
                        <span className="mdi mdi-download"/>
                    </div>
                    {fileDetails}
                </div>
            );

        }

    });

    var ns = global.ShareTemplates || {};
    ns.DLTemplate = DLTemplate;
    global.ShareTemplates = ns;

})(window);