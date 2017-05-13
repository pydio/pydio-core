/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

(function(global){

    const pydio = global.pydio;

    const loadHistoryBrowser = function(){

        pydio.UI.openComponentInModal('PydioVersioning', 'HistoryDialog', {node: pydio.getContextHolder().getUniqueNode()});

    }

    class HistoryApi{

        constructor(node){
            this.node = node;
        }

        getDataModel(){
            if(!this.versionsDm){
                const provider = new RemoteNodeProvider({get_action:'git_history',file:this.node.getPath()});
                this.versionsDm = new PydioDataModel(true);
                this.versionsDm.setAjxpNodeProvider(provider);
                this.versionsRoot = new AjxpNode("/", false, "Versions", "folder.png", provider);
                this.versionsDm.setRootNode(this.versionsRoot);
            }
            return this.versionsDm;
        }

        openVersion(file, commit_id, download = false){

            if(download){
                PydioApi.getClient().downloadSelection(null, 'git_getfile', {
                    file: file,
                    commit_id: commit_id,
                    attach: 'download'
                });
            }else{
                const src = pydio.Parameters.get('ajxpServerAccess')
                    + '&get_action=git_file&attach=inline'
                    + '&file=' + encodeURIComponent(file)
                    + '&commit_id=' + commit_id;
                global.open(src);
            }

        }

        revertToVersion(originalFilePath, file, commit_id, callback = null){

            if(!global.confirm(pydio.MessageHash["meta.git.13"])){
                return;
            }
            PydioApi.getClient().request({
                get_action:'git_revertfile',
                original_file:originalFilePath,
                file: file,
                commit_id: commit_id
            }, function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
                if(callback) callback(transport);
            });


        }

    }

    let HistoryBrowser = React.createClass({

        propTypes: {
            node: React.PropTypes.instanceOf(AjxpNode).isRequired,
            onRequestClose: React.PropTypes.func
        },

        propsToState: function(node){
            const api = new HistoryApi(node);
            this._selectionObserver = function(){
               if(api.getDataModel().isEmpty()) {
                    this.setState({selectedNode:null})
                } else {
                    this.setState({selectedNode:api.getDataModel().getUniqueNode()});
                }
            }.bind(this);
            api.getDataModel().observe('selection_changed', this._selectionObserver);
            return {api: api};
        },
        getInitialState: function(){
            return this.propsToState(this.props.node);
        },
        componentWillReceiveProps: function(nextProps){
            if(nextProps.node !== this.props.node){
                if(this._selectionObserver){
                    this.state.api.getDataModel().stopObserving('selection_changed', this._selectionObserver);
                }
                this.setState(this.propsToState(nextProps.node));
            }
        },
        nodeClicked: function(node, clickType, event){
            this.state.api.getDataModel().setSelectedNodes([node]);
        },
        applyAction: function(action){
            const file = this.state.selectedNode.getMetadata().get('FILE');
            const commitId = this.state.selectedNode.getMetadata().get('ID');
            const originalPath = this.props.node.getPath();
            switch(action){
                case 'dl':
                    this.state.api.openVersion(file, commitId, true);
                    break;
                case 'open':
                    this.state.api.openVersion(file, commitId, false);
                    break;
                case 'revert':
                    this.state.api.revertToVersion(originalPath, file, commitId, function(){
                        if(this.props.onRequestClose) this.props.onRequestClose();
                    }.bind(this))
                    break;
                default:
                    break;
            }
        },
        render: function(){

            const mess = pydio.MessageHash;
            const tableKeys = {
                index: {label: mess['meta.git.9'], sortType: 'string', width: '5%'},
                ajxp_modiftime: {label: mess['meta.git.10'], sortType: 'string', width: '40%'},
                MESSAGE: {label: mess['meta.git.11'], sortType: 'string', width: '20%'},
                EVENT: {label: mess['meta.git.12'], sortType: 'string', width: '20%'}
            };

            let disabled = !this.state.selectedNode;
            return (
                <div>
                    <MaterialUI.Toolbar>
                        <MaterialUI.ToolbarGroup firstChild={true}>
                            <MaterialUI.FlatButton style={!disabled?{color:'white'}:{}} disabled={disabled} label={mess['meta.git.3']} tooltip={mess['meta.git.4']} onTouchTap={this.applyAction.bind(this, 'dl')}/>
                            <MaterialUI.FlatButton style={!disabled?{color:'white'}:{}} disabled={disabled} label={mess['meta.git.5']} tooltip={mess['meta.git.6']} onTouchTap={this.applyAction.bind(this, 'open')}/>
                            <MaterialUI.FlatButton style={!disabled?{color:'white'}:{}} disabled={disabled} label={mess['meta.git.7']} tooltip={mess['meta.git.8']} onTouchTap={this.applyAction.bind(this, 'revert')}/>
                        </MaterialUI.ToolbarGroup>
                    </MaterialUI.Toolbar>
                    <PydioComponents.NodeListCustomProvider
                        presetDataModel={this.state.api.getDataModel()}
                        actionBarGroups={[]}
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                        tableKeys={tableKeys}
                        entryHandleClicks={this.nodeClicked}
                    />
                </div>
            );

        }

    });

    if(global.ReactDND){
        const FakeDndBackend = function(){
            return{
                setup:function(){},
                teardown:function(){},
                connectDragSource:function(){},
                connectDragPreview:function(){},
                connectDropTarget:function(){}
            };
        };
        HistoryBrowser = ReactDND.DragDropContext(FakeDndBackend)(HistoryBrowser);
    }


    const HistoryDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogIsModal: false,
                dialogSize:'lg',
                dialogPadding: false
            };
        },
        submit(){
            this.dismiss();
        },
        render: function(){
            return (
                <div style={{width: '100%'}} className="layout-fill vertical-layout">
                    <HistoryBrowser node={this.props.node} onRequestClose={this.dismiss}/>
                </div>
            );
        }

    });

    global.PydioVersioning = {
        loadHistoryBrowser: loadHistoryBrowser,
        HistoryDialog: HistoryDialog
    };

})(window);