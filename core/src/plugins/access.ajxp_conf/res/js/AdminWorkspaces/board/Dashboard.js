import Workspace from '../model/Workspace'
import WorkspaceEditor from '../editor/WorkspaceEditor'
import WorkspaceCreator from '../editor/WorkspaceCreator'
import WorkspaceList from './WorkspaceList'

export default React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        rootNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        currentNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        openEditor:React.PropTypes.func.isRequired,
        openRightPane:React.PropTypes.func.isRequired,
        closeRightPane:React.PropTypes.func.isRequired
    },

    getInitialState:function(){
        return {selectedNode:null, filter:'workspaces'}
    },

    openWorkspace:function(node){
        if(this.refs.editor && this.refs.editor.isDirty()){
            if(!window.confirm(global.pydio.MessageHash["role_editor.19"])) {
                return false;
            }
        }

        let editor = WorkspaceEditor;
        const editorNode = XMLUtils.XPathSelectSingleNode(this.props.pydio.getXmlRegistry(), '//client_configs/component_config[@component="AdminWorkspaces.Dashboard"]/editor');
        if(editorNode){
            editor = editorNode.getAttribute('namespace') + '.' + editorNode.getAttribute('component');
        }
        var editorData = {
            COMPONENT:editor,
            PROPS:{
                ref:"editor",
                node:node,
                closeEditor:this.closeWorkspace,
                deleteWorkspace:this.deleteWorkspace,
                saveWorkspace:this.updateWorkspace
            }
        };
        this.props.openRightPane(editorData);

    },

    closeWorkspace:function(){
        if(this.refs.editor && this.refs.editor.isDirty()){
            if(!window.confirm(global.pydio.MessageHash["role_editor.19"])) {
                return false;
            }
        }
        //this.setState({selectedNode:null, showCreator:null});
        this.props.closeRightPane();
    },

    toggleWorkspacesFilter:function(){
        this.setState({filter:this.state.filter=='workspaces'?'templates':'workspaces'});
    },

    showWorkspaceCreator: function(type){
        if(typeof(type) != "string") type = "workspace";
        var editorData = {
            COMPONENT:WorkspaceCreator,
            PROPS:{
                ref:"editor",
                type:type,
                save:this.createWorkspace,
                closeEditor:this.closeWorkspace
            }
        };
        this.props.openRightPane(editorData);

    },

    showTplCreator: function(){
        this.showWorkspaceCreator('template');
    },

    createWorkspace: function(type, creatorState){
        var driver;
        if(!creatorState.selectedDriver && creatorState.selectedTemplate){
            driver = "ajxp_template_" + creatorState.selectedTemplate;
            // Move drivers options inside the values['driver'] instead of values['general']
            var tplDef = Workspace.TEMPLATES.get(creatorState.selectedTemplate);
            var driverDefs = Workspace.DRIVERS.get(tplDef.type).params;
            var newDriversValues = {};
            Object.keys(creatorState.values['general']).map(function(k){
                driverDefs.map(function(param){
                    if(param['name'] === k){
                        newDriversValues[k] = creatorState.values['general'][k];
                        delete creatorState.values['general'][k];
                    }
                });
            });
            creatorState.values['driver'] = newDriversValues;

        }else{
            driver = creatorState.selectedDriver;
        }
        if(creatorState.values['general']['DISPLAY']){
            var displayValues = {DISPLAY:creatorState.values['general']['DISPLAY']};
            delete creatorState.values['general']['DISPLAY'];
        }
        var generalValues = creatorState.values['general'];

        var saveData = LangUtils.objectMerge({
            DRIVER:driver,
            DRIVER_OPTIONS:LangUtils.objectMerge(creatorState.values['general'], creatorState.values['driver'])
        }, displayValues);

        var parameters = {
            get_action:'create_repository',
            json_data:JSON.stringify(saveData)
        };
        if(type == 'template'){
            parameters['sf_checkboxes_active'] = 'true';
        }
        PydioApi.getClient().request(parameters, function(transport){
            // Reload list & Open Editor
            this.refs.workspacesList.reload();
            var newId = XMLUtils.XPathGetSingleNodeText(transport.responseXML, "tree/reload_instruction/@file");
            var fakeNode = new AjxpNode('/fake/path/' + newId);
            fakeNode.getMetadata().set("ajxp_mime", "repository_editable");
            this.openWorkspace(fakeNode, 'driver');
        }.bind(this));
    },

    deleteWorkspace:function(workspaceId){
        if(window.confirm(this.context.getMessage('35', 'ajxp_conf'))){
            this.closeWorkspace();
            PydioApi.getClient().request({
                get_action:'delete',
                data_type:'repository',
                data_id:workspaceId
            }, function(transport){
                this.refs.workspacesList.reload();
            }.bind(this));
        }
    },

    /**
     *
     * @param workspaceModel Workspace
     * @param postData Object
     * @param editorData Object
     */
    updateWorkspace:function(workspaceModel, postData, editorData){
        var workspaceId = workspaceModel.wsId;
        if(workspaceModel.isTemplate()){
            var formDefs=[], formValues={}, templateAllFormDefs = [];
            if(!editorData["general"]){
                workspaceModel.buildEditor("general", formDefs, formValues, {}, templateAllFormDefs);
                var generalPostValues = PydioForm.Manager.getValuesForPOST(formDefs, formValues);
                postData = LangUtils.objectMerge(postData, generalPostValues);
            }
            if(!editorData["driver"]){
                workspaceModel.buildEditor("driver", formDefs, formValues, {}, templateAllFormDefs);
                var driverPostValues = PydioForm.Manager.getValuesForPOST(formDefs, formValues);
                postData = LangUtils.objectMerge(postData, driverPostValues);
            }
        }

        if(editorData['permission-mask']){
            postData['permission_mask'] = JSON.stringify(editorData['permission-mask']);
        }
        var mainSave = function(){
            PydioApi.getClient().request(LangUtils.objectMerge({
                get_action:'edit',
                sub_action:'edit_repository_data',
                repository_id:workspaceId
            }, postData), function(transport){
                this.refs['workspacesList'].reload();
            }.bind(this));
        }.bind(this);

        var metaSources = editorData['META_SOURCES'];
        if(Object.keys(metaSources["delete"]).length || Object.keys(metaSources["add"]).length || Object.keys(metaSources["edit"]).length){
            PydioApi.getClient().request(LangUtils.objectMerge({
                get_action:'edit',
                sub_action:'meta_source_edit',
                repository_id:workspaceId,
                bulk_data:JSON.stringify(metaSources)
            }), function(transport){
                if(this.refs['editor']){
                    this.refs['editor'].clearMetaSourceDiff();
                }
                mainSave();
            }.bind(this));
        }else{
            mainSave();
        }
    },

    reloadWorkspaceList:function(){
        this.refs.workspacesList.reload();
    },

    render:function(){
        var buttonContainer;
        if(this.state.filter == 'workspaces'){
            buttonContainer = (
                <div className="button-container">
                    <ReactMUI.FlatButton primary={true} label={this.context.getMessage('ws.3')} onClick={this.showWorkspaceCreator}/>
                    <ReactMUI.FlatButton onClick={this.toggleWorkspacesFilter} secondary={true} label={this.context.getMessage('ws.1')}/>
                </div>
            );
        }else{
            buttonContainer = (
                <div className="button-container">
                    <ReactMUI.FlatButton primary={true} label={this.context.getMessage('ws.4')} onClick={this.showTplCreator}/>
                    <ReactMUI.FlatButton onClick={this.toggleWorkspacesFilter} secondary={true} label={this.context.getMessage('ws.2')}/>
                </div>
            );
        }
        buttonContainer = (
            <div style={{marginRight:20}}>
                <div style={{float:'left'}}>{buttonContainer}</div>
                <div style={{float:'right'}}>
                    <ReactMUI.IconButton className="small-icon-button" iconClassName="icon-refresh" onClick={this.reloadWorkspaceList}/>
                </div>
            </div>
        );
        return (
            <div className="main-layout-nav-to-stack workspaces-board">
                <div className="left-nav vertical-layout" style={{width:'100%'}}>
                    <ReactMUI.Paper zDepth={0} className="vertical-layout layout-fill">
                        <div className="vertical-layout workspaces-list layout-fill">
                            <h1 className="admin-panel-title hide-on-vertical-layout">{this.context.getMessage('3', 'ajxp_conf')}</h1>
                            {buttonContainer}
                            <WorkspaceList
                                ref="workspacesList"
                                dataModel={this.props.dataModel}
                                rootNode={this.props.rootNode}
                                currentNode={this.props.rootNode}
                                openSelection={this.openWorkspace}
                                filter={this.state.filter}
                            />
                        </div>
                    </ReactMUI.Paper>
                </div>
            </div>
        );
    }

});
