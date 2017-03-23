import Dashboard from '../board/Dashboard'

var TaskEditor = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes: {
        closeAjxpDialog: React.PropTypes.func.isRequired,
        pydio:React.PropTypes.instanceOf(Pydio).isRequired,
        selection:React.PropTypes.instanceOf(PydioDataModel).isRequired,
    },

    updateActionDescription: function(actionList, actionValue){
        if(actionList.has(actionValue)){
            this.setState({
                currentActionData:actionList.get(actionValue)
            });
        }
    },

    onParameterChange: function(paramName, newValue, oldValue, additionalFormData){
        if(paramName === "schedule"){

            this.setState({cron:newValue});

        }else if(paramName === "action_name"){
            if(this._actionsList) {
                this.updateActionDescription(this._actionsList, newValue);
            } else{
                PydioApi.getClient().request({get_action:"list_all_plugins_actions"}, function(t){
                    if(!t.responseJSON || !t.responseJSON.LIST) return;
                    let _actionsList = new Map();
                    for(var plugin in t.responseJSON.LIST){
                        if(!t.responseJSON.LIST.hasOwnProperty(plugin)) continue;
                        t.responseJSON.LIST[plugin].map(function(a){
                            _actionsList.set(a.action, a);
                        });
                    }
                    this.updateActionDescription(_actionsList, newValue);
                    this._actionsList = _actionsList;
                }.bind(this));
            }
        }

    },

    onFormChange: function(newValues, dirty, removeValues){
        this.setState({values:newValues});
    },

    save: function(){
        let post = this.refs.formPanel.getValuesForPOST(this.state.values, '');
        post['get_action'] = 'scheduler_addTask';
        if(this.state.node){
            post['task_id'] = this.state.node.getMetadata().get('task_id');
        }
        PydioApi.getClient().request(post, function(){
            this.close();
            if(Dashboard.getInstance()){
                Dashboard.getInstance().refreshTasks();
            }
        }.bind(this));
    },

    previousButton: function(){

        if(this.state.node || this.state.tab === 0){
            return null;
        }

        let prevTab = function(){
            this.setState({tab: this.state.tab-1}, function(){
                this.refs.formPanel.externallySelectTab(this.state.tab);
            }.bind(this));
        }.bind(this);
        return <ReactMUI.FlatButton secondary={true} onClick={prevTab}>Previous</ReactMUI.FlatButton>;

    },

    nextSaveButton: function(){
        if(this.state.node || this.state.tab === 2){
            return <ReactMUI.FlatButton secondary={true} onClick={this.save}>Save</ReactMUI.FlatButton>;
        }
        let nextTab = function(){
            this.setState({tab: this.state.tab+1}, function(){
                this.refs.formPanel.externallySelectTab(this.state.tab);
            }.bind(this));
        }.bind(this);
        return <ReactMUI.FlatButton secondary={true} onClick={nextTab}>Next</ReactMUI.FlatButton>;
    },

    tabChange: function(tabIndex, tab){
        this.setState({tab:tabIndex});
    },

    getInitialState:function(){
        if(!this.props.selection || this.props.selection.isEmpty()){
            return {
                tab:0,
                values:{
                    schedule:'0 3 * * *',
                    repository_id:'ajxp_conf',
                    user_id:this.props.pydio.user.id
                },
                cron:'0 3 * * *'
            };
        }else{
            let values = this.props.selection.getUniqueNode().getMetadata();
            let objValues = {};
            let parameters;
            values.forEach(function(v,k){
                if(k === 'parameters'){
                    parameters = JSON.parse(v);
                }else{
                    objValues[k] = v;
                }
            });
            if(parameters){
                let i = 0;
                for(let k in parameters){
                    if(!parameters.hasOwnProperty(k)) continue;
                    objValues['param_name' + (i > 0 ? '_'+i  : '')] = k;
                    objValues['param_value' + (i > 0 ? '_'+i  : '')] = parameters[k];
                    i++;
                };
            }
            return {tab:0, values: objValues, cron:values.get('schedule'), node:this.props.selection.getUniqueNode()};
        }
    },

    getMessage: function(messId){
        return this.props.pydio.MessageHash[messId];
    },

    close: function(){
        this.props.onDismiss();
    },

    render: function(){

        if(!this._params){
            var definitionNode = XMLUtils.XPathSelectSingleNode(this.props.pydio.getXmlRegistry(), 'actions/action[@name="scheduler_addTask"]/processing/standardFormDefinition');
            this._params = PydioForm.Manager.parseParameters(definitionNode, "param");
        }
        let params = [];
        // Clone this._params
        this._params.map(function(o){
            let copy = LangUtils.deepCopy(o);
            if(copy.name == 'action_name' && this.state.currentActionData){
                if(this.state.currentActionData.parameters){
                    let actionParams = this.state.currentActionData.parameters;
                    let descParams = [];
                    actionParams.map(function(p){
                        descParams.push( (p.name === 'nodes' ? 'file': p.name) + ' (' + p.description + ')');
                    });
                    copy.description = "Declared Parameters : " + descParams.join(', ');
                }else{
                    copy.description = "No Declared Parameters";
                }
            }
            params.push(copy);
        }.bind(this));
        if(this.state.cron){
            params.push({
                name:'cron_legend',
                type:'legend',
                group:this.getMessage('action.scheduler.2'),
                description:'Current CRON: ' + Cronstrue.toString(this.state.cron)
            });
        }
        let tabs = [
            {label:'Schedule', groups:[1]},
            {label:'Action', groups:[2,0]},
            {label:'Context', groups:[3]}
        ];
        let nextSaveButton = this.nextSaveButton();
        let previousButton = this.previousButton();
        return (<div>
            <PydioForm.FormPanel
                ref="formPanel"
                parameters={params}
                values={this.state.values}
                depth={-1}
                tabs={tabs}
                onTabChange={this.tabChange}
                onChange={this.onFormChange}
                onParameterChange={this.onParameterChange}
            />
            <div className="dialogButtons" style={{position:'relative'}}>
                <ReactMUI.FlatButton default={true} onClick={this.close}>close</ReactMUI.FlatButton>
                {previousButton}
                {nextSaveButton}
            </div>
        </div>);
    }

});
export {TaskEditor as default}