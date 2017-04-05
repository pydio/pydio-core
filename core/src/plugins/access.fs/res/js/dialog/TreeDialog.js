const React = require('react')

const TreeDialog = React.createClass({

    propTypes:{
        isMove:React.PropTypes.bool.isRequired,
        submitValue:React.PropTypes.func.isRequired
    },

    mixins:[
        PydioReactUI.ActionDialogMixin,
        PydioReactUI.CancelButtonProviderMixin,
        PydioReactUI.SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: 'Copy Selection to...',
            dialogIsModal: true
        };
    },

    submit(){
        this.props.submitValue(this.state.selectedNode.getPath(), (this.state.wsId === '__CURRENT__' ? null : this.state.wsId));
        this.dismiss();
    },

    getInitialState: function(){
        let dm = new PydioDataModel();
        var nodeProvider = new RemoteNodeProvider();
        let root = new AjxpNode('/', false, 'ROOT', '', nodeProvider);
        nodeProvider.initProvider({});
        dm.setAjxpNodeProvider(nodeProvider);
        dm.setRootNode(root);
        root.load();
        return{
            dataModel: dm,
            selectedNode: root,
            wsId:'__CURRENT__'
        }
    },

    onNodeSelected: function(n){
        n.load();
        this.setState({
            selectedNode: n
        })
    },

    createNewFolder: function(){
        let parent = this.state.selectedNode;
        let nodeName = this.refs.newfolder_input.getValue();
        let oThis = this;

        PydioApi.getClient().request({
            get_action:'mkdir',
            dir: parent.getPath(),
            dirname:nodeName
        }, function(){
            let fullpath = parent.getPath() + '/' + nodeName;
            parent.observeOnce('loaded', function(){
                let n = parent.getChildren().get(fullpath);
                if(n) oThis.setState({selectedNode:n});
            });
            global.setTimeout(function(){
                parent.reload();
            }, 500);
            oThis.setState({newFolderFormOpen: false});
        });

    },

    handleRepositoryChange: function(event, index, value){
        let dm = new PydioDataModel();
        var nodeProvider = new RemoteNodeProvider();
        let root = new AjxpNode('/', false, 'ROOT', '', nodeProvider);
        if(value === '__CURRENT__'){
            nodeProvider.initProvider({});
        }else{
            nodeProvider.initProvider({tmp_repository_id: value});
        }
        dm.setAjxpNodeProvider(nodeProvider);
        dm.setRootNode(root);
        root.load();
        this.setState({dataModel:dm, selectedNode: root, wsId: value});
    },

    render: function(){
        let openNewFolderForm = function(){
            this.setState({newFolderFormOpen: !this.state.newFolderFormOpen});
        }.bind(this)

        let user = this.props.pydio.user;
        let wsSelector ;
        if(user && user.canCrossRepositoryCopy() && user.hasCrossRepositories()){
            let items = [
                <MaterialUI.MenuItem key={'current'} value={'__CURRENT__'} primaryText={"Current Workspace"} />
            ];
            user.getCrossRepositories().forEach(function(repo, key){
                items.push(<MaterialUI.MenuItem key={key} value={key} primaryText={repo.getLabel()} />);
            });

            wsSelector = (
                <div>
                    <MaterialUI.SelectField
                        style={{width:'100%'}}
                        floatingLabelText="Copy to another workspace"
                        value={this.state.wsId}
                        onChange={this.handleRepositoryChange}
                    >
                        {items}
                    </MaterialUI.SelectField>
                </div>
            );
        }
        let openStyle = {flex:1,width:'100%'};
        let closeStyle = {width:0};
        return (
            <div>
                {wsSelector}
                <MaterialUI.Paper zDepth={1} style={{height: 300, overflowX:'auto'}}>
                    <PydioComponents.FoldersTree
                        pydio={this.props.pydio}
                        dataModel={this.state.dataModel}
                        onNodeSelected={this.onNodeSelected}
                        showRoot={true}
                        draggable={false}
                    />
                </MaterialUI.Paper>
                <div style={{display:'flex',alignItems:'baseline'}}>
                    <MaterialUI.TextField
                        style={{flex:1,width:'100%'}}
                        floatingLabelText="Selected files will be moved to ..."
                        ref="input"
                        value={this.state.selectedNode.getPath()}
                        disabled={true}
                    />
                    <MaterialUI.Paper zDepth={this.state.newFolderFormOpen ? 0 : 1} circle={true}>
                        <MaterialUI.IconButton
                            iconClassName="mdi mdi-folder-plus"
                            tooltip="Create folder"
                            onClick={openNewFolderForm}
                        />
                    </MaterialUI.Paper>
                </div>
                <MaterialUI.Paper
                    className="bezier-transitions"
                    zDepth={0}
                    style={{
                        height:this.state.newFolderFormOpen?80:0,
                        overflow:'hidden',
                        paddingTop: this.state.newFolderFormOpen?10:0,
                        display:'flex',
                        alignItems:'baseline'
                    }}
                >
                    <MaterialUI.TextField hintText="New folder" ref="newfolder_input" style={{flex:1}}/>
                    <MaterialUI.RaisedButton style={{marginLeft:10, marginRight:2}} label="OK" onClick={this.createNewFolder}/>
                </MaterialUI.Paper>
            </div>
        );
    }

});

export {TreeDialog as default}